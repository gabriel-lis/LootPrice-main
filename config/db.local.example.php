<?php
/**
 * Modelo de configuração local do banco. Copie para config/db.local.php (fica fora do git).
 * Em produção NUNCA use root sem senha: crie um usuário do MySQL só para o LootPrice.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'lootprice');
define('DB_USER', 'lootprice_app');
define('DB_PASS', 'troque-esta-senha');
define('DB_PORT', '3306');
define('LP_DEV', false);
