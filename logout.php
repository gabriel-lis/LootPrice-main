<?php
/**
 * Logout do Usuário - LootPrice
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logoutUser();
setFlash('info', 'Você foi desconectado com sucesso.');
header('Location: index.php');
exit;
