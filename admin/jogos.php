<?php
/**
 * Gerenciamento de Jogos - Admin
 * LootPrice (RN10)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';
$pdo = adminIniciar('jogos.php');

// Exclusão de jogo (POST com token CSRF; adminIniciar já conferiu)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && lp_post('acao', 12) === 'excluir') {
    $idExcluir = (int)lp_post('id_jogo', 12);
    if ($idExcluir > 0) {
        try {
            $st = $pdo->prepare("SELECT imagem_capa FROM jogos WHERE id_jogo = :id");
            $st->execute(['id' => $idExcluir]);
            $capa = $st->fetchColumn() ?: null;

            $pdo->prepare("DELETE FROM jogos WHERE id_jogo = :id")->execute(['id' => $idExcluir]);

            // Apaga a capa do disco, a menos que outro jogo use o mesmo arquivo
            if ($capa) {
                $uso = $pdo->prepare("SELECT COUNT(*) FROM jogos WHERE imagem_capa = :c");
                $uso->execute(['c' => $capa]);
                if ((int)$uso->fetchColumn() === 0) adminApagarArquivo($capa, ['fotos/jogos/']);
            }
            setFlash('success', 'Jogo excluído com sucesso.');
        } catch (Throwable $e) {
            setFlash('error', adminErro('excluir jogo', $e));
        }
    }
    header('Location: jogos.php');
    exit;
}

// Lista todos os jogos com contagem de preços cadastrados
$sql = "SELECT j.*,
               COUNT(DISTINCT p.id_preco) AS qtd_precos,
               GROUP_CONCAT(DISTINCT c.nome SEPARATOR ', ') AS categorias
        FROM jogos j
        LEFT JOIN precos p ON j.id_jogo = p.id_jogo
        LEFT JOIN jogo_categoria jc ON j.id_jogo = jc.id_jogo
        LEFT JOIN categorias c ON jc.id_categoria = c.id_categoria
        GROUP BY j.id_jogo
        ORDER BY j.nome ASC";
$jogos = $pdo->query($sql)->fetchAll();

$pageTitle = 'Gerenciar Jogos - Admin LootPrice';
$basePath = '../';
$cssAdmin = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="adm-wrap">

  <div class="adm-head">
    <div>
      <a href="index.php" class="adm-voltar">← Voltar ao Dashboard</a>
      <h1 class="adm-title">🎮 Gerenciar Catálogo de Jogos</h1>
    </div>
    <a href="jogo_form.php" class="btn-adm ok">+ Adicionar Novo Jogo</a>
  </div>

  <div class="adm-tabela-wrap">
    <table class="adm-tabela">
      <thead>
        <tr>
          <th>Capa</th><th>Título</th><th>Categorias</th><th>Metacritic</th><th>Preços</th><th class="dir">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($jogos)): ?>
          <tr><td colspan="6" class="adm-vazio">Nenhum jogo cadastrado.</td></tr>
        <?php else: foreach ($jogos as $j): ?>
          <tr>
            <td style="width:50px;"><img class="adm-capa" src="../<?= sanitize(capaJogo($j['imagem_capa'])) ?>" alt=""></td>
            <td><strong><?= sanitize($j['nome']) ?></strong></td>
            <td class="adm-mini" style="font-size:13px;"><?= sanitize($j['categorias'] ?? 'Nenhuma') ?></td>
            <td><?= adminNotaMeta($j['nota_metacritic']) ?></td>
            <td><a href="precos.php?id_jogo=<?= (int)$j['id_jogo'] ?>" class="btn-link">💰 <?= (int)$j['qtd_precos'] ?> ofertas</a></td>
            <td class="dir">
              <a href="jogo_form.php?id=<?= (int)$j['id_jogo'] ?>" class="btn-link aviso">Editar</a>
              &nbsp;
              <form method="POST" action="jogos.php" class="adm-form-inline" onsubmit="return confirm('Tem certeza que deseja excluir este jogo?');">
                <?= csrfField() ?>
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id_jogo" value="<?= (int)$j['id_jogo'] ?>">
                <button type="submit" class="btn-link perigo">Excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
