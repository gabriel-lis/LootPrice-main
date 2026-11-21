<?php
/**
 * Avaliações e comentários dos jogos - LootPrice
 * Cada usuário (com e-mail confirmado) dá uma nota de 1 a 5 e pode escrever um comentário por jogo.
 * Pode editar a própria avaliação (salvar de novo substitui) ou excluí-la; o administrador pode
 * excluir qualquer uma. A tabela `avaliacoes` é criada por includes/migracoes.php.
 */

const LP_AVALIACAO_MAX_COMENTARIO = 1000;

/** Média e quantidade de notas do jogo. @return array{media:?float,total:int} */
function getReviewSummary(int $idJogo): array {
    $r = lp_consulta('getReviewSummary', "SELECT AVG(nota) AS media, COUNT(*) AS total FROM avaliacoes WHERE id_jogo = :j", ['j' => $idJogo])[0] ?? null;
    $total = (int)($r['total'] ?? 0);
    return ['media' => $total ? round((float)$r['media'], 1) : null, 'total' => $total];
}

/** Avaliações mais recentes do jogo, já com nome/foto de quem escreveu. */
function getGameReviews(int $idJogo, int $limite = 30): array {
    return lp_consulta('getGameReviews',
        "SELECT a.*, u.nome, u.username, u.foto_perfil
         FROM avaliacoes a JOIN usuarios u ON u.id_usuario = a.id_usuario
         WHERE a.id_jogo = :j ORDER BY a.criado_em DESC, a.id_avaliacao DESC LIMIT " . max(1, $limite), ['j' => $idJogo]);
}

function getUserReview(int $idUsuario, int $idJogo): ?array {
    return lp_consulta('getUserReview', "SELECT * FROM avaliacoes WHERE id_usuario = :u AND id_jogo = :j LIMIT 1", ['u' => $idUsuario, 'j' => $idJogo])[0] ?? null;
}

/** Salva (ou substitui) a avaliação. @return array [bool $ok, ?string $erro] */
function saveReview(int $idUsuario, int $idJogo, int $nota, string $comentario): array {
    if ($nota < 1 || $nota > 5) return [false, 'Escolha uma nota de 1 a 5.'];
    $comentario = trim($comentario);
    if (mb_strlen($comentario) > LP_AVALIACAO_MAX_COMENTARIO) return [false, 'O comentário pode ter no máximo ' . LP_AVALIACAO_MAX_COMENTARIO . ' caracteres.'];
    $pdo = getDB();
    if (!$pdo) return [false, 'Banco de dados indisponível agora.'];
    try {
        $pdo->prepare("INSERT INTO avaliacoes (id_usuario, id_jogo, nota, comentario) VALUES (:u, :j, :n, :c)
                       ON DUPLICATE KEY UPDATE nota = VALUES(nota), comentario = VALUES(comentario)")
            ->execute(['u' => $idUsuario, 'j' => $idJogo, 'n' => $nota, 'c' => $comentario !== '' ? $comentario : null]);
        return [true, null];
    } catch (Throwable $e) {
        lp_consulta_falhou('saveReview', $e);
        return [false, 'Não foi possível salvar sua avaliação. Tente de novo.'];
    }
}

/** Exclui a avaliação: o dono só a própria; administrador qualquer uma. */
function deleteReview(int $idAvaliacao, int $idUsuario, bool $ehAdmin): bool {
    $pdo = getDB();
    if (!$pdo) return false;
    try {
        $sql = "DELETE FROM avaliacoes WHERE id_avaliacao = :a" . ($ehAdmin ? '' : ' AND id_usuario = :u');
        $params = $ehAdmin ? ['a' => $idAvaliacao] : ['a' => $idAvaliacao, 'u' => $idUsuario];
        return $pdo->prepare($sql)->execute($params);
    } catch (Throwable $e) {
        lp_consulta_falhou('deleteReview', $e);
        return false;
    }
}

/** Estrelas em texto (★★★☆☆) para a nota. */
function estrelasTexto(float $nota): string {
    $cheias = (int)round($nota);
    return str_repeat('★', $cheias) . str_repeat('☆', 5 - $cheias);
}
