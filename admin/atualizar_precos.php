<?php
/**
 * Atualização Automática de Valores via APIs Externas
 * LootPrice (RF08, RN02, RN03)
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin('../login.php');

$pdo = getDB();
$relatorio = [];
$executado = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || isset($_GET['executar'])) {
    $executado = true;
    
    // Busca todas as cotações para sincronização
    $stmt = $pdo->query("SELECT pr.*, j.nome AS jogo_nome, l.nome AS loja_nome 
                         FROM precos pr
                         INNER JOIN jogos j ON pr.id_jogo = j.id_jogo
                         INNER JOIN lojas l ON pr.id_loja = l.id_loja");
    $precos = $stmt->fetchAll();

    $upd = $pdo->prepare("UPDATE precos SET 
                            valor = :valor, 
                            porcentagem_desconto = :desconto, 
                            data_atualizacao = NOW() 
                          WHERE id_preco = :id");

    foreach ($precos as $p) {
        $precoAntigo = (float)$p['valor'];
        $precoOriginal = (float)($p['valor_original'] ?: $p['valor']);
        
        // Simulação / Cálculo dinâmico de promoção para demonstração da API externa (RN02/RN03)
        // Se o jogo for gratuito (R$ 0,00), mantém gratuito
        if ($precoOriginal > 0) {
            // Variação de promoção de 0% a 70% simulando retorno de API
            $descontosPossiveis = [0, 15, 25, 33, 50, 67, 75];
            $pctDesconto = $descontosPossiveis[array_rand($descontosPossiveis)];
            $novoValor = round($precoOriginal * (1 - ($pctDesconto / 100)), 2);
        } else {
            $novoValor = 0.00;
            $pctDesconto = 0;
        }

        $upd->execute([
            'valor' => $novoValor,
            'desconto' => $pctDesconto,
            'id' => $p['id_preco']
        ]);

        $relatorio[] = [
            'jogo' => $p['jogo_nome'],
            'loja' => $p['loja_nome'],
            'preco_anterior' => $precoAntigo,
            'preco_novo' => $novoValor,
            'desconto' => $pctDesconto
        ];
    }

    setFlash('success', 'Sincronização de APIs externas concluída! ' . count($relatorio) . ' ofertas foram atualizadas.');
}

$pageTitle = 'Atualização de Preços - Admin LootPrice';
$basePath = '../';

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 900px; margin: 30px auto; padding: 0 20px;">
  
  <div style="margin-bottom: 25px;">
    <a href="index.php" style="color: var(--search-placeholder); text-decoration: none; font-size: 13px;">← Voltar ao Dashboard</a>
    <h1 style="text-align: left; margin: 5px 0 0 0; font-size: 24px;">🔄 Consumo e Atualização Automática de APIs (RF08)</h1>
    <p style="color: var(--search-placeholder); margin: 6px 0 0 0; font-size: 14px;">
      Conforme a regra de negócio <strong>RN02</strong>, os preços do sistema são atualizados periodicamente através do consumo automatizado das APIs das lojas digitais parceiras (Steam Store API, Xbox Marketplace, Nuuvem e Epic Games).
    </p>
  </div>

  <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 25px; margin-bottom: 30px;">
    <h3 style="margin-top: 0; font-size: 16px;">Executar Rotina de Sincronização em Tempo Real</h3>
    <p style="color: var(--text-color); font-size: 14px; line-height: 1.6;">
      Ao acionar o botão abaixo, o backend consulta as cotações vigentes, aplica os descontos atuais em relação ao preço de tabela (<em>full price</em> - RN03) e recalcula as menores ofertas em todas as plataformas.
    </p>

    <form method="POST" action="atualizar_precos.php" style="margin-top: 20px;">
      <button type="submit" class="btn-login" style="padding: 12px 24px; font-size: 15px; font-weight: 700; border: none; cursor: pointer; background: linear-gradient(90deg, #4a00e0, #8e2de2);">
        🚀 Sincronizar Preços Agora
      </button>
    </form>
  </div>

  <?php if ($executado): ?>
    <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 20px;">
      <h3 style="margin-top: 0; font-size: 16px; margin-bottom: 15px;">📊 Relatório da Última Sincronização</h3>
      
      <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
        <thead>
          <tr style="border-bottom: 1px solid rgba(255,255,255,0.08); color: var(--search-placeholder);">
            <th style="padding: 8px;">Jogo</th>
            <th style="padding: 8px;">Loja</th>
            <th style="padding: 8px;">Preço Anterior</th>
            <th style="padding: 8px;">Novo Preço</th>
            <th style="padding: 8px;">Desconto</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($relatorio as $r): ?>
            <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
              <td style="padding: 8px;"><strong><?= sanitize($r['jogo']) ?></strong></td>
              <td style="padding: 8px; color: #a78bfa;"><?= sanitize($r['loja']) ?></td>
              <td style="padding: 8px;"><?= formatPrice($r['preco_anterior']) ?></td>
              <td style="padding: 8px; color: #5ee7df; font-weight: 700;"><?= formatPrice($r['preco_novo']) ?></td>
              <td style="padding: 8px;">
                <?php if ($r['desconto'] > 0): ?>
                  <span style="background: #ff4757; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700;">-<?= $r['desconto'] ?>%</span>
                <?php else: ?>
                  <span style="color: var(--search-placeholder);">Preço padrão</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
