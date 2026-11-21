<?php
/**
 * Aviso de promoção da lista de desejos - LootPrice
 *
 * Depois que o robô atualiza os preços, quem tem um jogo na lista de desejos recebe UM e-mail
 * com todos os jogos dele que entraram em promoção (ou baixaram ainda mais de preço).
 *
 * Regras:
 *  - "Em promoção" = a oferta de menor preço do jogo tem desconto (valor_original > valor).
 *  - Só recebe quem tem o e-mail confirmado e não desligou os avisos (usuarios.alertas_email).
 *  - Cada jogo só é avisado de novo se o preço cair mais do que no último aviso; quando a promoção
 *    acaba, o "lembrete" é apagado, então a próxima promoção avisa outra vez.
 *  - Se o envio falhar nada é marcado como avisado: a próxima execução tenta de novo.
 *  - Todo e-mail traz um link para parar de receber (descadastrar.php).
 */

require_once __DIR__ . '/desejos.php';

const LP_AVISOS_MAX_EMAILS_POR_EXECUCAO = 100;   // protege a cota diária do Gmail

function ensureAvisosSchema(PDO $pdo): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        if (!$pdo->query("SHOW COLUMNS FROM usuarios LIKE 'alertas_email'")->fetch()) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN alertas_email TINYINT(1) NOT NULL DEFAULT 1");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS `avisos_preco` (
            `id_usuario` INT NOT NULL,
            `id_jogo` INT NOT NULL,
            `preco_avisado` DECIMAL(10,2) NOT NULL,
            `avisado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_usuario`, `id_jogo`),
            CONSTRAINT `fk_aviso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
            CONSTRAINT `fk_aviso_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ok = true;
    } catch (Exception $e) {
        $ok = false;
    }
    return $ok;
}

/* ---------- Link para parar de receber os avisos ---------- */

function lp_token_descadastro(int $idUsuario): string {
    require_once __DIR__ . '/mailer.php';
    $cfg = lp_mail_config() ?? [];
    $segredo = $cfg['segredo'] ?? hash('sha256', (string)($cfg['senha'] ?? 'lootprice'));
    return hash_hmac('sha256', "descadastrar:$idUsuario", $segredo);
}

function lp_url_descadastro(int $idUsuario): string {
    require_once __DIR__ . '/mailer.php';
    return lp_site_url() . '/descadastrar.php?u=' . $idUsuario . '&t=' . lp_token_descadastro($idUsuario);
}

/* ---------- Promoções de hoje ---------- */

/** Jogos cuja oferta de menor preço tem desconto: [id_jogo => dados da oferta]. */
function lp_promocoes_atuais(PDO $pdo): array {
    $rows = $pdo->query("SELECT j.id_jogo, j.nome, p.valor, p.valor_original, p.porcentagem_desconto, l.nome AS loja
                         FROM jogos j
                         JOIN precos p ON p.id_jogo = j.id_jogo
                         JOIN lojas l ON l.id_loja = p.id_loja
                         WHERE p.valor_original > p.valor AND p.porcentagem_desconto > 0
                           AND p.valor = (SELECT MIN(p2.valor) FROM precos p2 WHERE p2.id_jogo = j.id_jogo)
                         ORDER BY p.porcentagem_desconto DESC")->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[(int)$r['id_jogo']] ??= $r;   // empate de preço: fica o de maior desconto
    return $out;
}

function lp_dinheiro($v): string {
    return ((float)$v) <= 0 ? 'Grátis' : 'R$ ' . number_format((float)$v, 2, ',', '.');
}

/** Monta o e-mail (um por pessoa, com todos os jogos dela em promoção). */
function lp_email_promocao(array $user, array $jogos): array {
    require_once __DIR__ . '/mailer.php';
    $site = lp_site_url();
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $n = count($jogos);
    $primeiro = $esc(explode(' ', trim($user['nome']))[0]);

    $linhas = '';
    foreach ($jogos as $g) {
        $linhas .= '<tr><td style="padding:12px 0;border-bottom:1px solid #e6e0f7;">'
            . '<div style="font-weight:700;font-size:16px;">' . $esc($g['nome']) . '</div>'
            . '<div style="margin-top:4px;">'
            . '<span style="font-size:20px;font-weight:800;color:#15803d;">' . lp_dinheiro($g['valor']) . '</span> '
            . '<span style="text-decoration:line-through;color:#6b6489;">' . lp_dinheiro($g['valor_original']) . '</span> '
            . '<span style="background:#15803d;color:#ffffff;font-size:12px;font-weight:700;padding:2px 7px;border-radius:10px;">-' . (int)$g['porcentagem_desconto'] . '%</span>'
            . '<span style="color:#6b6489;font-size:13px;"> &nbsp;na ' . $esc($g['loja']) . '</span></div>'
            . '<div style="margin-top:8px;"><a href="' . $site . '/jogo.php?id=' . (int)$g['id_jogo'] . '" '
            . 'style="display:inline-block;background:#6d28d9;color:#ffffff;text-decoration:none;font-weight:700;font-size:13px;padding:8px 16px;border-radius:8px;">Ver ofertas</a></div>'
            . '</td></tr>';
    }

    $corpo = '<p>Olá, ' . $primeiro . '!</p>'
        . '<p>' . ($n === 1 ? 'Um jogo da sua lista de desejos está em promoção:' : "$n jogos da sua lista de desejos estão em promoção:") . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $linhas . '</table>'
        . '<p style="color:#6b6489;font-size:13px;margin-top:16px;">Preços conferidos pelo nosso robô; podem mudar a qualquer momento.</p>';

    $rodape = 'Você recebe este e-mail porque tem jogos na sua lista de desejos no LootPrice. '
        . '<a href="' . lp_url_descadastro((int)$user['id_usuario']) . '" style="color:#6d28d9;">Parar de receber avisos</a>.';

    $primeiroJogo = $jogos[0];
    $assunto = $n === 1
        ? 'Promoção: ' . $primeiroJogo['nome'] . ' (-' . (int)$primeiroJogo['porcentagem_desconto'] . '%)'
        : "$n jogos da sua lista de desejos estão em promoção";

    return [$assunto, lp_email_template($n === 1 ? 'Jogo em promoção' : 'Jogos em promoção', $corpo, $rodape)];
}

/**
 * Envia os avisos. Com $simular = true só descobre quem receberia (não envia nem grava nada).
 * $apenasUsuario limita a um id (útil em testes).
 * @return array ['promocoes','usuarios','jogos','enviados','falhas','detalhe'=>[ [usuario, [jogos]] ... ]]
 */
function lp_enviar_avisos_promocao(PDO $pdo, bool $simular = false, ?int $apenasUsuario = null, int $max = LP_AVISOS_MAX_EMAILS_POR_EXECUCAO): array {
    $res = ['promocoes' => 0, 'usuarios' => 0, 'jogos' => 0, 'enviados' => 0, 'falhas' => 0, 'detalhe' => []];
    if (!ensureAvisosSchema($pdo) || !ensureDesejosTable($pdo)) return $res;

    $promos = lp_promocoes_atuais($pdo);
    $res['promocoes'] = count($promos);

    // Promoção que acabou: apaga o lembrete, para a próxima promoção voltar a avisar
    if (!$simular) {
        if ($promos) {
            $ids = implode(',', array_map('intval', array_keys($promos)));
            $pdo->exec("DELETE FROM avisos_preco WHERE id_jogo NOT IN ($ids)");
        } else {
            $pdo->exec("DELETE FROM avisos_preco");
        }
    }
    if (!$promos) return $res;

    $sql = "SELECT u.id_usuario, u.nome, u.email, d.id_jogo, a.preco_avisado
            FROM lista_desejos d
            JOIN usuarios u ON u.id_usuario = d.id_usuario
            LEFT JOIN avisos_preco a ON a.id_usuario = d.id_usuario AND a.id_jogo = d.id_jogo
            WHERE u.email_verificado = 1 AND u.alertas_email = 1"
         . ($apenasUsuario ? " AND u.id_usuario = " . (int)$apenasUsuario : '')
         . " ORDER BY u.id_usuario";
    $porUsuario = [];
    foreach ($pdo->query($sql)->fetchAll() as $r) {
        $promo = $promos[(int)$r['id_jogo']] ?? null;
        if (!$promo) continue;                                          // esse jogo não está em promoção
        if ($r['preco_avisado'] !== null && (float)$promo['valor'] >= (float)$r['preco_avisado'] - 0.005) continue; // já avisado
        $porUsuario[(int)$r['id_usuario']]['user'] = $r;
        $porUsuario[(int)$r['id_usuario']]['jogos'][] = $promo;
    }
    $res['usuarios'] = count($porUsuario);

    $enviadosAgora = 0;
    foreach ($porUsuario as $idUsuario => $dados) {
        $res['jogos'] += count($dados['jogos']);
        $res['detalhe'][] = [$dados['user']['email'], array_map(fn($g) => $g['nome'] . ' ' . lp_dinheiro($g['valor']) . ' (-' . $g['porcentagem_desconto'] . '%)', $dados['jogos'])];
        if ($simular) continue;
        if ($enviadosAgora >= $max) break;                              // o resto vai na próxima execução

        require_once __DIR__ . '/mailer.php';
        [$assunto, $html] = lp_email_promocao($dados['user'], $dados['jogos']);
        [$ok] = lp_enviar_email($dados['user']['email'], $dados['user']['nome'], $assunto, $html);
        if (!$ok) { $res['falhas']++; continue; }

        $ins = $pdo->prepare("INSERT INTO avisos_preco (id_usuario, id_jogo, preco_avisado, avisado_em) VALUES (?,?,?,NOW())
                              ON DUPLICATE KEY UPDATE preco_avisado = VALUES(preco_avisado), avisado_em = NOW()");
        foreach ($dados['jogos'] as $g) $ins->execute([$idUsuario, $g['id_jogo'], $g['valor']]);
        $res['enviados']++;
        $enviadosAgora++;
        sleep(1);                                                       // sem pressa: evita bloqueio por rajada
    }
    return $res;
}
