<?php
/**
 * Ajustes de estrutura do banco aplicados sozinhos, uma única vez (veja lp_schema_uma_vez em config/db.php).
 * Quem instala do zero com database/schema.sql já recebe tudo isso pronto; este arquivo atualiza
 * bancos que já existiam antes das mudanças.
 *
 *  - precos: um preço por jogo+loja (UNIQUE), sem duplicados
 *  - historico_precos: cada mudança de preço fica registrada (menor preço histórico)
 *  - tentativas_login: limite de tentativas de login por e-mail/username e por IP
 *  - usuarios.tipo_usuario: sem o valor "visitante" (ninguém usava)
 */
function lp_migrar_extra(PDO $pdo): void {
    lp_schema_uma_vez('extra_v1', function () use ($pdo) {
        // 1) Um preço por jogo+loja
        if (!$pdo->query("SHOW INDEX FROM precos WHERE Key_name = 'uq_preco_jogo_loja'")->fetch()) {
            $pdo->exec("DELETE p1 FROM precos p1 JOIN precos p2
                        ON p1.id_jogo = p2.id_jogo AND p1.id_loja = p2.id_loja AND p1.id_preco < p2.id_preco");
            $pdo->exec("ALTER TABLE precos ADD UNIQUE KEY uq_preco_jogo_loja (id_jogo, id_loja)");
        }

        // 2) Histórico de preços (+ carga inicial com o preço de hoje)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `historico_precos` (
            `id_historico` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `id_jogo` INT NOT NULL,
            `id_loja` INT NOT NULL,
            `valor` DECIMAL(10,2) NOT NULL,
            `valor_original` DECIMAL(10,2) DEFAULT NULL,
            `registrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_hist_jogo` (`id_jogo`, `registrado_em`),
            CONSTRAINT `fk_hist_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE,
            CONSTRAINT `fk_hist_loja` FOREIGN KEY (`id_loja`) REFERENCES `lojas` (`id_loja`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if ((int)$pdo->query("SELECT COUNT(*) FROM historico_precos")->fetchColumn() === 0) {
            $pdo->exec("INSERT INTO historico_precos (id_jogo, id_loja, valor, valor_original, registrado_em)
                        SELECT id_jogo, id_loja, valor, valor_original, COALESCE(data_atualizacao, NOW()) FROM precos");
        }

        // 4) Limite de tentativas de login (anti força bruta)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `tentativas_login` (
            `chave` CHAR(64) NOT NULL PRIMARY KEY,
            `tentativas` INT NOT NULL DEFAULT 0,
            `primeira_em` DATETIME NOT NULL,
            `bloqueado_ate` DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 5) tipo_usuario sem "visitante"
        $pdo->exec("UPDATE usuarios SET tipo_usuario = 'usuario' WHERE tipo_usuario = 'visitante'");
        $pdo->exec("ALTER TABLE usuarios MODIFY tipo_usuario ENUM('usuario','administrador') NOT NULL DEFAULT 'usuario'");
    });
}
