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
require_once __DIR__ . '/itad.php';

const LP_UA = 'LootPriceBot/1.0 (projeto academico)';
const LP_MAX_NOVOS_POR_EXECUCAO = 8;     // jogos novos cadastrados por execução
const LP_MAX_JOGOS_NO_SITE = 100;        // para de cadastrar populares ao chegar nesse total
const LP_LIMIAR_NOME = 0.88;             // semelhança mínima entre nomes para aceitar o jogo da loja
const LP_RETENTAR_NAO_ENCONTRADO_DIAS = 7;
const LP_ESPERA_BANCO_TENTATIVAS = 40;   // tarefa agendada: espera o MySQL subir (40 x 30 s = 20 min)
const LP_ESPERA_BANCO_SEGUNDOS = 30;
// Intervalo mínimo (segundos) entre pedidos ao mesmo site. Lojas que limitam acessos (429) ganham mais calma.
const LP_ESPERA_HOST = ['www.nuuvem.com' => 3.0, 'api.isthereanydeal.com' => 1.0];
const LP_ESPERA_PADRAO = 0.35;
const LP_RETRY_AFTER_MAX = 30;           // não espera mais que isso (s) quando a loja manda Retry-After

/* ====================================================================
 * Utilidades: HTTP, JSON e comparação de nomes
 * ==================================================================== */

/**
 * GET/POST educado: identifica o robô, espera entre chamadas (por site) e tenta de novo uma vez.
 * Se o site responder 429 (muitos acessos), respeita o Retry-After; se continuar barrando, o site
 * fica "em pausa" até o fim da execução (nada de insistir e piorar o bloqueio).
 */
function lp_http(string $url, ?string $postJson = null, array $headers = []): ?string
{
    static $ultima = [];      // host => instante do último pedido
    static $pausados = [];    // hosts que barraram o robô nesta execução
    $cafile = lp_cafile();
    $host = (string)parse_url($url, PHP_URL_HOST);
    if (isset($pausados[$host])) return null;
    $minimo = LP_ESPERA_HOST[$host] ?? LP_ESPERA_PADRAO;

    for ($tentativa = 1; $tentativa <= 2; $tentativa++) {
        $espera = $minimo - (microtime(true) - ($ultima[$host] ?? 0.0));
        if ($espera > 0) usleep((int)($espera * 1e6));

        $retryAfter = 0;
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
            CURLOPT_HEADERFUNCTION => function ($c, string $linha) use (&$retryAfter) {
                if (stripos($linha, 'retry-after:') === 0) $retryAfter = (int)trim(substr($linha, 12));
                return strlen($linha);
            },
        ];
        if ($cafile) $opts[CURLOPT_CAINFO] = $cafile;
        if ($postJson !== null) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $postJson;
            $opts[CURLOPT_HTTPHEADER] = array_merge($headers, ['Content-Type: application/json']);
        }
        curl_setopt_array($ch, $opts);
        $corpo = curl_exec($ch);
        $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ultima[$host] = microtime(true);

        // Algumas lojas (Nuuvem) respondem 200 com uma página de "Client Challenge" (anti-robô em JavaScript)
        // em vez dos dados: isso é um bloqueio, não "busca sem resultado".
        if ($corpo !== false && $codigo > 0 && $codigo < 400 && $postJson === null
            && strlen($corpo) < 20000 && str_contains($corpo, '<title>Client Challenge</title>')) {
            $GLOBALS['lp_bloqueios'][$host] = 403;
            $pausados[$host] = true;
            lp_log("   $host exigiu verificação anti-robô (Client Challenge): pausando as consultas a esse site até o fim desta execução.");
            return null;
        }
        if ($corpo !== false && $codigo > 0 && $codigo < 400) return $corpo;
        if ($codigo === 429 || $codigo === 403) $GLOBALS['lp_bloqueios'][$host] = $codigo; // loja barrou o robô
        if ($codigo === 429) {
            if ($tentativa === 1) { // respeita o tempo pedido pela loja e tenta mais uma vez
                sleep(min(max($retryAfter, 5), LP_RETRY_AFTER_MAX));
                continue;
            }
            $pausados[$host] = true;
            lp_log("   $host limitou os acessos (429): pausando as consultas a esse site até o fim desta execução.");
            return null;
        }
        if ($codigo === 403 || $codigo === 404) return null; // não adianta insistir
        sleep(1);
    }
    return null;
}

/** Código HTTP (429/403) se a loja barrou o robô nesta execução, senão null. */
function lp_loja_barrada(string $chave): ?int
{
    $dominios = ['steam' => 'steampowered.com', 'gog' => 'gog.com', 'xbox' => 'microsoft.com',
                 'nuuvem' => 'nuuvem.com', 'epic' => 'epicgames.com'];
    foreach ($GLOBALS['lp_bloqueios'] ?? [] as $host => $cod) {
        if (isset($dominios[$chave]) && str_contains($host, $dominios[$chave])) return $cod;
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
    if ($html === null) return lp_itad_preco($jogo, 'nuuvem'); // bloqueou? plano B

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
    if ($bloqueado) return lp_itad_preco($jogo, 'epic');

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
        lp_log('Epic Games bloqueou o acesso automático (proteção anti-robô). Usando o plano B (IsThereAnyDeal) se houver chave; senão os preços da Epic ficam como estão.');
        return lp_itad_preco($jogo, 'epic');
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

    $guardarHistorico = $pdo->prepare('INSERT INTO historico_precos (id_jogo, id_loja, valor, valor_original) VALUES (?,?,?,?)');

    if ($atual) {
        $pdo->prepare('UPDATE precos SET valor=?, valor_original=?, porcentagem_desconto=?, link_compra=?, data_atualizacao=NOW() WHERE id_preco=?')
            ->execute([$r['valor'], $r['original'], $r['desconto'], $url, $atual['id_preco']]);
        // Só entra no histórico quando o preço mudou (o gráfico não enche de pontos repetidos)
        if (abs((float)$atual['valor'] - (float)$r['valor']) > 0.001) {
            $guardarHistorico->execute([$idJogo, $idLoja, $r['valor'], $r['original']]);
        }
        return (float)$atual['valor'];
    }
    $pdo->prepare('INSERT INTO precos (id_jogo,id_loja,id_plataforma,valor,valor_original,porcentagem_desconto,link_compra) VALUES (?,?,?,?,?,?,?)')
        ->execute([$idJogo, $idLoja, $idPlataforma, $r['valor'], $r['original'], $r['desconto'], $url]);
    $guardarHistorico->execute([$idJogo, $idLoja, $r['valor'], $r['original']]);
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

/**
 * Andamento da coleta em robo/logs/andamento.json (lido por admin/atualizar_precos.php, que mostra o progresso).
 * Cada chamada junta os campos novos aos que já existiam.
 */
function lp_andamento(array $campos = []): array
{
    static $estado = ['rodando' => false, 'jogo_atual' => 0, 'jogos' => 0, 'linhas' => []];
    $estado = array_merge($estado, $campos, ['atualizado_em' => date('Y-m-d H:i:s')]);
    $arq = __DIR__ . '/../robo/logs/andamento.json';
    if (is_dir(dirname($arq))) @file_put_contents($arq, json_encode($estado, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $estado;
}

/** Registra uma linha de log (e mostra na tela se houver $eco). Sem argumento, devolve todas as linhas. */
function lp_log(?string $msg = null): array
{
    static $linhas = [];
    if ($msg !== null) {
        $linha = '[' . date('H:i:s') . '] ' . $msg;
        $linhas[] = $linha;
        if (!empty($GLOBALS['lp_eco'])) ($GLOBALS['lp_eco'])($linha);
        lp_andamento(['linhas' => array_slice($linhas, -25)]);
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
    $lojasFuncionaram = []; // lojas que devolveram pelo menos um preço nesta execução
    $numero = 0;
    lp_andamento(['jogos' => count($jogos), 'jogo_atual' => 0]);

    foreach ($jogos as $jogo) {
        lp_andamento(['jogo_atual' => ++$numero]);
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
                if (!$ref && !lp_loja_barrada($loja['chave'])) lp_salvar_ref($pdo, $idJogo, $idLoja, null, null, false);
                $resumo['sem_preco']++;
                continue;
            }
            $lojasFuncionaram[$idLoja] = $loja['chave'];
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

    // Loja que não devolveu preço NENHUM nesta execução: o site dela provavelmente mudou (ou bloqueou o robô).
    // A Epic já é conhecida por bloquear (Cloudflare), então não conta como alerta.
    $resumo['lojas_sem_dados'] = [];
    $resumo['lojas_bloqueadas'] = [];
    foreach ($lojas as $loja) {
        if ($loja['chave'] !== 'epic' && !isset($lojasFuncionaram[(int)$loja['id_loja']])) {
            $resumo['lojas_sem_dados'][] = $loja['nome'];
            $bloqueio = lp_loja_barrada($loja['chave']);
            if ($bloqueio) {
                $resumo['lojas_bloqueadas'][] = $loja['nome'];
                lp_log("ALERTA: {$loja['nome']} bloqueou o robô (HTTP $bloqueio, limite de acessos). Mantendo os últimos preços; tente de novo mais tarde.");
            } else {
                lp_log("ALERTA: {$loja['nome']} não devolveu nenhum preço nesta coleta. O site da loja pode ter mudado; revise includes/coletor.php.");
            }
        }
    }

    lp_limpar_precos_velhos($pdo, $lojasFuncionaram, $resumo);
    try { lp_reparar_capas($pdo); } catch (Throwable $e) { lp_log('Falha ao reparar capas: ' . $e->getMessage()); }
}

/**
 * Remove o preço de uma loja que deixou de vender o jogo: se a loja respondeu normalmente nesta execução
 * (devolveu preço de outros jogos) e este preço não é atualizado há mais de 14 dias, ele sai do site.
 * A Epic fica de fora: ela bloqueia o robô, então "não atualizou" não significa "deixou de vender".
 */
function lp_limpar_precos_velhos(PDO $pdo, array $lojasFuncionaram, array &$resumo): void
{
    $resumo['removidos'] = 0;
    foreach ($lojasFuncionaram as $idLoja => $chave) {
        if ($chave === 'epic') continue;
        $del = $pdo->prepare('DELETE FROM precos WHERE id_loja = ? AND data_atualizacao < (NOW() - INTERVAL 14 DAY)');
        $del->execute([$idLoja]);
        if ($del->rowCount() > 0) {
            $resumo['removidos'] += $del->rowCount();
            lp_log("Removidos {$del->rowCount()} preço(s) antigos da loja {$chave} (a loja deixou de vender esses jogos).");
        }
    }
}

/** Jogos cadastrados sem capa (o download falhou antes): tenta baixar de novo a da Steam. */
function lp_reparar_capas(PDO $pdo): void
{
    $sem = $pdo->query("SELECT j.id_jogo, r.id_externo FROM jogos j
                          JOIN jogo_loja_ref r ON r.id_jogo = j.id_jogo AND r.encontrado = 1
                          JOIN lojas l ON l.id_loja = r.id_loja AND LOWER(l.nome) LIKE '%steam%'
                          WHERE (j.imagem_capa IS NULL OR j.imagem_capa = '') AND r.id_externo REGEXP '^[0-9]+$' LIMIT 10")->fetchAll();
    foreach ($sem as $j) {
        $capa = lp_baixar_capa((int)$j['id_externo'], null);
        if ($capa) {
            $pdo->prepare('UPDATE jogos SET imagem_capa = ? WHERE id_jogo = ?')->execute([$capa, $j['id_jogo']]);
            lp_log("Capa recuperada do jogo #{$j['id_jogo']}.");
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

    // Pela linha de comando (tarefa agendada) espera o MySQL subir: o PC costuma ligar e rodar a tarefa
    // antes de o XAMPP iniciar o banco. Pela web tenta uma vez só (o admin já está com o banco ligado).
    $pdo = getDB();
    if (!$pdo && PHP_SAPI === 'cli') {
        for ($t = 1; $t <= LP_ESPERA_BANCO_TENTATIVAS && !$pdo; $t++) {
            lp_log('Banco indisponível; nova tentativa em ' . LP_ESPERA_BANCO_SEGUNDOS . ' s (' . $t . '/' . LP_ESPERA_BANCO_TENTATIVAS . ')...');
            sleep(LP_ESPERA_BANCO_SEGUNDOS);
            $pdo = getDB();
        }
    }
    if (!$pdo) {
        $resumo['erro'] = 'Sem conexão com o banco (o MySQL do XAMPP está ligado?).';
    } else {
        lp_andamento(['rodando' => true, 'inicio' => $resumo['inicio'], 'jogo_atual' => 0, 'jogos' => 0, 'linhas' => []]);
        lp_log('Início da coleta de preços');
        try {
            lp_coletar($pdo, $resumo);
            $resumo['ok'] = true;
        } catch (Throwable $e) {
            $resumo['erro'] = $e->getMessage();
            lp_log('ERRO: ' . $e->getMessage());
        }

        // Preços novos gravados: avisa quem tem jogo em promoção na lista de desejos
        if ($resumo['ok']) {
            try {
                require_once __DIR__ . '/avisos.php';
                $av = lp_enviar_avisos_promocao($pdo);
                $resumo['avisos'] = ['enviados' => $av['enviados'], 'falhas' => $av['falhas'], 'jogos' => $av['jogos']];
                lp_log("Avisos de promoção: {$av['enviados']} e-mail(s) enviado(s), {$av['falhas']} falha(s).");
            } catch (Throwable $e) {
                lp_log('Falha nos avisos de promoção: ' . $e->getMessage());
            }
        }
        // Manutenção: acessos com mais de 180 dias não servem mais para recomendação/popularidade
        try { $pdo->exec('DELETE FROM acessos_jogos WHERE criado_em < (NOW() - INTERVAL 180 DAY)'); } catch (Throwable $e) {}
        lp_log("Fim: {$resumo['atualizados']} preços atualizados, " . count($resumo['novos']) . ' jogos novos.');
    }

    $resumo['fim'] = date('Y-m-d H:i:s');
    @file_put_contents($pasta . '/ultima_execucao.json', json_encode($resumo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    // O coletor.log não cresce sem limite: passando de 1 MB vira coletor.log.1 (só o anterior fica guardado)
    $logArq = $pasta . '/coletor.log';
    if (is_file($logArq) && filesize($logArq) > 1048576) @rename($logArq, $logArq . '.1');
    @file_put_contents($logArq, "=== {$resumo['inicio']} ===\n" . implode("\n", lp_log()) . "\n\n", FILE_APPEND);
    lp_andamento(['rodando' => false, 'fim' => $resumo['fim'], 'erro' => $resumo['erro']]);

    flock($trava, LOCK_UN);
    fclose($trava);
    return $resumo;
}
