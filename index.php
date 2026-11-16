<?php
/**
 * Catálogo Principal de Jogos - LootPrice
 * Layout inspirado no HowLongToBeat (Cards Horizontais em Grid de 2 Colunas)
 * com nota do Metacritic destacada no topo com fonte preta (RN12),
 * e tabela enxuta (Menor Preço e Game Pass).
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'LootPrice - Comparador de Preços de Jogos';
$basePath = '';

// Parâmetros de busca e filtros (RF01 e RF03)
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : null;
$categoryId = !empty($_GET['categoria']) ? (int)$_GET['categoria'] : null;
$platformId = !empty($_GET['plataforma']) ? (int)$_GET['plataforma'] : null;
$priceRange = !empty($_GET['preco']) ? trim($_GET['preco']) : null;

$jogos = getGamesCatalog($searchTerm, $categoryId, $platformId, $priceRange);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Estilos dedicados para o layout HowLongToBeat com Metacritic visível e fonte preta -->
<style>
  :root {
    --hltb-bg: #14141e;
    --hltb-card-bg: #1a1a26;
    --hltb-card-border: rgba(255, 255, 255, 0.07);
    --hltb-title-color: #f2b35b;
    --hltb-blue: #2b78c5;
    --hltb-blue-hover: #358ee6;
    --hltb-purple: #6c5ce7;
    --hltb-green: #2ecc71;
    --hltb-dark-block: #1c2e42;
  }

  .hltb-main-container {
    max-width: 1200px;
    margin: 20px auto 60px auto;
    padding: 0 16px;
  }

  /* Barra de Filtros Compacta e Moderna */
  .hltb-filters-bar {
    background: var(--hltb-card-bg);
    border: 1px solid var(--hltb-card-border);
    border-radius: 8px;
    padding: 12px 18px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
  }

  .hltb-filter-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }

  .hltb-select {
    background: #0f0f18;
    color: #e0e0e0;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 6px;
    padding: 7px 12px;
    font-size: 13px;
    cursor: pointer;
    outline: none;
    transition: border-color 0.2s;
  }
  .hltb-select:focus {
    border-color: var(--hltb-title-color);
  }

  /* Cabeçalho da Seção (Popular Games) */
  .hltb-section-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 16px;
  }

  .hltb-section-title {
    font-size: 24px;
    font-weight: 800;
    color: var(--hltb-title-color);
    letter-spacing: -0.5px;
    margin: 0;
  }

  .hltb-results-count {
    font-size: 13px;
    color: #8e9aa8;
  }

  /* Grid Principal: 2 Colunas lado a lado no Desktop */
  .hltb-games-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
  }

  @media (max-width: 860px) {
    .hltb-games-grid {
      grid-template-columns: 1fr;
    }
  }

  /* Card Individual Horizontal */
  .hltb-card {
    background: var(--hltb-card-bg);
    border: 1px solid var(--hltb-card-border);
    border-radius: 6px;
    padding: 14px;
    display: flex;
    gap: 14px;
    align-items: stretch;
    transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    min-height: 140px;
    box-sizing: border-box;
  }

  .hltb-card:hover {
    border-color: rgba(242, 179, 91, 0.4);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
  }

  /* Capa do Jogo na Esquerda (Sem nada cobrindo a arte) */
  .hltb-cover-wrap {
    flex-shrink: 0;
    width: 90px;
    display: block;
    border-radius: 4px;
    overflow: hidden;
    position: relative;
    background: #0b0b12;
  }

  .hltb-cover-img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    display: block;
    transition: transform 0.25s ease;
  }

  .hltb-card:hover .hltb-cover-img {
    transform: scale(1.06);
  }

  /* Corpo com Título e Estatísticas na Direita */
  .hltb-body {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    flex-grow: 1;
    min-width: 0;
  }

  /* Linha do Título com Badge Metacritic na Direita */
  .hltb-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
  }

  .hltb-title {
    font-size: 15px;
    font-weight: 700;
    color: #ffffff;
    text-decoration: none;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.25;
    transition: color 0.15s;
    flex-grow: 1;
  }

  .hltb-title:hover {
    color: var(--hltb-title-color);
  }

  /* Selo do Metacritic destacado ao lado do título com apenas o número (fonte preta) */
  .hltb-header-meta {
    flex-shrink: 0;
    min-width: 28px;
    height: 24px;
    padding: 0 6px;
    border-radius: 4px;
    font-weight: 900;
    font-size: 13px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.5);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #111111 !important;
  }

  /* Tabela de Estatísticas com Blocos Coloridos */
  .hltb-stats-table {
    display: flex;
    flex-direction: column;
    gap: 3px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 4px;
    overflow: hidden;
  }

  .hltb-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 28px;
    background: rgba(255, 255, 255, 0.02);
  }

  .hltb-row-label {
    font-size: 12px;
    font-weight: 500;
    color: #cbd5e1;
    padding-left: 8px;
    white-space: nowrap;
  }

  /* Blocos Coloridos da Direita */
  .hltb-row-val {
    width: 135px;
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    padding: 5px 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    height: 100%;
    box-sizing: border-box;
  }

  .hltb-val-blue {
    background: var(--hltb-blue);
  }

  .hltb-val-purple {
    background: var(--hltb-purple);
  }

  .hltb-val-darkblue {
    background: var(--hltb-dark-block);
    color: #94a3b8;
  }

  /* Rodapé do Card com Link */
  .hltb-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-top: 6px;
  }

  .hltb-action-link {
    font-size: 11px;
    font-weight: 600;
    color: #8e9aa8;
    text-decoration: none;
    letter-spacing: 0.3px;
    transition: color 0.15s;
  }

  .hltb-action-link:hover {
    color: var(--hltb-title-color);
    text-decoration: underline;
  }
</style>

<div class="hltb-main-container">
  
  <!-- Barra de Filtros (RF01 e RF03) -->
  <form method="GET" action="index.php" class="hltb-filters-bar">
    <div class="hltb-filter-group">
      <span style="font-weight: 700; font-size: 13px; color: #cbd5e1;">Filtrar jogos:</span>

      <?php if (!empty($searchTerm)): ?>
        <input type="hidden" name="q" value="<?= sanitize($searchTerm) ?>">
      <?php endif; ?>

      <!-- Filtro Plataforma -->
      <select name="plataforma" onchange="this.form.submit()" class="hltb-select" aria-label="Plataforma">
        <option value="">Todas as Plataformas</option>
        <?php foreach ($platforms as $p): ?>
          <option value="<?= $p['id_plataforma'] ?>" <?= ($platformId === (int)$p['id_plataforma']) ? 'selected' : '' ?>>
            <?= sanitize($p['nome']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Filtro Categoria -->
      <select name="categoria" onchange="this.form.submit()" class="hltb-select" aria-label="Categoria">
        <option value="">Todas as Categorias</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id_categoria'] ?>" <?= ($categoryId === (int)$c['id_categoria']) ? 'selected' : '' ?>>
            <?= sanitize($c['nome']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Filtro Preço -->
      <select name="preco" onchange="this.form.submit()" class="hltb-select" aria-label="Faixa de Preço">
        <option value="">Qualquer Preço</option>
        <option value="free" <?= ($priceRange === 'free') ? 'selected' : '' ?>>Apenas Grátis</option>
        <option value="under50" <?= ($priceRange === 'under50') ? 'selected' : '' ?>>Até R$ 50,00</option>
        <option value="under100" <?= ($priceRange === 'under100') ? 'selected' : '' ?>>Até R$ 100,00</option>
        <option value="over100" <?= ($priceRange === 'over100') ? 'selected' : '' ?>>Acima de R$ 100,00</option>
      </select>
    </div>

    <?php if (!empty($searchTerm) || !empty($categoryId) || !empty($platformId) || !empty($priceRange)): ?>
      <a href="index.php" style="color: #ff6b6b; font-size: 12px; font-weight: 600; text-decoration: none;">
        ✕ Limpar Filtros
      </a>
    <?php endif; ?>
  </form>

  <!-- Cabeçalho da Seção de Jogos -->
  <div class="hltb-section-header">
    <h2 class="hltb-section-title">Popular Games</h2>
    <span class="hltb-results-count">Mostrando <?= count($jogos) ?> jogos disponíveis</span>
  </div>

  <!-- Grid de Jogos (2 Colunas Estilo HowLongToBeat) -->
  <?php if (empty($jogos)): ?>
    <div style="text-align: center; padding: 60px 20px; background: var(--hltb-card-bg); border-radius: 8px;">
      <h3 style="color: #fff; margin-bottom: 8px;">Nenhum jogo encontrado</h3>
      <p style="color: #8e9aa8; font-size: 14px;">Tente redefinir os filtros ou buscar por outro termo.</p>
      <a href="index.php" class="btn-login" style="margin-top: 15px; display: inline-block;">Ver todos os jogos</a>
    </div>
  <?php else: ?>
    
    <div class="hltb-games-grid">
      <?php foreach ($jogos as $j): 
        $hasGamePass = !empty($j['has_game_pass']);
        $score = !empty($j['nota_metacritic']) ? (int)$j['nota_metacritic'] : null;
        $ano = !empty($j['data_lancamento']) ? ' (' . date('Y', strtotime($j['data_lancamento'])) . ')' : '';
        $metaColor = getMetacriticColor($score);
      ?>
        <div class="hltb-card">
          
          <!-- Capa na Esquerda (Sem nada em cima da foto, imagem 100% limpa) -->
          <a href="jogo.php?id=<?= (int)$j['id_jogo'] ?>" class="hltb-cover-wrap" title="<?= sanitize($j['nome']) ?>">
            <img src="<?= sanitize($j['imagem_capa']) ?>" alt="<?= sanitize($j['nome']) ?>" class="hltb-cover-img" loading="lazy">
          </a>

          <!-- Informações e Tabela de Estatísticas na Direita -->
          <div class="hltb-body">
            
            <!-- Título com o Selo do Metacritic em destaque ao lado (Fonte preta) -->
            <div class="hltb-title-row">
              <a href="jogo.php?id=<?= (int)$j['id_jogo'] ?>" class="hltb-title" title="<?= sanitize($j['nome']) ?>">
                <?= sanitize($j['nome']) . $ano ?>
              </a>

              <?php if ($score !== null): ?>
                <div class="hltb-header-meta" style="background: <?= $metaColor ?>;" title="Nota oficial Metacritic: <?= $score ?>">
                  <?= $score ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="hltb-stats-table">
              
              <!-- Linha 1: Menor Preço (RN01) -->
              <div class="hltb-row">
                <span class="hltb-row-label">Menor Preço</span>
                <span class="hltb-row-val hltb-val-blue">
                  <?= formatPrice($j['menor_preco']) ?>
                </span>
              </div>

              <!-- Linha 2: Game Pass (RF07) -->
              <div class="hltb-row">
                <span class="hltb-row-label">Game Pass</span>
                <?php if ($hasGamePass): ?>
                  <span class="hltb-row-val hltb-val-purple">Disponível</span>
                <?php else: ?>
                  <span class="hltb-row-val hltb-val-darkblue">Não incluso</span>
                <?php endif; ?>
              </div>

            </div>

            <!-- Rodapé do Card com Link estilo 'Add to Profile' -->
            <div class="hltb-footer">
              <a href="jogo.php?id=<?= (int)$j['id_jogo'] ?>" class="hltb-action-link">
                Ver Ofertas ➔
              </a>
            </div>

          </div>

        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
