<?php
/**
 * Confirmação de e-mail - LootPrice
 * A pessoa digita o código de 6 dígitos enviado por e-mail (ver includes/verificacao.php).
 */
require_once __DIR__ . '/includes/functions.php';

requireLogin('login.php');

$pdo = getDB();
$sessao = currentUser();
$id = (int)($sessao['id_usuario'] ?? 0);
if (!$pdo || !ensureVerificacaoSchema($pdo)) {
    setFlash('error', 'Não foi possível acessar a verificação agora. Tente de novo em instantes.');
    header('Location: index.php');
    exit;
}

$st = $pdo->prepare("SELECT email, email_verificado FROM usuarios WHERE id_usuario = ?");
$st->execute([$id]);
$u = $st->fetch();
if (!$u) { logoutUser(); header('Location: login.php'); exit; }

/** Para onde ir depois: o jogo que a pessoa estava vendo (se houver) ou a home. */
function destinoPosVerificacao(): string {
    $d = $_SESSION['voltar_para'] ?? null;
    unset($_SESSION['voltar_para']);
    return ($d && preg_match('/^jogo\.php\?id=\d+$/', $d)) ? $d : 'index.php';
}

if ((int)$u['email_verificado'] === 1) {
    $_SESSION['usuario']['email_verificado'] = 1;
    setFlash('info', 'Seu e-mail já está confirmado.');
    header('Location: ' . destinoPosVerificacao());
    exit;
}

if (isset($_GET['pular'])) {
    header('Location: ' . destinoPosVerificacao());
    exit;
}

$erro = null;
$info = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfValido()) {
        $erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif (($_POST['acao'] ?? '') === 'reenviar') {
        [$ok, $msg] = enviarCodigoVerificacao($pdo, $id);
        if ($ok) $info = 'Enviamos um novo código para ' . mascararEmail($u['email']) . '.';
        else $erro = $msg;
    } else {
        [$ok, $msg] = confirmarCodigoVerificacao($pdo, $id, (string)($_POST['codigo'] ?? ''));
        if ($ok) {
            $_SESSION['usuario']['email_verificado'] = 1;
            setFlash('success', 'E-mail confirmado! Obrigado.');
            header('Location: ' . destinoPosVerificacao());
            exit;
        }
        $erro = $msg;
    }
}

$temCodigo = (bool)$pdo->query("SELECT 1 FROM verificacoes_email WHERE id_usuario = $id AND tipo = 'cadastro' AND expira_em > NOW()")->fetchColumn();
$espera = segundosParaReenvio($pdo, $id);

$pageTitle = 'Confirmar e-mail - LootPrice';
$basePath = '';
$hideSearch = true;
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= assetUrl('css_index/verificacao.css') ?>">

<div class="verif-wrap">
  <div class="verif-card">
    <h1>Confirme seu e-mail</h1>
    <p class="verif-sub">
      <?php if ($temCodigo): ?>
        Enviamos um código de 6 números para <strong><?= sanitize(mascararEmail($u['email'])) ?></strong>.<br>
        Digite-o abaixo. Ele vale por <?= LP_COD_VALIDADE_MIN ?> minutos.
      <?php else: ?>
        Para usar a lista de desejos, confirme o e-mail <strong><?= sanitize(mascararEmail($u['email'])) ?></strong>.<br>
        Clique em enviar para receber o código.
      <?php endif; ?>
    </p>

    <?php if ($erro): ?><div class="verif-msg verif-erro" role="alert"><?= sanitize($erro) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="verif-msg verif-info" role="status"><?= sanitize($info) ?></div><?php endif; ?>

    <?php if ($temCodigo): ?>
    <form method="POST" action="verificar_email.php" autocomplete="off">
      <?= csrfField() ?>
      <input type="hidden" name="acao" value="confirmar">
      <input class="verif-codigo" type="text" name="codigo" inputmode="numeric" autocomplete="one-time-code"
             maxlength="6" pattern="[0-9]{6}" placeholder="000000" aria-label="Código de 6 números" required autofocus>
      <button type="submit" class="verif-btn">Confirmar e-mail</button>
    </form>
    <?php endif; ?>

    <div class="verif-rodape">
      <form method="POST" action="verificar_email.php" style="display:inline;">
        <?= csrfField() ?>
        <input type="hidden" name="acao" value="reenviar">
        <?= $temCodigo ? 'Não chegou? ' : '' ?>
        <button type="submit" class="verif-link" <?= $espera > 0 ? 'disabled' : '' ?>>
          <?= $temCodigo ? 'Enviar um novo código' : 'Enviar código' ?>
        </button>
        <?php if ($espera > 0): ?><span>(aguarde <?= $espera ?>s)</span><?php endif; ?>
      </form>
      <br><a href="verificar_email.php?pular=1">Confirmar depois</a> · <a href="perfil.php">Trocar o e-mail</a>
      <br><small>Olhe também a caixa de spam.</small>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
