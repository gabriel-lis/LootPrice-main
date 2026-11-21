<?php
/**
 * Moderação das avaliações/comentários - Admin
 * LootPrice (RN10)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';
$pdo = adminIniciar('avaliacoes.php');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && lp_post('acao', 12) === 'excluir') {
    $ok = deleteReview((int)lp_post('id_avaliacao', 12), (int)currentUser()['id_usuario'], true);
    setFlash($ok ? 'success' : 'error', $ok ? 'Avaliação excluída.' : 'Não foi possível excluir.');
    header('Location: avaliacoes.php');
    exit;
}

$lista = $pdo->query("SELECT a.*, u.nome AS usuario, j.nome AS jogo
                      FROM avaliacoes a
                      JOIN usuarios u ON u.id_usuario = a.id_usuario
                      JOIN jogos j ON j.id_jogo = a.id_jogo
                      ORDER BY a.criado_em DESC LIMIT 200")->fetchAll();

$pageTitle = 'Avaliações - Admin LootPrice';
$basePath = '../';
$cssAdmin = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="adm-wrap">
  <div class="adm-head">
    <div>
      <a href="index.php" class="adm-voltar">← Voltar ao Dashboard</a>
      <h1 class="adm-title">⭐ Avaliações e comentários</h1>
      <p class="adm-sub">As 200 mais recentes. Exclua o que for ofensivo ou inadequado.</p>
    </div>
  </div>

  <div class="adm-tabela-wrap">
    <table class="adm-tabela">
      <thead><tr><th>Data</th><th>Jogo</th><th>Usuário</th><th>Nota</th><th>Comentário</th><th class="dir">Ações</th></tr></thead>
      <tbody>
        <?php if (!$lista): ?><tr><td colspan="6" class="adm-vazio">Nenhuma avaliação ainda.</td></tr><?php endif; ?>
        <?php foreach ($lista as $a): ?>
          <tr>
            <td class="adm-mini" style="font-size:13px;"><?= date('d/m/Y H:i', strtotime($a['criado_em'])) ?></td>
            <td><a href="../jogo.php?id=<?= (int)$a['id_jogo'] ?>#avaliacoes" class="btn-link"><?= sanitize($a['jogo']) ?></a></td>
            <td><?= sanitize($a['usuario']) ?></td>
            <td style="color: var(--warning); white-space:nowrap;"><?= estrelasTexto((float)$a['nota']) ?></td>
            <td style="max-width:360px; overflow-wrap:anywhere;"><?= $a['comentario'] ? nl2br(sanitize($a['comentario'])) : '<span class="adm-mini">—</span>' ?></td>
            <td class="dir">
              <form method="POST" action="avaliacoes.php" class="adm-form-inline" onsubmit="return confirm('Excluir esta avaliação?');">
                <?= csrfField() ?>
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id_avaliacao" value="<?= (int)$a['id_avaliacao'] ?>">
                <button type="submit" class="btn-link perigo">Excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
