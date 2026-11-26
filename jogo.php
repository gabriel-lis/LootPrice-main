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
$cssPerfil = true; // estilo do botão de lista de desejos

// Dados complementares do banco de dados
$precos = getGamePrices($idJogo);
$categorias = getGameCategories($idJogo);
$plataformas = getGamePlatforms($idJogo);
$assinaturas = getGameSubscriptions($idJogo);
$historico = getPriceHistory($idJogo);
$pageDescription = 'Compare o preço de ' . $jogo['nome'] . ' nas principais lojas e veja onde está mais barato.';

// Data da coleta mais antiga/recente (para avisar quando o preço está velho)
$ultimaAtualizacao = null;
foreach ($precos as $p) {
    if (!empty($p['data_atualizacao']) && ($ultimaAtualizacao === null || $p['data_atualizacao'] > $ultimaAtualizacao)) $ultimaAtualizacao = $p['data_atualizacao'];
}
$idadeGeral = idadeDoPreco($ultimaAtualizacao);

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
    color: var(--on-accent);
    font-weight: 800;
    font-size: 11px;
    padding: 2px 10px;
    border-radius: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 6px var(--card-shadow);
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
    color: var(--on-accent);
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
    background: var(--btn-bg);
    color: var(--on-accent);
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
  .bloco-pagina { margin-top: 28px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 18px; }
  .bloco-titulo { margin: 0 0 8px; font-size: 18px; color: var(--title-color); display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 14px; }
  .bloco-sub { margin: 0 0 12px; font-size: 14px; color: var(--text-muted); }
  .hist-grafico { width: 100%; height: auto; max-height: 150px; display: block; background: var(--surface-2); border-radius: 8px; }
  .hist-eixo { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); margin-top: 6px; }
  .precos-visitante { position: relative; }
  .precos-borrados { filter: blur(7px); pointer-events: none; user-select: none; }
  .precos-overlay {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 10px; text-align: center; padding: 16px;
  }
  .precos-overlay p { margin: 0; font-weight: 600; color: var(--heading); }
  .precos-overlay span { font-size: 13px; color: var(--text-muted); }
  .precos-overlay span a { color: var(--accent); font-weight: 700; }
  .btn-entrar-precos {
    background: var(--btn-bg); color: var(--on-accent); padding: 10px 32px; border-radius: 8px;
    font-weight: 700; text-decoration: none;
  }
  .btn-entrar-precos:hover { filter: brightness(1.15); }
</style>

<div class="game-details-wrapper">
  
  <header style="margin-bottom: 25px;">
    <a href="catalogo.php" class="btn-back">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
      Voltar ao catálogo
    </a>
    <div class="jogo-titulo-linha">
      <h1 style="text-align: left; margin: 0; font-size: 32px;"><?= sanitize($jogo['nome']) ?></h1>
      <?php
        $logado = isLoggedIn();
        $naLista = $logado && isInWishlist((int)currentUser()['id_usuario'], $idJogo);
        $iconeCoracao = '<svg viewBox="0 0 24 24" width="18" height="18" fill="' . ($naLista ? 'currentColor' : 'none') . '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21.2l7.8-7.7 1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg>';
      ?>
      <?php if ($logado && !isEmailVerified()): ?>
        <a href="verificar_email.php" class="btn-desejo" title="Confirme seu e-mail para usar a lista de desejos"><?= $iconeCoracao ?> Confirmar e-mail para usar a lista de desejos</a>
      <?php elseif ($logado): ?>
        <form method="POST" action="desejos.php" class="desejo-form">
          <?= csrfField() ?>
          <input type="hidden" name="id_jogo" value="<?= $idJogo ?>">
          <input type="hidden" name="acao" value="<?= $naLista ? 'remover' : 'adicionar' ?>">
          <button type="submit" class="btn-desejo<?= $naLista ? ' ativo' : '' ?>" aria-pressed="<?= $naLista ? 'true' : 'false' ?>">
            <?= $iconeCoracao ?> <?= $naLista ? 'Na lista de desejos' : 'Adicionar à lista de desejos' ?>
          </button>
        </form>
      <?php else: ?>
        <a href="login.php" class="btn-desejo" title="Entre na sua conta para usar a lista de desejos"><?= $iconeCoracao ?> Entrar para adicionar à lista de desejos</a>
      <?php endif; ?>
    </div>

    <!-- Categorias do Jogo -->
    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;">
      <?php foreach ($categorias as $c): ?>
        <a href="catalogo.php?categoria=<?= (int)$c['id_categoria'] ?>" class="category-pill">
          <?= sanitize($c['nome']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </header>

  <!-- Conteúdo Principal -->
  <main class="game-container" style="margin-left: 0; padding-left: 0; width: 100%;">
    
    <!-- Mídia e Data -->
    <div class="game-media">
      <img src="<?= sanitize(capaJogo($jogo['imagem_capa'])) ?>" 
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
          <span style="background: <?= getMetacriticColor((float)$jogo['nota_metacritic']) ?>; color: var(--on-accent); font-weight: 800; font-size: 14px; padding: 2px 8px; border-radius: 6px;">
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
          <span style="font-size: 12px; color: var(--text-muted);" title="Os preços são coletados uma vez por dia pelo robô do LootPrice">
            <?= $ultimaAtualizacao ? 'Última coleta: ' . sanitize($idadeGeral['texto']) : '' ?>
          </span>
        </div>

        <?php if (empty($precos)): ?>
          <div style="padding: 20px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; text-align: center; color: var(--text-muted);">
            Nenhum preço cadastrado para este jogo no momento.
          </div>
        <?php else: ?>
          <?php if (!$logado): ?>
            <div class="precos-visitante">
              <div class="precos-borrados" aria-hidden="true">
          <?php endif; ?>
          <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($precos as $idx => $p): 
              // Visitante: valores falsos (os reais nunca saem do servidor)
              if (!$logado) { $p['valor'] = 99.99; $p['valor_original'] = null; $p['porcentagem_desconto'] = null; $p['link_compra'] = null; $p['id_preco'] = 0; }
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
                        <span class="discount" style="background: var(--danger-border); color: var(--on-accent); font-size:11px; padding:2px 6px; border-radius:4px;">-<?= $porcentagem ?>%</span>
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
          <?php if (!$logado): ?>
              </div>
              <div class="precos-overlay">
                <p>Entre na sua conta para ver os preços e os links das lojas.</p>
                <a href="login.php?voltar=<?= urlencode('jogo.php?id=' . $idJogo) ?>" class="btn-entrar-precos">Entrar</a>
                <span>Não tem conta? <a href="login.php?aba=cadastro&amp;voltar=<?= urlencode('jogo.php?id=' . $idJogo) ?>">Criar conta</a></span>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

      </div>

      <!-- Histórico de preços: menor preço já registrado e evolução do menor preço por dia -->
      <?php if ($logado && !empty($historico['menor'])):
        $serie = $historico['serie'];
        $menorH = $historico['menor'];
      ?>
        <div class="bloco-pagina" id="historico">
          <h3 class="bloco-titulo">Histórico de preços</h3>
          <p class="bloco-sub">
            Menor preço já registrado: <strong style="color: var(--success);"><?= formatPrice($menorH['valor']) ?></strong>
            na <?= sanitize($menorH['loja']) ?> (<?= date('d/m/Y', strtotime($menorH['registrado_em'])) ?>).
          </p>
          <?php if (count($serie) >= 2):
            $valores = array_map(fn($r) => (float)$r['valor'], $serie);
            $vmin = min($valores); $vmax = max($valores); $faixa = max(0.01, $vmax - $vmin);
            $W = 600; $H = 110; $n = count($serie); $pts = [];
            foreach ($serie as $i => $r) {
                $x = $n > 1 ? round($i * ($W - 20) / ($n - 1) + 10, 1) : $W / 2;
                $y = round(10 + ($vmax - (float)$r['valor']) / $faixa * ($H - 20), 1);
                $pts[] = "$x,$y";
            }
          ?>
            <svg class="hist-grafico" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Evolução do menor preço de <?= sanitize($jogo['nome']) ?> entre <?= date('d/m/Y', strtotime($serie[0]['dia'])) ?> e <?= date('d/m/Y', strtotime($serie[$n - 1]['dia'])) ?>">
              <polyline points="<?= implode(' ', $pts) ?>" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
              <?php foreach ($pts as $pt): [$cx, $cy] = explode(',', $pt); ?><circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="3.5" fill="var(--accent)"/><?php endforeach; ?>
            </svg>
            <div class="hist-eixo"><span><?= date('d/m/Y', strtotime($serie[0]['dia'])) ?> · <?= formatPrice($serie[0]['valor']) ?></span><span><?= date('d/m/Y', strtotime($serie[$n - 1]['dia'])) ?> · <?= formatPrice($serie[$n - 1]['valor']) ?></span></div>
          <?php else: ?>
            <p class="bloco-sub">O gráfico aparece quando houver pelo menos dois dias de coleta.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </section>

  </main>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
