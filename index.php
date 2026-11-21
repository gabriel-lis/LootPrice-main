<?php
/**
 * Página Inicial - LootPrice
 * 1) Destaques e recomendações  2) Descontos  3) Jogos  4) Botão "Catálogo completo"
 * Os filtros (plataforma, categoria, preço) ficam em catalogo.php.
 */
require_once __DIR__ . '/includes/functions.php';

// Links antigos com busca/filtros (index.php?q=...) seguem para o catálogo
if (isset($_GET['q']) || isset($_GET['categoria']) || isset($_GET['plataforma']) || isset($_GET['preco'])) {
    // Só repassa valores simples (ignora ?q[]=x e parecidos)
    $repassar = array_filter($_GET, 'is_string');
    header('Location: catalogo.php?' . http_build_query($repassar));
    exit;
}

$pageTitle = 'LootPrice - Comparador de Preços de Jogos';
$basePath = '';

$recomendados = getRecommendedGames(6);
$descontos    = getDiscountedGames(null, 6);
$jogos        = attachBestOffer(getGamesCatalog());
usort($jogos, fn($a, $b) => (float)($b['nota_metacritic'] ?? 0) <=> (float)($a['nota_metacritic'] ?? 0));
// "Jogos": os melhores do catálogo, sem repetir o que já aparece em Destaques (se sobrar o bastante)
$jaMostrados = array_column($recomendados, 'id_jogo');
$restantes = array_values(array_filter($jogos, fn($j) => !in_array($j['id_jogo'], $jaMostrados)));
$jogos = array_slice(count($restantes) >= 4 ? $restantes : $jogos, 0, 8);

$logado = isLoggedIn();

require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= assetUrl('css_index/cards.css') ?>">
<link rel="stylesheet" href="<?= assetUrl('css_index/home.css') ?>">

<div class="home-wrap">

  <!-- 1) Destaques e recomendações -->
  <section class="home-section" aria-labelledby="t-destaques">
    <div class="home-section-head">
      <div>
        <h2 class="home-section-title" id="t-destaques">Destaques e recomendações</h2>
        <p class="home-section-sub">
          <?= $logado
            ? 'Em alta agora e escolhidos pelo que você pesquisa e acessa.'
            : 'Os jogos em alta agora. <a href="login.php" style="color: var(--accent-text);">Entre na sua conta</a> para ver sugestões feitas para você.' ?>
        </p>
      </div>
    </div>

    <?php if (empty($recomendados)): ?>
      <div class="home-empty">Ainda não há jogos cadastrados.</div>
    <?php else: ?>
      <div class="poster-grid">
        <?php foreach ($recomendados as $j) renderPosterCard($j, $j['selo'] ?? null); ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- 2) Descontos -->
  <?php if (!empty($descontos)): ?>
  <section class="home-section" aria-labelledby="t-descontos">
    <div class="home-section-head">
      <div>
        <h2 class="home-section-title" id="t-descontos">Descontos</h2>
        <p class="home-section-sub">Os preços mais baixos do momento.</p>
      </div>
      <a class="home-section-link" href="descontos.php">Ver todos os descontos ➔</a>
    </div>

    <div class="price-chips" aria-label="Escolher faixa de preço">
      <a class="price-chip" href="descontos.php?ate=10">Até R$ 10</a>
      <a class="price-chip" href="descontos.php?ate=50">Até R$ 50</a>
      <a class="price-chip" href="descontos.php?ate=100">Até R$ 100</a>
    </div>

    <div class="poster-grid">
      <?php foreach ($descontos as $j) renderPosterCard($j); ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- 3) Jogos -->
  <?php if (!empty($jogos)): ?>
  <section class="home-section" aria-labelledby="t-jogos">
    <div class="home-section-head">
      <div>
        <h2 class="home-section-title" id="t-jogos">Jogos</h2>
        <p class="home-section-sub">Os mais bem avaliados do catálogo.</p>
      </div>
    </div>
    <div class="poster-grid">
      <?php foreach ($jogos as $j) renderPosterCard($j); ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- 4) Catálogo completo -->
  <div class="home-cta">
    <a class="btn-catalogo" href="catalogo.php">Catálogo completo</a>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
