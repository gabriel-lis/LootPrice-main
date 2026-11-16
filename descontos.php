<?php
/**
 * Descontos - LootPrice
 * Jogos com preço reduzido, filtrados por faixa de preço (até R$ 10, R$ 50 ou R$ 100).
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Descontos - LootPrice';
$basePath = '';

$faixas = [10 => 'Até R$ 10', 50 => 'Até R$ 50', 100 => 'Até R$ 100'];
$ate = isset($_GET['ate']) ? (int)$_GET['ate'] : 0;
if (!isset($faixas[$ate])) $ate = 0; // 0 = todas as faixas

$jogos = getDiscountedGames($ate ?: null);

require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= assetUrl('css_index/cards.css') ?>">
<link rel="stylesheet" href="<?= assetUrl('css_index/home.css') ?>">

<div class="home-wrap">
  <div class="home-section-head">
    <div>
      <h1 class="home-section-title">Descontos</h1>
      <p class="home-section-sub">Escolha até quanto quer pagar.</p>
    </div>
    <a class="home-section-link" href="index.php">← Voltar ao início</a>
  </div>

  <div class="price-chips" aria-label="Faixa de preço">
    <a class="price-chip <?= $ate === 0 ? 'active' : '' ?>" href="descontos.php">Todos</a>
    <?php foreach ($faixas as $valor => $rotulo): ?>
      <a class="price-chip <?= $ate === $valor ? 'active' : '' ?>" href="descontos.php?ate=<?= $valor ?>"><?= $rotulo ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($jogos)): ?>
    <div class="home-empty">
      Nenhum jogo com desconto <?= $ate ? 'nessa faixa de preço' : 'no momento' ?>.
      <?php if ($ate): ?><br><a href="descontos.php" style="color: var(--accent-text);">Ver todas as faixas</a><?php endif; ?>
    </div>
  <?php else: ?>
    <p class="home-section-sub" style="margin-bottom:14px;"><?= count($jogos) ?> <?= count($jogos) === 1 ? 'jogo' : 'jogos' ?></p>
    <div class="poster-grid">
      <?php foreach ($jogos as $j) renderPosterCard($j); ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
