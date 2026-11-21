-- =====================================================================
-- RESET DE DESENVOLVIMENTO: APAGA TODAS AS TABELAS DO LOOTPRICE (usuários, jogos, preços...).
-- Use só no seu computador, para recomeçar do zero. Depois importe database/schema.sql
-- e apague os marcadores logs/schema_*.ok (para o site refazer os ajustes automáticos).
-- =====================================================================

USE `lootprice`;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `tentativas_login`;
DROP TABLE IF EXISTS `historico_precos`;
DROP TABLE IF EXISTS `avisos_preco`;
DROP TABLE IF EXISTS `verificacoes_email`;
DROP TABLE IF EXISTS `lista_desejos`;
DROP TABLE IF EXISTS `acessos_jogos`;
DROP TABLE IF EXISTS `jogo_loja_ref`;
DROP TABLE IF EXISTS `precos`;
DROP TABLE IF EXISTS `jogo_assinatura`;
DROP TABLE IF EXISTS `jogo_plataforma`;
DROP TABLE IF EXISTS `jogo_categoria`;
DROP TABLE IF EXISTS `jogos`;
DROP TABLE IF EXISTS `servicos_assinatura`;
DROP TABLE IF EXISTS `lojas`;
DROP TABLE IF EXISTS `plataformas`;
DROP TABLE IF EXISTS `categorias`;
DROP TABLE IF EXISTS `usuarios`;
SET FOREIGN_KEY_CHECKS = 1;
