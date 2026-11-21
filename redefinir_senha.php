<?php
/**
 * Esqueci minha senha - passo 2: código recebido por e-mail + nova senha.
 */
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: perfil.php');
    exit;
}

$erro = null;
$info = null;
$email = trim($_POST['email'] ?? ($_SESSION['reset_email'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $pdo = getDB();
    if (!csrfValido()) {
        $erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif (!$pdo) {
        $erro = 'Erro de conexão com o banco de dados. Verifique se o MySQL no XAMPP está ativo.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe o e-mail da sua conta.';
    } elseif (($_POST['acao'] ?? '') === 'reenviar') {
        iniciarRecuperacaoSenha($pdo, $email);
        $_SESSION['reset_email'] = $email;
        $info = 'Se esse e-mail estiver cadastrado, enviamos um novo código (o intervalo mínimo entre envios é de ' . LP_COD_REENVIO_SEG . ' segundos).';
    } else {
        $nova = $_POST['nova_senha'] ?? '';
        $conf = $_POST['confirma_senha'] ?? '';
        if (trim($_POST['codigo'] ?? '') === '' || $nova === '') {
            $erro = 'Preencha o código e a nova senha.';
        } elseif ($nova !== $conf) {
            $erro = 'A confirmação não é igual à nova senha.';
        } else {
            [$ok, $msg] = redefinirSenhaComCodigo($pdo, $email, (string)$_POST['codigo'], $nova);
            if ($ok) {
                unset($_SESSION['reset_email']);
                setFlash('success', 'Senha redefinida! Entre com a nova senha.');
                header('Location: login.php');
                exit;
            }
            $erro = $msg;
        }
    }
}

$pageTitle = 'Redefinir senha - LootPrice';
$basePath = '';
$hideSearch = true;
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= assetUrl('css_index/verificacao.css') ?>">

<div class="verif-wrap">
  <div class="verif-card">
    <h1>Criar nova senha</h1>
    <p class="verif-sub">Digite o código de 6 números que enviamos por e-mail (vale <?= LP_COD_VALIDADE_MIN ?> minutos) e escolha a nova senha.</p>

    <?php if ($erro): ?><div class="verif-msg verif-erro" role="alert"><?= sanitize($erro) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="verif-msg verif-info" role="status"><?= sanitize($info) ?></div><?php endif; ?>

    <form method="POST" action="redefinir_senha.php" autocomplete="off">
      <?= csrfField() ?>
      <input type="hidden" name="acao" value="redefinir">

      <div class="verif-campo">
        <label for="email">E-mail da conta</label>
        <input type="email" id="email" name="email" required maxlength="100" autocomplete="email" value="<?= sanitize($email) ?>">
      </div>

      <div class="verif-campo">
        <label for="codigo">Código</label>
        <input class="verif-codigo" type="text" id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code"
               maxlength="6" pattern="[0-9]{6}" placeholder="000000" required <?= $email !== '' ? 'autofocus' : '' ?>>
      </div>

      <div class="verif-campo">
        <label for="nova_senha">Nova senha (mínimo 6 caracteres)</label>
        <input type="password" id="nova_senha" name="nova_senha" required minlength="6" autocomplete="new-password">
      </div>

      <div class="verif-campo">
        <label for="confirma_senha">Confirmar nova senha</label>
        <input type="password" id="confirma_senha" name="confirma_senha" required minlength="6" autocomplete="new-password">
      </div>

      <button type="submit" class="verif-btn">Redefinir senha</button>
    </form>

    <div class="verif-rodape">
      <form method="POST" action="redefinir_senha.php" style="display:inline;">
        <?= csrfField() ?>
        <input type="hidden" name="acao" value="reenviar">
        <input type="hidden" name="email" value="<?= sanitize($email) ?>">
        Não chegou? <button type="submit" class="verif-link">Enviar um novo código</button>
      </form>
      <br><a href="esqueci_senha.php">Usar outro e-mail</a> · <a href="login.php">Voltar para entrar</a>
      <br><small>Olhe também a caixa de spam.</small>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
