/** Ponte V1: rolagem automática à última mensagem, incluindo imagens e áudios. */
(() => {
  'use strict';
  const stream = document.getElementById('message-stream');
  const pane = stream?.closest('.chat-pane');
  if (!stream || !pane) return;

  const css = document.createElement('link');
  css.rel = 'stylesheet';
  css.href = 'assets/css/inbox-scroll.css?v=20261111-1';
  document.head.appendChild(css);

  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'jump-to-latest';
  button.title = 'Ir para a mensagem mais recente';
  button.setAttribute('aria-label', 'Ir para a mensagem mais recente');
  button.textContent = '↓';
  button.hidden = true;
  pane.appendChild(button);

  let followLatest = true;
  let hasScrolledOnce = false;
  let scheduled = false;
  let programmatic = false;
  const nearBottom = () => stream.scrollHeight - stream.scrollTop - stream.clientHeight <= 90;
  const updateButton = () => { button.hidden = followLatest; };

  function jumpToLatest(force = false) {
    if (!force && !followLatest) return;
    programmatic = true;
    stream.scrollTop = stream.scrollHeight;
    requestAnimationFrame(() => { programmatic = false; });
    hasScrolledOnce = true;
    followLatest = true;
    updateButton();
  }

  function scheduleJump(force = false) {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
      scheduled = false;
      jumpToLatest(force);
    });
  }

  stream.addEventListener('scroll', () => {
    if (programmatic || !hasScrolledOnce) return;
    followLatest = nearBottom();
    updateButton();
  }, { passive: true });

  button.addEventListener('click', () => scheduleJump(true));
  css.addEventListener('load', () => scheduleJump(true), { once: true });
  scheduleJump(true);
  if (document.readyState === 'complete') scheduleJump(true);
  else window.addEventListener('load', () => scheduleJump(true), { once: true });

  const resize = 'ResizeObserver' in window ? new ResizeObserver(() => scheduleJump()) : null;
  const observed = new WeakSet();
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
      for (const evt of ['load', 'loadedmetadata', 'loadeddata', 'error']) {
        el.addEventListener(evt, () => scheduleJump(), { passive: true });
      }
    });
  };
  watch();
  if ('MutationObserver' in window) {
    new MutationObserver(() => { watch(); scheduleJump(); })
      .observe(stream, { childList: true, subtree: true });
  }
  window.addEventListener('resize', () => scheduleJump());
})();
