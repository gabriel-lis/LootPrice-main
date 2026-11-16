<?php
/**
 * Footer Oficial
 * LootPrice
 */
$basePath = $basePath ?? '';
?>
  <footer>
    <p>&copy; <?= date('Y') ?> - LootPrice - Todos os direitos reservados</p>
  </footer>

  <script src="<?= $basePath ?>js_index/darkmode.js" defer></script>
  <script src="<?= $basePath ?>js_index/menulateral.js" defer></script>
  <script>
    // Inicializa o dropdown de categorias do sidebar
    document.addEventListener('DOMContentLoaded', () => {
      const catBtn = document.getElementById('categoryBtn');
      const catDropdown = document.getElementById('categoryDropdown');
      if (catBtn && catDropdown) {
        catBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          const isOpen = catDropdown.classList.toggle('show');
          catDropdown.setAttribute('aria-hidden', !isOpen);
          catBtn.setAttribute('aria-expanded', isOpen);
        });
        document.addEventListener('click', () => {
          catDropdown.classList.remove('show');
          catDropdown.setAttribute('aria-hidden', 'true');
          catBtn.setAttribute('aria-expanded', 'false');
        });
      }
    });
  </script>
</body>
</html>
