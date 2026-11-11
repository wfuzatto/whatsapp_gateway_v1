/** V1: bloqueia scroll do documento e mantém scroll no histórico da conversa. */
(() => {
  'use strict';

  const stream = document.getElementById('message-stream');
  const layout = document.querySelector('.inbox-layout');
  const pane = stream?.closest('.chat-pane');

  if (!stream || !layout || !pane) return;

  // Aplicar apenas à tela inbox, sem afetar as demais páginas administrativas.
  document.documentElement.classList.add('ponte-inbox-scroll');

  const existing = document.querySelector('link[data-inbox-scroll-css]');
  if (!existing) {
    const css = document.createElement('link');
    css.rel = 'stylesheet';
    css.dataset.inboxScrollCss = 'true';
    css.href = 'assets/css/inbox-scroll.css?v=20261111-2';
    document.head.appendChild(css);
    css.addEventListener('load', () => {
      window.scrollTo(0, 0);
      scheduleJump(true);
    }, { once: true });
  }

  let button = pane.querySelector('.jump-to-latest');
  if (!button) {
    button = document.createElement('button');
    button.type = 'button';
    button.className = 'jump-to-latest';
    button.title = 'Ir para a última mensagem';
    button.setAttribute('aria-label', 'Ir para a última mensagem');
    button.textContent = '↓';
    button.hidden = true;
    pane.appendChild(button);
  }

  let followLatest = true;
  let programmatic = false;
  let scheduled = false;
  const nearBottom = () => stream.scrollHeight - stream.scrollTop - stream.clientHeight < 96;
  const updateButton = () => { button.hidden = followLatest; };

  function jump(force = false) {
    if (!force && !followLatest) return;
    programmatic = true;
    stream.scrollTop = stream.scrollHeight;
    followLatest = true;
    updateButton();
    requestAnimationFrame(() => { programmatic = false; });
  }

  function scheduleJump(force = false) {
    // Se a pessoa está lendo mensagens anteriores, nunca puxar para baixo.
    if (!force && !followLatest) return;
    if (scheduled && !force) return;
    scheduled = true;
    requestAnimationFrame(() => {
      scheduled = false;
      jump(force);
    });
  }

  stream.addEventListener('scroll', () => {
    if (programmatic) return;
    followLatest = nearBottom();
    updateButton();
  }, { passive: true });

  button.addEventListener('click', () => scheduleJump(true));

  // Imagens carregadas, novos anexos e mensagens recebidas.
  const observed = new WeakSet();
  const resize = 'ResizeObserver' in window
    ? new ResizeObserver(() => scheduleJump())
    : null;
  const watch = () => {
    for (const row of stream.children) {
      if (!observed.has(row)) {
        observed.add(row);
        resize?.observe(row);
      }
    }
    stream.querySelectorAll('img,video,audio').forEach(el => {
      if (observed.has(el)) return;
      observed.add(el);
      ['load', 'loadedmetadata', 'loadeddata', 'error'].forEach(name => {
        el.addEventListener(name, () => scheduleJump(), { passive: true });
      });
    });
  };
  watch();
  if ('MutationObserver' in window) {
    new MutationObserver(() => { watch(); scheduleJump(); })
      .observe(stream, { childList: true, subtree: true });
  }

  window.addEventListener('resize', () => scheduleJump());
  if (document.readyState === 'complete') {
    scheduleJump(true);
  } else {
    window.addEventListener('load', () => scheduleJump(true), { once: true });
  }
  scheduleJump(true);
})();
