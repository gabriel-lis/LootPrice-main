<?php
/**
 * Teste do envio de e-mail (somente linha de comando).
 *
 *   C:\xampp\php\php.exe robo\testar_email.php seuemail@gmail.com
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Somente pela linha de comando.');
}

require_once __DIR__ . '/../includes/mailer.php';

$para = $argv[1] ?? '';
if ($para === '') {
    fwrite(STDERR, "Uso: php robo\\testar_email.php destino@exemplo.com\n");
    exit(1);
}

$html = lp_email_template('Teste de e-mail', '<p>Se você está lendo isto, o envio de e-mail do LootPrice está funcionando. 🎮</p>');
[$ok, $erro] = lp_enviar_email($para, '', 'LootPrice - teste de e-mail', $html);

echo $ok ? "OK: e-mail enviado para $para\n" : "FALHOU: $erro\n(detalhes em logs/email.log)\n";
exit($ok ? 0 : 1);
