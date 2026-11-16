<?php
/**
 * Gerenciamento de Jogos - Admin
 * LootPrice (RN10)
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin('../login.php');

$pdo = getDB();

// Exclusão de jogo (caso solicitado via POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
    $idExcluir = (int)$_POST['id_jogo'];
    if ($idExcluir > 0) {
        $stmt = $pdo->prepare("DELETE FROM jogos WHERE id_jogo = :id");
        $stmt->execute(['id' => $idExcluir]);
        setFlash('success', 'Jogo excluído com sucesso.');
        header('Location: jogos.php');
        exit;
    }
}

// Lista todos os jogos com contagem de preços cadastrados
$sql = "SELECT j.*, 
               COUNT(DISTINCT p.id_preco) AS qtd_precos,
               GROUP_CONCAT(DISTINCT c.nome SEPARATOR ', ') AS categorias
        FROM jogos j
        LEFT JOIN precos p ON j.id_jogo = p.id_jogo
        LEFT JOIN jogo_categoria jc ON j.id_jogo = jc.id_jogo
        LEFT JOIN categorias c ON jc.id_categoria = c.id_categoria
        GROUP BY j.id_jogo
        ORDER BY j.nome ASC";

$jogos = $pdo ? $pdo->query($sql)->fetchAll() : [];

$pageTitle = 'Gerenciar Jogos - Admin LootPrice';
$basePath = '../';

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1100px; margin: 30px auto; padding: 0 20px;">
  
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
      <a href="index.php" style="color: var(--search-placeholder); text-decoration: none; font-size: 13px;">← Voltar ao Dashboard</a>
      <h1 style="text-align: left; margin: 5px 0 0 0; font-size: 24px;">🎮 Gerenciar Catálogo de Jogos</h1>
    </div>

    <a href="jogo_form.php" class="btn-login" style="text-decoration: none; background: linear-gradient(90deg, #5ee7df, #b6ffdd); color: #111;">
      + Adicionar Novo Jogo
    </a>
  </div>

  <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; overflow-x: auto;">
    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
      <thead>
        <tr style="border-bottom: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02); color: var(--search-placeholder);">
          <th style="padding: 12px 16px;">Capa</th>
          <th style="padding: 12px 16px;">Título</th>
          <th style="padding: 12px 16px;">Categorias</th>
          <th style="padding: 12px 16px;">Metacritic</th>
          <th style="padding: 12px 16px;">Preços</th>
          <th style="padding: 12px 16px; text-align: right;">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($jogos)): ?>
          <tr><td colspan="6" style="padding: 25px; text-align: center; color: var(--search-placeholder);">Nenhum jogo cadastrado.</td></tr>
        <?php else: ?>
          <?php foreach ($jogos as $j): ?>
            <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
              <td style="padding: 10px 16px; width: 50px;">
                <img src="../<?= sanitize($j['imagem_capa']) ?>" alt="" style="width: 40px; height: 50px; object-fit: cover; border-radius: 4px;">
              </td>
              <td style="padding: 10px 16px;">
                <strong style="color: var(--text-color);"><?= sanitize($j['nome']) ?></strong>
              </td>
              <td style="padding: 10px 16px; color: var(--search-placeholder); font-size: 13px;">
                <?= sanitize($j['categorias'] ?? 'Nenhuma') ?>
              </td>
              <td style="padding: 10px 16px;">
                <span style="background: <?= getMetacriticColor($j['nota_metacritic']) ?>; color: #fff; font-weight: 700; font-size: 12px; padding: 2px 7px; border-radius: 4px;">
                  <?= (int)$j['nota_metacritic'] ?>
                </span>
              </td>
              <td style="padding: 10px 16px;">
                <a href="precos.php?id_jogo=<?= $j['id_jogo'] ?>" style="color: #5ee7df; text-decoration: none; font-size: 13px; font-weight: 600;">
                  💰 <?= $j['qtd_precos'] ?> ofertas
                </a>
              </td>
              <td style="padding: 10px 16px; text-align: right;">
                <a href="jogo_form.php?id=<?= $j['id_jogo'] ?>" style="color: #ffc857; text-decoration: none; font-weight: 600; margin-right: 12px;">Editar</a>
                
                <form method="POST" action="jogos.php" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este jogo?');">
                  <input type="hidden" name="acao" value="excluir">
                  <input type="hidden" name="id_jogo" value="<?= $j['id_jogo'] ?>">
                  <button type="submit" style="background: transparent; border: none; color: #ff6b6b; cursor: pointer; font-weight: 600; font-size: 14px;">
                    Excluir
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
