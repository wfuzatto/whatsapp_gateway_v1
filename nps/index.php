<?php
/**
 * Pesquisa NPS avulsa para testar um botão de URL do WhatsApp — V1.
 * Não utiliza o banco de dados, não envia mensagens e NÃO salva respostas.
 * Publicação: /sites/whatsapp_gateway/nps-teste/
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
$styleNonce = base64_encode(random_bytes(18));
header("Content-Security-Policy: default-src 'none'; style-src 'nonce-" . $styleNonce . "'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

function escape_html(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$submitted = false;
$error = '';
$score = null;
$classification = '';
$feedback = '';

if ($method === 'POST') {
    $raw = $_POST['score'] ?? null;
    if (!is_string($raw) || !preg_match('/^(10|[0-9])$/D', $raw)) {
        $error = 'Selecione uma nota de 0 a 10 para concluir o teste.';
    } else {
        $score = (int) $raw;
        // Comentário apenas para demonstração; não será salvo nem transmitido.
        $feedback = isset($_POST['comment']) && is_string($_POST['comment'])
            ? (function_exists('mb_substr') ? mb_substr(trim($_POST['comment']), 0, 500, 'UTF-8') : substr(trim($_POST['comment']), 0, 500))
            : '';
        $classification = $score <= 6 ? 'Detrator' : ($score <= 8 ? 'Neutro' : 'Promotor');
        $submitted = true;
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0d8064">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title>Pesquisa de satisfação | Ponte — teste</title>
  <style nonce="<?= escape_html($styleNonce) ?>">
    :root { color-scheme:light; --green:#0d8064; --dark:#12332a; --muted:#5c6f68; --border:#d7e7de; --bg:#f2f7f4; --white:#fff; --mint:#e5f6ed; }
    * { box-sizing:border-box }
    body { margin:0; background:linear-gradient(145deg,#ecf8f0,#f6f9f7 57%,#f8fbf9);
      color:var(--dark);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;line-height:1.45; }
    .wrap { width:min(100% - 32px,660px);min-height:100dvh;margin:auto;display:flex;flex-direction:column;justify-content:center;padding:36px 0 }
    .card {background:var(--white);border:1px solid #e4eee8;border-radius:24px;
      box-shadow:0 17px 55px rgba(14,70,43,.09);padding:clamp(22px,6vw,40px); }
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:28px;}
    .logo{width:43px;height:43px;border-radius:12px;display:grid;place-items:center;background:var(--green);color:#fff;font-size:27px;font-weight:850}
    .brandname{font-size:19px;font-weight:790;letter-spacing:-.025em;}
    .small{font-size:12px;color:var(--muted);}
    .pill {display:inline-block;border-radius:999px;background:#edf8f1;color:#166d50;
      padding:6px 11px;font-size:12px;font-weight:700;letter-spacing:.02em;}
    h1{font-size:clamp(26px,5vw,35px);line-height:1.13;letter-spacing:-.045em;margin:15px 0 12px;}
    .intro{color:var(--muted);margin:0 0 28px;font-size:15px}
    .scores{border:0;padding:0;margin:0}
    .scores legend{font-size:14px;font-weight:700;margin-bottom:14px}
    .buttons{display:grid;grid-template-columns:repeat(11,minmax(0,1fr));gap:6px}
    .score-option{position:relative;min-width:0}
    .score-option input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;margin:0}
    .score-option span{display:grid;place-items:center;height:43px;border:1px solid var(--border);
      border-radius:9px;background:#fff;font-weight:700;font-size:14px;transition:background .12s,border-color .12s,color .12s}
    .score-option input:hover+span {background:#f2faf5;border-color:#90c7ae}
    .score-option input:focus-visible+span{outline:3px solid #8cc8b5;outline-offset:2px}
    .score-option input:checked+span{background:var(--green);border-color:var(--green);color:#fff}
    .hints{display:flex;justify-content:space-between;gap:10px;color:var(--muted);font-size:12px;margin-top:12px}
    label[for=comment]{display:block;font-weight:700;font-size:14px;margin:29px 0 9px}
    textarea{display:block;width:100%;min-height:106px;resize:vertical;border:1px solid var(--border);
      border-radius:13px;padding:13px;font:inherit;color:var(--dark)}
    textarea:focus{outline:3px solid #cfeddf;border-color:#62b08e}
    .submit{display:block;width:100%;border:0;background:var(--green);color:#fff;border-radius:13px;
      margin-top:22px;padding:15px;font:700 16px system-ui;cursor:pointer}
    .submit:hover{background:#076a51}
    .submit:focus-visible{outline:3px solid #9dcfb9;outline-offset:3px}
    .foot{font-size:12px;color:#788b80;text-align:center;margin:18px 6px 0}
    .notice{background:#fdf2ed;color:#913f26;border-radius:10px;padding:12px;margin:14px 0}
    .success-icon{width:64px;height:64px;border-radius:50%;display:grid;place-items:center;
      background:var(--mint);font-size:32px;color:var(--green);margin:14px 0}
    .summary{background:#f3faf6;border:1px solid var(--border);border-radius:13px;padding:15px;margin-top:18px}
    .back{display:inline-block;margin-top:24px;color:var(--green);font-weight:700;text-decoration:none}
    .back:hover{text-decoration:underline}
    @media(max-width:540px){.buttons{grid-template-columns:repeat(6,minmax(0,1fr));gap:8px}
      .score-option span{height:48px}.score-option:last-child{grid-column:span 1}}
  </style>
</head>
<body>
<main class="wrap">
  <section class="card" aria-labelledby="title">
    <div class="brand"><span class="logo" aria-hidden="true">P</span>
      <div><div class="brandname">Ponte</div><div class="small">Pesquisa de satisfação</div></div>
    </div>
    <span class="pill">AMBIENTE DE TESTE</span>
    <?php if ($submitted): ?>
      <div class="success-icon" aria-hidden="true">✓</div>
      <h1 id="title">Obrigado pela participação!</h1>
      <p class="intro">Seu clique e o envio do formulário de teste foram processados com sucesso.</p>
      <div class="summary">
        <strong>Nota selecionada: <?= $score ?>/10</strong><br>
        <span class="small">Classificação: <?= escape_html($classification) ?></span>
        <?php if ($feedback !== ''): ?><p><?= nl2br(escape_html($feedback)) ?></p><?php endif; ?>
      </div>
      <p class="small">Esta é uma simulação: nenhuma resposta foi gravada em banco de dados ou enviada à Meta.</p>
      <a class="back" href="./">Responder novamente</a>
    <?php else: ?>
      <h1 id="title">Como foi sua experiência conosco?</h1>
      <p class="intro">De 0 a 10, qual a probabilidade de você recomendar nossa empresa a um amigo ou colega?</p>
      <?php if ($error !== ''): ?><p class="notice" role="alert"><?= escape_html($error) ?></p><?php endif; ?>
      <form method="post" action="" autocomplete="off">
        <fieldset class="scores">
          <legend>Selecione uma nota</legend>
          <div class="buttons">
            <?php for ($i = 0; $i <= 10; $i++): ?>
              <label class="score-option">
                <input type="radio" name="score" value="<?= $i ?>" aria-label="Nota <?= $i ?>" required
                  <?= isset($_POST['score']) && (string)$_POST['score'] === (string)$i ? 'checked' : '' ?>>
                <span><?= $i ?></span>
              </label>
            <?php endfor; ?>
          </div>
          <div class="hints"><span>0 · Não recomendaria</span><span>10 · Recomendaria muito</span></div>
        </fieldset>
        <label for="comment">Quer deixar um comentário? <span class="small">(opcional)</span></label>
        <textarea id="comment" name="comment" maxlength="500" placeholder="Conte como foi a sua experiência..."></textarea>
        <button class="submit" type="submit">Enviar avaliação</button>
      </form>
    <?php endif; ?>
  </section>
  <p class="foot">Demonstração do botão de pesquisa NPS • Não armazena dados pessoais nem respostas.</p>
</main>
</body>
</html>
