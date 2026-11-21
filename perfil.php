<?php
/**
 * Meu perfil - LootPrice
 * Foto de perfil, imagem de fundo (capa), nome e username.
 * Cada pessoa só enxerga o próprio perfil (não há busca nem página pública de usuários).
 */
require_once __DIR__ . '/includes/functions.php';

requireLogin('login.php');

$pdo = getDB();
$sessao = currentUser();
$id = (int)($sessao['id_usuario'] ?? 0);

/** Salva uma imagem enviada em fotos/perfis/. Retorna [caminho|null, erro|null]. */
function lp_salvar_imagem(array $arq, int $id, string $tipo): array {
    if (($arq['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($arq['error'] !== UPLOAD_ERR_OK) return [null, 'Não foi possível enviar a imagem. Tente outra.'];
    if ($arq['size'] > 5 * 1024 * 1024) return [null, 'A imagem deve ter no máximo 5 MB.'];

    $info = @getimagesize($arq['tmp_name']);
    $exts = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($exts[$info[2]])) return [null, 'Use uma imagem JPG, PNG, WEBP ou GIF.'];

    $dir = __DIR__ . '/fotos/perfis';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $nome = sprintf('u%d_%s_%s.%s', $id, $tipo, bin2hex(random_bytes(6)), $exts[$info[2]]);
    if (!move_uploaded_file($arq['tmp_name'], $dir . '/' . $nome)) return [null, 'Falha ao salvar a imagem no servidor.'];
    return ['fotos/perfis/' . $nome, null];
}

function lp_apagar_imagem(?string $caminho): void {
    if ($caminho && strpos($caminho, 'fotos/perfis/') === 0 && strpos($caminho, '..') === false) {
        $f = __DIR__ . '/' . $caminho;
        if (is_file($f)) @unlink($f);
    }
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
$stmt->execute(['id' => $id]);
$u = $stmt->fetch();
if (!$u) { logoutUser(); header('Location: login.php'); exit; }

$erro = null;

$erroSenha = null;
$sexosValidos = ['masculino', 'feminino', 'nao_informado'];
$metodoPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

if ($metodoPost && ($_POST['acao'] ?? '') === 'senha') {
    // ---- Alterar senha (a senha é guardada só como hash) ----
    $atual = $_POST['senha_atual'] ?? '';
    $nova  = $_POST['nova_senha'] ?? '';
    $conf  = $_POST['confirma_senha'] ?? '';
    if ($atual === '' || $nova === '' || $conf === '') {
        $erroSenha = 'Preencha a senha atual, a nova senha e a confirmação.';
    } elseif (!password_verify($atual, $u['senha'])) {
        $erroSenha = 'Senha atual incorreta.';
    } elseif (strlen($nova) < 6) {
        $erroSenha = 'A nova senha deve ter pelo menos 6 caracteres.';
    } elseif ($nova !== $conf) {
        $erroSenha = 'A confirmação não é igual à nova senha.';
    } elseif (password_verify($nova, $u['senha'])) {
        $erroSenha = 'A nova senha precisa ser diferente da atual.';
    } else {
        $pdo->prepare("UPDATE usuarios SET senha = :s WHERE id_usuario = :id")
            ->execute(['s' => password_hash($nova, PASSWORD_DEFAULT), 'id' => $id]);
        $stmt->execute(['id' => $id]);
        loginUser($stmt->fetch());
        setFlash('success', 'Senha alterada com sucesso!');
        header('Location: perfil.php');
        exit;
    }
} elseif ($metodoPost) {
    // ---- Editar perfil ----
    $nome = trim($_POST['nome'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $sexo = $_POST['sexo'] ?? 'nao_informado';
    $nascForm = trim($_POST['data_nascimento'] ?? '');
    $telForm = trim($_POST['telefone'] ?? '');

    if ($nome === '' || mb_strlen($nome) > 100) {
        $erro = 'Informe seu nome (até 100 caracteres).';
    } elseif (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        $erro = 'O username deve ter de 3 a 30 caracteres (letras, números, "_" ou ".").';
    } elseif (!in_array($sexo, $sexosValidos, true)) {
        $erro = 'Selecione uma opção válida para o sexo.';
    } else {
        $dup = $pdo->prepare("SELECT 1 FROM usuarios WHERE username = :u AND id_usuario <> :id LIMIT 1");
        $dup->execute(['u' => $username, 'id' => $id]);
        if ($dup->fetchColumn()) $erro = 'Não foi possível usar o nome de usuário "' . $username . '" porque ele já está em uso. Escolha outro.';
    }

    // Data de nascimento (opcional): data real, entre 1900 e hoje
    $nascimento = null;
    if (!$erro && $nascForm !== '') {
        $d = DateTime::createFromFormat('!Y-m-d', $nascForm);
        if (!$d || $d->format('Y-m-d') !== $nascForm || $d > new DateTime('today') || (int)$d->format('Y') < 1900) {
            $erro = 'Data de nascimento inválida.';
        } else {
            $nascimento = $nascForm;
        }
    }

    // Telefone (opcional): 10 a 13 dígitos, pode ter +, espaços, parênteses e hífen
    $telefone = null;
    if (!$erro && $telForm !== '') {
        $digitos = preg_replace('/\D/', '', $telForm);
        if (!preg_match('/^[0-9+()\s-]+$/', $telForm) || strlen($digitos) < 10 || strlen($digitos) > 13) {
            $erro = 'Telefone inválido. Use DDD + número, por exemplo (21) 99999-9999.';
        } else {
            $telefone = mb_substr($telForm, 0, 20);
        }
    }

    // E-mail: se mudou, exige a senha atual (e confere formato e duplicidade)
    $emailForm = trim($_POST['email'] ?? '');
    $emailNovo = $u['email'];
    if (!$erro && strcasecmp($emailForm, $u['email']) !== 0) {
        if (!filter_var($emailForm, FILTER_VALIDATE_EMAIL) || mb_strlen($emailForm) > 100) {
            $erro = 'Formato de e-mail inválido.';
        } elseif (($_POST['senha_atual'] ?? '') === '') {
            $erro = 'Para trocar o e-mail, informe sua senha atual.';
        } elseif (!password_verify($_POST['senha_atual'], $u['senha'])) {
            $erro = 'Senha atual incorreta. O e-mail não foi alterado.';
        } else {
            $dupE = $pdo->prepare("SELECT 1 FROM usuarios WHERE email = :e AND id_usuario <> :id LIMIT 1");
            $dupE->execute(['e' => $emailForm, 'id' => $id]);
            if ($dupE->fetchColumn()) $erro = 'Este e-mail já está cadastrado em outra conta.';
            else $emailNovo = $emailForm;
        }
    }

    $novaFoto = $novoFundo = null;
    if (!$erro) {
        [$novaFoto, $e1] = lp_salvar_imagem($_FILES['foto_perfil'] ?? [], $id, 'perfil');
        [$novoFundo, $e2] = lp_salvar_imagem($_FILES['foto_fundo'] ?? [], $id, 'fundo');
        $erro = $e1 ?? $e2;
        if ($erro) { lp_apagar_imagem($novaFoto); lp_apagar_imagem($novoFundo); }
    }

    if (!$erro) {
        $foto  = $u['foto_perfil'];
        $fundo = $u['foto_fundo'];
        if ($novaFoto)  { lp_apagar_imagem($foto);  $foto  = $novaFoto; }
        elseif (!empty($_POST['remover_foto']))  { lp_apagar_imagem($foto);  $foto  = null; }
        if ($novoFundo) { lp_apagar_imagem($fundo); $fundo = $novoFundo; }
        elseif (!empty($_POST['remover_fundo'])) { lp_apagar_imagem($fundo); $fundo = null; }

        $up = $pdo->prepare("UPDATE usuarios SET nome = :n, username = :u, sexo = :sx, data_nascimento = :dn, telefone = :tel, email = :em, foto_perfil = :fp, foto_fundo = :ff WHERE id_usuario = :id");
        $up->execute(['n' => $nome, 'u' => $username, 'sx' => $sexo, 'dn' => $nascimento, 'tel' => $telefone, 'em' => $emailNovo, 'fp' => $foto, 'ff' => $fundo, 'id' => $id]);

        $stmt->execute(['id' => $id]);
        loginUser($stmt->fetch());
        setFlash('success', 'Perfil atualizado!');
        header('Location: perfil.php');
        exit;
    }
    // Mantém o que a pessoa digitou ao mostrar o erro
    $u['nome'] = $nome;
    $u['username'] = $username;
    $u['sexo'] = $sexo;
    $u['data_nascimento'] = $nascForm;
    $u['telefone'] = $telForm;
    $emailDigitado = $emailForm;
}
$emailDigitado = $emailDigitado ?? $u['email'];

$aba = ($_GET['aba'] ?? '') === 'desejos' ? 'desejos' : 'perfil';
$desejos = getWishlistGames($id);
$pageTitle = 'Meu perfil - LootPrice';
$basePath = '';
require_once __DIR__ . '/includes/header.php';

$fundoOk = $u['foto_fundo'] && is_file(__DIR__ . '/' . $u['foto_fundo']);
$fundoUrl = $fundoOk ? sanitize($u['foto_fundo']) . '?v=' . filemtime(__DIR__ . '/' . $u['foto_fundo']) : '';
?>
<link rel="stylesheet" href="<?= assetUrl('css_index/cards.css') ?>">
<link rel="stylesheet" href="<?= assetUrl('css_index/home.css') ?>">
<div class="perfil-wrap">
  <?php if ($erro): ?>
    <div class="flash-alert flash-error" role="alert" style="margin:0 0 14px;"><span><?= sanitize($erro) ?></span></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="perfil-card<?= ($erro || $erroSenha) ? ' is-editing' : '' ?><?= $aba === 'desejos' ? ' is-desejos' : '' ?>" id="perfilForm">
    <div class="perfil-banner" id="banner" <?= $fundoOk ? 'style="background-image:url(\'' . $fundoUrl . '\')"' : '' ?>>
      <label class="perfil-edit perfil-edit-banner" title="Alterar imagem de fundo">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        Alterar fundo
        <input type="file" name="foto_fundo" id="inFundo" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
      </label>
    </div>

    <div class="perfil-head">
      <div class="perfil-avatar-box">
        <div id="avatarAtual"><?= avatarHtml($u, 'avatar-xl') ?></div>
        <label class="perfil-edit perfil-edit-avatar" title="Alterar foto de perfil">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          <input type="file" name="foto_perfil" id="inFoto" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
        </label>
      </div>
      <div class="perfil-ident">
        <h1 class="perfil-nome"><?= sanitize($u['nome']) ?></h1>
        <p class="perfil-user">@<?= sanitize($u['username'] ?: 'sem-username') ?></p>      </div>
      <button type="button" class="perfil-btn-editar" id="btnEditar" title="Editar perfil" aria-label="Editar perfil">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
        <span>Editar perfil</span>
      </button>
    </div>

    <nav class="perfil-abas" aria-label="Seções do perfil">
      <a href="perfil.php" class="perfil-aba<?= $aba === 'perfil' ? ' ativa' : '' ?>">Perfil</a>
      <a href="perfil.php?aba=desejos" class="perfil-aba<?= $aba === 'desejos' ? ' ativa' : '' ?>">Lista de desejos <span class="perfil-aba-n"><?= count($desejos) ?></span></a>
    </nav>

    <div class="perfil-form">
      <section class="perfil-bloco">
        <h2>Informações públicas</h2>
        <p class="perfil-bloco-sub">Estas informações aparecem no seu perfil.</p>
        <div class="perfil-campo">
          <label for="nome">Nome de exibição</label>
          <input type="text" id="nome" name="nome" maxlength="100" value="<?= sanitize($u['nome']) ?>" required>
        </div>
        <div class="perfil-campo">
          <label for="username">Nome de usuário</label>
          <div class="perfil-arroba"><span>@</span><input type="text" id="username" name="username" maxlength="30" pattern="[A-Za-z0-9_.]{3,30}" value="<?= sanitize($u['username']) ?>" required></div>
          <small>3 a 30 caracteres: letras, números, "_" ou ".".</small>
        </div>
        <?php if ($u['foto_perfil'] || $u['foto_fundo']): ?>
        <div class="perfil-remover">
          <?php if ($u['foto_perfil']): ?><label><input type="checkbox" name="remover_foto" value="1"> Remover foto de perfil</label><?php endif; ?>
          <?php if ($u['foto_fundo']): ?><label><input type="checkbox" name="remover_fundo" value="1"> Remover imagem de fundo</label><?php endif; ?>
        </div>
        <?php endif; ?>
        <p class="perfil-dica">Imagens JPG, PNG, WEBP ou GIF de até 5 MB. Sem foto, usamos a primeira letra do seu nome.</p>
      </section>

      <section class="perfil-bloco">
        <h2>Informações pessoais</h2>
        <p class="perfil-bloco-sub">Não são exibidas publicamente.</p>
        <div class="perfil-grid">
          <div class="perfil-campo">
            <label for="sexo">Sexo</label>
            <select id="sexo" name="sexo">
              <option value="masculino" <?= $u['sexo'] === 'masculino' ? 'selected' : '' ?>>Masculino</option>
              <option value="feminino" <?= $u['sexo'] === 'feminino' ? 'selected' : '' ?>>Feminino</option>
              <option value="nao_informado" <?= $u['sexo'] === 'nao_informado' ? 'selected' : '' ?>>Prefiro não responder</option>
            </select>
          </div>
          <div class="perfil-campo">
            <label for="data_nascimento">Data de nascimento</label>
            <input type="date" id="data_nascimento" name="data_nascimento" min="1900-01-01" max="<?= date('Y-m-d') ?>" value="<?= sanitize((string)($u['data_nascimento'] ?? '')) ?>">
          </div>
        </div>
        <div class="perfil-campo">
          <label for="telefone">Telefone</label>
          <input type="tel" id="telefone" name="telefone" maxlength="20" placeholder="(21) 99999-9999" value="<?= sanitize((string)($u['telefone'] ?? '')) ?>" autocomplete="tel">
        </div>
        <div class="perfil-campo">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" maxlength="100" value="<?= sanitize($emailDigitado) ?>" required autocomplete="email">
        </div>
        <div class="perfil-campo" id="campoSenha" hidden>
          <label for="senha_atual">Senha atual</label>
          <input type="password" id="senha_atual" name="senha_atual" autocomplete="current-password" placeholder="Confirme sua senha para trocar o e-mail">
          <small>Necessária apenas quando você muda o e-mail.</small>
        </div>
      </section>

      <div class="perfil-acoes">
        <button type="submit" class="perfil-salvar">Salvar alterações</button>
        <a href="perfil.php" class="perfil-cancelar">Cancelar</a>
      </div>
    </div>
  </form>

  <form method="POST" class="perfil-senha-card" id="senhaForm" autocomplete="off">
    <input type="hidden" name="acao" value="senha">
    <section class="perfil-bloco">
      <h2>Alterar senha</h2>
      <p class="perfil-bloco-sub">Sua senha é guardada criptografada (hash); ninguém consegue vê-la.</p>
      <button type="button" class="perfil-btn-senha" id="btnSenha" aria-expanded="<?= $erroSenha ? 'true' : 'false' ?>">Alterar senha</button>
      <div class="perfil-senha-campos" id="senhaCampos" <?= $erroSenha ? '' : 'hidden' ?>>
        <?php if ($erroSenha): ?><div class="flash-alert flash-error" role="alert"><span><?= sanitize($erroSenha) ?></span></div><?php endif; ?>
        <div class="perfil-campo">
          <label for="sa">Senha atual</label>
          <input type="password" id="sa" name="senha_atual" autocomplete="current-password" required>
        </div>
        <div class="perfil-grid">
          <div class="perfil-campo">
            <label for="ns">Nova senha</label>
            <input type="password" id="ns" name="nova_senha" minlength="6" autocomplete="new-password" required>
          </div>
          <div class="perfil-campo">
            <label for="cs">Confirmar nova senha</label>
            <input type="password" id="cs" name="confirma_senha" minlength="6" autocomplete="new-password" required>
          </div>
        </div>
        <div class="perfil-acoes"><button type="submit" class="perfil-salvar">Salvar nova senha</button></div>
      </div>
    </section>
  </form>

  <?php if ($aba === 'desejos'): ?>
  <section class="desejos" aria-label="Lista de desejos">
    <?php if (empty($desejos)): ?>
      <div class="home-empty">Sua lista de desejos está vazia.<br>Abra a página de um jogo e clique em <strong>Adicionar à lista de desejos</strong>.<br><a href="catalogo.php" style="color: var(--accent-text);">Ver o catálogo</a></div>
    <?php else: ?>
      <div class="desejo-lista">
        <?php foreach ($desejos as $j): $jid = (int)$j['id_jogo']; ?>
          <div class="desejo-item">
            <a href="jogo.php?id=<?= $jid ?>" class="desejo-capa">
              <img src="<?= sanitize($j['imagem_capa']) ?>" alt="<?= sanitize($j['nome']) ?>" loading="lazy">
            </a>
            <div class="desejo-info">
              <a href="jogo.php?id=<?= $jid ?>" class="desejo-nome"><?= sanitize($j['nome']) ?></a>
              <span class="desejo-preco"><?= isset($j['menor_preco']) ? ((float)$j['menor_preco'] <= 0 ? 'Gratuito' : 'A partir de ' . formatPrice($j['menor_preco'])) : '' ?></span>
            </div>
            <form method="POST" action="desejos.php">
              <input type="hidden" name="id_jogo" value="<?= $jid ?>">
              <input type="hidden" name="acao" value="remover">
              <input type="hidden" name="voltar" value="perfil">
              <button type="submit" class="desejo-remover">Remover</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>

<!-- Editor de recorte: a pessoa arrasta e dá zoom para escolher o que aparece -->
<div class="crop-modal" id="cropModal" hidden role="dialog" aria-modal="true" aria-labelledby="cropTitulo">
  <div class="crop-box">
    <h2 id="cropTitulo" class="crop-titulo">Ajustar imagem</h2>
    <p class="crop-dica">Arraste a imagem para escolher a parte que aparece e use o zoom para aproximar.</p>
    <div class="crop-stage" id="cropStage"><img id="cropImg" alt="" draggable="false"><div class="crop-mascara"></div></div>
    <div class="crop-zoom">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5M8 11h6"/></svg>
      <input type="range" id="cropZoom" min="1" max="4" step="0.01" value="1" aria-label="Zoom">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5M8 11h6M11 8v6"/></svg>
    </div>
    <div class="crop-acoes">
      <button type="button" class="perfil-salvar" id="cropOk">Aplicar</button>
      <button type="button" class="crop-cancelar" id="cropCancelar">Cancelar</button>
    </div>
  </div>
</div>

<script>
// Mostra o campo de senha só quando o e-mail é alterado
(function () {
  const email = document.getElementById('email'), box = document.getElementById('campoSenha');
  const original = <?= json_encode($u['email']) ?>;
  const sync = () => { box.hidden = email.value.trim().toLowerCase() === original.toLowerCase(); };
  email.addEventListener('input', sync); sync();
})();
// Mostra/esconde os campos de troca de senha
document.getElementById('btnSenha').addEventListener('click', function () {
  const c = document.getElementById('senhaCampos');
  c.hidden = !c.hidden; this.setAttribute('aria-expanded', String(!c.hidden));
  if (!c.hidden) document.getElementById('sa').focus();
});
// Abre o modo de edição ao clicar no ícone
document.getElementById('btnEditar').addEventListener('click', function () {
  document.getElementById('perfilForm').classList.add('is-editing');
  document.getElementById('nome').focus();
});

// ---------- Editor de recorte (foto de perfil redonda e imagem de fundo) ----------
(function () {
  const modal = document.getElementById('cropModal'), stage = document.getElementById('cropStage'),
        img = document.getElementById('cropImg'), zoomIn = document.getElementById('cropZoom'),
        titulo = document.getElementById('cropTitulo');
  const CFG = {
    perfil: { titulo: 'Ajustar foto de perfil', ratio: 1,   saida: [512, 512],   tipo: 'image/png',  nome: 'perfil.png', redondo: true },
    fundo:  { titulo: 'Ajustar imagem de fundo', ratio: 3.5, saida: [1400, 400], tipo: 'image/webp', nome: 'fundo.webp', redondo: false }
  };
  let cfg, input, url, nw, nh, fw, fh, smin, zoom, x, y, arrastando;

  function limitar() {                       // a imagem nunca deixa o quadro vazio
    const s = smin * zoom;
    x = Math.min(0, Math.max(fw - nw * s, x));
    y = Math.min(0, Math.max(fh - nh * s, y));
  }
  function desenhar() {
    limitar();
    const s = smin * zoom;
    img.style.width = nw * s + 'px'; img.style.height = nh * s + 'px';
    img.style.transform = 'translate(' + x + 'px,' + y + 'px)';
  }
  function aplicarZoom(novo, cx, cy) {       // zoom mantendo o ponto (cx,cy) do quadro parado
    novo = Math.min(4, Math.max(1, novo));
    const antes = smin * zoom, depois = smin * novo;
    x = cx - (cx - x) * (depois / antes); y = cy - (cy - y) * (depois / antes);
    zoom = novo; zoomIn.value = novo; desenhar();
  }
  function abrir(tipo, inputEl) {
    const f = inputEl.files[0]; if (!f) return;
    cfg = CFG[tipo]; input = inputEl;
    titulo.textContent = cfg.titulo;
    stage.className = 'crop-stage ' + (cfg.redondo ? 'crop-redondo' : 'crop-faixa');
    stage.style.aspectRatio = String(cfg.ratio);
    modal.hidden = false; document.body.style.overflow = 'hidden';
    url = URL.createObjectURL(f);
    img.onload = function () {
      nw = img.naturalWidth; nh = img.naturalHeight;
      fw = stage.clientWidth; fh = stage.clientHeight;
      smin = Math.max(fw / nw, fh / nh); zoom = 1; zoomIn.value = 1;
      x = (fw - nw * smin) / 2; y = (fh - nh * smin) / 2;   // começa centralizada
      desenhar();
    };
    img.src = url;
  }
  function fechar(limparInput) {
    modal.hidden = true; document.body.style.overflow = '';
    if (limparInput) input.value = '';
    if (url) { URL.revokeObjectURL(url); url = null; }
  }

  // Arrastar para escolher qual parte da foto aparece
  stage.addEventListener('pointerdown', e => { arrastando = { px: e.clientX, py: e.clientY, x, y }; stage.setPointerCapture(e.pointerId); });
  stage.addEventListener('pointermove', e => { if (!arrastando) return; x = arrastando.x + e.clientX - arrastando.px; y = arrastando.y + e.clientY - arrastando.py; desenhar(); });
  const soltar = () => { arrastando = null; };
  stage.addEventListener('pointerup', soltar); stage.addEventListener('pointercancel', soltar);
  stage.addEventListener('wheel', e => { e.preventDefault(); const r = stage.getBoundingClientRect(); aplicarZoom(zoom * (e.deltaY < 0 ? 1.08 : 1 / 1.08), e.clientX - r.left, e.clientY - r.top); }, { passive: false });
  zoomIn.addEventListener('input', () => aplicarZoom(parseFloat(zoomIn.value), fw / 2, fh / 2));
  document.getElementById('cropCancelar').addEventListener('click', () => fechar(true));
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) fechar(true); });

  // Confirmar: gera a imagem já recortada e coloca no campo do formulário
  document.getElementById('cropOk').addEventListener('click', function () {
    const s = smin * zoom, c = document.createElement('canvas');
    c.width = cfg.saida[0]; c.height = cfg.saida[1];
    const g = c.getContext('2d'); g.imageSmoothingQuality = 'high';
    g.drawImage(img, -x / s, -y / s, fw / s, fh / s, 0, 0, c.width, c.height);
    c.toBlob(function (blob) {
      if (!blob) { alert('Não foi possível recortar a imagem. Tente outra.'); return; }
      const arq = new File([blob], cfg.nome, { type: blob.type });
      const dt = new DataTransfer(); dt.items.add(arq); input.files = dt.files;
      const prev = URL.createObjectURL(blob);
      if (cfg === CFG.fundo) document.getElementById('banner').style.backgroundImage = 'url(' + prev + ')';
      else document.getElementById('avatarAtual').innerHTML = '<span class="avatar avatar-xl"><img src="' + prev + '" alt=""></span>';
      fechar(false);
    }, cfg.tipo, 0.92);
  });

  document.getElementById('inFoto').addEventListener('change', function () { abrir('perfil', this); });
  document.getElementById('inFundo').addEventListener('change', function () { abrir('fundo', this); });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
