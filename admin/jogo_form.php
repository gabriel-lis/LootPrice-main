<?php
/**
 * Formulário de Cadastro e Edição de Jogo - Admin
 * LootPrice (RN10, RN11, RF04, RF07, RN12)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';

$idJogo = (int)lp_get('id', 12);
$isEdicao = ($idJogo > 0);
$pdo = adminIniciar('jogo_form.php' . ($isEdicao ? "?id=$idJogo" : ''));

$erro = null;
$jogo = ['nome' => '', 'descricao' => '', 'data_lancamento' => '', 'nota_metacritic' => '', 'imagem_capa' => null];
$jogoCategorias = [];
$jogoPlataformas = [];
$jogoAssinaturas = [];

$ids = function (string $sql, int $id) use ($pdo): array {
    $st = $pdo->prepare($sql);
    $st->execute(['id' => $id]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
};

if ($isEdicao) {
    $stmt = $pdo->prepare("SELECT * FROM jogos WHERE id_jogo = :id LIMIT 1");
    $stmt->execute(['id' => $idJogo]);
    $res = $stmt->fetch();
    if (!$res) {
        setFlash('error', 'Jogo não encontrado.');
        header('Location: jogos.php');
        exit;
    }
    $jogo = $res;
    $jogoCategorias  = $ids("SELECT id_categoria FROM jogo_categoria WHERE id_jogo = :id", $idJogo);
    $jogoPlataformas = $ids("SELECT id_plataforma FROM jogo_plataforma WHERE id_jogo = :id", $idJogo);
    $jogoAssinaturas = $ids("SELECT id_servico FROM jogo_assinatura WHERE id_jogo = :id", $idJogo);
}

$todasCategorias = getAllCategories();
$todasPlataformas = getAllPlatforms();
$todasAssinaturas = getAllSubscriptions();

/** Só aceita ids que existem na lista (descarta lixo vindo do formulário). */
$filtrarIds = function ($enviados, array $lista, string $chave): array {
    if (!is_array($enviados)) return [];
    $validos = array_map('intval', array_column($lista, $chave));
    return array_values(array_unique(array_intersect(array_map('intval', $enviados), $validos)));
};

// Processa salvamento
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nome = lp_post('nome', 300);
    $descricao = lp_post('descricao', 5000);
    $dataTxt = lp_post('data_lancamento', 10);
    $notaTxt = lp_post('nota_metacritic', 10);
    $selCategorias  = $filtrarIds($_POST['categorias'] ?? [], $todasCategorias, 'id_categoria');
    $selPlataformas = $filtrarIds($_POST['plataformas'] ?? [], $todasPlataformas, 'id_plataforma');
    $selAssinaturas = $filtrarIds($_POST['assinaturas'] ?? [], $todasAssinaturas, 'id_servico');

    // Mantém o que foi digitado caso haja erro
    $jogo['nome'] = $nome; $jogo['descricao'] = $descricao; $jogo['data_lancamento'] = $dataTxt; $jogo['nota_metacritic'] = $notaTxt;
    $jogoCategorias = $selCategorias; $jogoPlataformas = $selPlataformas; $jogoAssinaturas = $selAssinaturas;

    $dataLancamento = null;
    $notaMetacritic = null;
    if ($nome === '') {
        $erro = 'O título do jogo é obrigatório.';
    } elseif (mb_strlen($nome) > 100) {
        $erro = 'O título pode ter no máximo 100 caracteres.';
    } else {
        if ($dataTxt !== '') {
            $d = DateTime::createFromFormat('!Y-m-d', $dataTxt);
            if (!$d || $d->format('Y-m-d') !== $dataTxt) $erro = 'Data de lançamento inválida.';
            else $dataLancamento = $dataTxt;
        }
        // Nota 0 é uma nota válida (só campo vazio vira "sem nota")
        if (!$erro && $notaTxt !== '') {
            if (!is_numeric($notaTxt) || (float)$notaTxt < 0 || (float)$notaTxt > 100) $erro = 'A nota Metacritic deve ser um número de 0 a 100.';
            else $notaMetacritic = (float)$notaTxt;
        }
    }

    // Upload da capa (opcional)
    $novaCapa = null;
    if (!$erro) {
        [$novaCapa, $erro] = adminSalvarImagem($_FILES['capa'] ?? [], 'fotos/jogos', 'capa', 3);
    }

    if (!$erro) {
        $capaAntiga = $isEdicao ? ($jogo['imagem_capa'] ?? null) : null;
        // Em edição, o $jogo['imagem_capa'] acima ainda é o do banco (só nome/descrição/data/nota foram sobrescritos)
        $capaFinal = $novaCapa ?? $capaAntiga;
        if (!empty($_POST['remover_capa']) && !$novaCapa) $capaFinal = null;

        try {
            $pdo->beginTransaction();
            if ($isEdicao) {
                $pdo->prepare("UPDATE jogos SET nome = :nome, descricao = :desc, data_lancamento = :dt, nota_metacritic = :nota, imagem_capa = :capa WHERE id_jogo = :id")
                    ->execute(['nome' => $nome, 'desc' => $descricao, 'dt' => $dataLancamento, 'nota' => $notaMetacritic, 'capa' => $capaFinal, 'id' => $idJogo]);
            } else {
                $pdo->prepare("INSERT INTO jogos (nome, descricao, data_lancamento, nota_metacritic, imagem_capa) VALUES (:nome, :desc, :dt, :nota, :capa)")
                    ->execute(['nome' => $nome, 'desc' => $descricao, 'dt' => $dataLancamento, 'nota' => $notaMetacritic, 'capa' => $capaFinal]);
                $idJogo = (int)$pdo->lastInsertId();
            }

            $vincular = function (string $tabela, string $colunaOutra, array $valores) use ($pdo, $idJogo) {
                $pdo->prepare("DELETE FROM $tabela WHERE id_jogo = :id")->execute(['id' => $idJogo]);
                $ins = $pdo->prepare("INSERT INTO $tabela (id_jogo, $colunaOutra) VALUES (:id, :v)");
                foreach ($valores as $v) $ins->execute(['id' => $idJogo, 'v' => $v]);
            };
            $vincular('jogo_categoria', 'id_categoria', $selCategorias);
            $vincular('jogo_plataforma', 'id_plataforma', $selPlataformas);
            $vincular('jogo_assinatura', 'id_servico', $selAssinaturas);

            $pdo->commit();

            // Capa substituída ou removida: o arquivo antigo sai do disco (se ninguém mais o usa)
            if ($capaAntiga && $capaAntiga !== $capaFinal) {
                $uso = $pdo->prepare("SELECT COUNT(*) FROM jogos WHERE imagem_capa = :c");
                $uso->execute(['c' => $capaAntiga]);
                if ((int)$uso->fetchColumn() === 0) adminApagarArquivo($capaAntiga, ['fotos/jogos/']);
            }
            setFlash('success', 'Jogo salvo com sucesso!');
            header('Location: jogos.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            adminApagarArquivo($novaCapa, ['fotos/jogos/']); // não deixa upload órfão se o banco falhou
            $erro = adminErro('salvar jogo', $e);
        }
    } elseif ($novaCapa) {
        adminApagarArquivo($novaCapa, ['fotos/jogos/']);
    }
}

$pageTitle = ($isEdicao ? 'Editar' : 'Novo') . ' Jogo - Admin LootPrice';
$basePath = '../';
$cssAdmin = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="adm-wrap adm-estreito">

  <div style="margin-bottom: 25px;">
    <a href="jogos.php" class="adm-voltar">← Voltar à lista de jogos</a>
    <h1 class="adm-title"><?= $isEdicao ? '✏️ Editar Jogo' : '➕ Cadastrar Novo Jogo' ?></h1>
  </div>

  <?php if ($erro): ?><div class="adm-aviso erro" role="alert"><?= sanitize($erro) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="adm-card" action="jogo_form.php<?= $isEdicao ? '?id=' . $idJogo : '' ?>">
    <?= csrfField() ?>

    <div class="adm-campo">
      <label for="j-nome">Título do Jogo *</label>
      <input type="text" id="j-nome" name="nome" required maxlength="100" value="<?= sanitize($jogo['nome']) ?>" placeholder="Ex: Elden Ring">
    </div>

    <div class="adm-campo">
      <label for="j-desc">Sinopse / Descrição</label>
      <textarea id="j-desc" name="descricao" rows="4" placeholder="Descrição do jogo..."><?= sanitize($jogo['descricao']) ?></textarea>
    </div>

    <div class="adm-linha3 adm-campo">
      <div>
        <label for="j-data">Data de Lançamento</label>
        <input type="date" id="j-data" name="data_lancamento" value="<?= sanitize($jogo['data_lancamento']) ?>">
      </div>
      <div>
        <label for="j-nota">Nota Metacritic (0 a 100)</label>
        <input type="number" id="j-nota" step="0.1" min="0" max="100" name="nota_metacritic" value="<?= sanitize((string)$jogo['nota_metacritic']) ?>" placeholder="Ex: 94.0">
      </div>
      <div>
        <label for="j-capa">Imagem de Capa</label>
        <input type="file" id="j-capa" name="capa" accept="image/jpeg,image/png,image/webp,image/gif">
        <div class="adm-dica">JPG, PNG, WEBP ou GIF, até 3 MB.</div>
      </div>
    </div>

    <?php if ($isEdicao): ?>
      <div class="adm-capa-atual">
        <img src="../<?= sanitize(capaJogo($jogo['imagem_capa'] ?? null)) ?>" alt="Capa atual">
        <label class="adm-check"><input type="checkbox" name="remover_capa" value="1"> Remover a capa atual (volta para a capa padrão)</label>
      </div>
    <?php endif; ?>

    <!-- Categorias -->
    <div class="adm-campo">
      <span class="adm-rotulo">Categorias / Gêneros:</span>
      <div class="adm-checks">
        <?php foreach ($todasCategorias as $cat): ?>
          <label class="adm-check"><input type="checkbox" name="categorias[]" value="<?= (int)$cat['id_categoria'] ?>" <?= in_array((int)$cat['id_categoria'], $jogoCategorias, true) ? 'checked' : '' ?>> <?= sanitize($cat['nome']) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Plataformas -->
    <div class="adm-campo">
      <span class="adm-rotulo">Plataformas Disponíveis (RF04):</span>
      <div class="adm-checks">
        <?php foreach ($todasPlataformas as $plat): ?>
          <label class="adm-check"><input type="checkbox" name="plataformas[]" value="<?= (int)$plat['id_plataforma'] ?>" <?= in_array((int)$plat['id_plataforma'], $jogoPlataformas, true) ? 'checked' : '' ?>> 🎮 <?= sanitize($plat['nome']) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Serviços de Assinatura -->
    <div class="adm-campo" style="margin-bottom: 25px;">
      <span class="adm-rotulo">Disponibilidade em Assinaturas (RF07):</span>
      <div class="adm-checks">
        <?php foreach ($todasAssinaturas as $ass): ?>
          <label class="adm-check assin"><input type="checkbox" name="assinaturas[]" value="<?= (int)$ass['id_servico'] ?>" <?= in_array((int)$ass['id_servico'], $jogoAssinaturas, true) ? 'checked' : '' ?>> <?= sanitize($ass['nome']) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="adm-rodape-form">
      <a href="jogos.php" class="btn-adm sec">Cancelar</a>
      <button type="submit" class="btn-adm">Salvar Jogo</button>
    </div>

  </form>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
