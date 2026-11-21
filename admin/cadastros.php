<?php
/**
 * Cadastros de apoio - Admin: lojas, categorias, plataformas e serviços de assinatura (CRUD).
 * LootPrice (RN10, RN11)
 *
 * Uma tela só, com uma configuração por tipo. Não deixa excluir nada que ainda esteja em uso
 * (excluir uma loja, por exemplo, apagaria todos os preços dela).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';
$pdo = adminIniciar('cadastros.php');

/**
 * tabela, chave, campos (nome => [rótulo, tipo, máx]), "uso" = consultas que contam onde o item é usado.
 * Tipos: texto | logo (arquivo de fotos/extras) | url | area
 */
$tipos = [
    'lojas' => [
        'titulo' => 'Lojas', 'singular' => 'loja', 'tabela' => 'lojas', 'chave' => 'id_loja',
        'campos' => ['nome' => ['Nome', 'texto', 100, true], 'logo' => ['Logo', 'logo', 255, false], 'url' => ['Site da loja', 'url', 255, false], 'pais' => ['País', 'texto', 50, false]],
        'uso' => ['preços' => 'SELECT COUNT(*) FROM precos WHERE id_loja = :id'],
    ],
    'categorias' => [
        'titulo' => 'Categorias', 'singular' => 'categoria', 'tabela' => 'categorias', 'chave' => 'id_categoria',
        'campos' => ['nome' => ['Nome', 'texto', 50, true]],
        'uso' => ['jogos' => 'SELECT COUNT(*) FROM jogo_categoria WHERE id_categoria = :id'],
    ],
    'plataformas' => [
        'titulo' => 'Plataformas', 'singular' => 'plataforma', 'tabela' => 'plataformas', 'chave' => 'id_plataforma',
        'campos' => ['nome' => ['Nome', 'texto', 50, true], 'icone' => ['Ícone', 'logo', 255, false]],
        'uso' => ['jogos' => 'SELECT COUNT(*) FROM jogo_plataforma WHERE id_plataforma = :id', 'preços' => 'SELECT COUNT(*) FROM precos WHERE id_plataforma = :id'],
    ],
    'assinaturas' => [
        'titulo' => 'Serviços de assinatura', 'singular' => 'serviço', 'tabela' => 'servicos_assinatura', 'chave' => 'id_servico',
        'campos' => ['nome' => ['Nome', 'texto', 100, true], 'descricao' => ['Descrição', 'area', 500, false], 'logo' => ['Logo', 'logo', 255, false]],
        'uso' => ['jogos' => 'SELECT COUNT(*) FROM jogo_assinatura WHERE id_servico = :id'],
    ],
];

// Imagens disponíveis para logo/ícone (fotos/extras, sem as variantes "dark…" do modo claro)
$logos = [];
foreach (glob(__DIR__ . '/../fotos/extras/*.{png,jpg,jpeg,webp,svg,gif}', GLOB_BRACE) ?: [] as $arq) {
    $b = basename($arq);
    if (strpos($b, 'dark') !== 0 && !preg_match('/^(light|dark)-mode|^logolt|^search|^metacritic/', $b)) $logos[] = 'fotos/extras/' . $b;
}
sort($logos);

/** Lê e valida os campos enviados. @return array [array $valores, ?string $erro] */
function lerCampos(array $cfg, array $logos): array {
    $v = [];
    foreach ($cfg['campos'] as $nome => [$rotulo, $tipo, $max, $obrig]) {
        $txt = lp_post($nome, $max + 1);
        if ($txt === '') {
            if ($obrig) return [$v, "Informe o campo \"$rotulo\"."];
            $v[$nome] = null;
            continue;
        }
        if (mb_strlen($txt) > $max) return [$v, "\"$rotulo\" pode ter no máximo $max caracteres."];
        if ($tipo === 'url' && !lp_link_valido($txt)) return [$v, "\"$rotulo\" precisa começar com http:// ou https://."];
        if ($tipo === 'logo' && !in_array($txt, $logos, true)) return [$v, "Escolha um arquivo de imagem da lista em \"$rotulo\"."];
        $v[$nome] = $txt;
    }
    return [$v, null];
}

$tipoAtual = lp_get('tipo', 20);
$editarId = (int)lp_get('editar', 12);
$erroForm = null;
$erroTipo = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $tipo = lp_post('tipo', 20);
    $acao = lp_post('acao', 12);
    $id = (int)lp_post('id', 12);
    $cfg = $tipos[$tipo] ?? null;
    $tipoAtual = $tipo;

    if (!$cfg) {
        setFlash('error', 'Cadastro desconhecido.');
        header('Location: cadastros.php');
        exit;
    }
    $tab = $cfg['tabela'];
    $pk = $cfg['chave'];

    try {
        if ($acao === 'excluir') {
            $emUso = [];
            foreach ($cfg['uso'] as $rotulo => $sql) {
                $st = $pdo->prepare($sql);
                $st->execute(['id' => $id]);
                if ($n = (int)$st->fetchColumn()) $emUso[] = "$n $rotulo";
            }
            if ($emUso) {
                setFlash('error', 'Não dá para excluir: ainda está em uso (' . implode(', ', $emUso) . ').');
            } else {
                $pdo->prepare("DELETE FROM $tab WHERE $pk = :id")->execute(['id' => $id]);
                setFlash('success', ucfirst($cfg['singular']) . ' excluído(a).');
            }
            header('Location: cadastros.php?tipo=' . $tipo);
            exit;
        }

        // criar / editar
        [$valores, $erro] = lerCampos($cfg, $logos);
        if ($erro) {
            $erroForm = $erro;
            $erroTipo = $tipo;
            $editarId = $acao === 'editar' ? $id : 0;
            $_GET['v'] = $valores; // devolve o que foi digitado
        } else {
            $cols = array_keys($valores);
            if ($acao === 'editar' && $id > 0) {
                $set = implode(', ', array_map(fn($c) => "$c = :$c", $cols));
                $pdo->prepare("UPDATE $tab SET $set WHERE $pk = :__id")->execute($valores + ['__id' => $id]);
                setFlash('success', ucfirst($cfg['singular']) . ' atualizado(a).');
            } else {
                $pdo->prepare("INSERT INTO $tab (" . implode(', ', $cols) . ") VALUES (" . implode(', ', array_map(fn($c) => ":$c", $cols)) . ")")->execute($valores);
                setFlash('success', ucfirst($cfg['singular']) . ' cadastrado(a).');
            }
            header('Location: cadastros.php?tipo=' . $tipo);
            exit;
        }
    } catch (PDOException $e) {
        // 23000 = nome repetido (UNIQUE) em categorias/plataformas
        $erroForm = ($e->getCode() === '23000') ? 'Já existe um cadastro com esse nome.' : adminErro('salvar cadastro', $e);
        $erroTipo = $tipo;
    }
}

$pageTitle = 'Lojas, Categorias e Assinaturas - Admin LootPrice';
$basePath = '../';
$cssAdmin = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="adm-wrap">

  <div class="adm-head">
    <div>
      <a href="index.php" class="adm-voltar">← Voltar ao Dashboard</a>
      <h1 class="adm-title">🏷️ Lojas, Categorias, Plataformas e Assinaturas</h1>
      <p class="adm-sub">Cadastros usados nos jogos e nos preços. O que já está em uso não pode ser excluído.</p>
    </div>
  </div>

  <?php foreach ($tipos as $chaveTipo => $cfg):
    $linhas = $pdo->query("SELECT * FROM {$cfg['tabela']} ORDER BY nome ASC")->fetchAll();
    $editando = null;
    if ($tipoAtual === $chaveTipo && $editarId > 0) {
        foreach ($linhas as $l) if ((int)$l[$cfg['chave']] === $editarId) $editando = $l;
    }
    $digitado = ($erroTipo === $chaveTipo && isset($_GET['v']) && is_array($_GET['v'])) ? $_GET['v'] : null;
    $valorCampo = fn(string $c) => sanitize((string)($digitado[$c] ?? $editando[$c] ?? ''));
  ?>
    <section class="adm-card" id="<?= $chaveTipo ?>">
      <h3><?= sanitize($cfg['titulo']) ?> (<?= count($linhas) ?>)</h3>

      <div class="adm-tabela-wrap" style="margin-bottom: 18px;">
        <table class="adm-tabela">
          <thead>
            <tr>
              <?php foreach ($cfg['campos'] as [$rotulo]): ?><th><?= sanitize($rotulo) ?></th><?php endforeach; ?>
              <th class="dir">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$linhas): ?><tr><td colspan="<?= count($cfg['campos']) + 1 ?>" class="adm-vazio">Nada cadastrado.</td></tr><?php endif; ?>
            <?php foreach ($linhas as $l): ?>
              <tr>
                <?php foreach ($cfg['campos'] as $nomeCampo => [$rotulo, $tipoCampo]): ?>
                  <td>
                    <?php if ($tipoCampo === 'logo' && !empty($l[$nomeCampo])): ?>
                      <img src="../<?= sanitize($l[$nomeCampo]) ?>" alt="" style="height:24px; width:auto; vertical-align:middle; background: var(--surface-2); border-radius:4px; padding:2px;"> <span class="adm-mini"><?= sanitize(basename($l[$nomeCampo])) ?></span>
                    <?php else: ?>
                      <?= sanitize((string)($l[$nomeCampo] ?? '')) ?: '<span class="adm-mini">—</span>' ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <td class="dir">
                  <a href="cadastros.php?tipo=<?= $chaveTipo ?>&amp;editar=<?= (int)$l[$cfg['chave']] ?>#<?= $chaveTipo ?>" class="btn-link aviso">Editar</a>
                  <form method="POST" action="cadastros.php" class="adm-form-inline" onsubmit="return confirm('Excluir este cadastro?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="tipo" value="<?= $chaveTipo ?>">
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?= (int)$l[$cfg['chave']] ?>">
                    <button type="submit" class="btn-link perigo">Excluir</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($erroTipo === $chaveTipo && $erroForm): ?><div class="adm-aviso erro" role="alert"><?= sanitize($erroForm) ?></div><?php endif; ?>

      <form method="POST" action="cadastros.php#<?= $chaveTipo ?>">
        <?= csrfField() ?>
        <input type="hidden" name="tipo" value="<?= $chaveTipo ?>">
        <input type="hidden" name="acao" value="<?= $editando ? 'editar' : 'criar' ?>">
        <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int)$editando[$cfg['chave']] ?>"><?php endif; ?>
        <strong style="display:block; margin-bottom:10px; font-size:14px;"><?= $editando ? 'Editando: ' . sanitize($editando['nome']) : 'Novo(a) ' . sanitize($cfg['singular']) ?></strong>

        <div class="adm-linha2">
          <?php foreach ($cfg['campos'] as $nomeCampo => [$rotulo, $tipoCampo, $max, $obrig]): ?>
            <div class="adm-campo">
              <label for="c-<?= $chaveTipo . '-' . $nomeCampo ?>"><?= sanitize($rotulo) ?><?= $obrig ? ' *' : '' ?></label>
              <?php if ($tipoCampo === 'logo'): ?>
                <select id="c-<?= $chaveTipo . '-' . $nomeCampo ?>" name="<?= $nomeCampo ?>">
                  <option value="">(nenhum)</option>
                  <?php foreach ($logos as $lg): ?>
                    <option value="<?= sanitize($lg) ?>" <?= $valorCampo($nomeCampo) === sanitize($lg) ? 'selected' : '' ?>><?= sanitize(basename($lg)) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($tipoCampo === 'area'): ?>
                <textarea id="c-<?= $chaveTipo . '-' . $nomeCampo ?>" name="<?= $nomeCampo ?>" rows="2" maxlength="<?= $max ?>"><?= $valorCampo($nomeCampo) ?></textarea>
              <?php else: ?>
                <input type="<?= $tipoCampo === 'url' ? 'url' : 'text' ?>" id="c-<?= $chaveTipo . '-' . $nomeCampo ?>" name="<?= $nomeCampo ?>" maxlength="<?= $max ?>" <?= $obrig ? 'required' : '' ?> value="<?= $valorCampo($nomeCampo) ?>">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="adm-rodape-form">
          <?php if ($editando): ?><a href="cadastros.php?tipo=<?= $chaveTipo ?>#<?= $chaveTipo ?>" class="btn-adm sec">Cancelar edição</a><?php endif; ?>
          <button type="submit" class="btn-adm"><?= $editando ? 'Salvar alterações' : 'Cadastrar' ?></button>
        </div>
      </form>
    </section>
  <?php endforeach; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
