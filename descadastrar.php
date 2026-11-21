<?php
/**
 * Parar de receber avisos de promoção - LootPrice
 * Chega pelo link do rodapé do e-mail (u = id do usuário, t = assinatura). Funciona sem login.
 * O link só mostra a confirmação; quem desliga é o botão (POST), para que leitores de e-mail
 * que "abrem" links sozinhos não cancelem o aviso de ninguém.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/avisos.php';

$idUsuario = (int)lp_str($_REQUEST['u'] ?? '', 12);
$token = lp_str($_REQUEST['t'] ?? '', 100);
$valido = $idUsuario > 0 && $token !== '' && hash_equals(lp_token_descadastro($idUsuario), $token);

$feito = false;
if ($valido && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $pdo = getDB();
    if ($pdo && ensureAvisosSchema($pdo)) {
        $pdo->prepare("UPDATE usuarios SET alertas_email = 0 WHERE id_usuario = ?")->execute([$idUsuario]);
        $feito = true;
    }
}

$pageTitle = 'Avisos de promoção - LootPrice';
$basePath = '';
$hideSearch = true;
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= assetUrl('css_index/verificacao.css') ?>">

<div class="verif-wrap">
  <div class="verif-card">
    <?php if (!$valido): ?>
      <h1>Link inválido</h1>
      <p class="verif-sub">Esse link não é válido ou foi alterado. Para mudar os avisos, entre na sua conta e abra o <a href="perfil.php" style="color: var(--accent-text);">perfil</a>.</p>
    <?php elseif ($feito): ?>
      <h1>Pronto!</h1>
      <p class="verif-sub">Você não vai mais receber e-mails de promoção da sua lista de desejos.<br>
        Mudou de ideia? Ligue de novo em <a href="perfil.php" style="color: var(--accent-text);">Meu perfil</a>, nas informações pessoais.</p>
    <?php else: ?>
      <h1>Parar de receber avisos?</h1>
      <p class="verif-sub">Você deixará de receber e-mails quando um jogo da sua lista de desejos entrar em promoção.</p>
      <form method="POST" action="descadastrar.php">
        <input type="hidden" name="u" value="<?= $idUsuario ?>">
        <input type="hidden" name="t" value="<?= sanitize($token) ?>">
        <button type="submit" class="verif-btn">Sim, parar de receber</button>
      </form>
    <?php endif; ?>
    <div class="verif-rodape"><a href="index.php">Voltar ao LootPrice</a></div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
