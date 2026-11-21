<?php
/**
 * Cria (ou promove) um administrador pela linha de comando. Substitui os usuários padrão
 * com senha conhecida (admin123/gamer123) que existiam no schema.sql.
 *
 * Uso:
 *   C:\xampp\php\php.exe robo\criar_admin.php --email=voce@exemplo.com --nome="Seu Nome" --username=voce [--senha=SuaSenha]
 *
 * Sem --senha o script gera uma senha aleatória e mostra uma única vez.
 * Se o e-mail já existir, a conta é promovida a administrador (e a senha só muda se você passar --senha).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Somente pela linha de comando.');
}

require_once __DIR__ . '/../config/db.php';

$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)=(.*)$/s', $a, $m)) $args[$m[1]] = $m[2];
}
$email = trim($args['email'] ?? '');
$nome = trim($args['nome'] ?? 'Administrador');
$username = trim($args['username'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
    fwrite(STDERR, "Uso: php robo/criar_admin.php --email=voce@exemplo.com --nome=\"Seu Nome\" --username=voce [--senha=...]\n"
                 . "(username: 3 a 30 caracteres: letras, números, _ ou .)\n");
    exit(1);
}

$pdo = getDB();
if (!$pdo) { fwrite(STDERR, "Sem conexão com o banco. O MySQL está ligado?\n"); exit(1); }

$senha = $args['senha'] ?? '';
$gerada = false;
if ($senha === '') { $senha = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '='); $gerada = true; }
if (strlen($senha) < 6) { fwrite(STDERR, "A senha precisa ter pelo menos 6 caracteres.\n"); exit(1); }

$ex = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE email = ?');
$ex->execute([$email]);
$id = $ex->fetchColumn();

if ($id) {
    $pdo->prepare("UPDATE usuarios SET tipo_usuario = 'administrador', email_verificado = 1 WHERE id_usuario = ?")->execute([$id]);
    if (isset($args['senha'])) $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id_usuario = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $id]);
    echo "Conta existente promovida a administrador (id $id)." . (isset($args['senha']) ? " Senha atualizada.\n" : " A senha não foi alterada.\n");
    exit(0);
}

$dup = $pdo->prepare('SELECT 1 FROM usuarios WHERE username = ?');
$dup->execute([$username]);
if ($dup->fetchColumn()) { fwrite(STDERR, "O username \"$username\" já está em uso.\n"); exit(1); }

$pdo->prepare("INSERT INTO usuarios (nome, username, email, email_verificado, senha, tipo_usuario) VALUES (?,?,?,1,?, 'administrador')")
    ->execute([$nome, $username, $email, password_hash($senha, PASSWORD_DEFAULT)]);
echo "Administrador criado: $email (username $username).\n";
if ($gerada) echo "Senha gerada (anote agora, ela não aparece de novo): $senha\n";
