<?php
/**
 * Conexão com o Banco de Dados MySQL via PDO
 * LootPrice - Sistema Oficial
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'lootprice');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', '3306');

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
        } catch (PDOException $e) {
            // Em caso de falha de conexão (ex: banco ainda não criado ou XAMPP desligado)
            return null;
        }
    }

    return $pdo;
}

// Inicializa a variável $pdo para uso direto
$pdo = getDB();
