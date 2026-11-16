<?php
/**
 * Robô de preços do LootPrice - execução pela linha de comando.
 * Agendado para todo dia às 14:00 (veja robo/agendar_14h.ps1).
 *
 * Teste manual:  C:\xampp\php\php.exe robo\coletar.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Somente pela linha de comando.');
}

date_default_timezone_set('America/Sao_Paulo');
if (in_array('--sem-novos', $argv ?? [], true)) putenv('LP_SEM_NOVOS=1'); // só atualiza preços, sem cadastrar jogos novos
require_once __DIR__ . '/../includes/coletor.php';

$resumo = lp_executar(function (string $linha) {
    echo $linha . PHP_EOL;
});

if ($resumo['erro']) {
    fwrite(STDERR, 'Erro: ' . $resumo['erro'] . PHP_EOL);
    exit(1);
}
exit(0);
