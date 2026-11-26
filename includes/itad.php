<?php
/**
 * Plano B de preços: API oficial da IsThereAnyDeal (https://docs.isthereanydeal.com).
 *
 * Usada só quando a loja barra o robô (Epic, Nuuvem). Precisa de chave grátis em config/itad.php
 * (modelo: config/itad.example.php). Sem chave, tudo aqui devolve null e o robô segue como antes.
 */

function lp_itad_chave(): ?string
{
    static $chave = false;
    if ($chave === false) {
        $arq = __DIR__ . '/../config/itad.php';
        $cfg = is_file($arq) ? include $arq : [];
        $c = is_array($cfg) ? trim((string)($cfg['chave'] ?? '')) : '';
        $chave = ($c !== '' && $c !== 'COLE_A_CHAVE_AQUI') ? $c : null;
    }
    return $chave;
}

/** Id do jogo na ITAD (só se o título for parecido o bastante com o nosso), com cache na execução. */
function lp_itad_id(string $nome, string $chave): ?string
{
    static $cache = [];
    if (array_key_exists($nome, $cache)) return $cache[$nome];
    $j = lp_json('https://api.isthereanydeal.com/games/lookup/v1?key=' . rawurlencode($chave) . '&title=' . rawurlencode($nome));
    $id = null;
    if (!empty($j['found']) && !empty($j['game']['id']) && lp_semelhanca($nome, (string)($j['game']['title'] ?? '')) >= LP_LIMIAR_NOME) {
        $id = (string)$j['game']['id'];
    }
    return $cache[$nome] = $id;
}

/**
 * Preço em reais do jogo numa loja ($palavraLoja = 'epic' ou 'nuuvem'), vindo da ITAD.
 * Mesmo formato das funções lp_loja_*: ['id','titulo','url','valor','original','desconto'] ou null.
 */
function lp_itad_preco(array $jogo, string $palavraLoja): ?array
{
    $chave = lp_itad_chave();
    if (!$chave) return null;
    $id = lp_itad_id($jogo['nome'], $chave);
    if (!$id) return null;

    $resp = lp_http('https://api.isthereanydeal.com/games/prices/v3?country=BR&key=' . rawurlencode($chave), json_encode([$id]));
    $j = $resp !== null ? json_decode($resp, true) : null;
    foreach ($j[0]['deals'] ?? [] as $d) {
        if (stripos((string)($d['shop']['name'] ?? ''), $palavraLoja) === false) continue;
        if (($d['price']['currency'] ?? '') !== 'BRL' || !isset($d['price']['amount'])) continue;
        $valor = (float)$d['price']['amount'];
        $original = (float)($d['regular']['amount'] ?? $valor);
        lp_log("   {$d['shop']['name']}: preço obtido pela IsThereAnyDeal (a loja bloqueou o robô)");
        return lp_preco($valor, $original) + ['id' => 'itad:' . $id, 'titulo' => $jogo['nome'], 'url' => (string)($d['url'] ?? '')];
    }
    return null;
}
