<?php
/**
 * Gerenciamento de Sessão e Autenticação
 * LootPrice
 */

require_once __DIR__ . '/../config/db.php';

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
 * Leitura segura de entrada: sempre devolve texto (se vier array, como ?q[]=x, vira vazio),
 * sem espaços nas pontas e limitado a $max caracteres.
 */
function lp_str($v, int $max = 255): string {
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}
/** Senha: texto exato, sem trim (espaços fazem parte da senha). */
function lp_senha(string $k): string { $v = $_POST[$k] ?? ''; return is_string($v) ? $v : ''; }
function lp_get(string $k, int $max = 255): string { return lp_str($_GET[$k] ?? '', $max); }
function lp_post(string $k, int $max = 255): string { return lp_str($_POST[$k] ?? '', $max); }

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
 * Exige POST com token CSRF válido; se não, avisa e volta para $redirectTo.
 */
function exigirPostCsrf(string $redirectTo): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValido()) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Requisição inválida ou expirada. Tente de novo.'];
        header("Location: $redirectTo");
        exit;
    }
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
    // Impressão da senha: se ela mudar em outro lugar, esta sessão deixa de valer (veja refreshSessionUser)
    $_SESSION['senha_fp'] = isset($user['senha']) ? senhaImpressao((string)$user['senha']) : null;
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

/* ====================================================================
 * Sessão sempre em dia com o banco
 * ==================================================================== */

/** Impressão curta do hash da senha (não revela o hash): muda quando a senha muda. */
function senhaImpressao(string $hashSenha): string {
    return substr(hash('sha256', $hashSenha), 0, 24);
}

/** Depois de trocar a senha nesta sessão: guarda a nova impressão para ela continuar valendo. */
function atualizarImpressaoSenha(string $novoHash): void {
    $_SESSION['senha_fp'] = senhaImpressao($novoHash);
}

/**
 * Confere a cada requisição se quem está na sessão ainda existe e ainda tem o mesmo papel:
 *  - conta excluída ou senha trocada (em outro lugar) => sessão encerrada;
 *  - rebaixado/promovido, e-mail confirmado, nome e fotos => valem na hora, sem precisar sair e entrar.
 * Se o banco estiver fora do ar mantém a sessão como está (as páginas que precisam do banco já falham sozinhas).
 */
function refreshSessionUser(): void {
    static $feito = false;
    if ($feito) return;
    $feito = true;
    if (!isLoggedIn()) return;

    $pdo = getDB();
    if (!$pdo) return;
    try {
        $s = $pdo->prepare('SELECT * FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $s->execute([(int)($_SESSION['usuario']['id_usuario'] ?? 0)]);
        $row = $s->fetch();
    } catch (Throwable $e) {
        lp_log_erro('Não consegui atualizar a sessão do usuário', $e);
        return;
    }

    $encerrar = !$row;
    if ($row) {
        $fp = senhaImpressao((string)$row['senha']);
        if (!empty($_SESSION['senha_fp']) && !hash_equals($_SESSION['senha_fp'], $fp)) {
            $encerrar = true;
        } else {
            $_SESSION['senha_fp'] = $fp;
            unset($row['senha']);
            $_SESSION['usuario'] = array_merge($_SESSION['usuario'], $row);
        }
    }
    if ($encerrar) {
        logoutUser();
        @session_start();
        $_SESSION['flash'] = ['type' => 'info', 'message' => 'Sua sessão foi encerrada. Entre novamente.'];
    }
}
refreshSessionUser();

/* ====================================================================
 * Limite de tentativas de login (anti força bruta)
 * Por e-mail/username (5 erros) e por IP (30 erros) em 15 minutos => bloqueio de 15 minutos.
 * ==================================================================== */

const LP_LOGIN_JANELA_MIN = 15;
const LP_LOGIN_MAX_POR_CONTA = 5;
const LP_LOGIN_MAX_POR_IP = 30;

function loginChaves(string $login): array {
    return [
        [hash('sha256', 'conta|' . mb_strtolower($login, 'UTF-8')), LP_LOGIN_MAX_POR_CONTA],
        [hash('sha256', 'ip|' . ($_SERVER['REMOTE_ADDR'] ?? '')), LP_LOGIN_MAX_POR_IP],
    ];
}

/** Segundos que faltam para poder tentar de novo (0 = liberado). */
function loginBloqueadoSegundos(PDO $pdo, string $login): int {
    $maior = 0;
    try {
        $s = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, NOW(), bloqueado_ate) FROM tentativas_login WHERE chave = ? AND bloqueado_ate > NOW()');
        foreach (loginChaves($login) as [$chave]) {
            $s->execute([$chave]);
            $maior = max($maior, (int)$s->fetchColumn());
        }
    } catch (Throwable $e) {
        lp_log_erro('Limite de login indisponível', $e);
    }
    return $maior;
}

function loginRegistrarFalha(PDO $pdo, string $login): void {
    try {
        $s = $pdo->prepare('INSERT INTO tentativas_login (chave, tentativas, primeira_em) VALUES (:c, 1, NOW())
            ON DUPLICATE KEY UPDATE
              tentativas = IF(primeira_em < NOW() - INTERVAL ' . LP_LOGIN_JANELA_MIN . ' MINUTE, 1, tentativas + 1),
              primeira_em = IF(primeira_em < NOW() - INTERVAL ' . LP_LOGIN_JANELA_MIN . ' MINUTE, NOW(), primeira_em),
              bloqueado_ate = IF(tentativas >= :lim, NOW() + INTERVAL ' . LP_LOGIN_JANELA_MIN . ' MINUTE, NULL)');
        foreach (loginChaves($login) as [$chave, $lim]) {
            $s->execute(['c' => $chave, 'lim' => $lim]);
        }
    } catch (Throwable $e) {
        lp_log_erro('Não consegui registrar a tentativa de login', $e);
    }
}

function loginLimparFalhas(PDO $pdo, string $login): void {
    try {
        // só zera a conta; o contador do IP continua (um IP que erra em várias contas segue limitado)
        $pdo->prepare('DELETE FROM tentativas_login WHERE chave = ?')->execute([loginChaves($login)[0][0]]);
    } catch (Throwable $e) {}
}
