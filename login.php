<?php
/**
 * Entrar / Criar conta - LootPrice
 * Página única com duas abas (Entrar e Criar minha conta).
 * Login: sessão PHP + password_verify. Cadastro: senha com BCrypt (password_hash).
 */
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Volta para o jogo que o visitante estava vendo (só aceita jogo.php?id=N)
if (isset($_GET['voltar']) && preg_match('/^jogo\.php\?id=\d+$/', $_GET['voltar'])) {
    $_SESSION['voltar_para'] = $_GET['voltar'];
}
function destinoPosLogin(string $padrao): string {
    $d = $_SESSION['voltar_para'] ?? null;
    unset($_SESSION['voltar_para']);
    return $d ?: $padrao;
}

$sexosValidos = ['masculino', 'feminino', 'nao_informado'];
$erro = null;
$aba = (($_GET['aba'] ?? '') === 'cadastro') ? 'cadastro' : 'login';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $acao = ($_POST['acao'] ?? '') === 'cadastro' ? 'cadastro' : 'login';
    $aba = $acao;
    if ($pdoInicial = getDB()) ensureVerificacaoSchema($pdoInicial); // coluna email_verificado

    if ($acao === 'login') {
        $login = trim($_POST['login'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if ($login === '' || $senha === '') {
            $erro = 'Por favor, preencha todos os campos.';
        } else {
            $pdo = getDB();
            if (!$pdo) {
                $erro = 'Erro de conexão com o banco de dados. Verifique se o MySQL no XAMPP está ativo.';
            } else {
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :login OR username = :login2 LIMIT 1");
                $stmt->execute(['login' => $login, 'login2' => $login]);
                $usuario = $stmt->fetch();

                if ($usuario && password_verify($senha, $usuario['senha'])) {
                    loginUser($usuario);
                    setFlash('success', 'Bem-vindo(a) de volta, ' . $usuario['nome'] . '!');
                    header('Location: ' . destinoPosLogin($usuario['tipo_usuario'] === 'administrador' ? 'admin/index.php' : 'index.php'));
                    exit;
                }
                $erro = 'E-mail/usuário ou senha incorretos.';
            }
        }
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $sexo = $_POST['sexo'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $confirmaSenha = $_POST['confirma_senha'] ?? '';

        if ($nome === '' || $username === '' || $sexo === '' || $email === '' || $senha === '') {
            $erro = 'Todos os campos obrigatórios devem ser preenchidos.';
        } elseif (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
            $erro = 'O username deve ter de 3 a 30 caracteres (letras, números, "_" ou ".").';
        } elseif (!in_array($sexo, $sexosValidos, true)) {
            $erro = 'Selecione uma opção válida para o sexo.';
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
                $check = $pdo->prepare("SELECT email, username FROM usuarios WHERE email = :email OR username = :username LIMIT 1");
                $check->execute(['email' => $email, 'username' => $username]);
                $existente = $check->fetch();
                if ($existente) {
                    $erro = (strcasecmp($existente['email'], $email) === 0)
                        ? 'Este e-mail já está cadastrado em nosso sistema.'
                        : 'Este username já está em uso. Escolha outro.';
                } else {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, username, sexo, email, senha, tipo_usuario, criado_em) VALUES (:nome, :username, :sexo, :email, :senha, 'usuario', NOW())");
                    $sucesso = $stmt->execute([
                        'nome' => $nome,
                        'username' => $username,
                        'sexo' => $sexo,
                        'email' => $email,
                        'senha' => $hash
                    ]);

                    if ($sucesso) {
                        $novoId = (int)$pdo->lastInsertId();
                        loginUser([
                            'id_usuario' => $novoId,
                            'nome' => $nome,
                            'username' => $username,
                            'foto_perfil' => null,
                            'foto_fundo' => null,
                            'email' => $email,
                            'email_verificado' => 0,
                            'tipo_usuario' => 'usuario'
                        ]);
                        // Manda o código de confirmação; se o envio falhar, a tela de confirmação permite reenviar
                        [$enviou] = enviarCodigoVerificacao($pdo, $novoId);
                        setFlash($enviou ? 'success' : 'info',
                            $enviou ? 'Conta criada! Enviamos um código para o seu e-mail.'
                                    : 'Conta criada! Não conseguimos enviar o código agora; peça um novo na próxima tela.');
                        header('Location: verificar_email.php'); // o "voltar_para" continua guardado na sessão
                        exit;
                    }
                    $erro = 'Ocorreu um erro ao cadastrar sua conta. Tente novamente.';
                }
            }
        }
    }
}

$pageTitle = ($aba === 'cadastro' ? 'Criar Conta' : 'Entrar') . ' - LootPrice';
$basePath = '';
$hideSearch = true;

require_once __DIR__ . '/includes/header.php';

$sexoAtual = $_POST['sexo'] ?? '';
$v = fn(string $k) => isset($_POST[$k]) ? sanitize($_POST[$k]) : '';
?>

<link rel="stylesheet" href="sites/login/css_login/cadastro.css">
<style>
  /* Página única: sem rolagem, header de ponta a ponta e footer sempre visível */
  html, body { height: 100vh; overflow: hidden; padding-top: 0; }
  body { padding-top: 60px; box-sizing: border-box; justify-content: flex-start; align-items: stretch; }
  footer { flex-shrink: 0; padding: 12px 24px; }
  .btn-entrar { display: none; } /* já estamos na página de entrar */

  .auth-main { flex: 1; min-height: 0; display: flex; justify-content: center; align-items: center; padding: 12px 20px; }
  .auth-main .container { width: 100%; max-width: 480px; padding: 22px 34px 26px; margin: 0 auto; max-height: 100%; overflow-y: auto; }

  /* Abas */
  .auth-tabs { display: flex; justify-content: center; gap: 10px; margin-bottom: 20px; }
  .auth-tab {
    border: none; cursor: pointer; font: inherit; font-weight: 700; font-size: 15px;
    padding: 11px 24px; border-radius: 999px; transition: .2s;
    background: rgba(142, 45, 226, .15); color: var(--label-color);
  }
  .auth-tab:hover { background: rgba(142, 45, 226, .3); }
  .auth-tab[aria-selected="true"] { background: var(--btn-bg); color: var(--btn-text); box-shadow: 0 4px 14px var(--btn-hover-shadow); }

  .auth-panel[hidden] { display: none; }
  .auth-panel h2 { text-align: center; color: var(--title-color); margin-bottom: 4px; font-size: 20px; }
  .auth-sub { text-align: center; font-size: 14px; color: var(--label-color); margin-bottom: 16px; }

  .auth-main .campo { margin-bottom: 11px; text-align: left; }
  .auth-main .campo label { display: block; margin: 0 0 5px; font-weight: 600; font-size: 14px; }
  .auth-main .campo input, .auth-main .campo select { margin: 0; padding: 11px 14px; font-size: 16px; }
  .auth-main button.btn { margin-top: 12px; padding: 13px; font-size: 16px; }

  .senha-wrap { position: relative; }
  .senha-wrap input { padding-right: 44px; }
  .senha-toggle { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 6px; color: var(--label-color); display: flex; }
  .senha-toggle svg { width: 20px; height: 20px; }

  .auth-erro { background: rgba(231,76,60,.2); border: 1px solid #e74c3c; color: #ff6b6b; padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; font-size: 14px; text-align: left; }
  .auth-troca { text-align: center; font-size: 14px; margin-top: 14px; color: var(--text-color); }
  .auth-troca button { background: none; border: none; cursor: pointer; font: inherit; font-weight: 600; color: #a78bfa; text-decoration: underline; }
</style>

<main class="auth-main">
  <div class="container">

    <div class="auth-tabs" role="tablist" aria-label="Acesso à conta">
      <button type="button" class="auth-tab" id="tab-login" role="tab" aria-controls="painel-login"
              aria-selected="<?= $aba === 'login' ? 'true' : 'false' ?>">Entrar</button>
      <button type="button" class="auth-tab" id="tab-cadastro" role="tab" aria-controls="painel-cadastro"
              aria-selected="<?= $aba === 'cadastro' ? 'true' : 'false' ?>">Criar minha conta</button>
    </div>

    <?php if ($erro): ?>
      <div class="auth-erro" role="alert"><?= sanitize($erro) ?></div>
    <?php endif; ?>

    <!-- ENTRAR -->
    <section class="auth-panel" id="painel-login" role="tabpanel" aria-labelledby="tab-login" <?= $aba === 'login' ? '' : 'hidden' ?>>
      <h2>Que bom te ver de novo!</h2>
      <p class="auth-sub">Entre com seu <strong>e-mail ou username</strong> e sua senha.</p>

      <form method="POST" action="login.php">
        <input type="hidden" name="acao" value="login">

        <div class="campo">
          <label for="login">E-mail ou username</label>
          <input type="text" id="login" name="login" required placeholder="Digite seu e-mail ou username"
                 autocomplete="username" value="<?= $aba === 'login' ? $v('login') : '' ?>">
        </div>

        <div class="campo">
          <label for="senha-login">Senha</label>
          <div class="senha-wrap">
            <input type="password" id="senha-login" name="senha" required placeholder="Sua senha" autocomplete="current-password">
            <button type="button" class="senha-toggle" data-alvo="senha-login" aria-label="Mostrar ou ocultar senha">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn">Acessar</button>
        <p class="auth-troca" style="margin-top: 10px;"><a href="esqueci_senha.php" style="color: var(--accent-text); font-weight: 600;">Esqueci minha senha</a></p>
      </form>

      <p class="auth-troca">Ainda não tem conta? <button type="button" data-ir="cadastro">Criar minha conta</button></p>
    </section>

    <!-- CRIAR CONTA -->
    <section class="auth-panel" id="painel-cadastro" role="tabpanel" aria-labelledby="tab-cadastro" <?= $aba === 'cadastro' ? '' : 'hidden' ?>>
      <h2>Crie sua conta</h2>
      <p class="auth-sub">Leva menos de um minuto.</p>

      <form method="POST" action="login.php?aba=cadastro">
        <input type="hidden" name="acao" value="cadastro">

        <div class="campo">
          <label for="nome">Nome completo</label>
          <input type="text" id="nome" name="nome" required maxlength="100" placeholder="Seu nome completo"
                 value="<?= $aba === 'cadastro' ? $v('nome') : '' ?>">
        </div>

        <div class="campo">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required minlength="3" maxlength="30"
                 pattern="[A-Za-z0-9_.]{3,30}" title="3 a 30 caracteres: letras, números, _ ou ."
                 placeholder="Como será exibido no site" value="<?= $aba === 'cadastro' ? $v('username') : '' ?>">
        </div>

        <div class="campo">
          <label for="sexo">Sexo</label>
          <select id="sexo" name="sexo" required>
            <option value="" disabled <?= $sexoAtual === '' ? 'selected' : '' ?>>Selecione...</option>
            <option value="masculino" <?= $sexoAtual === 'masculino' ? 'selected' : '' ?>>Masculino</option>
            <option value="feminino" <?= $sexoAtual === 'feminino' ? 'selected' : '' ?>>Feminino</option>
            <option value="nao_informado" <?= $sexoAtual === 'nao_informado' ? 'selected' : '' ?>>Prefiro não responder</option>
          </select>
        </div>

        <div class="campo">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" required placeholder="seuemail@exemplo.com"
                 autocomplete="email" value="<?= $aba === 'cadastro' ? $v('email') : '' ?>">
        </div>

        <div class="campo">
          <label for="senha-cad">Senha (mínimo 6 caracteres)</label>
          <div class="senha-wrap">
            <input type="password" id="senha-cad" name="senha" required minlength="6" placeholder="Crie uma senha forte" autocomplete="new-password">
            <button type="button" class="senha-toggle" data-alvo="senha-cad" aria-label="Mostrar ou ocultar senha">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div class="campo">
          <label for="confirma_senha">Confirmar senha</label>
          <input type="password" id="confirma_senha" name="confirma_senha" required placeholder="Repita a senha" autocomplete="new-password">
        </div>

        <button type="submit" class="btn">Finalizar cadastro</button>
      </form>

      <p class="auth-troca">Já tem conta? <button type="button" data-ir="login">Entrar</button></p>
    </section>

  </div>
</main>

<script>
(function () {
  var abas = { login: 'tab-login', cadastro: 'tab-cadastro' };
  var paineis = { login: 'painel-login', cadastro: 'painel-cadastro' };

  function mostrar(nome) {
    Object.keys(abas).forEach(function (k) {
      document.getElementById(abas[k]).setAttribute('aria-selected', k === nome ? 'true' : 'false');
      document.getElementById(paineis[k]).hidden = (k !== nome);
    });
    var erro = document.querySelector('.auth-erro');
    if (erro) erro.remove();
    document.title = (nome === 'cadastro' ? 'Criar Conta' : 'Entrar') + ' - LootPrice';
    try { history.replaceState(null, '', nome === 'cadastro' ? 'login.php?aba=cadastro' : 'login.php'); } catch (e) {}
  }

  Object.keys(abas).forEach(function (k) {
    document.getElementById(abas[k]).addEventListener('click', function () { mostrar(k); });
  });
  document.querySelectorAll('[data-ir]').forEach(function (b) {
    b.addEventListener('click', function () { mostrar(b.dataset.ir); });
  });
  document.querySelectorAll('.senha-toggle').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = document.getElementById(b.dataset.alvo);
      i.type = i.type === 'password' ? 'text' : 'password';
    });
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
