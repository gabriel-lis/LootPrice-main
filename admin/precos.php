<?php
/**
 * Gerenciamento de Preços e Lojas - Admin
 * LootPrice (RF05, RF06, RF09, RN01, RN03, RN11)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';
$filtroJogoId = (int)lp_get('id_jogo', 12) ?: null;
$pdo = adminIniciar('precos.php' . ($filtroJogoId ? "?id_jogo=$filtroJogoId" : ''));

$acao = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') ? lp_post('acao', 20) : '';
$voltar = 'precos.php' . ($filtroJogoId ? "?id_jogo=$filtroJogoId" : '');
$erroForm = null;
$form = ['id_jogo' => $filtroJogoId, 'id_loja' => 0, 'id_plataforma' => 0, 'valor' => '', 'valor_original' => '', 'link_compra' => ''];

// Exclusão de preço
if ($acao === 'excluir_preco') {
    $idPreco = (int)lp_post('id_preco', 12);
    if ($idPreco > 0) {
        $pdo->prepare("DELETE FROM precos WHERE id_preco = :id")->execute(['id' => $idPreco]);
        setFlash('success', 'Oferta excluída com sucesso.');
    }
    header("Location: $voltar");
    exit;
}

// Inserção ou atualização de preço (um preço por jogo+loja: salvar de novo na mesma loja atualiza)
if ($acao === 'salvar_preco') {
    $form = [
        'id_jogo' => (int)lp_post('id_jogo', 12), 'id_loja' => (int)lp_post('id_loja', 12), 'id_plataforma' => (int)lp_post('id_plataforma', 12),
        'valor' => lp_post('valor', 20), 'valor_original' => lp_post('valor_original', 20), 'link_compra' => lp_post('link_compra', 300),
    ];
    $valor = lp_parse_valor($form['valor']);
    $valorOriginal = $form['valor_original'] !== '' ? lp_parse_valor($form['valor_original']) : null;

    if ($form['id_jogo'] <= 0 || $form['id_loja'] <= 0 || $form['id_plataforma'] <= 0) {
        $erroForm = 'Escolha o jogo, a loja e a plataforma.';
    } elseif ($valor === null) {
        $erroForm = 'Preço atual inválido. Use números, por exemplo 59,90 (0 para jogo grátis).';
    } elseif ($form['valor_original'] !== '' && $valorOriginal === null) {
        $erroForm = 'Preço original inválido. Use números, por exemplo 119,90.';
    } elseif ($form['link_compra'] !== '' && !lp_link_valido($form['link_compra'])) {
        $erroForm = 'O link de compra precisa começar com http:// ou https:// (máx. 255 caracteres).';
    } else {
        // Cálculo de desconto (RN03)
        $desconto = 0;
        if ($valorOriginal && $valorOriginal > $valor && $valor > 0) {
            $desconto = (int)round((1 - ($valor / $valorOriginal)) * 100);
        }
        try {
            $pdo->beginTransaction();
            $ex = $pdo->prepare("SELECT valor FROM precos WHERE id_jogo = :j AND id_loja = :l");
            $ex->execute(['j' => $form['id_jogo'], 'l' => $form['id_loja']]);
            $valorAntigo = $ex->fetchColumn();

            $pdo->prepare("INSERT INTO precos (id_jogo, id_loja, id_plataforma, valor, valor_original, porcentagem_desconto, link_compra, data_atualizacao)
                           VALUES (:j, :l, :p, :v, :vo, :d, :link, NOW())
                           ON DUPLICATE KEY UPDATE id_plataforma = VALUES(id_plataforma), valor = VALUES(valor), valor_original = VALUES(valor_original),
                                                   porcentagem_desconto = VALUES(porcentagem_desconto), link_compra = VALUES(link_compra), data_atualizacao = NOW()")
                ->execute(['j' => $form['id_jogo'], 'l' => $form['id_loja'], 'p' => $form['id_plataforma'], 'v' => $valor, 'vo' => $valorOriginal,
                           'd' => $desconto, 'link' => $form['link_compra'] !== '' ? $form['link_compra'] : null]);

            // Histórico: só quando é preço novo ou mudou
            if ($valorAntigo === false || abs((float)$valorAntigo - $valor) > 0.001) {
                $pdo->prepare("INSERT INTO historico_precos (id_jogo, id_loja, valor, valor_original) VALUES (:j, :l, :v, :vo)")
                    ->execute(['j' => $form['id_jogo'], 'l' => $form['id_loja'], 'v' => $valor, 'vo' => $valorOriginal]);
            }
            $pdo->commit();
            setFlash('success', $valorAntigo === false ? 'Nova cotação registrada com sucesso!' : 'Cotação dessa loja atualizada.');
            header('Location: precos.php?id_jogo=' . $form['id_jogo']);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erroForm = adminErro('salvar cotação', $e);
        }
    }
}

// Busca a lista de ofertas
$sql = "SELECT pr.*, j.nome AS jogo_nome, l.nome AS loja_nome, p.nome AS plataforma_nome
        FROM precos pr
        INNER JOIN jogos j ON pr.id_jogo = j.id_jogo
        INNER JOIN lojas l ON pr.id_loja = l.id_loja
        INNER JOIN plataformas p ON pr.id_plataforma = p.id_plataforma";
if ($filtroJogoId) $sql .= " WHERE pr.id_jogo = :id_jogo";
$sql .= " ORDER BY j.nome ASC, pr.valor ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($filtroJogoId ? ['id_jogo' => $filtroJogoId] : []);
$precos = $stmt->fetchAll();

$todosJogos = $pdo->query("SELECT id_jogo, nome FROM jogos ORDER BY nome ASC")->fetchAll();
$todasLojas = getAllStores();
$todasPlataformas = getAllPlatforms();

$pageTitle = 'Gerenciar Preços e Lojas - Admin LootPrice';
$basePath = '../';
$cssAdmin = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="adm-wrap">

  <div class="adm-head">
    <div>
      <a href="index.php" class="adm-voltar">← Voltar ao Dashboard</a>
      <h1 class="adm-title">💰 Gerenciar Preços e Ofertas por Loja</h1>
    </div>

    <!-- Filtro por Jogo -->
    <form method="GET" action="precos.php" class="adm-form-inline">
      <select name="id_jogo" class="adm-select" onchange="this.form.submit()" aria-label="Filtrar por jogo">
        <option value="">Todos os Jogos</option>
        <?php foreach ($todosJogos as $tj): ?>
          <option value="<?= (int)$tj['id_jogo'] ?>" <?= ($filtroJogoId === (int)$tj['id_jogo']) ? 'selected' : '' ?>><?= sanitize($tj['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="adm-2col">

    <!-- Tabela de Preços -->
    <div class="adm-tabela-wrap">
      <table class="adm-tabela">
        <thead>
          <tr><th>Jogo</th><th>Loja / Plat.</th><th>Preço</th><th>Desconto</th><th class="dir">Ações</th></tr>
        </thead>
        <tbody>
          <?php if (empty($precos)): ?>
            <tr><td colspan="5" class="adm-vazio">Nenhuma cotação encontrada.</td></tr>
          <?php else: foreach ($precos as $p): $idade = idadeDoPreco($p['data_atualizacao'] ?? null); ?>
            <tr>
              <td><strong><?= sanitize($p['jogo_nome']) ?></strong></td>
              <td>
                <span style="color: var(--accent-text); font-weight:600;"><?= sanitize($p['loja_nome']) ?></span>
                <div class="adm-mini"><?= sanitize($p['plataforma_nome']) ?></div>
              </td>
              <td>
                <span class="adm-valor"><?= formatPrice($p['valor']) ?></span>
                <?php if (!empty($p['valor_original']) && $p['valor_original'] > $p['valor']): ?>
                  <span class="adm-riscado">R$ <?= number_format((float)$p['valor_original'], 2, ',', '.') ?></span>
                <?php endif; ?>
                <span class="adm-mini" style="<?= $idade['velho'] ? 'color: var(--warning);' : '' ?>" title="Data da última coleta/edição"><?= $idade['velho'] ? '⚠ ' : '' ?><?= sanitize($idade['texto']) ?></span>
              </td>
              <td>
                <?php if (!empty($p['porcentagem_desconto'])): ?>
                  <span class="adm-badge desc">-<?= (int)$p['porcentagem_desconto'] ?>%</span>
                <?php else: ?><span class="adm-mini">-</span><?php endif; ?>
              </td>
              <td class="dir">
                <?php if (!empty($p['link_compra']) && lp_link_valido($p['link_compra'])): ?>
                  <a href="<?= sanitize($p['link_compra']) ?>" target="_blank" rel="noopener noreferrer" class="btn-link aviso">Link</a>
                <?php endif; ?>
                <form method="POST" action="precos.php<?= $filtroJogoId ? '?id_jogo=' . $filtroJogoId : '' ?>" class="adm-form-inline" onsubmit="return confirm('Remover esta cotação?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="acao" value="excluir_preco">
                  <input type="hidden" name="id_preco" value="<?= (int)$p['id_preco'] ?>">
                  <button type="submit" class="btn-link perigo">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Formulário para adicionar/atualizar preço de loja -->
    <div class="adm-card">
      <h3>➕ Adicionar ou Atualizar Cotação</h3>
      <p class="adm-dica" style="margin-top:-6px; margin-bottom:12px;">Se o jogo já tem preço nessa loja, ele é atualizado (não duplica).</p>

      <?php if ($erroForm): ?><div class="adm-aviso erro" role="alert"><?= sanitize($erroForm) ?></div><?php endif; ?>

      <form method="POST" action="precos.php<?= $filtroJogoId ? '?id_jogo=' . $filtroJogoId : '' ?>">
        <?= csrfField() ?>
        <input type="hidden" name="acao" value="salvar_preco">

        <div class="adm-campo">
          <label for="f-jogo">Jogo *</label>
          <select name="id_jogo" id="f-jogo" required>
            <?php foreach ($todosJogos as $tj): ?>
              <option value="<?= (int)$tj['id_jogo'] ?>" <?= ((int)$form['id_jogo'] === (int)$tj['id_jogo']) ? 'selected' : '' ?>><?= sanitize($tj['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="adm-campo">
          <label for="f-loja">Loja Parceira *</label>
          <select name="id_loja" id="f-loja" required>
            <?php foreach ($todasLojas as $tl): ?>
              <option value="<?= (int)$tl['id_loja'] ?>" <?= ((int)$form['id_loja'] === (int)$tl['id_loja']) ? 'selected' : '' ?>><?= sanitize($tl['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="adm-campo">
          <label for="f-plat">Plataforma *</label>
          <select name="id_plataforma" id="f-plat" required>
            <?php foreach ($todasPlataformas as $tp): ?>
              <option value="<?= (int)$tp['id_plataforma'] ?>" <?= ((int)$form['id_plataforma'] === (int)$tp['id_plataforma']) ? 'selected' : '' ?>><?= sanitize($tp['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="adm-linha2 adm-campo">
          <div>
            <label for="f-valor">Preço Atual (R$) *</label>
            <input type="text" id="f-valor" name="valor" required inputmode="decimal" placeholder="Ex: 59,90" value="<?= sanitize($form['valor']) ?>">
          </div>
          <div>
            <label for="f-orig">Preço Original (R$)</label>
            <input type="text" id="f-orig" name="valor_original" inputmode="decimal" placeholder="Ex: 119,90" value="<?= sanitize($form['valor_original']) ?>">
          </div>
        </div>

        <div class="adm-campo">
          <label for="f-link">Link Direto de Compra (RF09)</label>
          <input type="url" id="f-link" name="link_compra" maxlength="255" placeholder="https://store.steampowered.com/app/..." value="<?= sanitize($form['link_compra']) ?>">
        </div>

        <button type="submit" class="btn-adm" style="width:100%;">Salvar Cotação</button>
      </form>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
