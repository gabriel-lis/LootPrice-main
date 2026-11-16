<?php
/**
 * Gerenciamento de Preços e Lojas - Admin
 * LootPrice (RF05, RF06, RF09, RN01, RN03, RN11)
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin('../login.php');

$pdo = getDB();
$filtroJogoId = isset($_GET['id_jogo']) ? (int)$_GET['id_jogo'] : null;

// Exclusão de preço
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir_preco') {
    $idPreco = (int)$_POST['id_preco'];
    if ($idPreco > 0) {
        $st = $pdo->prepare("DELETE FROM precos WHERE id_preco = :id");
        $st->execute(['id' => $idPreco]);
        setFlash('success', 'Oferta excluída com sucesso.');
        header('Location: precos.php' . ($filtroJogoId ? "?id_jogo=$filtroJogoId" : ''));
        exit;
    }
}

// Inserção ou Atualização de preço
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_preco') {
    $idJogo = (int)$_POST['id_jogo'];
    $idLoja = (int)$_POST['id_loja'];
    $idPlat = (int)$_POST['id_plataforma'];
    $valor = (float)str_replace(',', '.', $_POST['valor']);
    $valorOriginal = !empty($_POST['valor_original']) ? (float)str_replace(',', '.', $_POST['valor_original']) : null;
    $linkCompra = trim($_POST['link_compra'] ?? '');

    // Cálculo de desconto (RN03)
    $desconto = 0;
    if ($valorOriginal && $valorOriginal > $valor && $valor > 0) {
        $desconto = (int)round((1 - ($valor / $valorOriginal)) * 100);
    }

    if ($idJogo > 0 && $idLoja > 0 && $idPlat > 0) {
        $stmt = $pdo->prepare("INSERT INTO precos (id_jogo, id_loja, id_plataforma, valor, valor_original, porcentagem_desconto, link_compra, data_atualizacao) 
                               VALUES (:j, :l, :p, :v, :vo, :d, :link, NOW())");
        $stmt->execute([
            'j' => $idJogo,
            'l' => $idLoja,
            'p' => $idPlat,
            'v' => $valor,
            'vo' => $valorOriginal,
            'd' => $desconto,
            'link' => $linkCompra
        ]);
        setFlash('success', 'Nova cotação registrada com sucesso!');
        header('Location: precos.php?id_jogo=' . $idJogo);
        exit;
    }
}

// Busca a lista de ofertas
$sql = "SELECT pr.*, 
               j.nome AS jogo_nome, 
               l.nome AS loja_nome, 
               p.nome AS plataforma_nome
        FROM precos pr
        INNER JOIN jogos j ON pr.id_jogo = j.id_jogo
        INNER JOIN lojas l ON pr.id_loja = l.id_loja
        INNER JOIN plataformas p ON pr.id_plataforma = p.id_plataforma";

if ($filtroJogoId) {
    $sql .= " WHERE pr.id_jogo = :id_jogo";
}

$sql .= " ORDER BY j.nome ASC, pr.valor ASC";

$stmt = $pdo->prepare($sql);
if ($filtroJogoId) {
    $stmt->execute(['id_jogo' => $filtroJogoId]);
} else {
    $stmt->execute();
}
$precos = $stmt->fetchAll();

$todosJogos = $pdo->query("SELECT id_jogo, nome FROM jogos ORDER BY nome ASC")->fetchAll();
$todasLojas = getAllStores();
$todasPlataformas = getAllPlatforms();

$pageTitle = 'Gerenciar Preços e Lojas - Admin LootPrice';
$basePath = '../';

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1100px; margin: 30px auto; padding: 0 20px;">
  
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
      <a href="index.php" style="color: var(--search-placeholder); text-decoration: none; font-size: 13px;">← Voltar ao Dashboard</a>
      <h1 style="text-align: left; margin: 5px 0 0 0; font-size: 24px;">💰 Gerenciar Preços e Ofertas por Loja</h1>
    </div>

    <!-- Filtro por Jogo -->
    <form method="GET" action="precos.php" style="display: flex; gap: 8px; align-items: center;">
      <select name="id_jogo" onchange="this.form.submit()" style="background: var(--search-bg); color: var(--text-color); border: 1px solid var(--search-border); border-radius: 8px; padding: 8px 12px; font-size: 13px;">
        <option value="">Todos os Jogos</option>
        <?php foreach ($todosJogos as $tj): ?>
          <option value="<?= $tj['id_jogo'] ?>" <?= ($filtroJogoId === (int)$tj['id_jogo']) ? 'selected' : '' ?>>
            <?= sanitize($tj['nome']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px; align-items: start;">
    
    <!-- Tabela de Preços -->
    <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
        <thead>
          <tr style="border-bottom: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02); color: var(--search-placeholder);">
            <th style="padding: 12px 14px;">Jogo</th>
            <th style="padding: 12px 14px;">Loja / Plat.</th>
            <th style="padding: 12px 14px;">Preço</th>
            <th style="padding: 12px 14px;">Desconto</th>
            <th style="padding: 12px 14px; text-align: right;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($precos)): ?>
            <tr><td colspan="5" style="padding: 25px; text-align: center; color: var(--search-placeholder);">Nenhuma cotação encontrada.</td></tr>
          <?php else: ?>
            <?php foreach ($precos as $p): ?>
              <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
                <td style="padding: 12px 14px;">
                  <strong><?= sanitize($p['jogo_nome']) ?></strong>
                </td>
                <td style="padding: 12px 14px;">
                  <span style="color: #a78bfa; font-weight: 600;"><?= sanitize($p['loja_nome']) ?></span>
                  <div style="font-size: 11px; color: var(--search-placeholder);"><?= sanitize($p['plataforma_nome']) ?></div>
                </td>
                <td style="padding: 12px 14px;">
                  <strong style="color: #5ee7df;"><?= formatPrice($p['valor']) ?></strong>
                  <?php if (!empty($p['valor_original']) && $p['valor_original'] > $p['valor']): ?>
                    <div style="font-size: 11px; text-decoration: line-through; opacity: 0.6;">
                      R$ <?= number_format($p['valor_original'], 2, ',', '.') ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="padding: 12px 14px;">
                  <?php if (!empty($p['porcentagem_desconto']) && $p['porcentagem_desconto'] > 0): ?>
                    <span style="background: #ff4757; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700;">
                      -<?= $p['porcentagem_desconto'] ?>%
                    </span>
                  <?php else: ?>
                    <span style="color: var(--search-placeholder); font-size: 12px;">-</span>
                  <?php endif; ?>
                </td>
                <td style="padding: 12px 14px; text-align: right;">
                  <?php if (!empty($p['link_compra'])): ?>
                    <a href="<?= sanitize($p['link_compra']) ?>" target="_blank" style="color: #ffc857; text-decoration: none; margin-right: 8px; font-size: 12px;">Link</a>
                  <?php endif; ?>

                  <form method="POST" action="precos.php" style="display: inline;" onsubmit="return confirm('Remover esta cotação?');">
                    <input type="hidden" name="acao" value="excluir_preco">
                    <input type="hidden" name="id_preco" value="<?= $p['id_preco'] ?>">
                    <?php if ($filtroJogoId): ?>
                      <input type="hidden" name="id_jogo" value="<?= $filtroJogoId ?>">
                    <?php endif; ?>
                    <button type="submit" style="background: transparent; border: none; color: #ff6b6b; cursor: pointer; font-size: 13px; font-weight: 600;">
                      Excluir
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Formulário para Adicionar Preço de Loja -->
    <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 20px;">
      <h3 style="margin-top: 0; font-size: 16px; margin-bottom: 15px;">➕ Adicionar Cotação</h3>

      <form method="POST" action="precos.php">
        <input type="hidden" name="acao" value="salvar_preco">

        <div style="margin-bottom: 14px;">
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Jogo *</label>
          <select name="id_jogo" required style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text);">
            <?php foreach ($todosJogos as $tj): ?>
              <option value="<?= $tj['id_jogo'] ?>" <?= ($filtroJogoId === (int)$tj['id_jogo']) ? 'selected' : '' ?>>
                <?= sanitize($tj['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="margin-bottom: 14px;">
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Loja Parceira *</label>
          <select name="id_loja" required style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text);">
            <?php foreach ($todasLojas as $tl): ?>
              <option value="<?= $tl['id_loja'] ?>"><?= sanitize($tl['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="margin-bottom: 14px;">
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Plataforma *</label>
          <select name="id_plataforma" required style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text);">
            <?php foreach ($todasPlataformas as $tp): ?>
              <option value="<?= $tp['id_plataforma'] ?>"><?= sanitize($tp['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
          <div>
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Preço Atual (R$) *</label>
            <input type="text" name="valor" required placeholder="Ex: 59.90" style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
          </div>
          <div>
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Preço Original (R$)</label>
            <input type="text" name="valor_original" placeholder="Ex: 119.90" style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
          </div>
        </div>

        <div style="margin-bottom: 16px;">
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Link Direto de Compra (RF09)</label>
          <input type="url" name="link_compra" placeholder="https://store.steampowered.com/app/..." style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
        </div>

        <button type="submit" class="btn-login" style="width: 100%; border: none; cursor: pointer; padding: 10px;">
          Salvar Cotação
        </button>
      </form>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
