<?php
/**
 * Componentes de card reutilizados na home, no catálogo e nos descontos.
 * LootPrice
 */

/**
 * Card vertical (capa grande) usado nas seções da home e na página de descontos.
 * $selo: texto curto sobre a capa (ex.: "Parecido com Elden Ring").
 */
function renderPosterCard(array $j, ?string $selo = null): void {
    $id = (int)$j['id_jogo'];
    $preco = (float)($j['menor_preco'] ?? 0);
    $desconto = (int)($j['desconto'] ?? 0);
    $score = !empty($j['nota_metacritic']) ? (int)$j['nota_metacritic'] : null;
    ?>
    <a class="poster" href="jogo.php?id=<?= $id ?>" title="<?= sanitize($j['nome']) ?>">
      <div class="poster-cover">
        <img src="<?= sanitize($j['imagem_capa']) ?>" alt="<?= sanitize($j['nome']) ?>" loading="lazy">
      </div>
      <div class="poster-body">
        <?php if ($selo): ?><span class="poster-tag"><?= sanitize($selo) ?></span><?php endif; ?>
        <div class="poster-title-row">
          <h3 class="poster-title"><?= sanitize($j['nome']) ?></h3>
          <?php if ($score !== null): ?>
            <span class="poster-score" style="background: <?= getMetacriticColor((float)$score) ?>;" title="Nota Metacritic: <?= $score ?>"><?= $score ?></span>
          <?php endif; ?>
        </div>
        <div class="poster-price">
          <strong><?= formatPrice($j['menor_preco']) ?></strong>
          <?php if ($desconto > 0 && $preco > 0): ?>
            <span class="poster-discount">-<?= $desconto ?>%</span>
            <?php if (!empty($j['valor_original'])): ?><s class="poster-old"><?= formatPrice($j['valor_original']) ?></s><?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </a>
    <?php
}

/**
 * Card horizontal com tabela (menor preço / Game Pass), usado no catálogo completo.
 */
function renderGameRowCard(array $j): void {
    $id = (int)$j['id_jogo'];
    $hasGamePass = !empty($j['has_game_pass']);
    $score = !empty($j['nota_metacritic']) ? (int)$j['nota_metacritic'] : null;
    $ano = !empty($j['data_lancamento']) ? ' (' . date('Y', strtotime($j['data_lancamento'])) . ')' : '';
    ?>
    <div class="hltb-card">
      <a href="jogo.php?id=<?= $id ?>" class="hltb-cover-wrap" title="<?= sanitize($j['nome']) ?>">
        <img src="<?= sanitize($j['imagem_capa']) ?>" alt="<?= sanitize($j['nome']) ?>" class="hltb-cover-img" loading="lazy">
      </a>
      <div class="hltb-body">
        <div class="hltb-title-row">
          <a href="jogo.php?id=<?= $id ?>" class="hltb-title" title="<?= sanitize($j['nome']) ?>">
            <?= sanitize($j['nome']) . $ano ?>
          </a>
          <?php if ($score !== null): ?>
            <div class="hltb-header-meta" style="background: <?= getMetacriticColor((float)$score) ?>;" title="Nota oficial Metacritic: <?= $score ?>">
              <?= $score ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="hltb-stats-table">
          <div class="hltb-row">
            <span class="hltb-row-label">Menor Preço</span>
            <span class="hltb-row-val hltb-val-blue"><?= formatPrice($j['menor_preco']) ?></span>
          </div>
          <div class="hltb-row">
            <span class="hltb-row-label">Game Pass</span>
            <?php if ($hasGamePass): ?>
              <span class="hltb-row-val hltb-val-purple">Disponível</span>
            <?php else: ?>
              <span class="hltb-row-val hltb-val-darkblue">Não incluso</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="hltb-footer">
          <a href="jogo.php?id=<?= $id ?>" class="hltb-action-link">Ver Ofertas ➔</a>
        </div>
      </div>
    </div>
    <?php
}
