<?php
/**
 * Esqueci minha senha - passo 1: a pessoa informa o e-mail e recebe um código.
 * A resposta é sempre a mesma, exista ou não conta com esse e-mail.
 */
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: perfil.php'); // logado troca a senha pelo perfil
    exit;
}

$erro = null;
$emailDigitado = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $emailDigitado = trim($_POST['email'] ?? '');
    $pdo = getDB();
    if (!csrfValido()) {
        $erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif (!filter_var($emailDigitado, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (!$pdo) {
        $erro = 'Erro de conexão com o banco de dados. Verifique se o MySQL no XAMPP está ativo.';
    } else {
        iniciarRecuperacaoSenha($pdo, $emailDigitado);
        $_SESSION['reset_email'] = $emailDigitado;
        setFlash('info', 'Se esse e-mail estiver cadastrado, enviamos um código para ele.');
        header('Location: redefinir_senha.php');
        exit;
    }
}

$pageTitle = 'Esqueci minha senha - LootPrice';
$basePath = '';
$hideSearch = true;
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= assetUrl('css_index/verificacao.css') ?>">

<div class="verif-wrap">
  <div class="verif-card">
    <h1>Esqueceu a senha?</h1>
    <p class="verif-sub">Digite o e-mail da sua conta. Vamos enviar um código de 6 números para você criar uma senha nova.</p>

    <?php if ($erro): ?><div class="verif-msg verif-erro" role="alert"><?= sanitize($erro) ?></div><?php endif; ?>

    <form method="POST" action="esqueci_senha.php">
      <?= csrfField() ?>
      <div class="verif-campo">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" required autofocus maxlength="100"
               autocomplete="email" placeholder="seuemail@exemplo.com" value="<?= sanitize($emailDigitado) ?>">
      </div>
      <button type="submit" class="verif-btn">Enviar código</button>
    </form>

    <div class="verif-rodape">
      <a href="redefinir_senha.php">Já tenho um código</a> · <a href="login.php">Voltar para entrar</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
