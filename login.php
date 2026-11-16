<?php
/**
 * Login de Usuários - LootPrice
 * Autenticação via Banco de Dados com Sessão PHP e password_verify
 */
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$erro = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        $pdo = getDB();
        if (!$pdo) {
            $erro = 'Erro de conexão com o banco de dados. Verifique se o MySQL no XAMPP está ativo.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Autenticação bem-sucedida
                loginUser($usuario);
                setFlash('success', 'Bem-vindo(a) de volta, ' . $usuario['nome'] . '!');

                if ($usuario['tipo_usuario'] === 'administrador') {
                    header('Location: admin/index.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $erro = 'E-mail ou senha incorretos.';
            }
        }
    }
}

$pageTitle = 'Entrar na Conta - LootPrice';
$basePath = '';

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="sites/login/css_login/cadastro.css">

<main style="display: flex; justify-content: center; align-items: center; min-height: 70vh; padding: 20px;">
  
  <div class="container" style="max-width: 420px; width: 100%; margin: 0 auto;">
    <h2 style="text-align: center; margin-bottom: 25px; color: var(--title-color);">🕹️ Login</h2>

    <?php if ($erro): ?>
      <div style="background: rgba(231, 76, 60, 0.2); border: 1px solid #e74c3c; color: #ff6b6b; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
        <?= sanitize($erro) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      
      <div style="margin-bottom: 16px;">
        <label for="email" style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px;">E-mail:</label>
        <input type="email" id="email" name="email" required placeholder="seuemail@exemplo.com"
               value="<?= isset($_POST['email']) ? sanitize($_POST['email']) : '' ?>"
               style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
      </div>

      <div style="margin-bottom: 20px;">
        <label for="senha" style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px;">Senha:</label>
        <input type="password" id="senha" name="senha" required placeholder="Sua senha"
               style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
      </div>

      <button type="submit" class="btn" style="width: 100%; padding: 12px; font-weight: 700; border-radius: 8px; cursor: pointer; border: none; background: var(--btn-bg); color: #fff; font-size: 15px;">
        Entrar
      </button>

    </form>

    <div class="extra-links" style="margin-top: 20px; text-align: center; font-size: 14px;">
      <p style="color: var(--text-color);">Ainda não tem conta? <a href="cadastro.php" style="color: #a78bfa; font-weight: 600;">Registrar-se</a></p>
    </div>

    <!-- Dica de Contas Padrão para apresentação/avaliação -->
    <div style="margin-top: 25px; padding: 12px; background: rgba(255,255,255,0.03); border-radius: 8px; font-size: 12px; color: var(--search-placeholder);">
      <strong>Contas de Teste:</strong><br>
      • Admin: <code>admin@lootprice.com</code> | Senha: <code>admin123</code><br>
      • Usuário: <code>gamer@lootprice.com</code> | Senha: <code>gamer123</code>
    </div>

  </div>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
