<?php
/**
 * Painel Administrativo - Dashboard
 * LootPrice (RN10)
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin('../login.php');

$pdo = getDB();
$totalJogos = 0;
$totalPrecos = 0;
$totalUsuarios = 0;
$totalLojas = 0;

if ($pdo) {
    $totalJogos = (int)$pdo->query("SELECT COUNT(*) FROM jogos")->fetchColumn();
    $totalPrecos = (int)$pdo->query("SELECT COUNT(*) FROM precos")->fetchColumn();
    $totalUsuarios = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $totalLojas = (int)$pdo->query("SELECT COUNT(*) FROM lojas")->fetchColumn();
    
    // Últimos jogos atualizados
    $ultimosJogos = $pdo->query("SELECT * FROM jogos ORDER BY id_jogo DESC LIMIT 5")->fetchAll();
}

$pageTitle = 'Painel Administrativo - LootPrice';
$basePath = '../';

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1100px; margin: 30px auto; padding: 0 20px;">
  
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
      <h1 style="text-align: left; margin: 0; font-size: 26px;">⚙️ Painel de Administração</h1>
      <p style="color: var(--search-placeholder); margin: 5px 0 0 0; font-size: 14px;">
        Gerenciamento de banco de dados, catálogo de jogos, cotações e usuários.
      </p>
    </div>

    <div style="display: flex; gap: 10px;">
      <a href="jogo_form.php" class="btn-login" style="text-decoration: none; background: linear-gradient(90deg, #5ee7df, #b6ffdd); color: #111;">
        + Novo Jogo
      </a>
      <a href="atualizar_precos.php" class="btn-login" style="text-decoration: none;">
        🔄 Atualizar Preços (APIs)
      </a>
    </div>
  </div>

  <!-- Cards de Estatísticas -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 35px;">
    
    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; border-left: 4px solid #8e2de2;">
      <div style="font-size: 12px; color: var(--search-placeholder); text-transform: uppercase; font-weight: 700;">Jogos Cadastrados</div>
      <div style="font-size: 28px; font-weight: 800; color: #fff; margin-top: 6px;"><?= $totalJogos ?></div>
      <a href="jogos.php" style="font-size: 12px; color: #a78bfa; text-decoration: none; display: inline-block; margin-top: 8px;">Gerenciar jogos →</a>
    </div>

    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; border-left: 4px solid #5ee7df;">
      <div style="font-size: 12px; color: var(--search-placeholder); text-transform: uppercase; font-weight: 700;">Preços Registrados</div>
      <div style="font-size: 28px; font-weight: 800; color: #fff; margin-top: 6px;"><?= $totalPrecos ?></div>
      <a href="precos.php" style="font-size: 12px; color: #5ee7df; text-decoration: none; display: inline-block; margin-top: 8px;">Ver cotações →</a>
    </div>

    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; border-left: 4px solid #ffc857;">
      <div style="font-size: 12px; color: var(--search-placeholder); text-transform: uppercase; font-weight: 700;">Usuários Cadastrados</div>
      <div style="font-size: 28px; font-weight: 800; color: #fff; margin-top: 6px;"><?= $totalUsuarios ?></div>
      <a href="usuarios.php" style="font-size: 12px; color: #ffc857; text-decoration: none; display: inline-block; margin-top: 8px;">Gerenciar usuários →</a>
    </div>

    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; border-left: 4px solid #2ecc71;">
      <div style="font-size: 12px; color: var(--search-placeholder); text-transform: uppercase; font-weight: 700;">Lojas Parceiras</div>
      <div style="font-size: 28px; font-weight: 800; color: #fff; margin-top: 6px;"><?= $totalLojas ?></div>
      <span style="font-size: 12px; color: #2ecc71; display: inline-block; margin-top: 8px;">Steam, Epic, Xbox...</span>
    </div>

  </div>

  <!-- Ações Rápidas -->
  <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    
    <!-- Lista dos últimos jogos cadastrados -->
    <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 20px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin: 0; font-size: 16px;">🎮 Jogos Recentes</h3>
        <a href="jogos.php" style="color: #a78bfa; font-size: 13px; text-decoration: none;">Ver todos</a>
      </div>

      <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <thead>
          <tr style="text-align: left; border-bottom: 1px solid rgba(255,255,255,0.08); color: var(--search-placeholder);">
            <th style="padding: 10px 8px;">Jogo</th>
            <th style="padding: 10px 8px;">Metacritic</th>
            <th style="padding: 10px 8px;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($ultimosJogos)): ?>
            <tr><td colspan="3" style="padding: 15px; text-align: center; color: var(--search-placeholder);">Nenhum jogo cadastrado.</td></tr>
          <?php else: ?>
            <?php foreach ($ultimosJogos as $uj): ?>
              <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
                <td style="padding: 12px 8px; display: flex; align-items: center; gap: 10px;">
                  <img src="../<?= sanitize($uj['imagem_capa']) ?>" style="width: 35px; height: 45px; object-fit: cover; border-radius: 4px;">
                  <strong><?= sanitize($uj['nome']) ?></strong>
                </td>
                <td style="padding: 12px 8px;">
                  <span style="background: <?= getMetacriticColor($uj['nota_metacritic']) ?>; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 12px;">
                    <?= (int)$uj['nota_metacritic'] ?>
                  </span>
                </td>
                <td style="padding: 12px 8px;">
                  <a href="jogo_form.php?id=<?= $uj['id_jogo'] ?>" style="color: #ffc857; text-decoration: none; margin-right: 10px;">Editar</a>
                  <a href="../jogo.php?id=<?= $uj['id_jogo'] ?>" target="_blank" style="color: #5ee7df; text-decoration: none;">Ver</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Links de navegação admin -->
    <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 20px;">
      <h3 style="margin-top: 0; font-size: 16px; margin-bottom: 15px;">🧭 Atalhos de Gestão</h3>
      <div style="display: flex; flex-direction: column; gap: 10px;">
        <a href="jogos.php" style="background: rgba(255,255,255,0.04); color: var(--text-color); padding: 12px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
          <span>📦 Catálogo de Jogos</span>
          <span>→</span>
        </a>
        <a href="precos.php" style="background: rgba(255,255,255,0.04); color: var(--text-color); padding: 12px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
          <span>💰 Gerenciar Preços e Lojas</span>
          <span>→</span>
        </a>
        <a href="usuarios.php" style="background: rgba(255,255,255,0.04); color: var(--text-color); padding: 12px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
          <span>👥 Gerenciar Usuários</span>
          <span>→</span>
        </a>
        <a href="../index.php" style="background: rgba(255,255,255,0.04); color: var(--text-color); padding: 12px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
          <span>🌐 Visualizar Site Público</span>
          <span>→</span>
        </a>
      </div>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
