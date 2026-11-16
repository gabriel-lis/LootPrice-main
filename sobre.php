<?php
/**
 * Sobre LootPrice
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Sobre o Projeto - LootPrice';
$basePath = '';

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="css_index/sobre.css">

<div class="about-main-wrapper" style="max-width: 1000px; margin: 40px auto; padding: 0 20px;">
  
  <h1 class="hero-title" style="text-align: center; margin-bottom: 10px;">LootPrice</h1>
  <p class="hero-subtitle" style="text-align: center; margin-bottom: 40px;">Conectando jogadores às melhores ofertas de jogos digitais.</p>

  <div class="content-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
    
    <div class="glass-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 24px;">
      <h3 style="color: var(--title-color); margin-top: 0;">🎯 Objetivo do Projeto</h3>
      <p style="color: var(--text-color); line-height: 1.6; font-size: 14px;">
        Desenvolver uma plataforma web centralizada que simplifique a tomada de decisão do jogador, permitindo navegar por um acervo de jogos digitais, comparar preços em tempo real nas principais lojas (Steam, Epic, Xbox, Nuuvem e GOG) e verificar a disponibilidade em catálogos de assinatura como o Xbox Game Pass.
      </p>
    </div>

    <div class="glass-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 24px;">
      <h3 style="color: var(--title-color); margin-top: 0;">🛠️ Tecnologias Utilizadas</h3>
      <p style="color: var(--text-color); line-height: 1.6; font-size: 14px;">
        Backend em <strong>PHP 8</strong> estruturado com <strong>PDO</strong>, banco de dados relacional <strong>MySQL / MariaDB</strong> modelado conforme o MER/DER oficial, criptografia de senhas (BCrypt), sessões autenticadas e frontend responsivo em HTML5, CSS3 moderno e JavaScript.
      </p>
    </div>

    <div class="glass-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 24px;">
      <h3 style="color: var(--title-color); margin-top: 0;">👥 Integrantes do Projeto</h3>
      <ul style="color: var(--text-color); line-height: 1.8; font-size: 14px; padding-left: 20px;">
        <li><strong>Pedro Duarte</strong></li>
        <li><strong>Gabriel Leandro</strong></li>
        <li><strong>Daniel Cinquine</strong></li>
      </ul>
    </div>

  </div>

  <div style="text-align: center; margin-top: 40px;">
    <a href="index.php" class="btn-login" style="text-decoration: none; display: inline-block;">
      Voltar para o Catálogo
    </a>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
