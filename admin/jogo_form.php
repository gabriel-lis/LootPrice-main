<?php
/**
 * Formulário de Cadastro e Edição de Jogo - Admin
 * LootPrice (RN10, RN11, RF04, RF07, RN12)
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin('../login.php');

$pdo = getDB();
$idJogo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdicao = ($idJogo > 0);

$erro = null;
$jogo = [
    'nome' => '',
    'descricao' => '',
    'data_lancamento' => '',
    'nota_metacritic' => '',
    'imagem_capa' => 'fotos/jogos/'
];
$jogoCategorias = [];
$jogoPlataformas = [];
$jogoAssinaturas = [];

if ($isEdicao) {
    $stmt = $pdo->prepare("SELECT * FROM jogos WHERE id_jogo = :id LIMIT 1");
    $stmt->execute(['id' => $idJogo]);
    $res = $stmt->fetch();
    if ($res) {
        $jogo = $res;
        // Categorias vinculadas
        $st = $pdo->prepare("SELECT id_categoria FROM jogo_categoria WHERE id_jogo = :id");
        $st->execute(['id' => $idJogo]);
        $jogoCategorias = $st->fetchAll(PDO::FETCH_COLUMN);

        // Plataformas vinculadas
        $st = $pdo->prepare("SELECT id_plataforma FROM jogo_plataforma WHERE id_jogo = :id");
        $st->execute(['id' => $idJogo]);
        $jogoPlataformas = $st->fetchAll(PDO::FETCH_COLUMN);

        // Assinaturas vinculadas
        $st = $pdo->prepare("SELECT id_servico FROM jogo_assinatura WHERE id_jogo = :id");
        $st->execute(['id' => $idJogo]);
        $jogoAssinaturas = $st->fetchAll(PDO::FETCH_COLUMN);
    } else {
        setFlash('error', 'Jogo não encontrado.');
        header('Location: jogos.php');
        exit;
    }
}

// Processa salvamento
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $dataLancamento = !empty($_POST['data_lancamento']) ? $_POST['data_lancamento'] : null;
    $notaMetacritic = !empty($_POST['nota_metacritic']) ? (float)$_POST['nota_metacritic'] : null;
    $imagemCapa = trim($_POST['imagem_capa'] ?? '');
    $selCategorias = $_POST['categorias'] ?? [];
    $selPlataformas = $_POST['plataformas'] ?? [];
    $selAssinaturas = $_POST['assinaturas'] ?? [];

    if (empty($nome)) {
        $erro = 'O título do jogo é obrigatório.';
    } else {
        try {
            $pdo->beginTransaction();

            if ($isEdicao) {
                $sql = "UPDATE jogos SET nome = :nome, descricao = :desc, data_lancamento = :dt, nota_metacritic = :nota, imagem_capa = :capa WHERE id_jogo = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'nome' => $nome,
                    'desc' => $descricao,
                    'dt' => $dataLancamento,
                    'nota' => $notaMetacritic,
                    'capa' => $imagemCapa,
                    'id' => $idJogo
                ]);
            } else {
                $sql = "INSERT INTO jogos (nome, descricao, data_lancamento, nota_metacritic, imagem_capa) VALUES (:nome, :desc, :dt, :nota, :capa)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'nome' => $nome,
                    'desc' => $descricao,
                    'dt' => $dataLancamento,
                    'nota' => $notaMetacritic,
                    'capa' => $imagemCapa
                ]);
                $idJogo = (int)$pdo->lastInsertId();
            }

            // Atualiza Categorias
            $pdo->prepare("DELETE FROM jogo_categoria WHERE id_jogo = :id")->execute(['id' => $idJogo]);
            if (!empty($selCategorias)) {
                $insCat = $pdo->prepare("INSERT INTO jogo_categoria (id_jogo, id_categoria) VALUES (:id, :id_cat)");
                foreach ($selCategorias as $cId) {
                    $insCat->execute(['id' => $idJogo, 'id_cat' => (int)$cId]);
                }
            }

            // Atualiza Plataformas
            $pdo->prepare("DELETE FROM jogo_plataforma WHERE id_jogo = :id")->execute(['id' => $idJogo]);
            if (!empty($selPlataformas)) {
                $insPlat = $pdo->prepare("INSERT INTO jogo_plataforma (id_jogo, id_plataforma) VALUES (:id, :id_plat)");
                foreach ($selPlataformas as $pId) {
                    $insPlat->execute(['id' => $idJogo, 'id_plat' => (int)$pId]);
                }
            }

            // Atualiza Assinaturas (Game Pass, etc.)
            $pdo->prepare("DELETE FROM jogo_assinatura WHERE id_jogo = :id")->execute(['id' => $idJogo]);
            if (!empty($selAssinaturas)) {
                $insAss = $pdo->prepare("INSERT INTO jogo_assinatura (id_jogo, id_servico) VALUES (:id, :id_serv)");
                foreach ($selAssinaturas as $sId) {
                    $insAss->execute(['id' => $idJogo, 'id_serv' => (int)$sId]);
                }
            }

            $pdo->commit();
            setFlash('success', 'Jogo salvo com sucesso!');
            header('Location: jogos.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = 'Erro ao salvar jogo: ' . $e->getMessage();
        }
    }
}

$todasCategorias = getAllCategories();
$todasPlataformas = getAllPlatforms();
$todasAssinaturas = getAllSubscriptions();

$pageTitle = ($isEdicao ? 'Editar' : 'Novo') . ' Jogo - Admin LootPrice';
$basePath = '../';

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 800px; margin: 30px auto; padding: 0 20px;">
  
  <div style="margin-bottom: 25px;">
    <a href="jogos.php" style="color: var(--search-placeholder); text-decoration: none; font-size: 13px;">← Voltar à lista de jogos</a>
    <h1 style="text-align: left; margin: 5px 0 0 0; font-size: 24px;">
      <?= $isEdicao ? '✏️ Editar Jogo' : '➕ Cadastrar Novo Jogo' ?>
    </h1>
  </div>

  <?php if ($erro): ?>
    <div style="background: rgba(231, 76, 60, 0.2); border: 1px solid #e74c3c; color: #ff6b6b; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
      <?= sanitize($erro) ?>
    </div>
  <?php endif; ?>

  <form method="POST" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 25px;">
    
    <div style="margin-bottom: 18px;">
      <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 14px;">Título do Jogo *</label>
      <input type="text" name="nome" required value="<?= sanitize($jogo['nome']) ?>" placeholder="Ex: Elden Ring"
             style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
    </div>

    <div style="margin-bottom: 18px;">
      <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 14px;">Sinopse / Descrição</label>
      <textarea name="descricao" rows="4" placeholder="Descrição do jogo..."
                style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box; font-family: inherit;"><?= sanitize($jogo['descricao']) ?></textarea>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 18px;">
      <div>
        <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 14px;">Data de Lançamento</label>
        <input type="date" name="data_lancamento" value="<?= sanitize($jogo['data_lancamento']) ?>"
               style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
      </div>

      <div>
        <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 14px;">Nota Metacritic (0 a 100)</label>
        <input type="number" step="0.1" min="0" max="100" name="nota_metacritic" value="<?= sanitize($jogo['nota_metacritic']) ?>" placeholder="Ex: 94.0"
               style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
      </div>

      <div>
        <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 14px;">Caminho da Imagem de Capa</label>
        <input type="text" name="imagem_capa" value="<?= sanitize($jogo['imagem_capa']) ?>" placeholder="fotos/jogos/exemplo.jpg"
               style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
      </div>
    </div>

    <!-- Categorias -->
    <div style="margin-bottom: 20px;">
      <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 14px;">Categorias / Gêneros:</label>
      <div style="display: flex; flex-wrap: wrap; gap: 12px;">
        <?php foreach ($todasCategorias as $cat): ?>
          <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; background: rgba(255,255,255,0.03); padding: 6px 12px; border-radius: 6px; cursor: pointer;">
            <input type="checkbox" name="categorias[]" value="<?= $cat['id_categoria'] ?>"
                   <?= in_array($cat['id_categoria'], $jogoCategorias) ? 'checked' : '' ?>>
            <?= sanitize($cat['nome']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Plataformas -->
    <div style="margin-bottom: 20px;">
      <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 14px;">Plataformas Disponíveis (RF04):</label>
      <div style="display: flex; flex-wrap: wrap; gap: 12px;">
        <?php foreach ($todasPlataformas as $plat): ?>
          <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; background: rgba(255,255,255,0.03); padding: 6px 12px; border-radius: 6px; cursor: pointer;">
            <input type="checkbox" name="plataformas[]" value="<?= $plat['id_plataforma'] ?>"
                   <?= in_array($plat['id_plataforma'], $jogoPlataformas) ? 'checked' : '' ?>>
            🎮 <?= sanitize($plat['nome']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Serviços de Assinatura -->
    <div style="margin-bottom: 25px;">
      <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 14px;">Disponibilidade em Assinaturas (RF07):</label>
      <div style="display: flex; flex-wrap: wrap; gap: 12px;">
        <?php foreach ($todasAssinaturas as $ass): ?>
          <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; background: rgba(16, 124, 65, 0.15); border: 1px solid rgba(16, 124, 65, 0.4); padding: 6px 12px; border-radius: 6px; cursor: pointer; color: #2ecc71;">
            <input type="checkbox" name="assinaturas[]" value="<?= $ass['id_servico'] ?>"
                   <?= in_array($ass['id_servico'], $jogoAssinaturas) ? 'checked' : '' ?>>
            <?= sanitize($ass['nome']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 12px;">
      <a href="jogos.php" class="btn-login" style="background: transparent; border: 1px solid rgba(255,255,255,0.1); color: var(--text-color); text-decoration: none;">
        Cancelar
      </a>
      <button type="submit" class="btn-login" style="border: none; cursor: pointer; background: linear-gradient(90deg, #4a00e0, #8e2de2);">
        Salvar Jogo
      </button>
    </div>

  </form>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
