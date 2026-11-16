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
    border: 2px solid #2ecc71 !important;
    background: linear-gradient(180deg, rgba(46, 204, 113, 0.08), rgba(0,0,0,0.2)) !important;
    box-shadow: 0 0 15px rgba(46, 204, 113, 0.35) !important;
    position: relative;
  }
  .badge-best-deal {
    position: absolute;
    top: -11px;
    right: 14px;
    background: #2ecc71;
    color: #111;
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
    background: linear-gradient(90deg, rgba(16, 124, 65, 0.25), rgba(16, 124, 65, 0.08));
    border: 1px solid rgba(16, 124, 65, 0.6);
    padding: 12px 18px;
    border-radius: 12px;
    margin: 20px 0;
  }
  .category-pill {
    background: rgba(142, 45, 226, 0.2);
    border: 1px solid rgba(142, 45, 226, 0.5);
    color: #e0e0e0;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
  }
  .category-pill:hover {
    background: rgba(142, 45, 226, 0.4);
  }
  .platform-badge {
    background: rgba(255, 255, 255, 0.06);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .btn-buy-direct {
    background: linear-gradient(90deg, #4a00e0, #8e2de2);
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
    <a href="index.php" style="color: var(--search-placeholder); text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 15px;">
      ← Voltar ao catálogo
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
        <div style="display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 8px 14px; border-radius: 10px; margin-top: 6px; width: 100%; box-sizing: border-box; justify-content: center;">
          <img src="fotos/extras/metacritic2.png" alt="Metacritic" style="height: 22px; width: auto;">
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
      <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 18px;">
        <h3 style="margin-top: 0; color: var(--title-color); font-size: 16px;">Sobre o Jogo</h3>
        <p class="sinopse" style="font-size: 15px; margin: 0; color: var(--text-color);">
          <?= nl2br(sanitize($jogo['descricao'])) ?>
        </p>
      </div>

      <!-- Disponibilidade em Serviços de Assinatura (RF07) -->
      <?php if (!empty($assinaturas)): ?>
        <div class="subscription-banner">
          <img src="fotos/extras/xbox.png" alt="Assinatura" style="width: 32px; height: 32px; object-fit: contain;">
          <div>
            <div style="font-weight: 800; color: #2ecc71; font-size: 14px;">Incluso em Catálogo de Assinatura!</div>
            <div style="font-size: 13px; color: var(--text-color);">
              Este jogo está disponível sem custo adicional no <strong><?= implode(', ', array_map(fn($a) => sanitize($a['nome']), $assinaturas)) ?></strong>.
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Plataformas Suportadas (RF04) -->
      <div style="margin-top: 20px;">
        <h4 style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: var(--search-placeholder);">Plataformas Disponíveis:</h4>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
          <?php foreach ($plataformas as $plat): ?>
            <span class="platform-badge">
              🎮 <?= sanitize($plat['nome']) ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Comparador de Preços em Tempo Real entre Lojas Digitais (RF05, RF06, RN01, RF09) -->
      <div style="margin-top: 25px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
          <h3 style="margin: 0; color: var(--title-color); font-size: 18px;">Comparador de Preços por Loja</h3>
          <span style="font-size: 12px; color: var(--search-placeholder);">Atualizado automaticamente</span>
        </div>

        <?php if (empty($precos)): ?>
          <div style="padding: 20px; background: rgba(255,255,255,0.03); border-radius: 8px; text-align: center; color: var(--search-placeholder);">
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
                  <img src="<?= sanitize($p['loja_logo'] ?: 'fotos/extras/steam.png') ?>" alt="<?= sanitize($p['loja_nome']) ?>" class="platform-img" style="width: 44px; height: 44px;">
                  <div>
                    <div class="platform-name" style="font-size: 16px;"><?= sanitize($p['loja_nome']) ?></div>
                    <div style="font-size: 12px; color: var(--search-placeholder);">Plataforma: <?= sanitize($p['plataforma_nome']) ?></div>
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
                        <span class="normal-price" style="color: <?= $isBestDeal ? '#2ecc71' : 'var(--highlight)' ?>;"><?= formatPrice($p['valor']) ?></span>
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
