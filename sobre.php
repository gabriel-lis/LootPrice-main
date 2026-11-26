<?php
/**
 * Sobre LootPrice
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Sobre o Projeto - LootPrice';
$pageDescription = 'LootPrice compara preços de jogos digitais nas principais lojas.';
$basePath = '';

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="css_index/sobre.css?v=<?= filemtime(__DIR__ . '/css_index/sobre.css') ?>">

<main class="sobre">

  <header class="sobre-hero">
    <h1>LootPrice</h1>
    <p>O seu site para <strong>lootear</strong> os melhores preços dos jogos.</p>
  </header>

  <section class="sobre-loot">
    <h2>Lootear (v.)</h2>
    <p>
      Verbo de gamer: abrir o baú, vasculhar o mapa e sair com o melhor item.
      No LootPrice, o baú é o catálogo e o item raro é o <em>menor preço</em>.
    </p>
    <ul class="loot-lista">
      <li><span class="loot-raridade comum">Comum</span> Preço cheio. Passa reto.</li>
      <li><span class="loot-raridade raro">Raro</span> Promoção boa. Vale o clique.</li>
      <li><span class="loot-raridade epico">Épico</span> Menor preço histórico. Lootou, chefe.</li>
    </ul>
  </section>

  <section class="sobre-resumo">
    <p>
      O LootPrice reúne em um só lugar os preços de Steam, Epic, Xbox, Nuuvem e GOG,
      mostra o menor valor, o histórico de preços e a presença em assinaturas como o Xbox Game Pass.
      Os preços são atualizados diariamente por um robô, e quem cria uma conta pode montar uma lista de desejos
      e receber aviso por e-mail quando um jogo entra em promoção.
    </p>
  </section>

  <div class="sobre-grid">
    <section class="sobre-card">
      <h2>A party de lootadores</h2>
      <ul>
        <li>Pedro Duarte</li>
        <li>Gabriel Leandro</li>
        <li>Daniel Cinquine</li>
      </ul>
      <p class="sobre-nota">Nenhum baú foi ferido na criação deste site.</p>
    </section>
  </div>

  <a href="catalogo.php" class="sobre-voltar">Abrir o baú (ver catálogo)</a>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
