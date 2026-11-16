<?php
/**
 * Robô de coleta de preços do LootPrice (RF08 / RN02)
 *
 * - Consulta Steam, GOG, Xbox, Nuuvem e Epic Games (cada loja do jeito que ela permite).
 * - Grava os preços na tabela `precos` e lembra qual é o jogo em cada loja em `jogo_loja_ref`.
 * - Descobre jogos populares (SteamSpy + Steam) e os cadastra no site.
 *
 * Roda pela linha de comando (robo/coletar.php, agendado às 14:00) ou pelo botão
 * em admin/atualizar_precos.php.
 */
require_once __DIR__ . '/../config/db.php';

const LP_UA = 'LootPriceBot/1.0 (projeto academico)';
const LP_MAX_NOVOS_POR_EXECUCAO = 8;     // jogos novos cadastrados por execução
const LP_MAX_JOGOS_NO_SITE = 100;        // para de cadastrar populares ao chegar nesse total
const LP_LIMIAR_NOME = 0.88;             // semelhança mínima entre nomes para aceitar o jogo da loja
const LP_RETENTAR_NAO_ENCONTRADO_DIAS = 7;

/* ====================================================================
 * Utilidades: HTTP, JSON e comparação de nomes
 * ==================================================================== */

/** GET/POST educado: identifica o robô, espera entre chamadas e tenta de novo uma vez. */
function lp_http(string $url, ?string $postJson = null, array $headers = []): ?string
{
    static $ultima = 0.0;
    $cafile = 'C:\xampp\apache\bin\curl-ca-bundle.crt';

    for ($tentativa = 1; $tentativa <= 2; $tentativa++) {
        $espera = 0.35 - (microtime(true) - $ultima);
        if ($espera > 0) usleep((int)($espera * 1e6));

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_USERAGENT      => LP_UA,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if (!ini_get('curl.cainfo') && is_file($cafile)) $opts[CURLOPT_CAINFO] = $cafile;
        if ($postJson !== null) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $postJson;
            $opts[CURLOPT_HTTPHEADER] = array_merge($headers, ['Content-Type: application/json']);
        }
        curl_setopt_array($ch, $opts);
        $corpo = curl_exec($ch);
        $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ultima = microtime(true);

        if ($corpo !== false && $codigo > 0 && $codigo < 400) return $corpo;
        if ($codigo === 403 || $codigo === 404) return null; // não adianta insistir
        sleep(1);
    }
    return null;
}

function lp_json(string $url, ?string $postJson = null, array $headers = []): ?array
{
    $corpo = lp_http($url, $postJson, $headers);
    if ($corpo === null) return null;
    $dados = json_decode($corpo, true);
    return is_array($dados) ? $dados : null;
}

/** minúsculas, sem acento/pontuação/símbolos de marca registrada. */
function lp_norm(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
    ]);
    $s = preg_replace("/['’`´]/u", '', $s);
    $s = preg_replace('/[™®©]/u', '', $s);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim($s);
}

/** nome "essencial": sem palavras de edição (remastered, definitive, goty...). */
function lp_core(string $s): string
{
    static $ruido = ['the','of','game','year','edition','remastered','remaster','deluxe','complete',
        'definitive','directors','director','cut','goty','ultimate','standard','gold','premium',
        'collection','enhanced','anniversary','bundle','pack'];
    $palavras = array_filter(explode(' ', lp_norm($s)), fn($w) => $w !== '' && !in_array($w, $ruido, true));
    return implode(' ', $palavras);
}

function lp_semelhanca(string $a, string $b): float
{
    if (lp_norm($a) !== '' && lp_norm($a) === lp_norm($b)) return 1.0; // título idêntico
    $ca = lp_core($a);
    $cb = lp_core($b);
    if ($ca === '' || $cb === '') return 0.0;
    if ($ca === $cb) return 0.95; // mesmo jogo, outra edição (deluxe, remastered...): só se não houver o título exato
    // Qualquer outra diferença é OUTRO jogo ("Risk of Rain" x "Risk of Rain 2", "Rainbow Six Siege" x "Vegas").
    return 0.0;
}

/** Escolhe o candidato (['titulo'=>...]) mais parecido com $nome, ou null se nenhum passar do limiar. */
function lp_melhor(string $nome, array $candidatos): ?array
{
    $melhor = null;
    $melhorNota = 0.0;
    foreach ($candidatos as $c) {
        $nota = lp_semelhanca($nome, $c['titulo']);
        if ($nota < LP_LIMIAR_NOME) continue;
        $desempate = $nota === $melhorNota && $melhor && mb_strlen($c['titulo']) < mb_strlen($melhor['titulo']);
        if ($nota > $melhorNota || $desempate) {
            $melhor = $c;
            $melhorNota = $nota;
        }
    }
    return $melhor;
}

function lp_preco(float $valor, float $original): array
{
    $original = max($original, $valor);
    $desconto = $original > 0 ? (int)round((1 - $valor / $original) * 100) : 0;
    return ['valor' => round($valor, 2), 'original' => round($original, 2), 'desconto' => max(0, $desconto)];
}

/* ====================================================================
 * Lojas. Cada função recebe ($jogo, $ref) e devolve
 * ['id','titulo','url','valor','original','desconto'] ou null.
 * $ref = jogo já reconhecido na loja (['id_externo','titulo']) ou null.
 * ==================================================================== */

/* ---------- Steam ---------- */

function lp_steam_detalhes(int $appid): ?array
{
    $j = lp_json("https://store.steampowered.com/api/appdetails?appids=$appid&cc=br&l=portuguese");
    $d = $j[$appid]['data'] ?? null;
    return is_array($d) ? $d : null;
}

function lp_steam_para_preco(array $d, int $appid): ?array
{
    if (!empty($d['is_free'])) {
        $p = lp_preco(0, 0);
    } elseif (!empty($d['price_overview'])) {
        $po = $d['price_overview'];
        $p = lp_preco($po['final'] / 100, $po['initial'] / 100);
    } else {
        return null; // sem preço (não lançado / indisponível no Brasil)
    }
    return $p + ['id' => (string)$appid, 'titulo' => $d['name'] ?? '', 'url' => "https://store.steampowered.com/app/$appid/"];
}

function lp_loja_steam(array $jogo, ?array $ref): ?array
{
    $appid = $ref ? (int)$ref['id_externo'] : 0;
    if (!$appid) {
        $j = lp_json('https://store.steampowered.com/api/storesearch/?term=' . rawurlencode(lp_norm($jogo['nome'])) . '&l=portuguese&cc=BR');
        $cands = [];
        foreach ($j['items'] ?? [] as $it) {
            if (($it['type'] ?? '') === 'app') $cands[] = ['titulo' => $it['name'], 'id' => $it['id']];
        }
        $achado = lp_melhor($jogo['nome'], $cands);
        if (!$achado) return null;
        $appid = (int)$achado['id'];
    }
    $d = lp_steam_detalhes($appid);
    $r = $d ? lp_steam_para_preco($d, $appid) : null;
    if ($r && !$ref) $r['titulo'] = $d['name'];
    return $r;
}

/* ---------- GOG ---------- */

function lp_loja_gog(array $jogo, ?array $ref): ?array
{
    $busca = $ref['titulo'] ?? $jogo['nome'];
    $j = lp_json('https://catalog.gog.com/v1/catalog?limit=12&query=like%3A' . rawurlencode(lp_norm($busca)) . '&countryCode=BR&currencyCode=BRL');
    $cands = [];
    foreach ($j['products'] ?? [] as $p) {
        if (!in_array($p['productType'] ?? '', ['game', 'pack'], true)) continue;
        $cands[] = ['titulo' => $p['title'], 'id' => (string)$p['id'], 'p' => $p];
    }
    $achado = null;
    if ($ref) {
        foreach ($cands as $c) if ($c['id'] === (string)$ref['id_externo']) { $achado = $c; break; }
    }
    $achado = $achado ?? lp_melhor($jogo['nome'], $cands);
    if (!$achado) return null;

    $p = $achado['p'];
    $valor = (float)($p['price']['finalMoney']['amount'] ?? -1);
    if ($valor < 0) return null;
    $original = (float)($p['price']['baseMoney']['amount'] ?? $valor);
    return lp_preco($valor, $original) + ['id' => $achado['id'], 'titulo' => $achado['titulo'], 'url' => $p['storeLink'] ?? 'https://www.gog.com'];
}

/* ---------- Xbox ---------- */

function lp_xbox_preco(string $bigId): ?array
{
    $j = lp_json('https://displaycatalog.mp.microsoft.com/v7.0/products?bigIds=' . rawurlencode($bigId) . '&market=BR&languages=pt-BR');
    $prod = $j['Products'][0] ?? null;
    if (!$prod) return null;
    foreach ($prod['DisplaySkuAvailabilities'] ?? [] as $sku) {
        foreach ($sku['Availabilities'] ?? [] as $av) {
            $pr = $av['OrderManagementData']['Price'] ?? null;
            if ($pr && isset($pr['ListPrice'])) {
                $lista = (float)$pr['ListPrice'];
                return lp_preco($lista, (float)($pr['MSRP'] ?? $lista)) + [
                    'id'     => $bigId,
                    'titulo' => $prod['LocalizedProperties'][0]['ProductTitle'] ?? '',
                    'url'    => 'https://www.xbox.com/pt-BR/games/store/-/' . $bigId,
                ];
            }
        }
    }
    return null;
}

function lp_loja_xbox(array $jogo, ?array $ref): ?array
{
    if ($ref) return lp_xbox_preco($ref['id_externo']);

    $j = lp_json('https://www.microsoft.com/services/api/v3/suggest?market=pt-BR&clientId=7F27B536-CF6B-4C65-8638-A0F8CBDFCA65'
        . '&sources=DCatAll-Products&filter=%2BClientType%3AStoreWeb&counts=8&query=' . rawurlencode(lp_norm($jogo['nome'])));
    $cands = [];
    foreach ($j['ResultSets'] ?? [] as $set) {
        foreach ($set['Suggests'] ?? [] as $s) {
            $meta = array_column($s['Metas'] ?? [], 'Value', 'Key');
            if (($meta['ProductType'] ?? '') === 'Games' && !empty($meta['BigCatalogId'])) {
                $cands[] = ['titulo' => $s['Title'], 'id' => $meta['BigCatalogId']];
            }
        }
    }
    $achado = lp_melhor($jogo['nome'], $cands);
    return $achado ? lp_xbox_preco($achado['id']) : null;
}

/* ---------- Nuuvem (lê o HTML da busca) ---------- */

function lp_loja_nuuvem(array $jogo, ?array $ref): ?array
{
    $busca = $ref['titulo'] ?? $jogo['nome'];
    $html = lp_http('https://www.nuuvem.com/br-en/catalog/page/1/search/' . rawurlencode(lp_norm($busca)));
    if ($html === null) return null;

    $partes = preg_split('/(?=data-wishlist-button-product-data-value=")/', $html);
    array_shift($partes);
    $cands = [];
    foreach ($partes as $parte) {
        if (!preg_match('/^data-wishlist-button-product-data-value="([^"]+)"/', $parte, $m)) continue;
        $d = json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true);
        if (!is_array($d) || !isset($d['price'], $d['name'], $d['url'])) continue;
        // Só chave de PC: página de jogo (/item/, não /bundle/) cuja lista de plataformas inclui Windows/PC.
        if (!str_contains($d['url'], '/item/')) continue;
        $plataformas = preg_match('#<ul class="platform-tags">(.*?)</ul>#s', $parte, $pt) ? strip_tags($pt[1]) : '';
        // ("Windows + Xbox" = jogo Play Anywhere, a chave funciona no PC; só console puro é descartado)
        if (!preg_match('/\b(Windows|PC)\b/i', $plataformas)) continue;
        $desconto = 0;
        if (preg_match('/-\s?(\d{1,2})\s?%/', substr($parte, 0, 6000), $pm)) $desconto = (int)$pm[1];
        $cands[] = ['titulo' => $d['name'], 'id' => (string)$d['id'], 'd' => $d, 'desc' => $desconto];
    }
    $achado = null;
    if ($ref) {
        foreach ($cands as $c) if ($c['id'] === (string)$ref['id_externo']) { $achado = $c; break; }
    }
    $achado = $achado ?? lp_melhor($jogo['nome'], $cands);
    if (!$achado) return null;

    $valor = (float)$achado['d']['price'];
    $original = $achado['desc'] > 0 && $achado['desc'] < 100 ? $valor / (1 - $achado['desc'] / 100) : $valor;
    return lp_preco($valor, $original) + ['id' => $achado['id'], 'titulo' => $achado['titulo'], 'url' => $achado['d']['url']];
}

/* ---------- Epic Games ---------- */

/**
 * A Epic protege a loja com um desafio anti-robô (Cloudflare). O robô tenta UMA vez, se identificando
 * normalmente; se for bloqueado, não insiste nem se disfarça: o último preço conhecido é mantido.
 */
function lp_loja_epic(array $jogo, ?array $ref): ?array
{
    static $bloqueado = false;
    if ($bloqueado) return null;

    $query = 'query($kw:String,$country:String!,$locale:String){Catalog{searchStore(keywords:$kw,country:$country,locale:$locale,count:10){'
        . 'elements{title id productSlug urlSlug catalogNs{mappings(pageType:"productHome"){pageSlug}} '
        . 'price(country:$country){totalPrice{originalPrice discountPrice}}}}}}';
    $corpo = json_encode(['query' => $query, 'variables' => [
        'kw' => $ref['titulo'] ?? lp_norm($jogo['nome']), 'country' => 'BR', 'locale' => 'pt-BR',
    ]]);
    $resp = lp_http('https://store.epicgames.com/graphql', $corpo);
    $j = $resp !== null ? json_decode($resp, true) : null;
    if (!is_array($j) || !isset($j['data']['Catalog']['searchStore']['elements'])) {
        $bloqueado = true;
        lp_log('Epic Games bloqueou o acesso automático (proteção anti-robô). Preços da Epic mantidos como estão.');
        return null;
    }

    $cands = [];
    foreach ($j['data']['Catalog']['searchStore']['elements'] as $e) {
        $cands[] = ['titulo' => $e['title'], 'id' => $e['id'], 'e' => $e];
    }
    $achado = null;
    if ($ref) {
        foreach ($cands as $c) if ($c['id'] === $ref['id_externo']) { $achado = $c; break; }
    }
    $achado = $achado ?? lp_melhor($jogo['nome'], $cands);
    if (!$achado) return null;

    $e = $achado['e'];
    $tp = $e['price']['totalPrice'] ?? null;
    if (!$tp) return null;
    $slug = $e['catalogNs']['mappings'][0]['pageSlug'] ?? ($e['productSlug'] ?: $e['urlSlug']);
    $slug = preg_replace('#/home$#', '', (string)$slug);
    return lp_preco($tp['discountPrice'] / 100, $tp['originalPrice'] / 100) + [
        'id' => $achado['id'], 'titulo' => $achado['titulo'], 'url' => "https://store.epicgames.com/pt-BR/p/$slug",
    ];
}

/* ====================================================================
 * Banco de dados
 * ==================================================================== */

function lp_garantir_tabelas(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `jogo_loja_ref` (
        `id_jogo` INT NOT NULL,
        `id_loja` INT NOT NULL,
        `id_externo` VARCHAR(120) DEFAULT NULL,
        `titulo` VARCHAR(255) DEFAULT NULL,
        `encontrado` TINYINT(1) NOT NULL DEFAULT 1,
        `verificado_em` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_jogo`, `id_loja`),
        CONSTRAINT `fk_ref_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE,
        CONSTRAINT `fk_ref_loja` FOREIGN KEY (`id_loja`) REFERENCES `lojas` (`id_loja`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function lp_salvar_ref(PDO $pdo, int $idJogo, int $idLoja, ?string $idExterno, ?string $titulo, bool $achou): void
{
    $pdo->prepare("INSERT INTO jogo_loja_ref (id_jogo, id_loja, id_externo, titulo, encontrado, verificado_em)
                   VALUES (?,?,?,?,?,NOW())
                   ON DUPLICATE KEY UPDATE id_externo=VALUES(id_externo), titulo=VALUES(titulo),
                                           encontrado=VALUES(encontrado), verificado_em=NOW()")
        ->execute([$idJogo, $idLoja, $idExterno, $titulo, (int)$achou]);
}

/** Grava o preço (atualiza se já existe jogo+loja, senão insere). Devolve o valor anterior (ou null). */
function lp_salvar_preco(PDO $pdo, int $idJogo, int $idLoja, int $idPlataforma, array $r): ?float
{
    $url = mb_substr($r['url'], 0, 255);
    $sel = $pdo->prepare('SELECT id_preco, valor FROM precos WHERE id_jogo=? AND id_loja=? LIMIT 1');
    $sel->execute([$idJogo, $idLoja]);
    $atual = $sel->fetch();

    if ($atual) {
        $pdo->prepare('UPDATE precos SET valor=?, valor_original=?, porcentagem_desconto=?, link_compra=?, data_atualizacao=NOW() WHERE id_preco=?')
            ->execute([$r['valor'], $r['original'], $r['desconto'], $url, $atual['id_preco']]);
        return (float)$atual['valor'];
    }
    $pdo->prepare('INSERT INTO precos (id_jogo,id_loja,id_plataforma,valor,valor_original,porcentagem_desconto,link_compra) VALUES (?,?,?,?,?,?,?)')
        ->execute([$idJogo, $idLoja, $idPlataforma, $r['valor'], $r['original'], $r['desconto'], $url]);
    return null;
}

/* ====================================================================
 * Descoberta de jogos populares
 * ==================================================================== */

function lp_categoria_id(PDO $pdo, string $nome): int
{
    $s = $pdo->prepare('SELECT id_categoria FROM categorias WHERE nome=?');
    $s->execute([$nome]);
    $id = $s->fetchColumn();
    if ($id) return (int)$id;
    $pdo->prepare('INSERT INTO categorias (nome) VALUES (?)')->execute([$nome]);
    return (int)$pdo->lastInsertId();
}

function lp_categorias_do_jogo(array $d): array
{
    $porGenero = ['1'=>'Ação','25'=>'Aventura','3'=>'RPG','2'=>'Estratégia','28'=>'Simulação',
                  '9'=>'Corrida','18'=>'Esportes','23'=>'Indie','29'=>'Multiplayer','4'=>'Casual'];
    $cats = [];
    foreach ($d['genres'] ?? [] as $g) {
        if (isset($porGenero[$g['id']])) $cats[] = $porGenero[$g['id']];
    }
    foreach ($d['categories'] ?? [] as $c) {
        if (in_array((int)$c['id'], [1, 36, 20, 27, 9, 38], true)) { $cats[] = 'Multiplayer'; break; }
    }
    $texto = mb_strtolower(($d['name'] ?? '') . ' ' . ($d['short_description'] ?? ''));
    $dicas = ['Shooter'=>'/tiro|shooter|fps/', 'Terror'=>'/terror|horror/', 'Battle Royale'=>'/battle royale/',
              'Roguelike'=>'/roguelike|roguelite/', 'Soulslike'=>'/soulslike|souls-like/'];
    foreach ($dicas as $nome => $regex) {
        if (preg_match($regex, $texto)) $cats[] = $nome;
    }
    return array_values(array_unique($cats));
}

function lp_data_lancamento(?string $texto): ?string
{
    if (!$texto) return null;
    $meses = ['jan'=>1,'fev'=>2,'mar'=>3,'abr'=>4,'mai'=>5,'jun'=>6,'jul'=>7,'ago'=>8,'set'=>9,'out'=>10,'nov'=>11,'dez'=>12];
    if (!preg_match('/(\d{1,2})\s*(?:de\s+)?([a-zç]{3})[a-zç]*\.?\s*(?:de\s+|,\s*)?(\d{4})/iu', $texto, $m)) return null;
    $mes = $meses[mb_strtolower($m[2])] ?? null;
    return $mes ? sprintf('%04d-%02d-%02d', $m[3], $mes, $m[1]) : null;
}

/** Baixa a capa (vertical, se existir) para fotos/jogos/. Devolve o caminho relativo ou null. */
function lp_baixar_capa(int $appid, ?string $headerImage): ?string
{
    $destino = __DIR__ . "/../fotos/jogos/steam_$appid.jpg";
    $fontes = ["https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/$appid/library_600x900.jpg"];
    if ($headerImage) $fontes[] = $headerImage;
    foreach ($fontes as $url) {
        $bin = lp_http($url);
        if ($bin !== null && strlen($bin) > 5000 && substr($bin, 0, 2) === "\xFF\xD8") {
            file_put_contents($destino, $bin);
            return "fotos/jogos/steam_$appid.jpg";
        }
    }
    return null;
}

/** Cadastra até $max jogos populares (SteamSpy: mais jogados nas últimas 2 semanas; fallback: mais vendidos da Steam). */
function lp_descobrir_populares(PDO $pdo, int $max, array &$resumo): void
{
    $total = (int)$pdo->query('SELECT COUNT(*) FROM jogos')->fetchColumn();
    if ($total >= LP_MAX_JOGOS_NO_SITE) {
        lp_log("Catálogo já tem $total jogos (limite " . LP_MAX_JOGOS_NO_SITE . "); sem novos cadastros.");
        return;
    }

    $candidatos = []; // [appid => nome]
    $spy = lp_json('https://steamspy.com/api.php?request=top100in2weeks');
    foreach ($spy ?? [] as $appid => $it) $candidatos[(int)$appid] = $it['name'] ?? '';
    if (!$candidatos) {
        $feat = lp_json('https://store.steampowered.com/api/featuredcategories?cc=br&l=portuguese');
        foreach ($feat['top_sellers']['items'] ?? [] as $it) {
            if (($it['type'] ?? 1) === 0) $candidatos[(int)$it['id']] = $it['name'];
        }
    }
    if (!$candidatos) { lp_log('Não consegui a lista de jogos populares agora.'); return; }

    $existentes = $pdo->query('SELECT nome FROM jogos')->fetchAll(PDO::FETCH_COLUMN);
    $nucleos = array_map('lp_core', $existentes);
    $idsSteam = $pdo->query("SELECT r.id_externo FROM jogo_loja_ref r JOIN lojas l ON l.id_loja=r.id_loja WHERE LOWER(l.nome) LIKE '%steam%'")->fetchAll(PDO::FETCH_COLUMN);
    $idsSteam = array_flip($idsSteam);
    $lojaSteam = (int)$pdo->query("SELECT id_loja FROM lojas WHERE LOWER(nome) LIKE '%steam%' LIMIT 1")->fetchColumn();

    $novos = 0;
    $examinados = 0;
    foreach ($candidatos as $appid => $nome) {
        if ($novos >= $max || $examinados >= 45 || $total + $novos >= LP_MAX_JOGOS_NO_SITE) break;
        if (isset($idsSteam[(string)$appid]) || in_array(lp_core($nome), $nucleos, true)) continue;

        $examinados++;
        $d = lp_steam_detalhes($appid);
        if (!$d || ($d['type'] ?? '') !== 'game') continue;
        $adulto = array_intersect($d['content_descriptors']['ids'] ?? [], [3, 4]);
        if ($adulto) continue;
        if (in_array(lp_core($d['name']), $nucleos, true)) continue;

        $titulo = mb_substr(trim($d['name']), 0, 100);
        $desc = trim(html_entity_decode(strip_tags($d['short_description'] ?? $d['about_the_game'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $desc = mb_substr($desc, 0, 700) ?: null;
        $nota = isset($d['metacritic']['score']) ? (float)$d['metacritic']['score'] : null;
        $capa = lp_baixar_capa($appid, $d['header_image'] ?? null);

        $pdo->prepare('INSERT INTO jogos (nome, descricao, data_lancamento, nota_metacritic, imagem_capa) VALUES (?,?,?,?,?)')
            ->execute([$titulo, $desc, lp_data_lancamento($d['release_date']['date'] ?? null), $nota, $capa]);
        $idJogo = (int)$pdo->lastInsertId();

        $pdo->prepare('INSERT IGNORE INTO jogo_plataforma (id_jogo, id_plataforma) VALUES (?, 1)')->execute([$idJogo]);
        foreach (lp_categorias_do_jogo($d) as $cat) {
            $pdo->prepare('INSERT IGNORE INTO jogo_categoria (id_jogo, id_categoria) VALUES (?,?)')
                ->execute([$idJogo, lp_categoria_id($pdo, $cat)]);
        }
        if ($lojaSteam) lp_salvar_ref($pdo, $idJogo, $lojaSteam, (string)$appid, $d['name'], true);

        $nucleos[] = lp_core($d['name']);
        $novos++;
        $resumo['novos'][] = $titulo;
        lp_log("Novo jogo popular cadastrado: $titulo");
    }
    if ($novos === 0) lp_log('Nenhum jogo popular novo para cadastrar hoje.');
}

/* ====================================================================
 * Execução completa
 * ==================================================================== */

/** Registra uma linha de log (e mostra na tela se houver $eco). Sem argumento, devolve todas as linhas. */
function lp_log(?string $msg = null): array
{
    static $linhas = [];
    if ($msg !== null) {
        $linha = '[' . date('H:i:s') . '] ' . $msg;
        $linhas[] = $linha;
        if (!empty($GLOBALS['lp_eco'])) ($GLOBALS['lp_eco'])($linha);
    }
    return $linhas;
}

function lp_coletar(PDO $pdo, array &$resumo): void
{
    lp_garantir_tabelas($pdo);

    // 1) Jogos populares novos entram primeiro, para já terem preço hoje.
    if (getenv('LP_SEM_NOVOS')) {
        lp_log('Modo --sem-novos: sem cadastrar jogos populares nesta execução.');
    } else {
        try {
            lp_descobrir_populares($pdo, LP_MAX_NOVOS_POR_EXECUCAO, $resumo);
        } catch (Throwable $e) {
            lp_log('Falha ao descobrir jogos populares: ' . $e->getMessage());
        }
    }

    // 2) Lojas conhecidas.
    $handlers = ['steam' => 'lp_loja_steam', 'gog' => 'lp_loja_gog', 'xbox' => 'lp_loja_xbox',
                 'nuuvem' => 'lp_loja_nuuvem', 'epic' => 'lp_loja_epic'];
    $lojas = [];
    foreach ($pdo->query('SELECT id_loja, nome FROM lojas')->fetchAll() as $l) {
        foreach ($handlers as $chave => $fn) {
            if (stripos($l['nome'], $chave) !== false) { $lojas[] = $l + ['fn' => $fn, 'chave' => $chave]; break; }
        }
    }

    $jogos = $pdo->query('SELECT id_jogo, nome FROM jogos ORDER BY id_jogo')->fetchAll();
    $resumo['jogos'] = count($jogos);

    foreach ($jogos as $jogo) {
        lp_log("» {$jogo['nome']}");
        $valorSteam = null; // referência: se a Steam cobra e outra loja diz R$ 0, o dado da outra é suspeito
        foreach ($lojas as $loja) {
            $idJogo = (int)$jogo['id_jogo'];
            $idLoja = (int)$loja['id_loja'];

            $s = $pdo->prepare('SELECT id_externo, titulo, encontrado, verificado_em FROM jogo_loja_ref WHERE id_jogo=? AND id_loja=?');
            $s->execute([$idJogo, $idLoja]);
            $ref = $s->fetch() ?: null;

            // Jogo que não existe na loja: só tenta de novo depois de alguns dias.
            if ($ref && !$ref['encontrado']) {
                if (strtotime($ref['verificado_em']) > time() - LP_RETENTAR_NAO_ENCONTRADO_DIAS * 86400) continue;
                $ref = null;
            }
            // Jogos antigos: aproveita o link da Steam já cadastrado como referência.
            if (!$ref && $loja['chave'] === 'steam') {
                $q = $pdo->prepare('SELECT link_compra FROM precos WHERE id_jogo=? AND id_loja=? LIMIT 1');
                $q->execute([$idJogo, $idLoja]);
                if (preg_match('#/app/(\d+)#', (string)$q->fetchColumn(), $m)) {
                    $ref = ['id_externo' => $m[1], 'titulo' => $jogo['nome']];
                }
            }

            try {
                $r = ($loja['fn'])($jogo, $ref);
            } catch (Throwable $e) {
                $r = null;
                lp_log("   {$loja['nome']}: erro ({$e->getMessage()})");
            }

            if ($r === null) {
                if (!$ref) lp_salvar_ref($pdo, $idJogo, $idLoja, null, null, false);
                $resumo['sem_preco']++;
                continue;
            }
            if ($loja['chave'] === 'steam') $valorSteam = $r['valor'];
            if ($r['valor'] > 5000 || ($r['valor'] == 0 && $valorSteam > 0)) {
                lp_log("   {$loja['nome']}: preço suspeito (R$ {$r['valor']}), ignorado");
                continue;
            }

            lp_salvar_ref($pdo, $idJogo, $idLoja, (string)$r['id'], $r['titulo'], true);
            $idPlat = $loja['chave'] === 'xbox' ? 2 : 1;
            $antes = lp_salvar_preco($pdo, $idJogo, $idLoja, $idPlat, $r);
            if ($loja['chave'] === 'xbox') {
                $pdo->prepare('INSERT IGNORE INTO jogo_plataforma (id_jogo, id_plataforma) VALUES (?, 2)')->execute([$idJogo]);
            }
            $resumo['atualizados']++;
            $resumo['relatorio'][] = ['jogo' => $jogo['nome'], 'loja' => $loja['nome'],
                                      'antes' => $antes, 'depois' => $r['valor'], 'desconto' => $r['desconto']];
            lp_log(sprintf('   %-12s R$ %s%s', $loja['nome'], number_format($r['valor'], 2, ',', '.'),
                $r['desconto'] ? " (-{$r['desconto']}%)" : ''));
        }
    }
}

/**
 * Ponto de entrada: trava contra execuções simultâneas, registra log e resumo.
 * $eco recebe cada linha de log (ex.: echo no terminal).
 */
function lp_executar(?callable $eco = null): array
{
    @set_time_limit(0);
    @ini_set('memory_limit', '256M');
    $GLOBALS['lp_eco'] = $eco;

    $pasta = __DIR__ . '/../robo/logs';
    if (!is_dir($pasta)) @mkdir($pasta, 0777, true);

    $resumo = ['ok' => false, 'inicio' => date('Y-m-d H:i:s'), 'fim' => null, 'jogos' => 0,
               'atualizados' => 0, 'sem_preco' => 0, 'novos' => [], 'relatorio' => [], 'erro' => null];

    $trava = fopen($pasta . '/coletor.lock', 'c');
    if (!$trava || !flock($trava, LOCK_EX | LOCK_NB)) {
        $resumo['erro'] = 'Já existe uma coleta em andamento.';
        return $resumo;
    }

    $pdo = getDB();
    if (!$pdo) {
        $resumo['erro'] = 'Sem conexão com o banco (o MySQL do XAMPP está ligado?).';
    } else {
        lp_log('Início da coleta de preços');
        try {
            lp_coletar($pdo, $resumo);
            $resumo['ok'] = true;
        } catch (Throwable $e) {
            $resumo['erro'] = $e->getMessage();
            lp_log('ERRO: ' . $e->getMessage());
        }
        lp_log("Fim: {$resumo['atualizados']} preços atualizados, " . count($resumo['novos']) . ' jogos novos.');
    }

    $resumo['fim'] = date('Y-m-d H:i:s');
    @file_put_contents($pasta . '/ultima_execucao.json', json_encode($resumo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    @file_put_contents($pasta . '/coletor.log', "=== {$resumo['inicio']} ===\n" . implode("\n", lp_log()) . "\n\n", FILE_APPEND);

    flock($trava, LOCK_UN);
    fclose($trava);
    return $resumo;
}
