<?php
/**
 * Página Detalhada do Jogo (Dinâmica) - LootPrice
 * Substitui as 10 páginas HTML estáticas anteriores.
 * Requisitos: RF04, RF05, RF06, RF07, RF09, RN01, RN03, RN06, RN11, RN12
 */
require_once __DIR__ . '/includes/functions.php';

$idJogo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idJogo <= 0) {
    header('Location: index.php');
    exit;
}

$jogo = getGameById($idJogo);
if (!$jogo) {
    setFlash('error', 'Jogo não encontrado no catálogo.');
    header('Location: index.php');
    exit;
}

// Registra a visita (alimenta popularidade e recomendações)
logGameAccess($idJogo, 'visita');

$pageTitle = $jogo['nome'] . ' - Comparador de Preços LootPrice';
$basePath = '';

// Dados complementares do banco de dados
$precos = getGamePrices($idJogo);
$categorias = getGameCategories($idJogo);
$plataformas = getGamePlatforms($idJogo);
$assinaturas = getGameSubscriptions($idJogo);

// Identifica a loja com o menor preço (RN01, RF06)
$menorPrecoValor = null;
$menorPrecoId = null;
if (!empty($precos)) {
    $menorPrecoValor = $precos[0]['valor'];
    $menorPrecoId = $precos[0]['id_preco'];

    // Empate no menor preço (inclusive jogo grátis em 2+ lojas): nenhuma loja leva o selo "Menor Preço"
    $empatadas = 0;
    foreach ($precos as $p) {
        if (round((float)$p['valor'], 2) === round((float)$menorPrecoValor, 2)) $empatadas++;
    }
    if ($empatadas >= 2) $menorPrecoId = null;
}

// Verifica se está no Game Pass
$noGamePass = false;
foreach ($assinaturas as $ass) {
    if (stripos($ass['nome'], 'Game Pass') !== false) {
        $noGamePass = true;
        break;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Estilos da página de jogo -->
<link rel="stylesheet" href="sites/jogos/css_jogos/jogos.css">
<style>
  .game-details-wrapper {
    max-width: 1050px;
    margin: 30px auto;
    padding: 0 20px;
  }
  .best-deal-card {
    border: 2px solid var(--success) !important;
    background: var(--success-soft) !important;
    box-shadow: 0 0 15px var(--success-soft) !important;
    position: relative;
  }
  .badge-best-deal {
    position: absolute;
    top: -11px;
    right: 14px;
    background: var(--success);
    color: #fff;
    font-weight: 800;
    font-size: 11px;
    padding: 2px 10px;
    border-radius: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.4);
  }
  .subscription-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--success-soft);
    border: 1px solid var(--success);
    padding: 12px 18px;
    border-radius: 12px;
    margin: 20px 0;
  }
  .category-pill {
    background: var(--accent-soft);
    border: 1px solid var(--accent);
    color: var(--text-color);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
  }
  .category-pill:hover {
    background: var(--accent);
    color: #fff;
  }
  .platform-badge {
    background: var(--surface-2);
    border: 1px solid var(--border);
    color: var(--text-color);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .btn-buy-direct {
    background: var(--accent);
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
  }
  .btn-buy-direct:hover {
    filter: brightness(1.15);
    transform: translateY(-2px);
  }
</style>

<div class="game-details-wrapper">
  
  <header style="margin-bottom: 25px;">
    <a href="catalogo.php" class="btn-back">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
      Voltar ao catálogo
    </a>
    <h1 style="text-align: left; margin: 0; font-size: 32px;"><?= sanitize($jogo['nome']) ?></h1>

    <!-- Categorias do Jogo -->
    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;">
      <?php foreach ($categorias as $c): ?>
        <a href="index.php?categoria=<?= $c['id_categoria'] ?>" class="category-pill">
          <?= sanitize($c['nome']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </header>

  <!-- Conteúdo Principal -->
  <main class="game-container" style="margin-left: 0; padding-left: 0; width: 100%;">
    
    <!-- Mídia e Data -->
    <div class="game-media">
      <img src="<?= sanitize($jogo['imagem_capa']) ?>" 
           alt="Capa de <?= sanitize($jogo['nome']) ?>" 
           class="game-main-img" 
           style="margin-left: 0; width: 220px; height: 300px; object-fit: cover;">
      
      <?php if (!empty($jogo['data_lancamento'])): ?>
        <p class="release-date">
          Lançamento: <?= date('d/m/Y', strtotime($jogo['data_lancamento'])) ?>
        </p>
      <?php endif; ?>

      <!-- Avaliação Metacritic Oficial (RN12) -->
      <?php if (!empty($jogo['nota_metacritic'])): ?>
        <div style="display: flex; align-items: center; gap: 10px; background: var(--surface); border: 1px solid var(--border); padding: 8px 14px; border-radius: 10px; margin-top: 6px; width: 100%; box-sizing: border-box; justify-content: center;">
          <img src="fotos/extras/metacritic-icon.png" alt="Metacritic" style="height: 26px; width: 26px;">
          <span style="font-size: 13px; font-weight: 600;">Metascore:</span>
          <span style="background: <?= getMetacriticColor($jogo['nota_metacritic']) ?>; color: #fff; font-weight: 800; font-size: 14px; padding: 2px 8px; border-radius: 6px;">
            <?= (int)$jogo['nota_metacritic'] ?>
          </span>
        </div>
      <?php endif; ?>
    </div>

    <!-- Informações, Plataformas e Comparador de Preços (RF04, RF05, RF06, RF07) -->
    <section class="game-info" style="flex: 1;">
      
      <!-- Sinopse -->
      <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 18px;">
        <h3 style="margin-top: 0; color: var(--heading); font-size: 16px;">Sobre o Jogo</h3>
        <p class="sinopse" style="font-size: 15px; margin: 0; color: var(--text-color);">
          <?= nl2br(sanitize($jogo['descricao'])) ?>
        </p>
      </div>

      <!-- Disponibilidade em Serviços de Assinatura (RF07) -->
      <?php if (!empty($assinaturas)): ?>
        <div class="subscription-banner">
          <img src="fotos/extras/xbox.png" alt="Assinatura" class="logo-dark" style="width: 32px; height: 32px; object-fit: contain;">
          <img src="fotos/extras/darkxbox.png" alt="Assinatura" class="logo-light" style="width: 32px; height: 32px; object-fit: contain;">
          <div>
            <div style="font-weight: 800; color: var(--success); font-size: 14px;">Incluso em Catálogo de Assinatura!</div>
            <div style="font-size: 13px; color: var(--text-color);">
              Este jogo está disponível sem custo adicional no <strong><?= implode(', ', array_map(fn($a) => sanitize($a['nome']), $assinaturas)) ?></strong>.
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Plataformas Suportadas (RF04) -->
      <div style="margin-top: 20px;">
        <h4 style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: var(--text-muted);">Plataformas Disponíveis:</h4>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
          <?php foreach ($plataformas as $plat): ?>
            <span class="platform-badge">
              <?= sanitize($plat['nome']) ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Comparador de Preços em Tempo Real entre Lojas Digitais (RF05, RF06, RN01, RF09) -->
      <div style="margin-top: 25px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
          <h3 style="margin: 0; color: var(--title-color); font-size: 18px;">Comparador de Preços por Loja</h3>
          <span style="font-size: 12px; color: var(--text-muted);">Atualizado automaticamente</span>
        </div>

        <?php if (empty($precos)): ?>
          <div style="padding: 20px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; text-align: center; color: var(--text-muted);">
            Nenhum preço cadastrado para este jogo no momento.
          </div>
        <?php else: ?>
          <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($precos as $idx => $p): 
              $isBestDeal = ($p['id_preco'] === $menorPrecoId);
              $temDesconto = (!empty($p['valor_original']) && $p['valor_original'] > $p['valor'] && $p['valor'] > 0);
              $porcentagem = $p['porcentagem_desconto'] ?: ($temDesconto ? round((1 - ($p['valor'] / $p['valor_original'])) * 100) : 0);
            ?>
              <div class="platform-card <?= $isBestDeal ? 'best-deal-card' : '' ?>" style="width: 100%; box-sizing: border-box; justify-content: space-between; padding: 14px 18px;">
                
                <?php if ($isBestDeal): ?>
                  <span class="badge-best-deal">⭐ Menor Preço</span>
                <?php endif; ?>

                <div style="display: flex; align-items: center; gap: 14px;">
                  <?php
                    $logoEscuro = $p['loja_logo'] ?: 'fotos/extras/steam.png';
                    $logoClaro = preg_replace('#([^/]+)$#', 'dark$1', $logoEscuro);
                    if (!is_file(__DIR__ . '/' . $logoClaro)) { $logoClaro = $logoEscuro; $logoFiltro = ' logo-invert'; } else { $logoFiltro = ''; }
                  ?>
                  <img src="<?= sanitize($logoEscuro) ?>" alt="<?= sanitize($p['loja_nome']) ?>" class="platform-img logo-dark" style="width: 44px; height: 44px;">
                  <img src="<?= sanitize($logoClaro) ?>" alt="<?= sanitize($p['loja_nome']) ?>" class="platform-img logo-light<?= $logoFiltro ?>" style="width: 44px; height: 44px;">
                  <div>
                    <div class="platform-name" style="font-size: 16px;"><?= sanitize($p['loja_nome']) ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);">Plataforma: <?= sanitize($p['plataforma_nome']) ?></div>
                  </div>
                </div>

                <div style="display: flex; align-items: center; gap: 20px;">
                  <!-- Exibição de Preço com Desconto (RN03) -->
                  <div style="text-align: right;">
                    <?php if ($temDesconto): ?>
                      <div class="platform-price" style="margin: 0;">
                        <span class="old-price">R$ <?= number_format($p['valor_original'], 2, ',', '.') ?></span>
                        <span class="new-price"><?= formatPrice($p['valor']) ?></span>
                        <span class="discount" style="background:#ff4757; color:#fff; font-size:11px; padding:2px 6px; border-radius:4px;">-<?= $porcentagem ?>%</span>
                      </div>
                    <?php else: ?>
                      <div class="platform-price" style="margin: 0;">
                        <span class="normal-price" style="color: <?= $isBestDeal ? 'var(--success)' : 'var(--highlight)' ?>;"><?= formatPrice($p['valor']) ?></span>
                      </div>
                    <?php endif; ?>
                  </div>

                  <!-- Botão de Redirecionamento Direto para Compra (RF09, RN06) -->
                  <?php if (!empty($p['link_compra'])): ?>
                    <a href="<?= sanitize($p['link_compra']) ?>" target="_blank" rel="noopener noreferrer" class="btn-buy-direct" title="Ir para loja <?= sanitize($p['loja_nome']) ?>">
                      Ir à loja ➔
                    </a>
                  <?php endif; ?>
                </div>

              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>

    </section>

  </main>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
