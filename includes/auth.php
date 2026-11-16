<?php
/**
 * Gerenciamento de Sessão e Autenticação
 * LootPrice
 */

if (session_status() === PHP_SESSION_NONE) {
    $savePath = session_save_path();
    if (empty($savePath) || !@is_writable($savePath)) {
        @session_save_path(sys_get_temp_dir());
    }
    @session_start();
}

/**
 * Verifica se há um usuário autenticado na sessão
 */
function isLoggedIn(): bool {
    return isset($_SESSION['usuario']) && is_array($_SESSION['usuario']);
}

/**
 * Retorna os dados do usuário autenticado ou null
 */
function currentUser(): ?array {
    return $_SESSION['usuario'] ?? null;
}

/**
 * Verifica se o usuário atual possui privilégios de administrador (RN10)
 */
function isAdmin(): bool {
    return isLoggedIn() && (($_SESSION['usuario']['tipo_usuario'] ?? '') === 'administrador');
}

/**
 * Exige que o usuário esteja logado para acessar a página
 */
function requireLogin(string $redirectTo = 'login.php'): void {
    if (!isLoggedIn()) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'Você precisa estar conectado para acessar esta página.'
        ];
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Exige privilégios de administrador para acessar a página (RN10)
 */
function requireAdmin(string $redirectTo = '../login.php'): void {
    if (!isAdmin()) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'Acesso restrito a administradores.'
        ];
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Registra o usuário na sessão
 */
function loginUser(array $user): void {
    unset($user['senha']);
    $_SESSION['usuario'] = $user;
}

/**
 * Encerra a sessão do usuário
 */
function logoutUser(): void {
    $_SESSION['usuario'] = null;
    unset($_SESSION['usuario']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_destroy();
    }
}
