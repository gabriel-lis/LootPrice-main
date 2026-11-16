<?php
/**
 * Header e Barra de Navegação Unificada
 * LootPrice
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';

$basePath = $basePath ?? '';
$pageTitle = $pageTitle ?? 'LootPrice - O melhor preço de jogos em um só lugar';
$flash = getFlash();
$user = currentUser();
$categories = getAllCategories();
$platforms = getAllPlatforms();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= sanitize($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= $basePath ?>css_index/lootprice.css">
  <link rel="stylesheet" href="<?= $basePath ?>css_index/responsive.css">
  <link rel="shortcut icon" href="<?= $basePath ?>fotos/extras/logoltdark.png" type="image/png">
  <style>
    /* Estilos aprimorados para filtros, flash messages e badges */
    .flash-alert {
      padding: 12px 20px;
      margin: 15px auto;
      max-width: 1200px;
      border-radius: 8px;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: space-between;
      animation: fadeIn 0.3s ease;
    }
    .flash-success { background: rgba(46, 204, 113, 0.2); border: 1px solid #2ecc71; color: #2ecc71; }
    .flash-error { background: rgba(231, 76, 60, 0.2); border: 1px solid #e74c3c; color: #ff6b6b; }
    .flash-info { background: rgba(52, 152, 219, 0.2); border: 1px solid #3498db; color: #5dade2; }
    
    .badge-admin {
      background: linear-gradient(90deg, #ffc857, #ff7b54);
      color: #111;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      text-decoration: none;
      margin-left: 6px;
      display: inline-block;
      vertical-align: middle;
    }
    .badge-admin:hover { opacity: 0.9; }

    .user-greet {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 600;
      color: var(--text-color);
      font-size: 14px;
    }
    .user-greet::before {
      content: attr(data-initial);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: var(--btn-bg);
      color: #fff;
      font-weight: bold;
      font-size: 12px;
    }
    .btn-logout {
      background: transparent;
      border: 1px solid rgba(255, 71, 87, 0.4);
      color: #ff6b6b;
      padding: 6px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 13px;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    .btn-logout:hover {
      background: #ff4757;
      color: #fff;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-6px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <!-- Barra de Navegação Superior -->
  <header class="navbar">
    <div class="left-section">
      <button id="menu-btn" class="menu-btn" aria-label="Abrir menu">☰</button>
      <a href="<?= $basePath ?>index.php" target="_top">
        <img id="lootprice-logo" src="<?= $basePath ?>fotos/extras/logoltdark.png" alt="LootPrice">
      </a>
    </div>

    <!-- Barra de busca rápida (RF01) -->
    <div class="search-container">
      <form action="<?= $basePath ?>index.php" method="GET" style="display: flex; width: 100%;">
        <label for="searchBar" class="visually-hidden">Pesquisar jogo</label>
        <input type="text" id="searchBar" name="q" class="search-input" 
               placeholder="Pesquisar jogo..." 
               value="<?= isset($_GET['q']) ? sanitize($_GET['q']) : '' ?>"
               aria-label="Pesquisar jogos">
      </form>
    </div>

    <!-- Área de Autenticação e Tema (Dinâmica no PHP) -->
    <div id="auth-area" class="auth-buttons">
      <button id="theme-toggle" class="btn-theme" aria-label="Alternar tema">
        <img src="<?= $basePath ?>fotos/extras/dark-mode.png" alt="Tema" class="theme-icon">
      </button>

      <?php if ($user): ?>
        <span class="user-greet" data-initial="<?= strtoupper(substr($user['nome'], 0, 1)) ?>" aria-label="Usuário <?= sanitize($user['nome']) ?>">
          Olá, <?= sanitize(explode(' ', $user['nome'])[0]) ?>
        </span>

        <?php if (isAdmin()): ?>
          <a href="<?= $basePath ?>admin/index.php" class="badge-admin">Admin</a>
        <?php endif; ?>

        <a href="<?= $basePath ?>logout.php" class="btn-logout">Sair</a>
      <?php else: ?>
        <a href="<?= $basePath ?>login.php" class="btn-login">Entrar</a>
        <a href="<?= $basePath ?>cadastro.php" class="btn-login">Registrar</a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Sidebar Lateral Responsiva -->
  <nav id="sidebar" class="sidebar" role="navigation" aria-label="Menu principal">
    <div class="sidebar-top">
      <button id="sidebar-close" class="menu-close" aria-label="Fechar menu">✕</button>
    </div>

    <div class="sidebar-search" role="search">
      <form action="<?= $basePath ?>index.php" method="GET" style="display:flex; width:100%;">
        <input type="text" id="sidebarSearchBar" name="q" class="search-input" placeholder="Pesquisar jogo..."
               value="<?= isset($_GET['q']) ? sanitize($_GET['q']) : '' ?>" aria-label="Pesquisar jogos">
        <button id="sidebarSearchBtn" type="submit" aria-label="Pesquisar">
          <img src="<?= $basePath ?>fotos/extras/search.png" alt="Pesquisar" class="sidebar-search-icon">
        </button>
      </form>
    </div>

    <div class="sidebar-category" style="position:relative;">
      <button id="categoryBtn" class="category-btn" aria-haspopup="listbox" aria-expanded="false">
        Categorias ▾
      </button>

      <div id="categoryDropdown" class="category-dropdown" role="listbox" aria-labelledby="categoryBtn" aria-hidden="true">
        <a href="<?= $basePath ?>index.php" class="category-option" style="text-decoration:none; display:block;">Todas as Categorias</a>
        <?php foreach ($categories as $cat): ?>
          <a href="<?= $basePath ?>index.php?categoria=<?= $cat['id_categoria'] ?>" class="category-option" style="text-decoration:none; display:block;">
            <?= sanitize($cat['nome']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <a href="<?= $basePath ?>index.php">Início</a>
    <a href="<?= $basePath ?>sobre.php">Sobre LootPrice</a>

    <?php if (isAdmin()): ?>
      <a href="<?= $basePath ?>admin/index.php" style="color: #ffc857; font-weight: bold;">⚙️ Painel Admin</a>
    <?php endif; ?>
  </nav>

  <div id="sidebar-backdrop" class="sidebar-backdrop" tabindex="-1"></div>

  <!-- Exibição de Alertas do Sistema (Flash Messages) -->
  <?php if ($flash): ?>
    <div class="flash-alert flash-<?= sanitize($flash['type']) ?>" role="alert">
      <span><?= sanitize($flash['message']) ?></span>
      <button onclick="this.parentElement.remove()" style="background:transparent; border:none; color:inherit; font-size:18px; cursor:pointer;">&times;</button>
    </div>
  <?php endif; ?>
