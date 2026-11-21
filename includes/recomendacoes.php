<?php
/**
 * Home: recomendações, popularidade e descontos
 * LootPrice
 *
 * Carregado por includes/functions.php.
 */

/**
 * URL de um asset com versão (filemtime): o navegador nunca usa CSS/JS antigo em cache.
 */
function assetUrl(string $path): string {
    $file = __DIR__ . '/../' . $path;
    $v = is_file($file) ? filemtime($file) : time();
    return $path . '?v=' . $v;
}

/**
 * Garante a tabela de acessos (visitas a jogos e buscas). Criada sob demanda,
 * então não é preciso reimportar o schema.sql num banco que já existe.
 */
function ensureAcessosTable(PDO $pdo): bool {
    // Roda só uma vez por instalação (marca em logs/schema_acessos.ok)
    return lp_schema_uma_vez('acessos', function () use ($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `acessos_jogos` (
            `id_acesso` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `id_usuario` INT NULL,
            `id_jogo` INT NOT NULL,
            `tipo` ENUM('visita','busca') NOT NULL DEFAULT 'visita',
            `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_acesso_usuario` (`id_usuario`),
            INDEX `idx_acesso_jogo` (`id_jogo`, `criado_em`),
            CONSTRAINT `fk_acesso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
            CONSTRAINT `fk_acesso_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    });
}

/**
 * Registra que alguém abriu um jogo ('visita') ou o encontrou numa pesquisa ('busca').
 * Alimenta "popular agora" (todos os acessos) e o gosto de cada usuário logado.
 * O mesmo acesso repetido na mesma sessão em menos de 10 min é ignorado.
 */
function logGameAccess(int $idJogo, string $tipo = 'visita'): void {
    $pdo = getDB();
    if (!$pdo || $idJogo <= 0 || !ensureAcessosTable($pdo)) return;

    $key = $tipo . ':' . $idJogo;
    if (isset($_SESSION['acessos_log'][$key]) && time() - $_SESSION['acessos_log'][$key] < 600) return;
    $_SESSION['acessos_log'][$key] = time();

    try {
        $user = currentUser();
        $stmt = $pdo->prepare("INSERT INTO acessos_jogos (id_usuario, id_jogo, tipo) VALUES (:u, :j, :t)");
        $stmt->execute([
            'u' => $user['id_usuario'] ?? null,
            'j' => $idJogo,
            't' => $tipo === 'busca' ? 'busca' : 'visita',
        ]);
    } catch (Throwable $e) { lp_log_erro('logGameAccess', $e); }
}

/**
 * Jogos populares nos últimos N dias: [id_jogo => quantidade de acessos].
 */
function getPopularGameCounts(int $days = 30): array {
    $pdo = getDB();
    if (!$pdo || !ensureAcessosTable($pdo)) return [];
    try {
        $stmt = $pdo->prepare("SELECT id_jogo, COUNT(*) AS total FROM acessos_jogos
                               WHERE criado_em >= (NOW() - INTERVAL " . (int)$days . " DAY)
                               GROUP BY id_jogo");
        $stmt->execute();
        $out = [];
        foreach ($stmt->fetchAll() as $r) $out[(int)$r['id_jogo']] = (int)$r['total'];
        return $out;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Afinidade de cada jogo que o usuário logado já abriu/pesquisou: [id_jogo => peso].
 * Visita pesa 3; aparecer numa pesquisa dele pesa 2.
 */
function getUserInterestWeights(): array {
    $user = currentUser();
    $pdo = getDB();
    if (!$user || !$pdo || !ensureAcessosTable($pdo)) return [];
    try {
        $stmt = $pdo->prepare("SELECT id_jogo, SUM(CASE tipo WHEN 'visita' THEN 3 ELSE 2 END) AS peso
                               FROM acessos_jogos WHERE id_usuario = :u GROUP BY id_jogo");
        $stmt->execute(['u' => $user['id_usuario']]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) $out[(int)$r['id_jogo']] = (int)$r['peso'];
        return $out;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Anexa a melhor oferta (loja de menor preço) a cada jogo:
 * 'valor_original' e 'desconto' (% de desconto dessa oferta).
 */
function attachBestOffer(array $games): array {
    static $offers = null; // a home chama várias vezes: consulta só uma
    $pdo = getDB();
    if ($offers === null) {
        $offers = [];
        if ($pdo) try {
            $rows = $pdo->query("SELECT pr.id_jogo, pr.valor, pr.valor_original, pr.porcentagem_desconto
                                 FROM precos pr
                                 WHERE pr.valor = (SELECT MIN(p2.valor) FROM precos p2 WHERE p2.id_jogo = pr.id_jogo)
                                 ORDER BY pr.porcentagem_desconto DESC")->fetchAll();
            foreach ($rows as $r) {
                $offers[(int)$r['id_jogo']] ??= $r; // empate de preço: fica o de maior desconto
            }
        } catch (Throwable $e) { lp_log_erro('attachBestOffer', $e); }
    }
    foreach ($games as &$g) {
        $o = $offers[(int)$g['id_jogo']] ?? null;
        $g['desconto'] = $o ? (int)$o['porcentagem_desconto'] : 0;
        $g['valor_original'] = $o ? $o['valor_original'] : null;
    }
    unset($g);
    return $games;
}

function gameCategoryList(array $g): array {
    return array_values(array_filter(array_map('trim', explode(',', (string)($g['categorias_nomes'] ?? '')))));
}

/**
 * Destaques e recomendações (padrão: 6 jogos). Cada jogo volta com 'selo' (o motivo).
 * - Logado e com histórico: prioriza jogos que dividem categorias com o que ele pesquisou/abriu
 *   (quem busca Elden Ring recebe Dark Souls III), com "popular agora" e nota como desempate.
 * - Sem histórico ou visitante: jogos em alta no momento, depois os mais bem avaliados.
 */
function getRecommendedGames(int $limit = 6): array {
    $all = attachBestOffer(getGamesCatalog());
    if (!$all) return [];

    $byId = [];
    foreach ($all as $g) $byId[(int)$g['id_jogo']] = $g;

    $pop = getPopularGameCounts();
    $maxPop = max(1, $pop ? max($pop) : 1);
    $interest = array_intersect_key(getUserInterestWeights(), $byId);

    // peso de cada categoria = soma dos pesos dos jogos que o usuário acessou
    $catWeight = [];
    foreach ($interest as $id => $w) {
        foreach (gameCategoryList($byId[$id]) as $c) $catWeight[$c] = ($catWeight[$c] ?? 0) + $w;
    }
    $personalOf = fn(array $g) => array_sum(array_map(fn($c) => $catWeight[$c] ?? 0, gameCategoryList($g)));

    $maxPersonal = 0;
    foreach ($byId as $id => $g) {
        if (!isset($interest[$id])) $maxPersonal = max($maxPersonal, $personalOf($g));
    }

    $scored = [];
    foreach ($byId as $id => $g) {
        $seen = isset($interest[$id]);
        $cats = gameCategoryList($g);
        $personal = $personalOf($g);
        $popN = ($pop[$id] ?? 0) / $maxPop;
        $meta = (float)($g['nota_metacritic'] ?? 0) / 100;

        $score = ($maxPersonal > 0 ? 10 * $personal / $maxPersonal : 0) + 3 * $popN + 1.5 * $meta;
        if ($seen) $score -= 100; // já visto: só entra se faltar jogo para completar a lista

        // selo: com qual jogo do histórico ele mais se parece?
        $selo = null;
        if ($personal > 0 && !$seen) {
            $best = 0; $bestName = null;
            foreach ($interest as $iid => $w) {
                $shared = count(array_intersect($cats, gameCategoryList($byId[$iid])));
                if ($shared > 0 && $shared * 100 + $w > $best) { $best = $shared * 100 + $w; $bestName = $byId[$iid]['nome']; }
            }
            if ($bestName) $selo = 'Parecido com ' . $bestName;
        }
        // Tag só quando informa algo: "Parecido com X" ou "Em alta agora".
        // "Em alta" exige volume real: pelo menos 3 acessos e 40% do campeão de acessos (antes bastava 1).
        if (!$selo && ($pop[$id] ?? 0) >= max(3, (int)ceil($maxPop * 0.4))) $selo = 'Em alta agora';

        $g['selo'] = $selo;
        $scored[] = ['score' => $score, 'g' => $g];
    }

    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice(array_column($scored, 'g'), 0, $limit);
}

/**
 * Jogos com desconto real (preço > 0 e % > 0), do maior desconto para o menor.
 * $maxPrice limita pelo menor preço (ex.: 10, 50, 100).
 */
function getDiscountedGames(?float $maxPrice = null, ?int $limit = null): array {
    $out = [];
    foreach (attachBestOffer(getGamesCatalog()) as $g) {
        $preco = (float)($g['menor_preco'] ?? 0);
        if ($g['desconto'] <= 0 || $preco <= 0) continue;
        if ($maxPrice !== null && $preco > $maxPrice) continue;
        $out[] = $g;
    }
    usort($out, fn($a, $b) => ($b['desconto'] <=> $a['desconto']) ?: ((float)$a['menor_preco'] <=> (float)$b['menor_preco']));
    return $limit ? array_slice($out, 0, $limit) : $out;
}
