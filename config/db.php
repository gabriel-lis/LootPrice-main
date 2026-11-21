<?php
/**
 * Conexão com o Banco de Dados MySQL via PDO
 * LootPrice - Sistema Oficial
 *
 * Credenciais: valem os padrões do XAMPP (root, sem senha) SÓ para desenvolvimento local.
 * Em qualquer outro ambiente crie config/db.local.php (fora do git; modelo em
 * config/db.local.example.php) com o usuário/senha do banco e, se quiser, LP_DEV.
 */

if (is_file(__DIR__ . '/db.local.php')) {
    require_once __DIR__ . '/db.local.php';
}

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'lootprice');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('DB_PORT') || define('DB_PORT', '3306');
// Modo desenvolvimento: mostra detalhes de erro nas telas administrativas. Em produção fica false.
defined('LP_DEV') || define('LP_DEV', false);

/**
 * Registra um erro em logs/erros.log (nunca na tela). Troca o arquivo por um novo
 * ao passar de 1 MB, guardando só o anterior (erros.log.1).
 */
function lp_log_erro(string $contexto, ?Throwable $e = null): void {
    $pasta = __DIR__ . '/../logs';
    if (!is_dir($pasta)) @mkdir($pasta, 0775, true);
    $arq = $pasta . '/erros.log';
    if (is_file($arq) && filesize($arq) > 1048576) @rename($arq, $arq . '.1');
    $linha = '[' . date('Y-m-d H:i:s') . '] ' . $contexto . ($e ? ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : '') . PHP_EOL;
    @file_put_contents($arq, $linha, FILE_APPEND | LOCK_EX);
}

/**
 * Executa $criar() (CREATE TABLE / ALTER ...) uma única vez por instalação.
 * Um arquivo em logs/schema_<nome>.ok lembra que já foi feito, então as páginas
 * não rodam CREATE TABLE a cada requisição. Se recriar o banco do zero, apague logs/schema_*.ok.
 */
function lp_schema_uma_vez(string $nome, callable $criar): bool {
    static $feitos = [];
    if (isset($feitos[$nome])) return $feitos[$nome];
    $marca = __DIR__ . '/../logs/schema_' . preg_replace('/[^a-z0-9_]/i', '', $nome) . '.ok';
    if (is_file($marca)) return $feitos[$nome] = true;
    try {
        $criar();
        if (!is_dir(dirname($marca))) @mkdir(dirname($marca), 0775, true);
        @file_put_contents($marca, date('c'));
        return $feitos[$nome] = true;
    } catch (Throwable $e) {
        lp_log_erro("Falha ao preparar o banco ($nome)", $e);
        return $feitos[$nome] = false;
    }
}

/**
 * Arquivo de certificados para o cURL. Se o php.ini já configura (curl.cainfo / openssl.cafile) não mexe;
 * senão procura o do XAMPP ao lado do PHP em uso. Devolve null quando não precisa/ não achou.
 */
function lp_cafile(string $iniDoPhp = 'curl.cainfo'): ?string
{
    // $iniDoPhp: 'curl.cainfo' (cURL) ou 'openssl.cafile' (sockets/SMTP): o que o php.ini já resolve não é sobrescrito
    static $cache = [];
    if (array_key_exists($iniDoPhp, $cache)) return $cache[$iniDoPhp];
    if (ini_get($iniDoPhp)) return $cache[$iniDoPhp] = null;
    $candidatos = [
        dirname(PHP_BINARY) . '/../apache/bin/curl-ca-bundle.crt',
        dirname(PHP_BINARY) . '/extras/ssl/cacert.pem',
        'C:\xampp\apache\bin\curl-ca-bundle.crt',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
    ];
    foreach ($candidatos as $c) {
        if (is_file($c)) return $cache[$iniDoPhp] = $c;
    }
    return $cache[$iniDoPhp] = null;
}

function getDB(): ?PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            require_once __DIR__ . '/../includes/migracoes.php';
            lp_migrar_extra($pdo);
        } catch (PDOException $e) {
            // Banco fora do ar (ex.: XAMPP desligado): quem chama trata o null; o motivo vai para o log
            static $logado = false;
            if (!$logado) { $logado = true; lp_log_erro('Sem conexão com o banco', $e); }
            return null;
        }
    }

    return $pdo;
}

// Inicializa a variável $pdo para uso direto
$pdo = getDB();
