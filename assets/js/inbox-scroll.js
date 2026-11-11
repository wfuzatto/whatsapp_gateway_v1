/** WhatsApp Gateway V1 — v3: cabeçalho Ajuda/Sair e scroll exclusivo do histórico. */
(() => {
  'use strict';
  if (window.__ponteV1UiV3) return; // Caso seja incluido no layout e no inbox.php.
  window.__ponteV1UiV3 = true;
  const VERSION = '20261111-3';
  if (!document.querySelector('link[data-ponte-v1-ui-v3]')) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'assets/css/inbox-scroll.css?v=' + VERSION;
    link.dataset.ponteV1UiV3 = '1';
    document.head.appendChild(link);
  }

  function mountHeaderActions() {
    const actions = document.querySelector('.topbar .top-actions');
    if (!actions) return;
    const help = document.querySelector('.sidebar .help-card a');
    const logout = document.querySelector('.sidebar .profile a.logout');
    if (!help || !logout) return;
    const add = (name, label, source, svg) => {
      if (actions.querySelector('[data-ponte-header-action="' + name + '"]')) return;
      const a = document.createElement('a');
      a.href = source.getAttribute('href');
      a.className = 'ponte-header-action' + (name === 'logout' ? ' ponto-sair' : '');
      a.dataset.ponteHeaderAction = name;
      a.title = label;
      a.setAttribute('aria-label', label);
      a.innerHTML = svg + '<span class="ponte-action-label">' + label + '</span>';
      actions.appendChild(a);
    };
    const helpSvg = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9 9a3 3 0 1 1 5.3 1.9c-1 1-2.3 1.3-2.3 3.1"/><path d="M12 17h.01"/></svg>';
    const exitSvg = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg>';
    add('help', 'Ajuda', help, helpSvg);
    add('logout', 'Sair', logout, exitSvg);
    document.documentElement.classList.add('ponte-header-v3');
  }

  function mountInbox() {
    const stream = document.getElementById('message-stream');
    const layout = document.querySelector('.inbox-layout');
    const pane = stream?.closest('.chat-pane');
    if (!stream || !layout || !pane) return;
    document.documentElement.classList.add('ponte-inbox-v3');
    if (stream.dataset.ponteScrollV3) return;
    stream.dataset.ponteScrollV3 = '1';

    let button = pane.querySelector('.jump-to-latest');
    if (!button) {
      button = document.createElement('button');
      button.type = 'button';
      button.className = 'jump-to-latest';
      button.title = 'Ir para a mensagem mais recente';
      button.setAttribute('aria-label', button.title);
      button.textContent = '↓';
      button.hidden = true;
      pane.appendChild(button);
    }
    let stick = true, automatic = false, initialized = false, scheduled = false;
    const nearBottom = () => stream.scrollHeight - stream.clientHeight - stream.scrollTop <= 100;
    const buttonState = () => { button.hidden = stick; };
    function go(force = false) {
      if (!force && !stick) return;
      automatic = true;
      stream.scrollTop = stream.scrollHeight;
      stick = true;
      initialized = true;
      buttonState();
      requestAnimationFrame(() => { automatic = false; });
    }
    function schedule(force = false) {
      if (!force && !stick) return;
      if (scheduled) return;
      scheduled = true;
      requestAnimationFrame(() => { scheduled = false; go(force); });
    }
    stream.addEventListener('scroll', () => {
      if (!initialized || automatic) return;
      stick = nearBottom();
      buttonState();
    }, { passive: true });
    button.addEventListener('click', () => schedule(true));

    const seen = new WeakSet();
    const resized = typeof ResizeObserver === 'function'
      ? new ResizeObserver(() => schedule()) : null;
    const watch = () => {
      for (const row of stream.children) {
        if (!seen.has(row)) { seen.add(row); resized?.observe(row); }
      }
      stream.querySelectorAll('img,video,audio').forEach(el => {
        if (seen.has(el)) return;
        seen.add(el);
        ['load', 'loadedmetadata', 'loadeddata'].forEach(type =>
          el.addEventListener(type, () => schedule(), { passive: true }));
      });
    };
    watch();
    if (typeof MutationObserver === 'function') {
      new MutationObserver(() => { watch(); schedule(); })
        .observe(stream, { childList: true, subtree: true });
    }
    document.querySelector('link[data-ponte-v1-ui-v3]')
      ?.addEventListener('load', () => schedule(true), { once: true });
    schedule(true);
    window.addEventListener('load', () => schedule(true), { once: true });
    window.addEventListener('resize', () => schedule());
  }

  const start = () => { mountHeaderActions(); mountInbox(); };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else start();
})();
