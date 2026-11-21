<?php
/**
 * Painel Administrativo - Dashboard
 * LootPrice (RN10)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';
$pdo = adminIniciar();

$totalJogos = (int)$pdo->query("SELECT COUNT(*) FROM jogos")->fetchColumn();
$totalPrecos = (int)$pdo->query("SELECT COUNT(*) FROM precos")->fetchColumn();
$totalUsuarios = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$totalLojas = (int)$pdo->query("SELECT COUNT(*) FROM lojas")->fetchColumn();
$ultimosJogos = $pdo->query("SELECT * FROM jogos ORDER BY id_jogo DESC LIMIT 5")->fetchAll();

// Saúde da coleta: data do preço mais recente e último resultado do robô
$ultimoPreco = $pdo->query("SELECT MAX(data_atualizacao) FROM precos")->fetchColumn() ?: null;
$idade = idadeDoPreco($ultimoPreco);
$arqResumo = __DIR__ . '/../robo/logs/ultima_execucao.json';
$ultimaColeta = is_file($arqResumo) ? json_decode(file_get_contents($arqResumo), true) : null;

$pageTitle = 'Painel Administrativo - LootPrice';
$basePath = '../';
$cssAdmin = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="adm-wrap">

  <div class="adm-head">
    <div>
      <h1 class="adm-title" style="margin-top:0;">⚙️ Painel de Administração</h1>
      <p class="adm-sub">Gerenciamento de banco de dados, catálogo de jogos, cotações e usuários.</p>
    </div>
    <div class="adm-actions">
      <a href="jogo_form.php" class="btn-adm ok">+ Novo Jogo</a>
      <a href="atualizar_precos.php" class="btn-adm">🔄 Atualizar Preços</a>
    </div>
  </div>

  <?php if ($ultimaColeta && !empty($ultimaColeta['erro'])): ?>
    <div class="adm-aviso erro" role="alert">
      <strong>A última coleta de preços falhou</strong> (<?= sanitize($ultimaColeta['fim'] ?? '') ?>): <?= sanitize($ultimaColeta['erro']) ?>
      <a href="atualizar_precos.php" style="color:inherit; font-weight:700;">Ver detalhes</a>
    </div>
  <?php elseif ($ultimaColeta && !empty($ultimaColeta['lojas_sem_dados'])): ?>
    <div class="adm-aviso alerta" role="alert">
      <strong>Atenção:</strong> a última coleta não trouxe preços de <?= sanitize(implode(', ', $ultimaColeta['lojas_sem_dados'])) ?>.
      <a href="atualizar_precos.php" style="color:inherit; font-weight:700;">Ver detalhes</a>
    </div>
  <?php elseif ($totalPrecos > 0 && $idade['velho']): ?>
    <div class="adm-aviso alerta" role="alert">
      <strong>Os preços estão desatualizados.</strong> O mais recente é de <?= sanitize($idade['texto']) ?>.
      <a href="atualizar_precos.php" style="color:inherit; font-weight:700;">Atualizar agora</a>
    </div>
  <?php endif; ?>

  <!-- Cards de Estatísticas -->
  <div class="adm-stats">
    <div class="adm-stat c1">
      <div class="rotulo">Jogos Cadastrados</div>
      <div class="numero"><?= $totalJogos ?></div>
      <a href="jogos.php">Gerenciar jogos →</a>
    </div>
    <div class="adm-stat c2">
      <div class="rotulo">Preços Registrados</div>
      <div class="numero"><?= $totalPrecos ?></div>
      <a href="precos.php">Ver cotações →</a>
    </div>
    <div class="adm-stat c3">
      <div class="rotulo">Usuários Cadastrados</div>
      <div class="numero"><?= $totalUsuarios ?></div>
      <a href="usuarios.php">Gerenciar usuários →</a>
    </div>
    <div class="adm-stat c4">
      <div class="rotulo">Lojas Parceiras</div>
      <div class="numero"><?= $totalLojas ?></div>
      <a href="cadastros.php">Lojas, categorias e assinaturas →</a>
    </div>
  </div>

  <div class="adm-2col">

    <!-- Lista dos últimos jogos cadastrados -->
    <div class="adm-card">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h3 style="margin:0;">🎮 Jogos Recentes</h3>
        <a href="jogos.php" class="btn-link">Ver todos</a>
      </div>

      <div class="adm-tabela-wrap" style="border:none;">
        <table class="adm-tabela">
          <thead>
            <tr><th>Jogo</th><th>Metacritic</th><th>Ações</th></tr>
          </thead>
          <tbody>
            <?php if (empty($ultimosJogos)): ?>
              <tr><td colspan="3" class="adm-vazio">Nenhum jogo cadastrado.</td></tr>
            <?php else: foreach ($ultimosJogos as $uj): ?>
              <tr>
                <td>
                  <span style="display:flex; align-items:center; gap:10px;">
                    <img class="adm-capa" src="../<?= sanitize(capaJogo($uj['imagem_capa'])) ?>" alt="">
                    <strong><?= sanitize($uj['nome']) ?></strong>
                  </span>
                </td>
                <td><?= adminNotaMeta($uj['nota_metacritic']) ?></td>
                <td>
                  <a href="jogo_form.php?id=<?= (int)$uj['id_jogo'] ?>" class="btn-link aviso">Editar</a> ·
                  <a href="../jogo.php?id=<?= (int)$uj['id_jogo'] ?>" target="_blank" rel="noopener" class="btn-link">Ver</a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Links de navegação admin -->
    <div class="adm-card">
      <h3>🧭 Atalhos de Gestão</h3>
      <div class="adm-atalhos">
        <a href="jogos.php"><span>📦 Catálogo de Jogos</span><span>→</span></a>
        <a href="precos.php"><span>💰 Preços e Ofertas</span><span>→</span></a>
        <a href="cadastros.php"><span>🏷️ Lojas, Categorias e Assinaturas</span><span>→</span></a>
        <a href="usuarios.php"><span>👥 Usuários</span><span>→</span></a>
        <a href="../index.php"><span>🌐 Visualizar Site Público</span><span>→</span></a>
      </div>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
