<?php
/**
 * Gerenciamento de Usuários - Admin
 * LootPrice (RN08, RN09, RN10)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';
$pdo = adminIniciar('usuarios.php');
$currentUser = currentUser();
$meuId = (int)$currentUser['id_usuario'];

$acao = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') ? lp_post('acao', 20) : '';

// Alteração de perfil (Promover / Rebaixar)
if ($acao === 'alterar_tipo') {
    $idUsuario = (int)lp_post('id_usuario', 12);
    $novoTipo = lp_post('tipo_usuario', 20);

    if (!in_array($novoTipo, ['usuario', 'administrador'], true)) {
        setFlash('error', 'Tipo de usuário inválido.');
    } elseif ($idUsuario === $meuId && $novoTipo !== 'administrador') {
        // O servidor confere (não só o select desabilitado): o admin não pode rebaixar a si mesmo
        setFlash('error', 'Você não pode rebaixar a sua própria conta. Peça a outro administrador.');
    } else {
        $alvo = $pdo->prepare("SELECT tipo_usuario FROM usuarios WHERE id_usuario = :id");
        $alvo->execute(['id' => $idUsuario]);
        $tipoAtual = $alvo->fetchColumn();
        if ($tipoAtual === false) {
            setFlash('error', 'Usuário não encontrado.');
        } elseif ($tipoAtual === 'administrador' && $novoTipo !== 'administrador' && lp_conta_admins($pdo) <= 1) {
            setFlash('error', 'O sistema precisa de pelo menos um administrador.');
        } else {
            $pdo->prepare("UPDATE usuarios SET tipo_usuario = :tipo WHERE id_usuario = :id")->execute(['tipo' => $novoTipo, 'id' => $idUsuario]);
            setFlash('success', 'Nível de acesso atualizado com sucesso.');
        }
    }
    header('Location: usuarios.php');
    exit;
}

// Exclusão de usuário
if ($acao === 'excluir_usuario') {
    $idUsuario = (int)lp_post('id_usuario', 12);

    if ($idUsuario === $meuId) {
        setFlash('error', 'Você não pode excluir sua própria conta de administrador.');
    } else {
        $st = $pdo->prepare("SELECT foto_perfil, foto_fundo, tipo_usuario FROM usuarios WHERE id_usuario = :id");
        $st->execute(['id' => $idUsuario]);
        $alvo = $st->fetch();
        if (!$alvo) {
            setFlash('error', 'Usuário não encontrado.');
        } elseif ($alvo['tipo_usuario'] === 'administrador' && lp_conta_admins($pdo) <= 1) {
            setFlash('error', 'O sistema precisa de pelo menos um administrador.');
        } else {
            $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id")->execute(['id' => $idUsuario]);
            // Remove as fotos do usuário do disco
            adminApagarArquivo($alvo['foto_perfil'], ['fotos/perfis/']);
            adminApagarArquivo($alvo['foto_fundo'], ['fotos/perfis/']);
            setFlash('success', 'Usuário removido do sistema.');
        }
    }
    header('Location: usuarios.php');
    exit;
}

$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY criado_em DESC")->fetchAll();

$pageTitle = 'Gerenciar Usuários - Admin LootPrice';
$basePath = '../';
$cssAdmin = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="adm-wrap">

  <div class="adm-head">
    <div>
      <a href="index.php" class="adm-voltar">← Voltar ao Dashboard</a>
      <h1 class="adm-title">👥 Gerenciar Usuários do Sistema</h1>
      <p class="adm-sub">Contas registradas no banco de dados (tabela <code>usuarios</code>). Mudanças de nível valem na hora, sem o usuário precisar sair e entrar.</p>
    </div>
  </div>

  <div class="adm-tabela-wrap">
    <table class="adm-tabela">
      <thead>
        <tr><th>ID</th><th>Nome</th><th>E-mail</th><th>Tipo de Perfil</th><th>Data de Cadastro</th><th class="dir">Ações</th></tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u):
          $isAdm = ($u['tipo_usuario'] === 'administrador');
          $isMe = ((int)$u['id_usuario'] === $meuId);
        ?>
          <tr>
            <td class="adm-mini">#<?= (int)$u['id_usuario'] ?></td>
            <td>
              <strong><?= sanitize($u['nome']) ?></strong>
              <?php if ($isMe): ?><span class="adm-mini" style="color: var(--accent-text); margin-left:4px;">(Você)</span><?php endif; ?>
            </td>
            <td><?= sanitize($u['email']) ?></td>
            <td>
              <form method="POST" action="usuarios.php" class="adm-form-inline">
                <?= csrfField() ?>
                <input type="hidden" name="acao" value="alterar_tipo">
                <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                <select name="tipo_usuario" class="adm-select" onchange="this.form.submit()" <?= $isMe ? 'disabled' : '' ?> aria-label="Tipo de perfil de <?= sanitize($u['nome']) ?>">
                  <option value="usuario" <?= !$isAdm ? 'selected' : '' ?>>Usuário</option>
                  <option value="administrador" <?= $isAdm ? 'selected' : '' ?>>Administrador</option>
                </select>
              </form>
            </td>
            <td class="adm-mini" style="font-size:13px;"><?= $u['criado_em'] ? date('d/m/Y H:i', strtotime($u['criado_em'])) : '—' ?></td>
            <td class="dir">
              <?php if (!$isMe): ?>
                <form method="POST" action="usuarios.php" class="adm-form-inline" onsubmit="return confirm('Deseja realmente excluir este usuário?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="acao" value="excluir_usuario">
                  <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                  <button type="submit" class="btn-link perigo">Excluir</button>
                </form>
              <?php else: ?>
                <span class="adm-mini">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
