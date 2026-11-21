<?php
/**
 * Logout do Usuário - LootPrice
 * Só sai por POST com token CSRF (um link/imagem em outro site não consegue deslogar ninguém).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrfValido()) {
    logoutUser();
    @session_start(); // sessão nova só para mostrar o aviso abaixo
    setFlash('info', 'Você foi desconectado com sucesso.');
}
header('Location: index.php');
exit;
