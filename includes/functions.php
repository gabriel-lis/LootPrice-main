<?php
/**
 * Funções Auxiliares e Consultas do Sistema
 * LootPrice
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/recomendacoes.php';
require_once __DIR__ . '/desejos.php';
require_once __DIR__ . '/verificacao.php';
require_once __DIR__ . '/avisos.php';
require_once __DIR__ . '/components.php';

/**
 * Sanitiza texto para saída segura em HTML (Prevenção XSS)
 */
function sanitize(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Formata valores monetários em formato brasileiro (R$ 00,00 ou Grátis)
 */
function formatPrice($val): string {
    if ($val === null || $val === '') {
        return 'Consulte';
    }
    $num = (float)$val;
    if ($num <= 0.001) {
        return 'Grátis';
    }
    return 'R$ ' . number_format($num, 2, ',', '.');
}

/**
 * Define uma mensagem temporária (Flash Message)
 */
function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Recupera e consome a mensagem temporária
 */
function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/**
 * Retorna classe de cor para a nota Metacritic
 */
function getMetacriticColor(?float $score): string {
    if ($score === null) return '#888';
    if ($score >= 75) return '#66cc33'; // verde
    if ($score >= 50) return '#ffcc33'; // amarelo
    return '#ff0000'; // vermelho
}

/**
 * Mensagem padrão quando uma consulta ao banco falha: o erro vai para logs/erros.log
 * e a página mostra "sem dados" (nunca dados inventados).
 */
function lp_consulta_falhou(string $onde, Throwable $e): void {
    lp_log_erro("Consulta falhou em $onde", $e);
}

/** Escapa % e _ digitados na busca (para usar com LIKE ... ESCAPE '\\'). */
function lp_like(string $texto): string {
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto) . '%';
}

/**
 * Monta a consulta base do catálogo (sem ORDER/LIMIT) e preenche $params.
 */
function lp_catalogo_sql(?string $search, ?int $categoryId, ?int $platformId, ?string $priceRange, array &$params): string {
    $sql = "SELECT j.*,
                   MIN(pr.valor) AS menor_preco,
                   GROUP_CONCAT(DISTINCT c.nome SEPARATOR ', ') AS categorias_nomes,
                   GROUP_CONCAT(DISTINCT c.id_categoria) AS categorias_ids,
                   GROUP_CONCAT(DISTINCT p.nome SEPARATOR ', ') AS plataformas_nomes,
                   GROUP_CONCAT(DISTINCT sa.nome SEPARATOR ', ') AS assinaturas_nomes,
                   MAX(CASE WHEN sa.nome LIKE '%Game Pass%' THEN 1 ELSE 0 END) AS has_game_pass
            FROM jogos j
            LEFT JOIN jogo_categoria jc ON j.id_jogo = jc.id_jogo
            LEFT JOIN categorias c ON jc.id_categoria = c.id_categoria
            LEFT JOIN jogo_plataforma jp ON j.id_jogo = jp.id_jogo
            LEFT JOIN plataformas p ON jp.id_plataforma = p.id_plataforma
            LEFT JOIN jogo_assinatura ja ON j.id_jogo = ja.id_jogo
            LEFT JOIN servicos_assinatura sa ON ja.id_servico = sa.id_servico
            LEFT JOIN precos pr ON j.id_jogo = pr.id_jogo
            WHERE 1=1";

    $search = trim((string)$search);
    if ($search !== '') {
        // Palavra-chave: procura no nome, na sinopse e nas categorias
        $sql .= " AND (j.nome LIKE :s1 ESCAPE '\\\\'
                    OR j.descricao LIKE :s2 ESCAPE '\\\\'
                    OR j.id_jogo IN (SELECT jc2.id_jogo FROM jogo_categoria jc2
                                     JOIN categorias c2 ON c2.id_categoria = jc2.id_categoria
                                     WHERE c2.nome LIKE :s3 ESCAPE '\\\\'))";
        $like = lp_like($search);
        $params['s1'] = $params['s2'] = $params['s3'] = $like;
    }
    if (!empty($categoryId)) {
        $sql .= " AND j.id_jogo IN (SELECT id_jogo FROM jogo_categoria WHERE id_categoria = :catId)";
        $params['catId'] = $categoryId;
    }
    if (!empty($platformId)) {
        $sql .= " AND j.id_jogo IN (SELECT id_jogo FROM jogo_plataforma WHERE id_plataforma = :platId)";
        $params['platId'] = $platformId;
    }

    $sql .= " GROUP BY j.id_jogo";

    // Faixa de preço (RF03). "Até R$ X" sempre inclui os jogos grátis.
    switch ($priceRange) {
        case 'free':     $sql .= " HAVING MIN(pr.valor) = 0"; break;
        case 'under50':  $sql .= " HAVING MIN(pr.valor) <= 50.00"; break;
        case 'under100': $sql .= " HAVING MIN(pr.valor) <= 100.00"; break;
        case 'over100':  $sql .= " HAVING MIN(pr.valor) > 100.00"; break;
    }
    return $sql;
}

/**
 * Busca a lista de jogos com suporte a filtros de busca, categoria, plataforma e faixa de preço (RF01, RF02, RF03).
 * $limit/$offset servem para paginar (catalogo.php). O resultado fica em cache durante a requisição
 * (a home chama esta função várias vezes).
 */
function getGamesCatalog(?string $search = null, ?int $categoryId = null, ?int $platformId = null, ?string $priceRange = null, ?int $limit = null, int $offset = 0): array {
    static $cache = [];
    $chave = md5(json_encode([$search, $categoryId, $platformId, $priceRange, $limit, $offset]));
    if (isset($cache[$chave])) return $cache[$chave];

    $pdo = getDB();
    if (!$pdo) return [];

    try {
        $params = [];
        $sql = lp_catalogo_sql($search, $categoryId, $platformId, $priceRange, $params);
        $sql .= " ORDER BY j.nome ASC";
        if ($limit !== null) $sql .= " LIMIT " . max(1, (int)$limit) . " OFFSET " . max(0, $offset);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $cache[$chave] = $stmt->fetchAll();
    } catch (Throwable $e) {
        lp_consulta_falhou('getGamesCatalog', $e);
        return [];
    }
}

/** Quantos jogos o catálogo tem com esses filtros (para a paginação). */
function countGamesCatalog(?string $search = null, ?int $categoryId = null, ?int $platformId = null, ?string $priceRange = null): int {
    $pdo = getDB();
    if (!$pdo) return 0;
    try {
        $params = [];
        $sql = "SELECT COUNT(*) FROM (" . lp_catalogo_sql($search, $categoryId, $platformId, $priceRange, $params) . ") t";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        lp_consulta_falhou('countGamesCatalog', $e);
        return 0;
    }
}

/**
 * Executa uma consulta que devolve várias linhas; em caso de erro registra no log e devolve [].
 */
function lp_consulta(string $onde, string $sql, array $params = []): array {
    $pdo = getDB();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        lp_consulta_falhou($onde, $e);
        return [];
    }
}

/**
 * Busca detalhes completos de um jogo pelo ID (RF04, RF07, RN12)
 */
function getGameById(int $id): ?array {
    return lp_consulta('getGameById', "SELECT * FROM jogos WHERE id_jogo = :id LIMIT 1", ['id' => $id])[0] ?? null;
}

/**
 * Retorna os preços de um jogo em todas as lojas cadastradas ordenado pelo menor preço (RN01, RF05, RF06)
 */
function getGamePrices(int $idJogo): array {
    return lp_consulta('getGamePrices',
        "SELECT pr.*,
                l.nome AS loja_nome,
                l.logo AS loja_logo,
                l.url AS loja_url,
                p.nome AS plataforma_nome,
                p.icone AS plataforma_icone
         FROM precos pr
         INNER JOIN lojas l ON pr.id_loja = l.id_loja
         INNER JOIN plataformas p ON pr.id_plataforma = p.id_plataforma
         WHERE pr.id_jogo = :id_jogo
         ORDER BY pr.valor ASC, l.nome ASC", ['id_jogo' => $idJogo]);
}

/**
 * Menor preço já registrado para o jogo (histórico) e a evolução do menor preço por dia.
 * @return array ['menor' => ?array{valor,registrado_em,loja}, 'serie' => array<array{dia,valor}>]
 */
function getPriceHistory(int $idJogo): array {
    $menor = lp_consulta('getPriceHistory/menor',
        "SELECT h.valor, h.registrado_em, l.nome AS loja
         FROM historico_precos h JOIN lojas l ON l.id_loja = h.id_loja
         WHERE h.id_jogo = :id ORDER BY h.valor ASC, h.registrado_em DESC LIMIT 1", ['id' => $idJogo])[0] ?? null;
    $serie = lp_consulta('getPriceHistory/serie',
        "SELECT DATE(registrado_em) AS dia, MIN(valor) AS valor
         FROM historico_precos WHERE id_jogo = :id
         GROUP BY DATE(registrado_em) ORDER BY dia ASC", ['id' => $idJogo]);
    return ['menor' => $menor, 'serie' => $serie];
}

/**
 * Retorna as categorias associadas a um jogo
 */
function getGameCategories(int $idJogo): array {
    return lp_consulta('getGameCategories',
        "SELECT c.* FROM categorias c
         INNER JOIN jogo_categoria jc ON c.id_categoria = jc.id_categoria
         WHERE jc.id_jogo = :id_jogo ORDER BY c.nome ASC", ['id_jogo' => $idJogo]);
}

/**
 * Retorna as plataformas associadas a um jogo (RF04)
 */
function getGamePlatforms(int $idJogo): array {
    return lp_consulta('getGamePlatforms',
        "SELECT p.* FROM plataformas p
         INNER JOIN jogo_plataforma jp ON p.id_plataforma = jp.id_plataforma
         WHERE jp.id_jogo = :id_jogo ORDER BY p.nome ASC", ['id_jogo' => $idJogo]);
}

/**
 * Retorna os serviços de assinatura associados a um jogo (ex: Game Pass - RF07)
 */
function getGameSubscriptions(int $idJogo): array {
    return lp_consulta('getGameSubscriptions',
        "SELECT sa.* FROM servicos_assinatura sa
         INNER JOIN jogo_assinatura ja ON sa.id_servico = ja.id_servico
         WHERE ja.id_jogo = :id_jogo", ['id_jogo' => $idJogo]);
}

/** Lista todas as categorias cadastradas */
function getAllCategories(): array {
    return lp_consulta('getAllCategories', "SELECT * FROM categorias ORDER BY nome ASC");
}

/** Lista todas as plataformas cadastradas */
function getAllPlatforms(): array {
    return lp_consulta('getAllPlatforms', "SELECT * FROM plataformas ORDER BY nome ASC");
}

/** Lista todas as lojas cadastradas */
function getAllStores(): array {
    return lp_consulta('getAllStores', "SELECT * FROM lojas ORDER BY nome ASC");
}

/** Lista todos os serviços de assinatura */
function getAllSubscriptions(): array {
    return lp_consulta('getAllSubscriptions', "SELECT * FROM servicos_assinatura ORDER BY nome ASC");
}

/**
 * Caminho da capa do jogo; se não houver arquivo, usa a capa padrão (evita imagem quebrada nos cards).
 */
function capaJogo(?string $caminho): string {
    $padrao = 'fotos/jogos/sem-capa.svg';
    if (!$caminho || strpos($caminho, '..') !== false) return $padrao;
    return is_file(__DIR__ . '/../' . $caminho) ? $caminho : $padrao;
}

/**
 * Preço "de enfeite" mostrado (borrado) a quem não está logado: o valor real nunca sai do servidor.
 */
function precoBorrado(): string {
    return '<span class="preco-borrado" title="Entre na sua conta para ver o preço" aria-label="Preço disponível após entrar">R$ 99,99</span>';
}

/**
 * Quando o preço foi coletado, em texto, e se já está velho (mais de 3 dias).
 * @return array{texto:string, velho:bool}
 */
function idadeDoPreco(?string $dataAtualizacao): array {
    if (!$dataAtualizacao) return ['texto' => 'data desconhecida', 'velho' => true];
    $t = strtotime($dataAtualizacao);
    $dias = (time() - $t) / 86400;
    return ['texto' => date('d/m/Y H:i', $t), 'velho' => $dias > 3];
}
