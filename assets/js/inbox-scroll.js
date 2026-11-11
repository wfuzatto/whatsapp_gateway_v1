/** WhatsApp Gateway V1 — v3: cabeçalho Ajuda/Sair e scroll exclusivo do histórico. */
(() => {
  'use strict';
  if (window.__ponteV1UiV3) return; // Caso seja incluido no layout e no inbox.php.
  window.__ponteV1UiV3 = true;
  window.__ponteV1ScrollStatus = {loaded:true,version:"v4",mounted:false,error:null};
  const VERSION = '20261111-4';
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
    window.__ponteV1ScrollStatus.mounted=true;
    if (stream.dataset.ponteScrollV3) return;
    stream.dataset.ponteScrollV3 = '1';

    // V4: impõe a altura disponível usando a posição real do painel na janela.
    // Não depende somente da cascata flex/grid das folhas de estilo antigas.
    const main = layout.closest('.main');
    const content = layout.closest('.content');
    const shell = document.querySelector('.app-shell');
    if (!main || !content || !shell) {
      window.__ponteV1ScrollStatus.error = 'Estrutura da página diferente da esperada';
      return;
    }
    function applyFixedHeight() {
      document.documentElement.style.setProperty('overflow', 'hidden', 'important');
      document.body.style.setProperty('overflow', 'hidden', 'important');
      shell.style.setProperty('position', 'fixed', 'important');
      shell.style.setProperty('inset', '0', 'important');
      shell.style.setProperty('height', '100dvh', 'important');
      shell.style.setProperty('overflow', 'hidden', 'important');
      main.style.setProperty('height', '100%', 'important');
      main.style.setProperty('overflow', 'hidden', 'important');
      content.style.setProperty('overflow', 'hidden', 'important');
      const footer = main.querySelector('.footer');
      const available = Math.max(160, window.innerHeight - layout.getBoundingClientRect().top - (footer?.getBoundingClientRect().height || 0) - 8);
      layout.style.setProperty('height', available + 'px', 'important');
      layout.style.setProperty('max-height', available + 'px', 'important');
      layout.style.setProperty('overflow', 'hidden', 'important');
      pane.style.setProperty('height', '100%', 'important');
      pane.style.setProperty('display', 'flex', 'important');
      pane.style.setProperty('flex-direction', 'column', 'important');
      pane.style.setProperty('overflow', 'hidden', 'important');
      let otherHeight = 0;
      for (const child of pane.children) {
        if (child === stream || child.classList.contains('jump-to-latest')) continue;
        if (getComputedStyle(child).position === 'absolute') continue;
        otherHeight += child.getBoundingClientRect().height;
      }
      const availableMessages = Math.max(60, pane.clientHeight - otherHeight);
      stream.style.setProperty('height', availableMessages + 'px', 'important');
      stream.style.setProperty('flex', '0 0 ' + availableMessages + 'px', 'important');
      stream.style.setProperty('min-height', '0', 'important');
      stream.style.setProperty('overflow-y', 'scroll', 'important');
      window.__ponteV1ScrollStatus.metrics = {
        documentScroll:document.documentElement.scrollHeight-document.documentElement.clientHeight,
        chatScroll:stream.scrollHeight-stream.clientHeight,
        chatHeight:availableMessages
      };
    }
    applyFixedHeight();
    window.addEventListener('resize', applyFixedHeight);
    window.visualViewport?.addEventListener('resize', applyFixedHeight);
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
      new MutationObserver(() => { watch(); applyFixedHeight(); schedule(); })
        .observe(stream, { childList: true, subtree: true });
    }
    document.querySelector('link[data-ponte-v1-ui-v3]')
      ?.addEventListener('load', () => schedule(true), { once: true });
    schedule(true);
    window.addEventListener('load', () => schedule(true), { once: true });
    window.addEventListener('resize', () => { applyFixedHeight(); schedule(); });
  }

  const start = () => { mountHeaderActions(); mountInbox(); };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else start();
})();
