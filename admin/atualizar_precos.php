<?php
/**
 * Atualização de preços (RF08, RN02, RN03)
 * Executa o robô de coleta (includes/coletor.php) manualmente.
 * No dia a dia ele roda sozinho às 14:00 (robo/agendar_14h.ps1).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/coletor.php';
requireAdmin('../login.php');

date_default_timezone_set('America/Sao_Paulo');

$arquivoResumo = __DIR__ . '/../robo/logs/ultima_execucao.json';
$resumo = null;
$executou = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $executou = true;
    ignore_user_abort(true);
    $resumo = lp_executar();
    if ($resumo['erro']) {
        setFlash('error', $resumo['erro']);
    } else {
        setFlash('success', $resumo['atualizados'] . ' preços atualizados e ' . count($resumo['novos']) . ' jogos novos cadastrados.');
    }
} elseif (is_file($arquivoResumo)) {
    $resumo = json_decode(file_get_contents($arquivoResumo), true);
}

$pageTitle = 'Atualização de Preços - Admin LootPrice';
$basePath = '../';

require_once __DIR__ . '/../includes/header.php';

$caixa = 'background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 22px; margin-bottom: 24px;';
?>

<div style="max-width: 900px; margin: 30px auto; padding: 0 20px;">

  <div style="margin-bottom: 25px;">
    <a href="index.php" style="color: var(--text-muted); text-decoration: none; font-size: 13px;">← Voltar ao Dashboard</a>
    <h1 style="text-align: left; margin: 5px 0 0 0; font-size: 24px;">Robô de preços</h1>
    <p style="color: var(--text-muted); margin: 6px 0 0 0; font-size: 14px; line-height: 1.6;">
      As lojas não oferecem API pública de preços. Por isso o robô consulta Steam, GOG, Xbox, Nuuvem e Epic Games
      <strong>todos os dias às 14:00</strong>, grava os valores no banco e cadastra jogos populares novos.
    </p>
  </div>

  <div style="<?= $caixa ?>">
    <h3 style="margin-top: 0; font-size: 16px;">Rodar agora</h3>
    <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6;">
      A coleta leva alguns minutos. Mantenha esta página aberta até terminar.
    </p>
    <form method="POST" action="atualizar_precos.php" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Coletando preços…';">
      <button type="submit" class="btn-login" style="padding: 12px 24px; font-size: 15px; font-weight: 700; border: none; cursor: pointer;">
        Atualizar preços agora
      </button>
    </form>
  </div>

  <?php if ($resumo): ?>
    <div style="<?= $caixa ?>">
      <h3 style="margin-top: 0; font-size: 16px;"><?= $executou ? 'Resultado desta coleta' : 'Última coleta' ?></h3>
      <p style="color: var(--text-muted); font-size: 13px; margin-top: 0;">
        <?= sanitize($resumo['inicio'] ?? '') ?> → <?= sanitize($resumo['fim'] ?? '') ?>
        · <?= (int)($resumo['jogos'] ?? 0) ?> jogos
        · <strong><?= (int)($resumo['atualizados'] ?? 0) ?></strong> preços atualizados
        · <?= count($resumo['novos'] ?? []) ?> jogos novos
        <?php if (isset($resumo['avisos'])): ?>
          · <?= (int)$resumo['avisos']['enviados'] ?> e-mails de promoção enviados<?= !empty($resumo['avisos']['falhas']) ? ' (' . (int)$resumo['avisos']['falhas'] . ' falharam)' : '' ?>
        <?php endif; ?>
      </p>
      <?php if (!empty($resumo['erro'])): ?>
        <p style="color: var(--text);"><strong>Erro:</strong> <?= sanitize($resumo['erro']) ?></p>
      <?php endif; ?>
      <?php if (!empty($resumo['novos'])): ?>
        <p style="font-size: 13px;"><strong>Novos jogos:</strong> <?= sanitize(implode(', ', $resumo['novos'])) ?></p>
      <?php endif; ?>

      <?php if (!empty($resumo['relatorio'])): ?>
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; margin-top: 12px;">
          <thead>
            <tr style="border-bottom: 1px solid var(--border); color: var(--text-muted);">
              <th style="padding: 8px;">Jogo</th>
              <th style="padding: 8px;">Loja</th>
              <th style="padding: 8px;">Antes</th>
              <th style="padding: 8px;">Agora</th>
              <th style="padding: 8px;">Desconto</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($resumo['relatorio'] as $r): ?>
              <tr style="border-bottom: 1px solid var(--border);">
                <td style="padding: 8px;"><strong><?= sanitize($r['jogo']) ?></strong></td>
                <td style="padding: 8px; color: var(--accent);"><?= sanitize($r['loja']) ?></td>
                <td style="padding: 8px; color: var(--text-muted);"><?= $r['antes'] === null ? 'novo' : formatPrice($r['antes']) ?></td>
                <td style="padding: 8px; font-weight: 700;"><?= formatPrice($r['depois']) ?></td>
                <td style="padding: 8px;"><?= $r['desconto'] > 0 ? '-' . (int)$r['desconto'] . '%' : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
