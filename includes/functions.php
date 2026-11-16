<?php
/**
 * Funções Auxiliares e Consultas do Sistema
 * LootPrice
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/recomendacoes.php';
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
 * Lista padrão de jogos semeados para fallback caso o banco não esteja importado ainda
 */
function getDefaultMockGames(): array {
    return [
        [
            'id_jogo' => 1,
            'nome' => 'Death Stranding (Director\'s Cut)',
            'descricao' => 'Death Stranding é um jogo de ação e aventura em mundo aberto desenvolvido por Kojima Productions.',
            'data_lancamento' => '2019-11-08',
            'nota_metacritic' => 86.0,
            'imagem_capa' => 'fotos/jogos/ds.jpg',
            'menor_preco' => 63.60,
            'has_game_pass' => 1,
            'categorias_nomes' => 'Aventura'
        ],
        [
            'id_jogo' => 2,
            'nome' => 'Tom Clancy\'s Rainbow Six Siege',
            'descricao' => 'Rainbow Six Siege é um jogo de tiro tático focado em combate tático e destruição ambiental.',
            'data_lancamento' => '2015-12-01',
            'nota_metacritic' => 79.0,
            'imagem_capa' => 'fotos/jogos/r6.jpg',
            'menor_preco' => 29.99,
            'has_game_pass' => 0,
            'categorias_nomes' => 'Shooter, Multiplayer'
        ],
        [
            'id_jogo' => 3,
            'nome' => 'Apex Legends',
            'descricao' => 'Apex Legends é um battle royale gratuito ambientado no universo de Titanfall.',
            'data_lancamento' => '2019-02-04',
            'nota_metacritic' => 89.0,
            'imagem_capa' => 'fotos/jogos/apex.jpg',
            'menor_preco' => 0.00,
            'has_game_pass' => 1,
            'categorias_nomes' => 'Battle Royale, Shooter'
        ],
        [
            'id_jogo' => 4,
            'nome' => 'Dead by Daylight',
            'descricao' => 'Dead by Daylight é um jogo de terror e sobrevivência multiplayer assimétrico (4vs1).',
            'data_lancamento' => '2016-06-14',
            'nota_metacritic' => 71.0,
            'imagem_capa' => 'fotos/jogos/dbd.png',
            'menor_preco' => 34.99,
            'has_game_pass' => 1,
            'categorias_nomes' => 'Terror, Multiplayer'
        ],
        [
            'id_jogo' => 5,
            'nome' => 'Elden Ring',
            'descricao' => 'Elden Ring é um RPG de ação em mundo aberto desenvolvido pela FromSoftware.',
            'data_lancamento' => '2022-02-25',
            'nota_metacritic' => 96.0,
            'imagem_capa' => 'fotos/jogos/er.jpg',
            'menor_preco' => 219.90,
            'has_game_pass' => 0,
            'categorias_nomes' => 'RPG, Soulslike'
        ],
        [
            'id_jogo' => 6,
            'nome' => 'Risk of Rain 2',
            'descricao' => 'Risk of Rain 2 é um clássico jogo roguelike de ação em terceira pessoa multiplayer.',
            'data_lancamento' => '2020-08-11',
            'nota_metacritic' => 85.0,
            'imagem_capa' => 'fotos/jogos/ror2.jpg',
            'menor_preco' => 19.79,
            'has_game_pass' => 0,
            'categorias_nomes' => 'Roguelike'
        ],
        [
            'id_jogo' => 7,
            'nome' => 'Sea of Thieves',
            'descricao' => 'Sea of Thieves é um jogo de aventura multiplayer em mundo aberto em um universo pirata.',
            'data_lancamento' => '2018-03-20',
            'nota_metacritic' => 82.0,
            'imagem_capa' => 'fotos/jogos/sot.jpg',
            'menor_preco' => 89.50,
            'has_game_pass' => 1,
            'categorias_nomes' => 'Aventura, Multiplayer'
        ],
        [
            'id_jogo' => 8,
            'nome' => 'Warframe',
            'descricao' => 'Warframe é um jogo cooperativo de ação rápida e tiro focado em ninjas espaciais cibernéticos.',
            'data_lancamento' => '2013-03-25',
            'nota_metacritic' => 85.0,
            'imagem_capa' => 'fotos/jogos/warframe.png',
            'menor_preco' => 0.00,
            'has_game_pass' => 0,
            'categorias_nomes' => 'MMO RPG, Shooter'
        ],
        [
            'id_jogo' => 9,
            'nome' => 'The Witcher 3: Wild Hunt',
            'descricao' => 'The Witcher 3 é um RPG épico de ação em mundo aberto desenvolvido pela CD Projekt Red.',
            'data_lancamento' => '2015-05-18',
            'nota_metacritic' => 93.0,
            'imagem_capa' => 'fotos/jogos/witcher.jpg',
            'menor_preco' => 12.99,
            'has_game_pass' => 0,
            'categorias_nomes' => 'RPG'
        ],
        [
            'id_jogo' => 10,
            'nome' => 'Dark Souls III',
            'descricao' => 'Dark Souls III é um RPG de ação com combate visceral e chefes lendários.',
            'data_lancamento' => '2016-04-12',
            'nota_metacritic' => 89.0,
            'imagem_capa' => 'fotos/jogos/ds3.png',
            'menor_preco' => 114.95,
            'has_game_pass' => 0,
            'categorias_nomes' => 'RPG, Soulslike'
        ]
    ];
}

/**
 * Busca a lista de jogos com suporte a filtros de busca, categoria, plataforma e faixa de preço (RF01, RF02, RF03)
 */
function getGamesCatalog(?string $search = null, ?int $categoryId = null, ?int $platformId = null, ?string $priceRange = null): array {
    $pdo = getDB();
    if (!$pdo) {
        // Fallback para mock caso banco não esteja pronto
        $mock = getDefaultMockGames();
        if (!empty($search)) {
            $mock = array_filter($mock, fn($j) => stripos($j['nome'], $search) !== false);
        }
        return array_values($mock);
    }

    try {
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

        $params = [];

        if (!empty($search)) {
            $sql .= " AND j.nome LIKE :search";
            $params['search'] = '%' . trim($search) . '%';
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

        // Filtro por faixa de preço (RF03)
        if (!empty($priceRange)) {
            if ($priceRange === 'free') {
                $sql .= " HAVING MIN(pr.valor) = 0";
            } elseif ($priceRange === 'under50') {
                $sql .= " HAVING MIN(pr.valor) <= 50.00 AND MIN(pr.valor) > 0";
            } elseif ($priceRange === 'under100') {
                $sql .= " HAVING MIN(pr.valor) <= 100.00";
            } elseif ($priceRange === 'over100') {
                $sql .= " HAVING MIN(pr.valor) > 100.00";
            }
        }

        $sql .= " ORDER BY j.nome ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        if (empty($results) && empty($search) && empty($categoryId) && empty($platformId) && empty($priceRange)) {
            return getDefaultMockGames();
        }

        return $results;
    } catch (Exception $e) {
        return getDefaultMockGames();
    }
}

/**
 * Busca detalhes completos de um jogo pelo ID (RF04, RF07, RN12)
 */
function getGameById(int $id): ?array {
    $pdo = getDB();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM jogos WHERE id_jogo = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $game = $stmt->fetch();
            if ($game) return $game;
        } catch (Exception $e) {}
    }

    // Mock fallback
    $mock = getDefaultMockGames();
    foreach ($mock as $m) {
        if ($m['id_jogo'] === $id) return $m;
    }
    return null;
}

/**
 * Retorna os preços de um jogo em todas as lojas cadastradas ordenado pelo menor preço (RN01, RF05, RF06)
 */
function getGamePrices(int $idJogo): array {
    $pdo = getDB();
    if ($pdo) {
        try {
            $sql = "SELECT pr.*, 
                           l.nome AS loja_nome, 
                           l.logo AS loja_logo, 
                           l.url AS loja_url,
                           p.nome AS plataforma_nome,
                           p.icone AS plataforma_icone
                    FROM precos pr
                    INNER JOIN lojas l ON pr.id_loja = l.id_loja
                    INNER JOIN plataformas p ON pr.id_plataforma = p.id_plataforma
                    WHERE pr.id_jogo = :id_jogo
                    ORDER BY pr.valor ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id_jogo' => $idJogo]);
            $res = $stmt->fetchAll();
            if (!empty($res)) return $res;
        } catch (Exception $e) {}
    }

    // Fallback de preços para visualização
    return [
        [
            'id_preco' => 1,
            'id_jogo' => $idJogo,
            'loja_nome' => 'Steam',
            'loja_logo' => 'fotos/extras/steam.png',
            'plataforma_nome' => 'PC',
            'valor' => 12.99,
            'valor_original' => 129.99,
            'porcentagem_desconto' => 90,
            'link_compra' => 'https://store.steampowered.com'
        ],
        [
            'id_preco' => 2,
            'id_jogo' => $idJogo,
            'loja_nome' => 'Xbox Store',
            'loja_logo' => 'fotos/extras/xbox.png',
            'plataforma_nome' => 'Xbox',
            'valor' => 14.30,
            'valor_original' => 143.00,
            'porcentagem_desconto' => 90,
            'link_compra' => 'https://www.xbox.com'
        ],
        [
            'id_preco' => 3,
            'id_jogo' => $idJogo,
            'loja_nome' => 'Epic Games',
            'loja_logo' => 'fotos/extras/epic.png',
            'plataforma_nome' => 'PC',
            'valor' => 99.96,
            'valor_original' => 99.96,
            'porcentagem_desconto' => 0,
            'link_compra' => 'https://store.epicgames.com'
        ]
    ];
}

/**
 * Retorna as categorias associadas a um jogo
 */
function getGameCategories(int $idJogo): array {
    $pdo = getDB();
    if ($pdo) {
        try {
            $sql = "SELECT c.* FROM categorias c
                    INNER JOIN jogo_categoria jc ON c.id_categoria = jc.id_categoria
                    WHERE jc.id_jogo = :id_jogo
                    ORDER BY c.nome ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id_jogo' => $idJogo]);
            $res = $stmt->fetchAll();
            if (!empty($res)) return $res;
        } catch (Exception $e) {}
    }
    return [['id_categoria' => 1, 'nome' => 'RPG'], ['id_categoria' => 4, 'nome' => 'Aventura']];
}

/**
 * Retorna as plataformas associadas a um jogo (RF04)
 */
function getGamePlatforms(int $idJogo): array {
    $pdo = getDB();
    if ($pdo) {
        try {
            $sql = "SELECT p.* FROM plataformas p
                    INNER JOIN jogo_plataforma jp ON p.id_plataforma = jp.id_plataforma
                    WHERE jp.id_jogo = :id_jogo
                    ORDER BY p.nome ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id_jogo' => $idJogo]);
            $res = $stmt->fetchAll();
            if (!empty($res)) return $res;
        } catch (Exception $e) {}
    }
    return [
        ['id_plataforma' => 1, 'nome' => 'PC'],
        ['id_plataforma' => 2, 'nome' => 'Xbox'],
        ['id_plataforma' => 3, 'nome' => 'PlayStation']
    ];
}

/**
 * Retorna os serviços de assinatura associados a um jogo (ex: Game Pass - RF07)
 */
function getGameSubscriptions(int $idJogo): array {
    $pdo = getDB();
    if ($pdo) {
        try {
            $sql = "SELECT sa.* FROM servicos_assinatura sa
                    INNER JOIN jogo_assinatura ja ON sa.id_servico = ja.id_servico
                    WHERE ja.id_jogo = :id_jogo";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id_jogo' => $idJogo]);
            return $stmt->fetchAll();
        } catch (Exception $e) {}
    }
    return [];
}

/**
 * Lista todas as categorias cadastradas
 */
function getAllCategories(): array {
    $pdo = getDB();
    if ($pdo) {
        try {
            return $pdo->query("SELECT * FROM categorias ORDER BY nome ASC")->fetchAll();
        } catch (Exception $e) {}
    }
    return [
        ['id_categoria' => 1, 'nome' => 'RPG'],
        ['id_categoria' => 2, 'nome' => 'Shooter'],
        ['id_categoria' => 3, 'nome' => 'Battle Royale'],
        ['id_categoria' => 4, 'nome' => 'Aventura'],
        ['id_categoria' => 5, 'nome' => 'Terror'],
        ['id_categoria' => 6, 'nome' => 'Roguelike'],
        ['id_categoria' => 7, 'nome' => 'Soulslike'],
        ['id_categoria' => 8, 'nome' => 'MMO RPG'],
        ['id_categoria' => 9, 'nome' => 'Multiplayer']
    ];
}

/**
 * Lista todas as plataformas cadastradas
 */
function getAllPlatforms(): array {
    $pdo = getDB();
    if ($pdo) {
        try {
            return $pdo->query("SELECT * FROM plataformas ORDER BY nome ASC")->fetchAll();
        } catch (Exception $e) {}
    }
    return [
        ['id_plataforma' => 1, 'nome' => 'PC'],
        ['id_plataforma' => 2, 'nome' => 'Xbox'],
        ['id_plataforma' => 3, 'nome' => 'PlayStation'],
        ['id_plataforma' => 4, 'nome' => 'Nintendo Switch']
    ];
}

/**
 * Lista todas as lojas cadastradas
 */
function getAllStores(): array {
    $pdo = getDB();
    if ($pdo) {
        try {
            return $pdo->query("SELECT * FROM lojas ORDER BY nome ASC")->fetchAll();
        } catch (Exception $e) {}
    }
    return [
        ['id_loja' => 1, 'nome' => 'Steam'],
        ['id_loja' => 2, 'nome' => 'Epic Games'],
        ['id_loja' => 3, 'nome' => 'Xbox Store'],
        ['id_loja' => 4, 'nome' => 'Nuuvem'],
        ['id_loja' => 5, 'nome' => 'GOG']
    ];
}

/**
 * Lista todos os serviços de assinatura
 */
function getAllSubscriptions(): array {
    $pdo = getDB();
    if ($pdo) {
        try {
            return $pdo->query("SELECT * FROM servicos_assinatura ORDER BY nome ASC")->fetchAll();
        } catch (Exception $e) {}
    }
    return [
        ['id_servico' => 1, 'nome' => 'Xbox Game Pass'],
        ['id_servico' => 2, 'nome' => 'EA Play'],
        ['id_servico' => 3, 'nome' => 'PlayStation Plus']
    ];
}
