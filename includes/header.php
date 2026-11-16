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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="<?= $basePath ?>css_index/theme.css?v=<?= @filemtime(__DIR__ . '/../css_index/theme.css') ?>">
  <link rel="stylesheet" href="<?= $basePath ?>css_index/lootprice.css?v=<?= @filemtime(__DIR__ . '/../css_index/lootprice.css') ?>">
  <link rel="stylesheet" href="<?= $basePath ?>css_index/responsive.css?v=<?= @filemtime(__DIR__ . '/../css_index/responsive.css') ?>">
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
      color: var(--header-fg);
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
    /* Header: menu + logo | busca centralizada | entrar */
    .navbar { width: 100%; box-sizing: border-box; padding: 0 20px; gap: 16px; box-shadow: 0 2px 16px rgba(0,0,0,.25); }
    .navbar .left-section { flex: 0 0 auto; gap: 14px; }
    .navbar .menu-btn {
      display: inline-flex; align-items: center; justify-content: center;
      width: 40px; height: 40px; padding: 0; border: none; border-radius: 10px;
      background: var(--header-control-bg); color: var(--header-fg); cursor: pointer; transition: background .2s;
    }
    .navbar .menu-btn:hover { background: var(--header-control-hover); color: var(--header-fg); }
    .navbar .search-container { flex: 1 1 auto; padding: 0 8px; }
    .search-pill { position: relative; display: flex; align-items: center; width: 100%; max-width: 620px; }
    .search-pill-icon { position: absolute; left: 16px; color: var(--search-placeholder); pointer-events: none; }
    .search-pill .search-input {
      max-width: none; height: 42px; padding: 0 18px 0 44px; font-size: 15px; font-family: var(--font);
      border: 1px solid var(--search-border); border-radius: 999px; box-shadow: none;
      color: var(--text-color); background: var(--search-bg);
    }
    .search-pill .search-input:focus { border-color: var(--search-focus-border); box-shadow: 0 0 0 3px rgba(255,255,255,.18); }
    .navbar .auth-buttons { flex: 0 0 auto; align-items: center; gap: 12px; }
    .navbar .btn-theme { border-radius: 10px; width: 40px; height: 40px; padding: 0; background: var(--header-control-bg); border: none; }
    .navbar .btn-theme:hover { background: var(--header-control-hover); }
    .btn-entrar {
      background: var(--entrar-bg) !important; color: var(--entrar-text) !important;
      text-decoration: none; padding: 10px 26px; border-radius: 10px; font-size: 15px; font-weight: 700;
      box-shadow: none !important;
    }
    .btn-entrar:hover { transform: translateY(-1px); filter: brightness(.95); }
    @media (max-width: 600px) { .navbar { padding: 0 10px; gap: 8px; } .btn-entrar { padding: 8px 14px; } }

    /* Menu lateral */
    .sidebar { border-right: 1px solid var(--border); }
    .sidebar a { margin: 2px 12px; padding: 13px 16px; border-radius: 10px; font-size: 15px; font-weight: 600; }
    .sidebar a:hover { background: var(--accent-soft); color: var(--accent-text); }
    .sidebar a.is-current { background: var(--accent); color: #fff; }

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
      <button id="menu-btn" class="menu-btn" aria-label="Abrir menu">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <a href="<?= $basePath ?>index.php" target="_top">
        <img id="lootprice-logo" src="<?= $basePath ?>fotos/extras/logoltdark.png" alt="LootPrice">
      </a>
    </div>

    <!-- Barra de busca rápida (RF01) -->
    <?php if (empty($hideSearch)): ?>
    <div class="search-container">
      <form action="<?= $basePath ?>catalogo.php" method="GET" class="search-pill" role="search">
        <label for="searchBar" class="visually-hidden">Pesquisar jogo</label>
        <svg class="search-pill-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="text" id="searchBar" name="q" class="search-input"
               placeholder="Buscar jogo ou palavra-chave"
               value="<?= isset($_GET['q']) ? sanitize($_GET['q']) : '' ?>"
               aria-label="Pesquisar jogos">
      </form>
    </div>
    <?php endif; ?>

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
        <a href="<?= $basePath ?>login.php" class="btn-login btn-entrar">Entrar</a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Sidebar Lateral Responsiva -->
  <nav id="sidebar" class="sidebar" role="navigation" aria-label="Menu principal">
    <?php $paginaAtual = basename($_SERVER['SCRIPT_NAME'] ?? ''); ?>
    <a href="<?= $basePath ?>catalogo.php" <?= $paginaAtual === 'catalogo.php' ? 'class="is-current" aria-current="page"' : '' ?>>Catálogo</a>
    <a href="<?= $basePath ?>descontos.php" <?= $paginaAtual === 'descontos.php' ? 'class="is-current" aria-current="page"' : '' ?>>Descontos</a>
    <a href="<?= $basePath ?>sobre.php" <?= $paginaAtual === 'sobre.php' ? 'class="is-current" aria-current="page"' : '' ?>>Sobre LootPrice</a>

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
