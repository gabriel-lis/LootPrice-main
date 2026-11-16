<?php
/**
 * Gerenciamento de Usuários - Admin
 * Substitui o mockup anterior do localStorage por controle real no Banco de Dados
 * LootPrice (RN08, RN09, RN10)
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin('../login.php');

$pdo = getDB();
$currentUser = currentUser();

// Alteração de perfil (Promover / Rebaixar)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'alterar_tipo') {
    $idUsuario = (int)$_POST['id_usuario'];
    $novoTipo = $_POST['tipo_usuario'];

    if (in_array($novoTipo, ['usuario', 'administrador'])) {
        $st = $pdo->prepare("UPDATE usuarios SET tipo_usuario = :tipo WHERE id_usuario = :id");
        $st->execute(['tipo' => $novoTipo, 'id' => $idUsuario]);
        setFlash('success', 'Nível de acesso atualizado com sucesso.');
        header('Location: usuarios.php');
        exit;
    }
}

// Exclusão de usuário
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir_usuario') {
    $idUsuario = (int)$_POST['id_usuario'];
    
    // Impede auto-exclusão
    if ($idUsuario === (int)$currentUser['id_usuario']) {
        setFlash('error', 'Você não pode excluir sua própria conta de administrador.');
    } else {
        $st = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id");
        $st->execute(['id' => $idUsuario]);
        setFlash('success', 'Usuário removido do sistema.');
    }
    header('Location: usuarios.php');
    exit;
}

$usuarios = $pdo ? $pdo->query("SELECT * FROM usuarios ORDER BY criado_em DESC")->fetchAll() : [];

$pageTitle = 'Gerenciar Usuários - Admin LootPrice';
$basePath = '../';

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1000px; margin: 30px auto; padding: 0 20px;">
  
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
      <a href="index.php" style="color: var(--search-placeholder); text-decoration: none; font-size: 13px;">← Voltar ao Dashboard</a>
      <h1 style="text-align: left; margin: 5px 0 0 0; font-size: 24px;">👥 Gerenciar Usuários do Sistema</h1>
      <p style="color: var(--search-placeholder); margin: 4px 0 0 0; font-size: 13px;">
        Usuários registrados salvos no banco de dados MySQL (tabela <code>usuarios</code>).
      </p>
    </div>
  </div>

  <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; overflow-x: auto;">
    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
      <thead>
        <tr style="border-bottom: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02); color: var(--search-placeholder);">
          <th style="padding: 12px 16px;">ID</th>
          <th style="padding: 12px 16px;">Nome</th>
          <th style="padding: 12px 16px;">E-mail</th>
          <th style="padding: 12px 16px;">Tipo de Perfil</th>
          <th style="padding: 12px 16px;">Data de Cadastro</th>
          <th style="padding: 12px 16px; text-align: right;">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u): 
          $isAdmin = ($u['tipo_usuario'] === 'administrador');
          $isMe = ($u['id_usuario'] == $currentUser['id_usuario']);
        ?>
          <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
            <td style="padding: 12px 16px; color: var(--search-placeholder);">#<?= $u['id_usuario'] ?></td>
            <td style="padding: 12px 16px;">
              <strong><?= sanitize($u['nome']) ?></strong>
              <?php if ($isMe): ?>
                <span style="font-size: 11px; color: #5ee7df; margin-left: 4px;">(Você)</span>
              <?php endif; ?>
            </td>
            <td style="padding: 12px 16px;"><?= sanitize($u['email']) ?></td>
            <td style="padding: 12px 16px;">
              <form method="POST" action="usuarios.php" style="display: inline;">
                <input type="hidden" name="acao" value="alterar_tipo">
                <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                
                <select name="tipo_usuario" onchange="this.form.submit()" <?= $isMe ? 'disabled' : '' ?>
                        style="background: <?= $isAdmin ? 'rgba(255, 200, 87, 0.2)' : 'rgba(255, 255, 255, 0.05)' ?>; color: <?= $isAdmin ? '#ffc857' : 'var(--text-color)' ?>; border: 1px solid <?= $isAdmin ? '#ffc857' : 'rgba(255,255,255,0.1)' ?>; border-radius: 6px; padding: 4px 8px; font-size: 12px; font-weight: 700; cursor: pointer;">
                  <option value="usuario" <?= !$isAdmin ? 'selected' : '' ?>>Usuário</option>
                  <option value="administrador" <?= $isAdmin ? 'selected' : '' ?>>Administrador</option>
                </select>
              </form>
            </td>
            <td style="padding: 12px 16px; color: var(--search-placeholder); font-size: 13px;">
              <?= date('d/m/Y H:i', strtotime($u['criado_em'])) ?>
            </td>
            <td style="padding: 12px 16px; text-align: right;">
              <?php if (!$isMe): ?>
                <form method="POST" action="usuarios.php" style="display: inline;" onsubmit="return confirm('Deseja realmente excluir este usuário?');">
                  <input type="hidden" name="acao" value="excluir_usuario">
                  <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                  <button type="submit" style="background: transparent; border: none; color: #ff6b6b; cursor: pointer; font-weight: 600; font-size: 13px;">
                    Excluir
                  </button>
                </form>
              <?php else: ?>
                <span style="font-size: 12px; color: var(--search-placeholder);">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
