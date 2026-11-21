<?php
/**
 * Envio de e-mails (Gmail SMTP via PHPMailer) - LootPrice
 *
 * Uso:  [$ok, $erro] = lp_enviar_email('pessoa@x.com', 'Nome', 'Assunto', '<p>HTML</p>');
 * A configuração fica em config/mail.php (copie de config/mail.example.php).
 * Falhas são gravadas em logs/email.log (sem a senha) e devolvidas em $erro.
 */

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/** Lê config/mail.php. Devolve null se não existir ou ainda estiver com os valores de exemplo. */
function lp_mail_config(): ?array {
    static $cfg = false;
    if ($cfg === false) {
        $arq = __DIR__ . '/../config/mail.php';
        $cfg = is_file($arq) ? (require $arq) : null;
        if (is_array($cfg) && (
            empty($cfg['usuario']) || empty($cfg['senha']) ||
            $cfg['usuario'] === 'seuemail@gmail.com' || $cfg['senha'] === 'xxxxxxxxxxxxxxxx'
        )) {
            $cfg = null;
        }
    }
    return is_array($cfg) ? $cfg : null;
}

/** URL base do site para links dentro dos e-mails (sem barra no final). */
function lp_site_url(): string {
    $cfg = lp_mail_config();
    return rtrim($cfg['site_url'] ?? 'http://localhost/LootPrice-main', '/');
}

function lp_email_log(string $msg): void {
    $msg = preg_replace("/\d{6}/", "******", $msg); // nunca grava códigos de verificação
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    @file_put_contents($dir . '/email.log', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

/**
 * Envia um e-mail HTML (com versão em texto simples).
 * @return array [bool $ok, ?string $erro]
 */
function lp_enviar_email(string $para, string $nomeDestino, string $assunto, string $html, ?string $texto = null): array {
    $cfg = lp_mail_config();
    if (!$cfg) {
        lp_email_log("NAO ENVIADO para $para ($assunto): config/mail.php ausente ou sem preencher.");
        return [false, 'O envio de e-mail ainda não foi configurado (config/mail.php).'];
    }
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Endereço de e-mail inválido.'];
    }

    $m = new PHPMailer(true);
    try {
        $m->isSMTP();
        $m->Host       = $cfg['host'] ?? 'smtp.gmail.com';
        $m->Port       = (int)($cfg['porta'] ?? 587);
        $m->SMTPAuth   = true;
        $m->Username   = $cfg['usuario'];
        $m->Password   = $cfg['senha'];
        $m->SMTPSecure = (($cfg['seguranca'] ?? 'tls') === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $m->CharSet    = 'UTF-8';
        $m->Timeout    = 20;

        // XAMPP no Windows costuma não ter o certificado raiz configurado no PHP
        $cafile = 'C:\xampp\apache\bin\curl-ca-bundle.crt';
        if (!ini_get('openssl.cafile') && is_file($cafile)) {
            $m->SMTPOptions = ['ssl' => ['cafile' => $cafile, 'verify_peer' => true, 'verify_peer_name' => true]];
        }

        $m->setFrom($cfg['remetente'] ?? $cfg['usuario'], $cfg['nome'] ?? 'LootPrice');
        $m->addAddress($para, $nomeDestino);
        $m->isHTML(true);
        $m->Subject = $assunto;
        $m->Body    = $html;
        $m->AltBody = $texto ?? trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>#i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
        try {
            $m->send();
        } catch (PHPMailerException $e) {
            // O Gmail às vezes derruba a conexão do nada: tenta mais uma vez antes de desistir
            lp_email_log("Primeira tentativa falhou para $para (" . $m->ErrorInfo . "); tentando de novo.");
            $m->smtpClose();
            sleep(1);
            $m->send();
        }
        lp_email_log("ENVIADO para $para ($assunto)");
        return [true, null];
    } catch (PHPMailerException $e) {
        lp_email_log("FALHA para $para ($assunto): " . $m->ErrorInfo);
        return [false, 'Não foi possível enviar o e-mail agora. Tente novamente em instantes.'];
    }
}

/**
 * Moldura padrão dos e-mails (cores fixas de propósito: clientes de e-mail não leem o theme.css).
 */
function lp_email_template(string $titulo, string $corpoHtml, ?string $rodape = null): string {
    $t = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $rodape = $rodape ?? 'Você recebeu este e-mail porque tem uma conta no LootPrice.';
    return '<!DOCTYPE html><html lang="pt-BR"><body style="margin:0;background:#f6f4ff;font-family:Segoe UI,Arial,sans-serif;color:#1c1433;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="100%" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;" cellpadding="0" cellspacing="0">'
        . '<tr><td style="background:#6d28d9;padding:18px 24px;color:#ffffff;font-size:20px;font-weight:800;">LootPrice</td></tr>'
        . '<tr><td style="padding:26px 24px;"><h1 style="margin:0 0 14px;font-size:20px;color:#1c1433;">' . $t . '</h1>'
        . '<div style="font-size:15px;line-height:1.55;">' . $corpoHtml . '</div></td></tr>'
        . '<tr><td style="padding:14px 24px;background:#efeaff;color:#6b6489;font-size:12px;">' . $rodape . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}
