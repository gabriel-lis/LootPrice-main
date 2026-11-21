<?php
/**
 * Salvar ou excluir a avaliação (nota + comentário) de um jogo (POST) - LootPrice
 */
require_once __DIR__ . '/includes/functions.php';

$idJogo = (int)lp_post('id_jogo', 12);
$voltar = 'jogo.php?id=' . $idJogo . '#avaliacoes';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $idJogo <= 0) {
    header('Location: index.php');
    exit;
}
requireLogin('login.php');
exigirPostCsrf($voltar);

if (!isEmailVerified()) {
    setFlash('error', 'Confirme seu e-mail para avaliar jogos.');
    header('Location: verificar_email.php');
    exit;
}
if (!getGameById($idJogo)) {
    setFlash('error', 'Jogo não encontrado no catálogo.');
    header('Location: index.php');
    exit;
}

$uid = (int)currentUser()['id_usuario'];
if (lp_post('acao', 12) === 'excluir') {
    $ok = deleteReview((int)lp_post('id_avaliacao', 12), $uid, isAdmin());
    setFlash($ok ? 'success' : 'error', $ok ? 'Avaliação excluída.' : 'Não foi possível excluir a avaliação.');
} else {
    [$ok, $erro] = saveReview($uid, $idJogo, (int)lp_post('nota', 2), lp_post('comentario', LP_AVALIACAO_MAX_COMENTARIO + 50));
    setFlash($ok ? 'success' : 'error', $ok ? 'Avaliação salva. Obrigado!' : $erro);
}
header('Location: ' . $voltar);
exit;
