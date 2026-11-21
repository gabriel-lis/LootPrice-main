<?php
/**
 * Modelo de configuração de e-mail (Gmail SMTP).
 *
 * COMO USAR:
 *   1. Copie este arquivo para config/mail.php (esse NÃO vai para o git).
 *   2. Preencha 'usuario' com o seu Gmail e 'senha' com uma SENHA DE APP.
 *
 * COMO GERAR A SENHA DE APP (a senha normal do Gmail não funciona):
 *   - Conta Google > Segurança > ative a "Verificação em duas etapas".
 *   - Depois acesse https://myaccount.google.com/apppasswords
 *   - Crie uma senha de app (nome: LootPrice) e copie as 16 letras (sem espaços).
 */
return [
    'host'       => 'smtp.gmail.com',
    'porta'      => 587,                       // 587 = STARTTLS (recomendado) | 465 = SSL
    'seguranca'  => 'tls',                     // 'tls' para 587, 'ssl' para 465
    'usuario'    => 'seuemail@gmail.com',      // seu Gmail
    'senha'      => 'xxxxxxxxxxxxxxxx',        // senha de app de 16 letras
    'remetente'  => 'seuemail@gmail.com',      // no Gmail precisa ser o mesmo e-mail de 'usuario'
    'nome'       => 'LootPrice',               // nome que aparece como remetente
    'segredo'    => 'troque-por-um-texto-aleatorio-longo',  // assina os links de "parar de receber avisos"
    'site_url'   => 'http://localhost/LootPrice-main',  // usado nos links dos e-mails (sem barra no final)
];
