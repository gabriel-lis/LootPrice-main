<?php
/**
 * Testes automáticos simples do LootPrice (sem framework).
 * Rodam só funções puras e consultas de leitura: NÃO alteram o banco.
 *
 *   C:\xampp\php\php.exe tests\executar.php
 *
 * Sai com código 1 se algum teste falhar (serve para CI ou para rodar antes de publicar).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente pela linha de comando.'); }

date_default_timezone_set('America/Sao_Paulo');
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/coletor.php';

$falhas = 0;
$total = 0;
function testa(string $nome, bool $ok, string $detalhe = ''): void {
    global $falhas, $total;
    $total++;
    if (!$ok) $falhas++;
    echo ($ok ? '  ok   ' : '  FALHOU ') . $nome . ($ok || $detalhe === '' ? '' : "  -> $detalhe") . PHP_EOL;
}

echo "Entrada segura\n";
$_GET = ['q' => ['x'], 'ok' => '  texto  ', 'longo' => str_repeat('a', 500)];
testa('lp_get ignora array (?q[]=x)', lp_get('q') === '');
testa('lp_get tira espaços', lp_get('ok') === 'texto');
testa('lp_get limita o tamanho', mb_strlen(lp_get('longo', 50)) === 50);

echo "Dinheiro e links\n";
testa('59,90', lp_parse_valor('59,90') === 59.90);
testa('1.299,90', lp_parse_valor('1.299,90') === 1299.90);
testa('R$ 10', lp_parse_valor('R$ 10') === 10.0);
testa('0 (grátis)', lp_parse_valor('0') === 0.0);
testa('texto inválido', lp_parse_valor('abc') === null);
testa('negativo inválido', lp_parse_valor('-3') === null);
testa('link https ok', lp_link_valido('https://store.steampowered.com/app/1'));
testa('link javascript: recusado', !lp_link_valido('javascript:alert(1)'));
testa('link sem protocolo recusado', !lp_link_valido('store.steampowered.com'));

echo "Busca\n";
testa('lp_like escapa % e _', lp_like('50%_') === '%50\\%\\_%');

echo "Capas e preços\n";
testa('capa ausente usa a padrão', capaJogo(null) === 'fotos/jogos/sem-capa.svg');
testa('capa inexistente usa a padrão', capaJogo('fotos/jogos/nao-existe.jpg') === 'fotos/jogos/sem-capa.svg');
testa('capa com .. é recusada', capaJogo('../config/db.php') === 'fotos/jogos/sem-capa.svg');
testa('preço de 10 dias é velho', idadeDoPreco(date('Y-m-d H:i:s', time() - 10 * 86400))['velho']);
testa('preço de agora não é velho', !idadeDoPreco(date('Y-m-d H:i:s'))['velho']);

echo "Robô (comparação de nomes)\n";
testa('título idêntico', lp_semelhanca('Elden Ring', 'ELDEN RING') === 1.0);
testa('Risk of Rain != Risk of Rain 2', lp_semelhanca('Risk of Rain', 'Risk of Rain 2') === 0.0);
testa('mesma edição (deluxe)', lp_semelhanca('Elden Ring', 'Elden Ring Deluxe Edition') >= LP_LIMIAR_NOME);
testa('preço com desconto', lp_preco(50, 100)['desconto'] === 50);

echo "Segurança\n";
testa('senhaImpressao muda com a senha', senhaImpressao('a') !== senhaImpressao('b'));
testa('CSRF recusa token vazio', !csrfValido());
$_SESSION['csrf'] = 'abc'; $_POST['csrf'] = 'abc';
testa('CSRF aceita token certo', csrfValido());
$_POST['csrf'] = ['abc'];
testa('CSRF recusa array', !csrfValido());

echo "Banco (somente leitura)\n";
$pdo = getDB();
testa('conecta ao banco', $pdo instanceof PDO);
if ($pdo) {
    testa('catálogo traz jogos', count(getGamesCatalog()) > 0);
    testa('contagem bate com a lista', countGamesCatalog() === count(getGamesCatalog()));
    testa('busca por % não casa tudo', count(getGamesCatalog('%')) < count(getGamesCatalog()));
    testa('"Até R$ 100" inclui "Até R$ 50"', count(getGamesCatalog(null, null, null, 'under100')) >= count(getGamesCatalog(null, null, null, 'under50')));
    testa('"Até R$ 50" inclui os grátis', count(getGamesCatalog(null, null, null, 'under50')) >= count(getGamesCatalog(null, null, null, 'free')));
    testa('UNIQUE(id_jogo,id_loja) em precos', (bool)$pdo->query("SHOW INDEX FROM precos WHERE Key_name = 'uq_preco_jogo_loja'")->fetch());
    foreach (['historico_precos', 'avaliacoes', 'tentativas_login'] as $t) {
        testa("tabela $t existe", (bool)$pdo->query("SHOW TABLES LIKE '$t'")->fetch());
    }
    testa('existe pelo menos 1 administrador', (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario='administrador'")->fetchColumn() >= 1);
}

echo PHP_EOL . ($falhas === 0 ? "Tudo certo: $total testes passaram." : "$falhas de $total testes FALHARAM.") . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
