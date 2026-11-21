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
    // Cookie de sessão: inacessível ao JavaScript e não enviado em requisições de outros sites
    @session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    @session_start();
}

/**
 * Token anti-CSRF da sessão. Use csrfField() dentro dos formulários POST
 * e csrfValido() no início do tratamento do POST.
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf" value="' . csrfToken() . '">';
}

function csrfValido(): bool {
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

/**
 * O e-mail da conta já foi confirmado? Sessões antigas (sem o campo) contam como confirmadas.
 */
function isEmailVerified(): bool {
    return isLoggedIn() && (int)($_SESSION['usuario']['email_verificado'] ?? 1) === 1;
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
    // Novo id de sessão a cada login evita fixação de sessão
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }
    $_SESSION['usuario'] = $user;
}

/**
 * Avatar do usuário: foto de perfil ou, se não houver, círculo com a inicial do nome.
 * $classe: classe CSS de tamanho (ex.: 'avatar-sm', 'avatar-xl').
 */
function avatarHtml(array $u, string $classe = 'avatar-sm', string $basePath = ''): string {
    $nome = trim((string)($u['nome'] ?? ''));
    $inicial = htmlspecialchars(mb_strtoupper(mb_substr($nome, 0, 1, 'UTF-8'), 'UTF-8') ?: '?', ENT_QUOTES, 'UTF-8');
    $foto = $u['foto_perfil'] ?? null;
    if ($foto && is_file(__DIR__ . '/../' . $foto)) {
        $src = htmlspecialchars($basePath . $foto, ENT_QUOTES, 'UTF-8') . '?v=' . filemtime(__DIR__ . '/../' . $foto);
        return '<span class="avatar ' . $classe . '"><img src="' . $src . '" alt=""></span>';
    }
    return '<span class="avatar avatar-letra ' . $classe . '" aria-hidden="true">' . $inicial . '</span>';
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
