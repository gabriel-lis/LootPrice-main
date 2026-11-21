-- =====================================================================
-- PROJETO LOOTPRICE - BANCO DE DADOS OFICIAL
-- Baseado no MER/DER da documentação acadêmica
-- Compatível com MySQL / MariaDB (XAMPP)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `lootprice` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lootprice`;

-- IMPORTANTE: este script é SEGURO para rodar várias vezes. Ele só cria o que falta (CREATE TABLE IF NOT EXISTS)
-- e só insere os dados iniciais que ainda não existem (INSERT IGNORE). Nunca apaga usuários nem jogos.
-- Para recriar o banco do zero (APAGA TUDO, só em desenvolvimento), use database/reset_dev.sql.
-- Não há usuário padrão com senha conhecida: crie o administrador com  php robo/criar_admin.php

-- ---------------------------------------------------------------------
-- 1. Tabela: usuarios
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `username` VARCHAR(30) NULL UNIQUE,
  `sexo` ENUM('masculino', 'feminino', 'nao_informado') NOT NULL DEFAULT 'nao_informado',
  `data_nascimento` DATE NULL,
  `telefone` VARCHAR(20) NULL,
  `foto_perfil` VARCHAR(255) NULL,
  `foto_fundo` VARCHAR(255) NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `email_verificado` TINYINT(1) NOT NULL DEFAULT 0,
  `alertas_email` TINYINT(1) NOT NULL DEFAULT 1,
  `senha` VARCHAR(255) NOT NULL,
  `tipo_usuario` ENUM('usuario', 'administrador') NOT NULL DEFAULT 'usuario',
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Tabela: categorias
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categorias` (
  `id_categoria` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Tabela: plataformas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plataformas` (
  `id_plataforma` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(50) NOT NULL UNIQUE,
  `icone` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Tabela: lojas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lojas` (
  `id_loja` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `url` VARCHAR(255) DEFAULT NULL,
  `pais` VARCHAR(50) DEFAULT 'Brasil'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Tabela: servicos_assinatura
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicos_assinatura` (
  `id_servico` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `descricao` TEXT DEFAULT NULL,
  `logo` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Tabela: jogos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `jogos` (
  `id_jogo` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `descricao` TEXT DEFAULT NULL,
  `data_lancamento` DATE DEFAULT NULL,
  `nota_metacritic` DECIMAL(3,1) DEFAULT NULL,
  `imagem_capa` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. Tabelas Associativas / Junction
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `jogo_categoria` (
  `id_jogo` INT NOT NULL,
  `id_categoria` INT NOT NULL,
  PRIMARY KEY (`id_jogo`, `id_categoria`),
  CONSTRAINT `fk_jc_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE,
  CONSTRAINT `fk_jc_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jogo_plataforma` (
  `id_jogo` INT NOT NULL,
  `id_plataforma` INT NOT NULL,
  PRIMARY KEY (`id_jogo`, `id_plataforma`),
  CONSTRAINT `fk_jp_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE,
  CONSTRAINT `fk_jp_plataforma` FOREIGN KEY (`id_plataforma`) REFERENCES `plataformas` (`id_plataforma`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jogo_assinatura` (
  `id_jogo` INT NOT NULL,
  `id_servico` INT NOT NULL,
  PRIMARY KEY (`id_jogo`, `id_servico`),
  CONSTRAINT `fk_ja_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE,
  CONSTRAINT `fk_ja_servico` FOREIGN KEY (`id_servico`) REFERENCES `servicos_assinatura` (`id_servico`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. Tabela: precos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `precos` (
  `id_preco` INT AUTO_INCREMENT PRIMARY KEY,
  `id_jogo` INT NOT NULL,
  `id_loja` INT NOT NULL,
  `id_plataforma` INT NOT NULL,
  `valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `valor_original` DECIMAL(10,2) DEFAULT NULL,
  `porcentagem_desconto` INT DEFAULT 0,
  `data_atualizacao` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `link_compra` VARCHAR(255) DEFAULT NULL,
  UNIQUE KEY `uq_preco_jogo_loja` (`id_jogo`, `id_loja`),
  INDEX `idx_preco_jogo` (`id_jogo`),
  INDEX `idx_preco_valor` (`valor`),
  CONSTRAINT `fk_precos_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE,
  CONSTRAINT `fk_precos_loja` FOREIGN KEY (`id_loja`) REFERENCES `lojas` (`id_loja`) ON DELETE CASCADE,
  CONSTRAINT `fk_precos_plataforma` FOREIGN KEY (`id_plataforma`) REFERENCES `plataformas` (`id_plataforma`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. Tabela: acessos_jogos (visitas e buscas: popularidade e recomendações)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `acessos_jogos` (
  `id_acesso` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT NULL,
  `id_jogo` INT NOT NULL,
  `tipo` ENUM('visita','busca') NOT NULL DEFAULT 'visita',
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_acesso_usuario` (`id_usuario`),
  INDEX `idx_acesso_jogo` (`id_jogo`, `criado_em`),
  CONSTRAINT `fk_acesso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_acesso_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9.1 Tabela: lista_desejos (jogos que cada usuário quer acompanhar)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lista_desejos` (
  `id_usuario` INT NOT NULL,
  `id_jogo` INT NOT NULL,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`, `id_jogo`),
  CONSTRAINT `fk_desejo_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_desejo_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. Tabela: jogo_loja_ref (qual é o jogo em cada loja; usada pelo robô de preços)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `jogo_loja_ref` (
  `id_jogo` INT NOT NULL,
  `id_loja` INT NOT NULL,
  `id_externo` VARCHAR(120) DEFAULT NULL,
  `titulo` VARCHAR(255) DEFAULT NULL,
  `encontrado` TINYINT(1) NOT NULL DEFAULT 1,
  `verificado_em` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_jogo`, `id_loja`),
  CONSTRAINT `fk_ref_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_loja` FOREIGN KEY (`id_loja`) REFERENCES `lojas` (`id_loja`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. Tabela: verificacoes_email (código de 6 dígitos para confirmar o e-mail)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `verificacoes_email` (
  `id_usuario` INT NOT NULL,
  `tipo` VARCHAR(20) NOT NULL DEFAULT 'cadastro',
  `codigo_hash` VARCHAR(255) NOT NULL,
  `expira_em` DATETIME NOT NULL,
  `tentativas` TINYINT NOT NULL DEFAULT 0,
  `enviado_em` DATETIME NOT NULL,
  PRIMARY KEY (`id_usuario`, `tipo`),
  CONSTRAINT `fk_verif_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. Tabela: avisos_preco (último preço avisado por e-mail; evita avisar o mesmo desconto duas vezes)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avisos_preco` (
  `id_usuario` INT NOT NULL,
  `id_jogo` INT NOT NULL,
  `preco_avisado` DECIMAL(10,2) NOT NULL,
  `avisado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`, `id_jogo`),
  CONSTRAINT `fk_aviso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_aviso_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. Tabela: historico_precos (cada mudança de preço; base do "menor preço histórico")
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `historico_precos` (
  `id_historico` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `id_jogo` INT NOT NULL,
  `id_loja` INT NOT NULL,
  `valor` DECIMAL(10,2) NOT NULL,
  `valor_original` DECIMAL(10,2) DEFAULT NULL,
  `registrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_hist_jogo` (`id_jogo`, `registrado_em`),
  CONSTRAINT `fk_hist_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_loja` FOREIGN KEY (`id_loja`) REFERENCES `lojas` (`id_loja`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. Tabela: avaliacoes (nota 1 a 5 e comentário de cada usuário em cada jogo)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avaliacoes` (
  `id_avaliacao` INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT NOT NULL,
  `id_jogo` INT NOT NULL,
  `nota` TINYINT NOT NULL,
  `comentario` VARCHAR(1000) DEFAULT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_avaliacao` (`id_usuario`, `id_jogo`),
  INDEX `idx_avaliacao_jogo` (`id_jogo`, `criado_em`),
  CONSTRAINT `fk_aval_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_aval_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 15. Tabela: tentativas_login (limite de tentativas: anti força bruta)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tentativas_login` (
  `chave` CHAR(64) NOT NULL PRIMARY KEY,
  `tentativas` INT NOT NULL DEFAULT 0,
  `primeira_em` DATETIME NOT NULL,
  `bloqueado_ate` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DADOS INICIAIS (SEEDS)
-- =====================================================================

-- Usuários: nenhum usuário padrão (antes havia admin123/gamer123, inseguro). Crie o administrador com: php robo/criar_admin.php

-- Categorias
INSERT IGNORE INTO `categorias` (`id_categoria`, `nome`) VALUES
(1, 'RPG'),
(2, 'Shooter'),
(3, 'Battle Royale'),
(4, 'Aventura'),
(5, 'Terror'),
(6, 'Roguelike'),
(7, 'Soulslike'),
(8, 'MMO RPG'),
(9, 'Multiplayer');

-- Plataformas
INSERT IGNORE INTO `plataformas` (`id_plataforma`, `nome`, `icone`) VALUES
(1, 'PC', 'fotos/extras/steam.png'),
(2, 'Xbox', 'fotos/extras/xbox.png'),
(3, 'PlayStation', 'fotos/extras/playstation.svg'),
(4, 'Nintendo Switch', 'fotos/extras/switch.svg');

-- Lojas
INSERT IGNORE INTO `lojas` (`id_loja`, `nome`, `logo`, `url`, `pais`) VALUES
(1, 'Steam', 'fotos/extras/steam.png', 'https://store.steampowered.com', 'Global'),
(2, 'Epic Games', 'fotos/extras/epic.png', 'https://store.epicgames.com', 'Global'),
(3, 'Xbox Store', 'fotos/extras/xbox.png', 'https://www.xbox.com', 'Global'),
(4, 'Nuuvem', 'fotos/extras/nuuvem.png', 'https://www.nuuvem.com', 'Brasil'),
(5, 'GOG', 'fotos/extras/gog.png', 'https://www.gog.com', 'Global');

-- Serviços de Assinatura
INSERT IGNORE INTO `servicos_assinatura` (`id_servico`, `nome`, `descricao`, `logo`) VALUES
(1, 'Xbox Game Pass', 'Acesso a uma biblioteca com centenas de jogos no PC e Xbox.', 'fotos/extras/xbox.png'),
(2, 'EA Play', 'Catálogo dos principais títulos da Electronic Arts.', 'fotos/extras/eaplay.svg'),
(3, 'PlayStation Plus', 'Biblioteca de jogos mensais para consoles PlayStation.', 'fotos/extras/psplus.svg');

-- Jogos
INSERT IGNORE INTO `jogos` (`id_jogo`, `nome`, `descricao`, `data_lancamento`, `nota_metacritic`, `imagem_capa`) VALUES
(1, 'Death Stranding', 'Death Stranding é um jogo de ação e aventura em mundo aberto desenvolvido por Kojima Productions. Os jogadores controlam Sam Bridges, um entregador em um Estados Unidos pós-apocalíptico, isolado por misteriosas criaturas chamadas BTs.', '2019-11-08', 86.0, 'fotos/jogos/ds.jpg'),
(2, 'Tom Clancy\'s Rainbow Six Siege', 'Rainbow Six Siege é um jogo de tiro tático focado em combate corpo a corpo, destruição ambiental e trabalho em equipe. Operadores com gadgets exclusivos duelam em partidas táticas de ataque e defesa.', '2015-12-01', 79.0, 'fotos/jogos/r6.jpg'),
(3, 'Apex Legends', 'Apex Legends é um battle royale gratuito ambientado no universo de Titanfall, onde equipes de três jogadores competem para ser a última sobrevivente com personagens únicos chamados Legends.', '2019-02-04', 89.0, 'fotos/jogos/apex.jpg'),
(4, 'Dead by Daylight', 'Dead by Daylight é um jogo de terror e sobrevivência multiplayer assimétrico (4vs1), onde um jogador assume o papel de um Assassino implacável e os outros quatro tentam escapar.', '2016-06-14', 71.0, 'fotos/jogos/dbd.png'),
(5, 'Elden Ring', 'Elden Ring é um RPG de ação em mundo aberto desenvolvido pela FromSoftware com narrativa colaborativa de Hidetaka Miyazaki e George R.R. Martin pelas Terras Intermédias.', '2022-02-25', 96.0, 'fotos/jogos/er.jpg'),
(6, 'Risk of Rain 2', 'Risk of Rain 2 é um clássico jogo roguelike de ação em terceira pessoa multiplayer, no qual você luta contra hordas de monstros alienígenas enquanto o nível de dificuldade aumenta com o tempo.', '2020-08-11', 85.0, 'fotos/jogos/ror2.jpg'),
(7, 'Sea of Thieves', 'Sea of Thieves é um jogo de aventura multiplayer em mundo aberto, ambientado em um universo de pirataria. Os jogadores formam tripulações para velejar, pilhar tesouros e guerrear em alto-mar.', '2018-03-20', 82.0, 'fotos/jogos/sot.jpg'),
(8, 'Warframe', 'Warframe é um jogo cooperativo de ação rápida e tiro em terceira pessoa focado em ninjas espaciais cibernéticos (Tenno) com trajes bio-mecânicos poderosos.', '2013-03-25', 85.0, 'fotos/jogos/warframe.png'),
(9, 'The Witcher 3: Wild Hunt', 'The Witcher 3 é um RPG épico de ação em mundo aberto desenvolvido pela CD Projekt Red. Acompanhe Geralt de Rívia em busca da Ciri em um universo repleto de monstros e escolhas morais.', '2015-05-18', 93.0, 'fotos/jogos/witcher.jpg'),
(10, 'Dark Souls III', 'Dark Souls III é um RPG de ação desenvolvido pela FromSoftware, conhecido por seu combate visceral, atmosfera sombria e chefes lendários ao explorar o reino de Lothric.', '2016-04-12', 89.0, 'fotos/jogos/ds3.png');

-- Vínculo Jogo-Categoria
INSERT IGNORE INTO `jogo_categoria` (`id_jogo`, `id_categoria`) VALUES
(1, 4), -- Death Stranding -> Aventura
(2, 2), -- Rainbow Six Siege -> Shooter
(2, 9), -- Rainbow Six Siege -> Multiplayer
(3, 3), -- Apex Legends -> Battle Royale
(3, 2), -- Apex Legends -> Shooter
(4, 5), -- Dead by Daylight -> Terror
(4, 9), -- Dead by Daylight -> Multiplayer
(5, 1), -- Elden Ring -> RPG
(5, 7), -- Elden Ring -> Soulslike
(6, 6), -- Risk of Rain 2 -> Roguelike
(7, 4), -- Sea of Thieves -> Aventura
(7, 9), -- Sea of Thieves -> Multiplayer
(8, 8), -- Warframe -> MMO RPG
(8, 2), -- Warframe -> Shooter
(9, 1), -- The Witcher 3 -> RPG
(10, 1), -- Dark Souls III -> RPG
(10, 7); -- Dark Souls III -> Soulslike

-- Vínculo Jogo-Plataforma
INSERT IGNORE INTO `jogo_plataforma` (`id_jogo`, `id_plataforma`) VALUES
(1, 1), (1, 2), (1, 3),
(2, 1), (2, 2), (2, 3),
(3, 1), (3, 2), (3, 3), (3, 4),
(4, 1), (4, 2), (4, 3), (4, 4),
(5, 1), (5, 2), (5, 3),
(6, 1), (6, 2), (6, 3), (6, 4),
(7, 1), (7, 2), (7, 3),
(8, 1), (8, 2), (8, 3), (8, 4),
(9, 1), (9, 2), (9, 3), (9, 4),
(10, 1), (10, 2), (10, 3);

-- Vínculo Jogo-Assinatura (Indica se está no Xbox Game Pass ou EA Play - RF07)
INSERT IGNORE INTO `jogo_assinatura` (`id_jogo`, `id_servico`) VALUES
(3, 2), -- Apex Legends -> EA Play
(7, 1), -- Sea of Thieves -> Xbox Game Pass
(4, 1), -- Dead by Daylight -> Xbox Game Pass
(1, 1); -- Death Stranding -> Xbox Game Pass

-- Preços dos jogos em diferentes lojas e plataformas (RN01, RN03, RN11, RF05, RF06, RF09)
INSERT IGNORE INTO `precos` (`id_jogo`, `id_loja`, `id_plataforma`, `valor`, `valor_original`, `porcentagem_desconto`, `link_compra`) VALUES
-- Death Stranding
(1, 1, 1, 63.60, 199.99, 68, 'https://store.steampowered.com/app/1850570/DEATH_STRANDING_DIRECTORS_CUT/'),
(1, 2, 1, 161.99, 161.99, 0, 'https://store.epicgames.com/pt-BR/p/death-stranding-directors-cut'),
(1, 3, 2, 159.00, 159.00, 0, 'https://www.xbox.com/en-US/games/store/death-stranding-directors-cut/9nqgn8tgnt8p'),

-- Rainbow Six Siege
(2, 1, 1, 29.99, 89.99, 67, 'https://store.steampowered.com/app/359550/Tom_Clancys_Rainbow_Six_Siege/'),
(2, 2, 1, 35.99, 89.99, 60, 'https://store.epicgames.com/pt-BR/p/rainbow-six-siege'),
(2, 3, 2, 49.99, 99.90, 50, 'https://www.xbox.com/pt-BR/games/store/tom-clancys-rainbow-six-siege/c12tmn44tt34'),

-- Apex Legends
(3, 1, 1, 0.00, 0.00, 0, 'https://store.steampowered.com/app/1172470/Apex_Legends/'),
(3, 2, 1, 0.00, 0.00, 0, 'https://store.epicgames.com/pt-BR/p/apex-legends'),
(3, 3, 2, 0.00, 0.00, 0, 'https://www.xbox.com/en-US/games/store/apex-legends/9NBLGGH5ZJW2'),

-- Dead by Daylight
(4, 1, 1, 34.99, 69.99, 50, 'https://store.steampowered.com/app/381210/Dead_by_Daylight/'),
(4, 2, 1, 69.99, 69.99, 0, 'https://store.epicgames.com/pt-BR/p/dead-by-daylight'),
(4, 3, 2, 104.99, 104.99, 0, 'https://www.xbox.com/pt-br/games/store/dead-by-daylight/c0n18rlhmbh4'),

-- Elden Ring
(5, 1, 1, 229.00, 274.50, 17, 'https://store.steampowered.com/app/1245620/ELDEN_RING/'),
(5, 3, 2, 299.90, 299.90, 0, 'https://www.xbox.com/pt-BR/games/store/elden-ring/9P3J32CTXLRZ'),
(5, 4, 1, 219.90, 274.50, 20, 'https://www.nuuvem.com/item/elden-ring'),

-- Risk of Rain 2
(6, 1, 1, 19.79, 59.99, 67, 'https://store.steampowered.com/app/632360/Risk_of_Rain_2/'),
(6, 3, 2, 24.90, 124.50, 80, 'https://www.xbox.com/pt-BR/games/store/risk-of-rain-2/9p25p70tt0q6'),

-- Sea of Thieves
(7, 1, 1, 89.50, 179.00, 50, 'https://store.steampowered.com/app/1172620/Sea_of_Thieves/'),
(7, 3, 2, 99.50, 199.90, 50, 'https://www.xbox.com/en-US/games/store/sea-of-thieves/9P2N57MC619K'),

-- Warframe
(8, 1, 1, 0.00, 0.00, 0, 'https://store.steampowered.com/app/230410/Warframe/'),
(8, 2, 1, 0.00, 0.00, 0, 'https://store.epicgames.com/pt-BR/p/warframe'),
(8, 3, 2, 0.00, 0.00, 0, 'https://www.xbox.com/pt-BR/games/store/warframe/bps4vg17c79s'),

-- The Witcher 3: Wild Hunt
(9, 1, 1, 12.99, 129.99, 90, 'https://store.steampowered.com/app/292030/The_Witcher_3_Wild_Hunt/'),
(9, 3, 2, 14.30, 143.00, 90, 'https://www.xbox.com/pt-br/games/store/the-witcher-3-wild-hunt/br765873cqjd'),
(9, 2, 1, 99.96, 99.96, 0, 'https://store.epicgames.com/pt-BR/p/the-witcher-3-wild-hunt'),
(9, 5, 1, 19.90, 129.99, 85, 'https://www.gog.com/game/the_witcher_3_wild_hunt'),

-- Dark Souls III
(10, 1, 1, 114.95, 229.90, 50, 'https://store.steampowered.com/app/374320/DARK_SOULS_III/'),
(10, 3, 2, 149.95, 229.90, 35, 'https://www.xbox.com/pt-br/games/store/dark-souls-iii/bw2xdrnsccpz');
