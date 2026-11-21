<?php
/**
 * Lista de desejos - LootPrice
 * Cada usuário logado guarda jogos para acompanhar; a lista é vista só no próprio perfil.
 */

/** Garante a tabela (criada sob demanda, como acessos_jogos). */
function ensureDesejosTable(PDO $pdo): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `lista_desejos` (
            `id_usuario` INT NOT NULL,
            `id_jogo` INT NOT NULL,
            `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_usuario`, `id_jogo`),
            CONSTRAINT `fk_desejo_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
            CONSTRAINT `fk_desejo_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ok = true;
    } catch (Exception $e) {
        $ok = false;
    }
    return $ok;
}

function isInWishlist(int $idUsuario, int $idJogo): bool {
    $pdo = getDB();
    if (!$pdo || !ensureDesejosTable($pdo)) return false;
    try {
        $s = $pdo->prepare("SELECT 1 FROM lista_desejos WHERE id_usuario = :u AND id_jogo = :j");
        $s->execute(['u' => $idUsuario, 'j' => $idJogo]);
        return (bool)$s->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

/** Adiciona ($adicionar = true) ou remove o jogo da lista. Retorna false se falhar. */
function setWishlist(int $idUsuario, int $idJogo, bool $adicionar): bool {
    $pdo = getDB();
    if (!$pdo || !ensureDesejosTable($pdo)) return false;
    try {
        $sql = $adicionar
            ? "INSERT IGNORE INTO lista_desejos (id_usuario, id_jogo) VALUES (:u, :j)"
            : "DELETE FROM lista_desejos WHERE id_usuario = :u AND id_jogo = :j";
        $pdo->prepare($sql)->execute(['u' => $idUsuario, 'j' => $idJogo]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/** Jogos da lista (mais recentes primeiro), já com menor preço e desconto para os cards. */
function getWishlistGames(int $idUsuario): array {
    $pdo = getDB();
    if (!$pdo || !ensureDesejosTable($pdo)) return [];
    try {
        $s = $pdo->prepare("SELECT id_jogo FROM lista_desejos WHERE id_usuario = :u ORDER BY criado_em DESC, id_jogo DESC");
        $s->execute(['u' => $idUsuario]);
        $ordem = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    } catch (Exception $e) {
        return [];
    }
    if (!$ordem) return [];
    $porId = [];
    foreach (attachBestOffer(getGamesCatalog()) as $g) $porId[(int)$g['id_jogo']] = $g;
    $out = [];
    foreach ($ordem as $id) if (isset($porId[$id])) $out[] = $porId[$id];
    return $out;
}

function countWishlist(int $idUsuario): int {
    $pdo = getDB();
    if (!$pdo || !ensureDesejosTable($pdo)) return 0;
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM lista_desejos WHERE id_usuario = :u");
        $s->execute(['u' => $idUsuario]);
        return (int)$s->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}
