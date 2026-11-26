<?php
/**
 * Atualização de preços (RF08, RN02, RN03)
 *
 * "Atualizar agora" dispara o robô (robo/coletar.php) EM SEGUNDO PLANO e esta página mostra o andamento
 * (barra + últimas linhas do log). Assim a coleta, que leva minutos, não prende a sessão do admin
 * nem estoura o timeout do Apache. No dia a dia o robô roda sozinho às 14:00 (robo/agendar_14h.ps1).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';
requireAdmin('../login.php');

date_default_timezone_set('America/Sao_Paulo');

$pasta = __DIR__ . '/../robo/logs';
$arqResumo = $pasta . '/ultima_execucao.json';
$arqAndamento = $pasta . '/andamento.json';

/** A coleta está rodando agora? (o robô segura um lock em coletor.lock enquanto trabalha) */
function coletaRodando(string $pasta): bool {
    if (!is_dir($pasta)) return false;
    $f = @fopen($pasta . '/coletor.lock', 'c');
    if (!$f) return false;
    $livre = flock($f, LOCK_EX | LOCK_NB);
    if ($livre) flock($f, LOCK_UN);
    fclose($f);
    return !$livre;
}

// ---- Andamento (consultado pelo JavaScript da página) ----
if (isset($_GET['status'])) {
    session_write_close(); // não segura a sessão enquanto responde
    $a = is_file($arqAndamento) ? (json_decode(file_get_contents($arqAndamento), true) ?: []) : [];
    $a['rodando'] = coletaRodando($pasta);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($a, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Disparar a coleta em segundo plano ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    exigirPostCsrf('atualizar_precos.php');

    if (coletaRodando($pasta)) {
        setFlash('info', 'Já existe uma coleta em andamento.');
    } else {
        // No Apache, PHP_BINDIR é o caminho de compilação (ex.: C:\php) e pode não existir:
        // procura o php.exe também ao lado da pasta de extensões (C:\xampp\php\ext → C:\xampp\php).
        $exe = stripos(PHP_OS, 'WIN') === 0 ? 'php.exe' : 'php';
        $php = '';
        foreach ([PHP_BINDIR, dirname((string) ini_get('extension_dir')), dirname((string) PHP_BINARY)] as $dir) {
            if ($dir !== '' && is_file($dir . DIRECTORY_SEPARATOR . $exe)) {
                $php = $dir . DIRECTORY_SEPARATOR . $exe;
                break;
            }
        }
        $script = realpath(__DIR__ . '/../robo/coletar.php');
        $semNovos = lp_post('novos', 3) === 'sim' ? '' : ' --sem-novos';
        if (!is_file($php) || !$script) {
            setFlash('error', 'Não achei o PHP de linha de comando para rodar o robô. Rode manualmente: php robo/coletar.php');
        } else {
            if (stripos(PHP_OS, 'WIN') === 0) {
                $cmd = 'start "" /B "' . $php . '" "' . $script . '"' . $semNovos . ' > NUL 2>&1';
                pclose(popen($cmd, 'r'));
            } else {
                exec('nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . $semNovos . ' > /dev/null 2>&1 &');
            }
            setFlash('success', 'Coleta iniciada em segundo plano. Acompanhe o andamento abaixo.');
        }
    }
    header('Location: atualizar_precos.php?iniciou=1');
    exit;
}

$resumo = is_file($arqResumo) ? json_decode(file_get_contents($arqResumo), true) : null;
$rodando = coletaRodando($pasta);
$acabouDeIniciar = isset($_GET['iniciou']); // o robô leva um instante para abrir o lock

$pageTitle = 'Atualização de Preços - Admin LootPrice';
$basePath = '../';
$cssAdmin = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="adm-wrap adm-estreito">

  <div style="margin-bottom: 25px;">
    <a href="index.php" class="adm-voltar">← Voltar ao Dashboard</a>
    <h1 class="adm-title">Robô de preços</h1>
  </div>

  <div class="adm-card">
    <h3>Rodar agora</h3>
    <form method="POST" action="atualizar_precos.php" id="formColeta">
      <?= csrfField() ?>
      <label class="adm-check" style="display:inline-flex; margin-bottom:14px;">
        <input type="checkbox" name="novos" value="sim" checked> Cadastrar jogos novos (até 8)
      </label><br>
      <button type="submit" class="btn-adm" id="btnColeta" <?= $rodando ? 'disabled' : '' ?>><?= $rodando ? 'Coleta em andamento…' : 'Atualizar preços agora' ?></button>
    </form>

    <div id="painelAndamento" <?= $rodando ? '' : 'hidden' ?> style="margin-top:18px;">
      <div id="textoAndamento" class="adm-sub" style="margin:0;">Iniciando…</div>
      <div class="adm-barra"><i id="barraAndamento"></i></div>
      <div class="adm-log" id="logAndamento" aria-live="polite"></div>
    </div>
  </div>

  <?php if ($resumo): ?>
    <div class="adm-card" id="cartaoResumo">
      <h3>Última coleta</h3>
      <p class="adm-sub" style="margin-top:0;">
        <?= sanitize($resumo['inicio'] ?? '') ?> → <?= sanitize($resumo['fim'] ?? '') ?>
        · <?= (int)($resumo['jogos'] ?? 0) ?> jogos
        · <strong><?= (int)($resumo['atualizados'] ?? 0) ?></strong> preços atualizados
        · <?= count($resumo['novos'] ?? []) ?> jogos novos
        <?php if (!empty($resumo['removidos'])): ?> · <?= (int)$resumo['removidos'] ?> preços antigos removidos<?php endif; ?>
        <?php if (isset($resumo['avisos'])): ?>
          · <?= (int)$resumo['avisos']['enviados'] ?> e-mails de promoção enviados<?= !empty($resumo['avisos']['falhas']) ? ' (' . (int)$resumo['avisos']['falhas'] . ' falharam)' : '' ?>
        <?php endif; ?>
      </p>
      <?php if (!empty($resumo['erro'])): ?>
        <div class="adm-aviso erro"><strong>Erro:</strong> <?= sanitize($resumo['erro']) ?></div>
      <?php endif; ?>
      <?php if (!empty($resumo['lojas_sem_dados'])): ?>
        <div class="adm-aviso alerta"><strong>Atenção:</strong> <?= sanitize(implode(', ', $resumo['lojas_sem_dados'])) ?> não trouxe preços.<?php if (!empty($resumo['lojas_bloqueadas'])): ?> <?= sanitize(implode(', ', $resumo['lojas_bloqueadas'])) ?> bloqueou o robô (limite de acessos); os últimos preços foram mantidos, tente de novo mais tarde.<?php endif; ?></div>
      <?php endif; ?>
      <?php if (!empty($resumo['novos'])): ?>
        <p style="font-size: 13px;"><strong>Novos jogos:</strong> <?= sanitize(implode(', ', $resumo['novos'])) ?></p>
      <?php endif; ?>

      <?php if (!empty($resumo['relatorio'])): ?>
        <div class="adm-tabela-wrap" style="max-height: 420px; overflow:auto;">
          <table class="adm-tabela">
            <thead><tr><th>Jogo</th><th>Loja</th><th>Antes</th><th>Agora</th><th>Desconto</th></tr></thead>
            <tbody>
              <?php foreach ($resumo['relatorio'] as $r): ?>
                <tr>
                  <td><strong><?= sanitize($r['jogo']) ?></strong></td>
                  <td style="color: var(--accent-text);"><?= sanitize($r['loja']) ?></td>
                  <td class="adm-mini" style="font-size:13px;"><?= $r['antes'] === null ? 'novo' : formatPrice($r['antes']) ?></td>
                  <td><strong><?= formatPrice($r['depois']) ?></strong></td>
                  <td><?= $r['desconto'] > 0 ? '-' . (int)$r['desconto'] . '%' : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>

<script>
// Acompanha a coleta em segundo plano: consulta o andamento a cada 3 s e recarrega ao terminar.
(function () {
  var painel = document.getElementById('painelAndamento'),
      texto = document.getElementById('textoAndamento'),
      barra = document.getElementById('barraAndamento'),
      log = document.getElementById('logAndamento'),
      btn = document.getElementById('btnColeta'),
      jaRodou = <?= ($rodando || $acabouDeIniciar) ? 'true' : 'false' ?>,
      espera = <?= $acabouDeIniciar ? 8 : 0 ?>;   // tentativas até o robô começar

  function atualizar() {
    fetch('atualizar_precos.php?status=1', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (a) {
        if (a.rodando) {
          jaRodou = true; espera = 0;
          painel.hidden = false;
          btn.disabled = true; btn.textContent = 'Coleta em andamento…';
          var total = a.jogos || 0, atual = a.jogo_atual || 0;
          texto.textContent = total ? 'Jogo ' + atual + ' de ' + total : 'Preparando…';
          barra.style.width = total ? Math.round(atual / total * 100) + '%' : '3%';
          log.textContent = (a.linhas || []).join('\n');
          log.scrollTop = log.scrollHeight;
          setTimeout(atualizar, 3000);
        } else if (espera > 0) {
          espera--; painel.hidden = false; texto.textContent = 'Iniciando o robô…';
          setTimeout(atualizar, 2000);
        } else if (jaRodou) {
          location.href = 'atualizar_precos.php'; // terminou: mostra o resultado
        }
      })
      .catch(function () { setTimeout(atualizar, 5000); });
  }
  if (jaRodou) atualizar();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
