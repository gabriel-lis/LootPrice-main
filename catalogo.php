<?php
/**
 * Catálogo Completo - LootPrice
 * Todos os jogos com os filtros de busca, plataforma, categoria e faixa de preço (RF01, RF02, RF03).
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Catálogo Completo - LootPrice';
$basePath = '';

$precisaFiltros = true; // o header carrega categorias e plataformas só aqui

// lp_get() aceita só texto: ?q[]=x vira vazio em vez de erro 500
$searchTerm = lp_get('q', 100) ?: null;
$categoryId = (int)lp_get('categoria', 10) ?: null;
$platformId = (int)lp_get('plataforma', 10) ?: null;
$priceRange = in_array(lp_get('preco', 10), ['free', 'under50', 'under100', 'over100'], true) ? lp_get('preco', 10) : null;

// Paginação: 24 jogos por página
const POR_PAGINA = 24;
$total = countGamesCatalog($searchTerm, $categoryId, $platformId, $priceRange);
$totalPaginas = max(1, (int)ceil($total / POR_PAGINA));
$pagina = min(max(1, (int)lp_get('pagina', 6)), $totalPaginas);
$jogos = getGamesCatalog($searchTerm, $categoryId, $platformId, $priceRange, POR_PAGINA, ($pagina - 1) * POR_PAGINA);

/** Link para outra página mantendo os filtros. */
function linkPagina(int $p): string {
    $q = array_filter(['q' => lp_get('q', 100), 'categoria' => lp_get('categoria', 10), 'plataforma' => lp_get('plataforma', 10), 'preco' => lp_get('preco', 10)], fn($v) => $v !== '');
    if ($p > 1) $q['pagina'] = $p;
    return 'catalogo.php' . ($q ? '?' . http_build_query($q) : '');
}

// Quem pesquisa um jogo demonstra interesse nele: isso alimenta as recomendações da home
if (!empty($searchTerm) && isLoggedIn()) {
    foreach (array_slice($jogos, 0, 3) as $achado) logGameAccess((int)$achado['id_jogo'], 'busca');
}

require_once __DIR__ . '/includes/header.php';
$temFiltro = !empty($searchTerm) || !empty($categoryId) || !empty($platformId) || !empty($priceRange);
?>
<link rel="stylesheet" href="<?= assetUrl('css_index/cards.css') ?>">
<link rel="stylesheet" href="<?= assetUrl('css_index/home.css') ?>">

<div class="hltb-main-container">

  <!-- Barra de Filtros (RF01 e RF03) -->
  <form method="GET" action="catalogo.php" class="hltb-filters-bar">
    <div class="hltb-filter-group">
      <span style="font-weight: 700; font-size: 13px; color: var(--text-muted);">Filtrar jogos:</span>

      <?php if (!empty($searchTerm)): ?>
        <input type="hidden" name="q" value="<?= sanitize($searchTerm) ?>">
      <?php endif; ?>

      <select name="plataforma" onchange="this.form.submit()" class="hltb-select" aria-label="Plataforma">
        <option value="">Todas as Plataformas</option>
        <?php foreach ($platforms as $p): ?>
          <option value="<?= $p['id_plataforma'] ?>" <?= ($platformId === (int)$p['id_plataforma']) ? 'selected' : '' ?>>
            <?= sanitize($p['nome']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="categoria" onchange="this.form.submit()" class="hltb-select" aria-label="Categoria">
        <option value="">Todas as Categorias</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id_categoria'] ?>" <?= ($categoryId === (int)$c['id_categoria']) ? 'selected' : '' ?>>
            <?= sanitize($c['nome']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="preco" onchange="this.form.submit()" class="hltb-select" aria-label="Faixa de Preço">
        <option value="">Qualquer Preço</option>
        <option value="free" <?= ($priceRange === 'free') ? 'selected' : '' ?>>Apenas Grátis</option>
        <option value="under50" <?= ($priceRange === 'under50') ? 'selected' : '' ?>>Até R$ 50,00</option>
        <option value="under100" <?= ($priceRange === 'under100') ? 'selected' : '' ?>>Até R$ 100,00</option>
        <option value="over100" <?= ($priceRange === 'over100') ? 'selected' : '' ?>>Acima de R$ 100,00</option>
      </select>
    </div>

    <?php if ($temFiltro): ?>
      <a href="catalogo.php" style="color: var(--danger); font-size: 12px; font-weight: 600; text-decoration: none;">✕ Limpar Filtros</a>
    <?php endif; ?>
  </form>

  <div class="hltb-section-header">
    <h2 class="hltb-section-title"><?= !empty($searchTerm) ? 'Resultados para "' . sanitize($searchTerm) . '"' : 'Catálogo completo' ?></h2>
    <span class="hltb-results-count"><?= $total ?> <?= $total === 1 ? 'jogo' : 'jogos' ?></span>
  </div>

  <?php if (empty($jogos)): ?>
    <div style="text-align: center; padding: 60px 20px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);">
      <h3 style="color: var(--heading); margin-bottom: 8px;">Nenhum jogo encontrado</h3>
      <p style="color: var(--text-muted); font-size: 14px;">Tente redefinir os filtros ou buscar por outro termo.</p>
      <a href="catalogo.php" class="btn-login" style="margin-top: 15px; display: inline-block;">Ver todos os jogos</a>
    </div>
  <?php else: ?>
    <div class="hltb-games-grid">
      <?php foreach ($jogos as $j) renderGameRowCard($j); ?>
    </div>

    <?php if ($totalPaginas > 1): ?>
      <nav class="paginacao" aria-label="Páginas do catálogo">
        <?php if ($pagina > 1): ?><a href="<?= sanitize(linkPagina($pagina - 1)) ?>" rel="prev">← Anterior</a><?php endif; ?>
        <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
        <?php if ($pagina < $totalPaginas): ?><a href="<?= sanitize(linkPagina($pagina + 1)) ?>" rel="next">Próxima →</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
