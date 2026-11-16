<?php
/**
 * Cadastro de Novos Usuários - LootPrice
 * Inserção segura no banco de dados com senha criptografada (BCrypt)
 */
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$erro = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmaSenha = $_POST['confirma_senha'] ?? '';

    if (empty($nome) || empty($email) || empty($senha)) {
        $erro = 'Todos os campos obrigatórios devem ser preenchidos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Formato de e-mail inválido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve conter no mínimo 6 caracteres.';
    } elseif ($senha !== $confirmaSenha) {
        $erro = 'As senhas informadas não coincidem.';
    } else {
        $pdo = getDB();
        if (!$pdo) {
            $erro = 'Erro de conexão com o banco de dados. Verifique o XAMPP.';
        } else {
            // Verifica se o e-mail já existe
            $check = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = :email LIMIT 1");
            $check->execute(['email' => $email]);
            if ($check->fetch()) {
                $erro = 'Este e-mail já está cadastrado em nosso sistema.';
            } else {
                // Criação do novo usuário
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo_usuario, criado_em) VALUES (:nome, :email, :senha, 'usuario', NOW())");
                $sucesso = $stmt->execute([
                    'nome' => $nome,
                    'email' => $email,
                    'senha' => $hash
                ]);

                if ($sucesso) {
                    $novoId = $pdo->lastInsertId();
                    // Autentica automaticamente
                    loginUser([
                        'id_usuario' => $novoId,
                        'nome' => $nome,
                        'email' => $email,
                        'tipo_usuario' => 'usuario'
                    ]);

                    setFlash('success', 'Cadastro realizado com sucesso! Bem-vindo(a) ao LootPrice.');
                    header('Location: index.php');
                    exit;
                } else {
                    $erro = 'Ocorreu um erro ao cadastrar sua conta. Tente novamente.';
                }
            }
        }
    }
}

$pageTitle = 'Criar Conta - LootPrice';
$basePath = '';

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="sites/login/css_login/cadastro.css">

<main style="display: flex; justify-content: center; align-items: center; min-height: 70vh; padding: 20px;">
  
  <div class="container" style="max-width: 440px; width: 100%; margin: 0 auto;">
    <h2 style="text-align: center; margin-bottom: 25px; color: var(--title-color);">📝 Criar Conta</h2>

    <?php if ($erro): ?>
      <div style="background: rgba(231, 76, 60, 0.2); border: 1px solid #e74c3c; color: #ff6b6b; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
        <?= sanitize($erro) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="cadastro.php">
      
      <div style="margin-bottom: 16px;">
        <label for="nome" style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px;">Nome Completo / Apelido:</label>
        <input type="text" id="nome" name="nome" required placeholder="Como quer ser chamado?"
               value="<?= isset($_POST['nome']) ? sanitize($_POST['nome']) : '' ?>"
               style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
      </div>

      <div style="margin-bottom: 16px;">
        <label for="email" style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px;">E-mail:</label>
        <input type="email" id="email" name="email" required placeholder="seuemail@exemplo.com"
               value="<?= isset($_POST['email']) ? sanitize($_POST['email']) : '' ?>"
               style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
      </div>

      <div style="margin-bottom: 16px;">
        <label for="senha" style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px;">Senha (mínimo 6 caracteres):</label>
        <input type="password" id="senha" name="senha" required placeholder="Crie uma senha forte"
               style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
      </div>

      <div style="margin-bottom: 20px;">
        <label for="confirma_senha" style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px;">Confirmar Senha:</label>
        <input type="password" id="confirma_senha" name="confirma_senha" required placeholder="Repita a senha"
               style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text); box-sizing: border-box;">
      </div>

      <button type="submit" class="btn" style="width: 100%; padding: 12px; font-weight: 700; border-radius: 8px; cursor: pointer; border: none; background: var(--btn-bg); color: #fff; font-size: 15px;">
        Finalizar Cadastro
      </button>

    </form>

    <div class="extra-links" style="margin-top: 20px; text-align: center; font-size: 14px;">
      <p style="color: var(--text-color);">Já possui uma conta? <a href="login.php" style="color: #a78bfa; font-weight: 600;">Faça Login</a></p>
    </div>

  </div>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
