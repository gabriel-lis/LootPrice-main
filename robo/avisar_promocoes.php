<?php
/**
 * Avisos de promoção da lista de desejos - execução manual (linha de comando).
 * O robô de preços já chama isto sozinho no fim de cada coleta (includes/coletor.php).
 *
 *   C:\xampp\php\php.exe robo\avisar_promocoes.php --simular          # só mostra quem receberia
 *   C:\xampp\php\php.exe robo\avisar_promocoes.php                    # envia de verdade
 *   C:\xampp\php\php.exe robo\avisar_promocoes.php --usuario=4        # limita a um usuário
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Somente pela linha de comando.');
}

date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/avisos.php';

$simular = in_array('--simular', $argv ?? [], true);
$usuario = null;
foreach ($argv ?? [] as $a) if (preg_match('/^--usuario=(\d+)$/', $a, $m)) $usuario = (int)$m[1];

$pdo = getDB();
if (!$pdo) { fwrite(STDERR, "Sem conexão com o banco (o MySQL do XAMPP está ligado?).\n"); exit(1); }

$r = lp_enviar_avisos_promocao($pdo, $simular, $usuario);
echo ($simular ? "[SIMULAÇÃO - nada foi enviado]\n" : '')
   . "Jogos em promoção hoje: {$r['promocoes']}\n"
   . "Pessoas a avisar: {$r['usuarios']} ({$r['jogos']} jogos)\n";
foreach ($r['detalhe'] as [$email, $lista]) {
    echo " - $email\n";
    foreach ($lista as $linha) echo "     · $linha\n";
}
if (!$simular) echo "E-mails enviados: {$r['enviados']} | falhas: {$r['falhas']}\n";
exit($r['falhas'] > 0 ? 1 : 0);
