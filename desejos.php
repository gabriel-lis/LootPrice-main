<?php
/**
 * Adicionar/remover jogo da lista de desejos (POST) - LootPrice
 */
require_once __DIR__ . '/includes/functions.php';

$idJogo = (int)($_POST['id_jogo'] ?? 0);
$voltar = ($_POST['voltar'] ?? '') === 'perfil' ? 'perfil.php?aba=desejos' : 'jogo.php?id=' . $idJogo;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $idJogo <= 0) {
    header('Location: index.php');
    exit;
}
requireLogin('login.php');

// A lista de desejos (e os avisos de preço) só funcionam com o e-mail confirmado
if (!isEmailVerified()) {
    setFlash('error', 'Confirme seu e-mail para usar a lista de desejos.');
    header('Location: verificar_email.php');
    exit;
}

if (!getGameById($idJogo)) {
    setFlash('error', 'Jogo não encontrado no catálogo.');
    header('Location: index.php');
    exit;
}

$adicionar = ($_POST['acao'] ?? '') === 'adicionar';
if (setWishlist((int)currentUser()['id_usuario'], $idJogo, $adicionar)) {
    setFlash('success', $adicionar ? 'Jogo adicionado à sua lista de desejos.' : 'Jogo removido da sua lista de desejos.');
} else {
    setFlash('error', 'Não foi possível atualizar sua lista de desejos. Tente novamente.');
}
header('Location: ' . $voltar);
exit;
