<?php
/**
 * Auxiliares do painel administrativo (admin/*.php) - LootPrice
 *
 *  - adminIniciar(): exige administrador e banco no ar (senão mostra aviso em vez de quebrar a página)
 *  - lp_parse_valor(): "59,90" / "1.299,90" / "59.90" -> float
 *  - adminSalvarImagem()/adminApagarArquivo(): upload e remoção de capas
 *  - lp_conta_admins(): quantos administradores existem (para nunca ficar sem nenhum)
 */

/**
 * Chamar no início de toda página admin. Devolve o PDO.
 * Também trata o POST: sem token CSRF válido a ação é recusada e volta para $voltar.
 */
function adminIniciar(string $voltar = 'index.php'): PDO {
    requireAdmin('../login.php');
    $pdo = getDB();
    if (!$pdo) {
        $pageTitle = 'Painel Administrativo - LootPrice';
        $basePath = '../';
        $cssAdmin = true;
        require __DIR__ . '/header.php';
        echo '<div class="adm-wrap"><div class="adm-aviso erro" role="alert"><strong>Banco de dados indisponível.</strong> '
           . 'Confira se o MySQL do XAMPP está ligado e recarregue a página.</div></div>';
        require __DIR__ . '/footer.php';
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') exigirPostCsrf($voltar);
    return $pdo;
}

/** Texto de erro para mostrar ao admin: detalhe só em modo desenvolvimento (LP_DEV); senão vai para o log. */
function adminErro(string $onde, Throwable $e): string {
    lp_log_erro("Admin: $onde", $e);
    return LP_DEV ? "$onde: " . $e->getMessage() : "Não foi possível concluir ($onde). O detalhe foi gravado em logs/erros.log.";
}

/**
 * Converte um valor digitado em dinheiro. Aceita "59,90", "59.90", "1.299,90", "R$ 59,90".
 * Devolve null se não for um número válido (ou for negativo).
 */
function lp_parse_valor(string $txt): ?float {
    $t = trim(str_ireplace('R$', '', $txt));
    if ($t === '') return null;
    if (strpos($t, ',') !== false) $t = str_replace(['.', ' '], '', $t); // "1.299,90" -> "1299,90"
    $t = str_replace(',', '.', $t);
    if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $t)) return null;
    return (float)$t;
}

/** Só aceita link http(s) com até 255 caracteres (bloqueia javascript:, data: etc.). */
function lp_link_valido(string $url): bool {
    return strlen($url) <= 255 && (bool)filter_var($url, FILTER_VALIDATE_URL) && (bool)preg_match('#^https?://#i', $url);
}

/**
 * Salva uma imagem enviada em $pastaRel (ex.: 'fotos/jogos'). Devolve [caminho relativo|null, erro|null].
 * Aceita JPG, PNG, WEBP e GIF até $maxMb; o nome do arquivo é aleatório.
 */
function adminSalvarImagem(array $arq, string $pastaRel, string $prefixo, int $maxMb = 3): array {
    if (($arq['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($arq['error'] !== UPLOAD_ERR_OK) return [null, 'Não foi possível enviar a imagem. Tente outra.'];
    if ($arq['size'] > $maxMb * 1024 * 1024) return [null, "A imagem deve ter no máximo $maxMb MB."];
    $info = @getimagesize($arq['tmp_name']);
    $exts = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($exts[$info[2]])) return [null, 'Use uma imagem JPG, PNG, WEBP ou GIF.'];
    $dir = __DIR__ . '/../' . $pastaRel;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $nome = $prefixo . '_' . bin2hex(random_bytes(6)) . '.' . $exts[$info[2]];
    if (!move_uploaded_file($arq['tmp_name'], $dir . '/' . $nome)) return [null, 'Falha ao salvar a imagem no servidor.'];
    return [$pastaRel . '/' . $nome, null];
}

/**
 * Apaga um arquivo de imagem do site, mas só se ele estiver dentro de uma das pastas permitidas
 * (nunca sai de fotos/jogos/ ou fotos/perfis/) e não for a capa padrão.
 */
function adminApagarArquivo(?string $caminho, array $pastasPermitidas = ['fotos/jogos/', 'fotos/perfis/']): void {
    if (!$caminho || strpos($caminho, '..') !== false || basename($caminho) === 'sem-capa.svg') return;
    foreach ($pastasPermitidas as $p) {
        if (strpos($caminho, $p) === 0) {
            $f = __DIR__ . '/../' . $caminho;
            if (is_file($f)) @unlink($f);
            return;
        }
    }
}

function lp_conta_admins(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'administrador'")->fetchColumn();
}

/** Selo da nota Metacritic para tabelas do admin. */
function adminNotaMeta($nota): string {
    if ($nota === null || $nota === '') return '<span class="adm-mini">—</span>';
    return '<span class="adm-badge meta" style="background:' . getMetacriticColor((float)$nota) . '">' . (int)$nota . '</span>';
}
