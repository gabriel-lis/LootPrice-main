<?php
/**
 * Confirmação de e-mail por código de 6 dígitos - LootPrice
 *
 * - O código vale 10 minutos, aceita 5 tentativas e só é guardado como hash.
 * - Um novo código só pode ser pedido a cada 60 segundos.
 * - A coluna usuarios.email_verificado e a tabela verificacoes_email são criadas sob demanda
 *   (contas que já existiam antes desta função ficam como confirmadas).
 */

const LP_COD_VALIDADE_MIN   = 10;
const LP_COD_MAX_TENTATIVAS = 5;
const LP_COD_REENVIO_SEG    = 60;

function ensureVerificacaoSchema(PDO $pdo): bool {
    // Roda só uma vez por instalação (marca em logs/schema_verificacao.ok)
    return lp_schema_uma_vez('verificacao', function () use ($pdo) {
        if (!$pdo->query("SHOW COLUMNS FROM usuarios LIKE 'email_verificado'")->fetch()) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN email_verificado TINYINT(1) NOT NULL DEFAULT 0 AFTER email");
            $pdo->exec("UPDATE usuarios SET email_verificado = 1"); // contas anteriores ficam confirmadas
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS `verificacoes_email` (
            `id_usuario` INT NOT NULL,
            `tipo` VARCHAR(20) NOT NULL DEFAULT 'cadastro',
            `codigo_hash` VARCHAR(255) NOT NULL,
            `expira_em` DATETIME NOT NULL,
            `tentativas` TINYINT NOT NULL DEFAULT 0,
            `enviado_em` DATETIME NOT NULL,
            PRIMARY KEY (`id_usuario`, `tipo`),
            CONSTRAINT `fk_verif_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    });
}

/** "gabriel@gmail.com" -> "ga*****@gmail.com" */
function mascararEmail(string $email): string {
    [$nome, $dominio] = array_pad(explode('@', $email, 2), 2, '');
    $ini = mb_substr($nome, 0, 2);
    return $ini . str_repeat('*', max(3, mb_strlen($nome) - 2)) . '@' . $dominio;
}

/** Segundos que faltam para poder pedir outro código (0 = já pode). */
function segundosParaReenvio(PDO $pdo, int $idUsuario, string $tipo = 'cadastro'): int {
    $s = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, enviado_em, NOW()) FROM verificacoes_email WHERE id_usuario = ? AND tipo = ?");
    $s->execute([$idUsuario, $tipo]);
    $desde = $s->fetchColumn();
    if ($desde === false || $desde === null) return 0;
    return max(0, LP_COD_REENVIO_SEG - (int)$desde);
}

/**
 * Gera um código novo e o envia para o e-mail cadastrado do usuário.
 * @return array [bool $ok, ?string $erro]
 */
function enviarCodigoVerificacao(PDO $pdo, int $idUsuario, string $tipo = 'cadastro'): array {
    if (!ensureVerificacaoSchema($pdo)) return [false, 'Não foi possível preparar a verificação agora.'];

    $u = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id_usuario = ?");
    $u->execute([$idUsuario]);
    $user = $u->fetch();
    if (!$user) return [false, 'Conta não encontrada.'];

    $falta = segundosParaReenvio($pdo, $idUsuario, $tipo);
    if ($falta > 0) return [false, "Aguarde $falta segundos para pedir um novo código."];

    $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT INTO verificacoes_email (id_usuario, tipo, codigo_hash, expira_em, tentativas, enviado_em)
                   VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL " . LP_COD_VALIDADE_MIN . " MINUTE), 0, NOW())
                   ON DUPLICATE KEY UPDATE codigo_hash = VALUES(codigo_hash), expira_em = VALUES(expira_em),
                                           tentativas = 0, enviado_em = NOW()")
        ->execute([$idUsuario, $tipo, password_hash($codigo, PASSWORD_DEFAULT)]);

    require_once __DIR__ . '/mailer.php';
    $nome = htmlspecialchars(explode(' ', trim($user['nome']))[0], ENT_QUOTES, 'UTF-8');

    // Texto do e-mail conforme o motivo do código
    $textos = [
        'cadastro'      => ['Confirme seu e-mail', 'Use este código para confirmar o seu e-mail no LootPrice:',
                            'Se não foi você que criou a conta, é só ignorar este e-mail.'],
        'senha'         => ['Redefinir sua senha', 'Recebemos um pedido para redefinir a senha da sua conta. Use este código:',
                            'Se não foi você que pediu, ignore este e-mail: sua senha continua a mesma.'],
        'senha_alterar' => ['Confirme a troca de senha', 'Para confirmar a troca da sua senha, use este código:',
                            'Se não foi você, ignore este e-mail e considere trocar sua senha.'],
    ];
    [$titulo, $intro, $aviso] = $textos[$tipo] ?? $textos['cadastro'];

    $corpo = '<p>Olá, ' . $nome . '!</p><p>' . $intro . '</p>'
        . '<p style="text-align:center;margin:22px 0;"><span style="display:inline-block;font-size:34px;font-weight:800;letter-spacing:8px;'
        . 'background:#efeaff;color:#4c1d95;padding:12px 22px;border-radius:10px;">' . $codigo . '</span></p>'
        . '<p>O código vale por ' . LP_COD_VALIDADE_MIN . ' minutos. ' . $aviso . '</p>';
    $html = lp_email_template($titulo, $corpo);

    [$ok, $erro] = lp_enviar_email($user['email'], $user['nome'], "Seu código LootPrice: $codigo", $html);
    if (!$ok) {
        // sem e-mail enviado não há por que segurar o reenvio
        $pdo->prepare("DELETE FROM verificacoes_email WHERE id_usuario = ? AND tipo = ?")->execute([$idUsuario, $tipo]);
        return [false, $erro];
    }
    return [true, null];
}

/**
 * Confere o código digitado. Em caso de acerto marca o e-mail como confirmado.
 * @return array [bool $ok, ?string $erro]
 */
function confirmarCodigoVerificacao(PDO $pdo, int $idUsuario, string $codigo, string $tipo = 'cadastro'): array {
    $codigo = preg_replace('/\D/', '', $codigo);
    if (strlen($codigo) !== 6) return [false, 'Digite os 6 números do código.'];

    $s = $pdo->prepare("SELECT codigo_hash, tentativas, (expira_em < NOW()) AS expirou
                        FROM verificacoes_email WHERE id_usuario = ? AND tipo = ?");
    $s->execute([$idUsuario, $tipo]);
    $row = $s->fetch();
    if (!$row) return [false, 'Não há código ativo. Peça um novo código.'];

    if ((int)$row['expirou'] === 1) {
        $pdo->prepare("DELETE FROM verificacoes_email WHERE id_usuario = ? AND tipo = ?")->execute([$idUsuario, $tipo]);
        return [false, 'Esse código expirou. Peça um novo código.'];
    }
    if ((int)$row['tentativas'] >= LP_COD_MAX_TENTATIVAS) {
        return [false, 'Muitas tentativas erradas. Peça um novo código.'];
    }
    if (!password_verify($codigo, $row['codigo_hash'])) {
        $pdo->prepare("UPDATE verificacoes_email SET tentativas = tentativas + 1 WHERE id_usuario = ? AND tipo = ?")->execute([$idUsuario, $tipo]);
        $restam = LP_COD_MAX_TENTATIVAS - (int)$row['tentativas'] - 1;
        return [false, $restam > 0 ? "Código incorreto. Você ainda tem $restam tentativa" . ($restam > 1 ? 's' : '') . '.'
                                   : 'Código incorreto. Peça um novo código.'];
    }

    $pdo->prepare("DELETE FROM verificacoes_email WHERE id_usuario = ? AND tipo = ?")->execute([$idUsuario, $tipo]);
    if ($tipo === 'cadastro') {
        $pdo->prepare("UPDATE usuarios SET email_verificado = 1 WHERE id_usuario = ?")->execute([$idUsuario]);
    }
    return [true, null];
}

/* ====================================================================
 * Senha: recuperação (esqueci minha senha) e aviso de senha alterada
 * ==================================================================== */

/**
 * Passo 1 da recuperação: se existir conta com esse e-mail, envia o código.
 * Não devolve nada de propósito: quem chama mostra sempre a mesma mensagem,
 * para ninguém descobrir quais e-mails estão cadastrados.
 */
function iniciarRecuperacaoSenha(PDO $pdo, string $email): void {
    $email = trim($email);
    if (!ensureVerificacaoSchema($pdo) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    $s = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ? LIMIT 1");
    $s->execute([$email]);
    $id = $s->fetchColumn();
    if ($id) enviarCodigoVerificacao($pdo, (int)$id, 'senha'); // o intervalo de 60 s vale aqui também
}

/**
 * Passo 2: confere o código e grava a nova senha (só como hash).
 * Qualquer falha devolve a mesma mensagem genérica (não revela se o e-mail existe).
 * @return array [bool $ok, ?string $erro]
 */
function redefinirSenhaComCodigo(PDO $pdo, string $email, string $codigo, string $novaSenha): array {
    $generico = 'Código inválido ou expirado. Confira o e-mail e o código, ou peça um novo.';
    if (!ensureVerificacaoSchema($pdo)) return [false, 'Não foi possível redefinir a senha agora.'];
    if (strlen($novaSenha) < 6) return [false, 'A nova senha deve ter pelo menos 6 caracteres.'];

    $s = $pdo->prepare("SELECT id_usuario, nome, email FROM usuarios WHERE email = ? LIMIT 1");
    $s->execute([trim($email)]);
    $user = $s->fetch();
    if (!$user) return [false, $generico];

    [$ok] = confirmarCodigoVerificacao($pdo, (int)$user['id_usuario'], $codigo, 'senha');
    if (!$ok) return [false, $generico];

    // Ter recebido o código no e-mail prova que a pessoa controla esse endereço
    $pdo->prepare("UPDATE usuarios SET senha = ?, email_verificado = 1 WHERE id_usuario = ?")
        ->execute([password_hash($novaSenha, PASSWORD_DEFAULT), $user['id_usuario']]);
    enviarAvisoSenhaAlterada($user['email'], $user['nome']);
    return [true, null];
}

/** E-mail de segurança: "sua senha foi alterada" (para trocas e redefinições). */
function enviarAvisoSenhaAlterada(string $email, string $nome): void {
    require_once __DIR__ . '/mailer.php';
    $primeiro = htmlspecialchars(explode(' ', trim($nome))[0], ENT_QUOTES, 'UTF-8');
    $corpo = '<p>Olá, ' . $primeiro . '!</p>'
        . '<p>A senha da sua conta no LootPrice foi alterada em <strong>' . date('d/m/Y \à\s H:i') . '</strong>.</p>'
        . '<p>Se foi você, não precisa fazer nada. Se <strong>não foi você</strong>, '
        . '<a href="' . lp_site_url() . '/esqueci_senha.php" style="color:#6d28d9;">redefina sua senha agora</a> '
        . 'e confira se o seu e-mail está seguro.</p>';
    lp_enviar_email($email, $nome, 'Sua senha do LootPrice foi alterada', lp_email_template('Senha alterada', $corpo));
}
