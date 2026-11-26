<?php
/**
 * Painel Administrativo - Jogos com preço desatualizado
 * Só o administrador vê este aviso; o cliente não.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';
$pdo = adminIniciar();

$limiteDias = 3;
$total = (int)$pdo->query("SELECT COUNT(*) FROM jogos")->fetchColumn();
$st = $pdo->prepare(
    "SELECT * FROM (
        SELECT j.id_jogo, j.nome, j.imagem_capa, COUNT(p.id_preco) AS qtd_precos, MAX(p.data_atualizacao) AS ultima
        FROM jogos j LEFT JOIN precos p ON p.id_jogo = j.id_jogo
        GROUP BY j.id_jogo, j.nome, j.imagem_capa
     ) t
     WHERE t.ultima IS NULL OR t.ultima < NOW() - INTERVAL ? DAY
     ORDER BY t.ultima IS NULL DESC, t.ultima ASC, t.nome"
);
$st->execute([$limiteDias]);
$lista = $st->fetchAll();

$pageTitle = 'Preços desatualizados - LootPrice';
$basePath = '../';
$cssAdmin = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="adm-wrap">
  <div class="adm-head">
    <div>
      <a href="index.php" class="adm-voltar">← Voltar ao Dashboard</a>
      <h1 class="adm-title">⏰ Preços desatualizados</h1>
      <p class="adm-sub"><?= count($lista) ?> de <?= $total ?> jogos estão com o preço mais recente há mais de <?= $limiteDias ?> dias.</p>
    </div>
    <div class="adm-actions">
      <a href="atualizar_precos.php" class="btn-adm">🔄 Atualizar Preços</a>
    </div>
  </div>

  <div class="adm-tabela-wrap">
    <table class="adm-tabela">
      <thead><tr><th></th><th>Jogo</th><th>Preços</th><th>Última atualização</th><th>Ações</th></tr></thead>
      <tbody>
      <?php if (empty($lista)): ?>
        <tr><td colspan="5" class="adm-vazio">Todos os preços estão em dia.</td></tr>
      <?php else: foreach ($lista as $j):
        $idade = idadeDoPreco($j['ultima']);
        $dias = $j['ultima'] ? (int)floor((time() - strtotime($j['ultima'])) / 86400) : null; ?>
        <tr>
          <td style="width:50px;"><img class="adm-capa" src="../<?= sanitize(capaJogo($j['imagem_capa'])) ?>" alt=""></td>
          <td><strong><?= sanitize($j['nome']) ?></strong></td>
          <td><?= (int)$j['qtd_precos'] ?></td>
          <td>
            <?= $j['ultima'] ? sanitize($idade['texto']) . ' <span class="adm-mini">(há ' . $dias . ' dia' . ($dias === 1 ? '' : 's') . ')</span>' : '<span class="adm-mini">Nunca atualizado (sem preços)</span>' ?>
          </td>
          <td>
            <a href="precos.php" class="btn-link aviso">Preços</a> ·
            <a href="../jogo.php?id=<?= (int)$j['id_jogo'] ?>" target="_blank" rel="noopener" class="btn-link">Ver</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
