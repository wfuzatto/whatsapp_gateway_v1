document.addEventListener('click', event => {
  const copy = event.target.closest('[data-copy]');
  if (copy) { navigator.clipboard.writeText(copy.dataset.copy); const old = copy.textContent; copy.textContent = 'Copiado'; setTimeout(() => copy.textContent = old, 1600); }
  const dismiss = event.target.closest('[data-dismiss] button'); if (dismiss) dismiss.parentElement.remove();
  const menu = event.target.closest('[data-menu]'); if (menu) document.getElementById('sidebar')?.classList.toggle('open');
  const opener = event.target.closest('[data-open]'); if (opener) document.getElementById(opener.dataset.open)?.scrollIntoView({behavior:'smooth',block:'center'});
  const emoji = event.target.closest('[data-emoji]'); if (emoji) { const box = document.querySelector('.composer textarea'); if (box) { box.value += ' \uD83D\uDE42'; box.focus(); } }
});
document.querySelectorAll('[data-filter]').forEach(input => input.addEventListener('input', () => {
  const table = document.getElementById(input.dataset.filter), needle = input.value.toLocaleLowerCase('pt-BR');
  table?.querySelectorAll('tbody tr').forEach(row => row.hidden = !row.innerText.toLocaleLowerCase('pt-BR').includes(needle));
}));
const wiredComposerControls = new WeakSet();
function bindComposer(root = document) {
  root.querySelectorAll('[data-attachment-input]').forEach(input => {
    if (wiredComposerControls.has(input)) return;
    wiredComposerControls.add(input);
    input.addEventListener('change', () => {
      const output = input.closest('.composer')?.querySelector('[data-attachment-name]');
      if (output) output.textContent = input.files?.[0]?.name || '';
    });
  });
  root.querySelectorAll('[data-quick-replies]').forEach(select => {
    if (wiredComposerControls.has(select)) return;
    wiredComposerControls.add(select);
    select.addEventListener('change', () => {
      const body = select.selectedOptions[0]?.dataset.body, area = select.closest('form')?.querySelector('textarea[name="body"]');
      if (body && area) { area.value = area.value ? area.value + '\n' + body : body; area.dispatchEvent(new Event('input')); area.focus(); }
      select.value = '';
    });
  });
  root.querySelectorAll('.composer textarea').forEach(area => {
    if (wiredComposerControls.has(area)) return;
    wiredComposerControls.add(area);
    area.addEventListener('input', () => { area.style.height = 'auto'; area.style.height = Math.min(area.scrollHeight, 120) + 'px'; });
    area.addEventListener('keydown', ev => { if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); area.form.requestSubmit(); } });
  });
}
bindComposer();

const inbox = document.querySelector('.inbox-shell .inbox-layout');
if (inbox) {
  let filter = 'all';
  const search = inbox.querySelector('[data-inbox-search]');
  const itemsContainer = inbox.querySelector('.conversation-items');
  const getItems = () => [...inbox.querySelectorAll('.conversation-item')];
  const updateConversationFilter = () => {
    const needle = (search?.value || '').toLocaleLowerCase('pt-BR');
    getItems().forEach(item => {
      const matchesFilter = filter === 'all' || (filter === 'unread' && item.dataset.unread === '1') || (filter === 'assigned' && item.dataset.assigned === '1');
      const matchesSearch = (item.innerText + ' ' + (item.dataset.search || '')).toLocaleLowerCase('pt-BR').includes(needle);
      item.hidden = !(matchesFilter && matchesSearch);
    });
  };
  inbox.querySelectorAll('[data-inbox-filter]').forEach(button => button.addEventListener('click', () => {
    filter = button.dataset.inboxFilter;
    inbox.querySelectorAll('[data-inbox-filter]').forEach(tab => tab.classList.toggle('selected', tab === button));
    updateConversationFilter();
  }));
  search?.addEventListener('input', updateConversationFilter);

  const stream = document.getElementById('message-stream');
  const jumpButton = inbox.querySelector('.jump-to-latest');
  if (stream) {
    let stickToBottom = true, programmaticScroll = false, scrollScheduled = false;
    const nearBottom = () => stream.scrollHeight - stream.clientHeight - stream.scrollTop <= 72;
    const updateJumpButton = () => { if (jumpButton) jumpButton.hidden = nearBottom(); };
    const goToLatest = () => {
      programmaticScroll = true;
      stream.scrollTop = stream.scrollHeight;
      stickToBottom = true;
      if (jumpButton) jumpButton.hidden = true;
      requestAnimationFrame(() => { programmaticScroll = false; updateJumpButton(); });
    };
    const scheduleLatest = () => {
      if (!stickToBottom || scrollScheduled) return;
      scrollScheduled = true;
      requestAnimationFrame(() => { scrollScheduled = false; if (stickToBottom) goToLatest(); });
    };
    stream.addEventListener('scroll', () => {
      if (programmaticScroll) return;
      stickToBottom = nearBottom();
      updateJumpButton();
    }, {passive:true});
    jumpButton?.addEventListener('click', goToLatest);
    requestAnimationFrame(goToLatest);

    const rowSizes = typeof ResizeObserver === 'function' ? new ResizeObserver(scheduleLatest) : null;
    const observedRows = new WeakSet();
    const observeRows = () => {
      stream.querySelectorAll('.message-row').forEach(row => {
        if (observedRows.has(row)) return;
        observedRows.add(row);
        rowSizes?.observe(row);
      });
    };
    observeRows();
    stream.addEventListener('load', event => {
      if (event.target.matches?.('img,video,audio')) scheduleLatest();
    }, true);
    ['loadedmetadata', 'loadeddata'].forEach(type => stream.addEventListener(type, scheduleLatest, true));
    if (typeof MutationObserver === 'function') {
      new MutationObserver(() => { observeRows(); scheduleLatest(); }).observe(stream, {childList:true, subtree:true});
    }

    let refreshing = false;
    const refreshInbox = async () => {
      if (refreshing || document.visibilityState !== 'visible') return;
      refreshing = true;
      try {
        const response = await fetch(location.href, {
          credentials: 'same-origin',
          cache: 'no-store',
          headers: {'X-Requested-With':'XMLHttpRequest'}
        });
        if (!response.ok) return;
        const markup = await response.text();
        const freshDocument = new DOMParser().parseFromString(markup, 'text/html');
        const freshInbox = freshDocument.querySelector('.inbox-layout');
        if (!freshInbox) return;

        const freshItems = freshInbox.querySelector('.conversation-items');
        const currentList = inbox.querySelector('.conversation-items');
        if (freshItems && currentList) {
          const listTop = currentList.scrollTop;
          currentList.innerHTML = freshItems.innerHTML;
          currentList.scrollTop = listTop;
          const currentCount = inbox.querySelector('.inbox-list-head span');
          const freshCount = freshInbox.querySelector('.inbox-list-head span');
          if (currentCount && freshCount) currentCount.textContent = freshCount.textContent;
          updateConversationFilter();
        }

        const freshStream = freshInbox.querySelector('#message-stream');
        if (freshStream && stream) {
          const known = new Map([...stream.querySelectorAll('.message-row[data-message-id]')].map(row => [row.dataset.messageId, row]));
          const freshRows = [...freshStream.querySelectorAll(':scope > .message-row[data-message-id]')];
          if (freshRows.length) stream.querySelector('.chat-empty')?.remove();
          freshRows.forEach(row => {
            const existing = known.get(row.dataset.messageId);
            if (!existing) {
              stream.appendChild(document.importNode(row, true));
              return;
            }
            const currentStatus = existing.querySelector('.bubble>small');
            const freshStatus = row.querySelector('.bubble>small');
            if (currentStatus && freshStatus && currentStatus.textContent !== freshStatus.textContent) currentStatus.textContent = freshStatus.textContent;
          });
          observeRows();
          scheduleLatest();
        }

        const currentPane = inbox.querySelector('.chat-pane');
        const freshPane = freshInbox.querySelector('.chat-pane');
        if (currentPane && freshPane) {
          const currentAlert = currentPane.querySelector('.window-alert');
          const freshAlert = freshPane.querySelector('.window-alert');
          if (currentAlert && freshAlert && (currentAlert.textContent !== freshAlert.textContent || currentAlert.className !== freshAlert.className)) {
            currentAlert.replaceWith(document.importNode(freshAlert, true));
          }
          const currentComposer = currentPane.querySelector(':scope > .composer,:scope > .template-composer');
          const freshComposer = freshPane.querySelector(':scope > .composer,:scope > .template-composer');
          if ((currentComposer?.className || '') !== (freshComposer?.className || '')) {
            const draft = currentComposer?.querySelector('textarea[name="body"]')?.value || '';
            if (currentComposer && freshComposer) currentComposer.replaceWith(document.importNode(freshComposer, true));
            else if (currentComposer) currentComposer.remove();
            else if (freshComposer) currentPane.querySelector('#message-stream')?.insertAdjacentElement('afterend', document.importNode(freshComposer, true));
            const currentHint = currentPane.querySelector(':scope > .composer-hint');
            const freshHint = freshPane.querySelector(':scope > .composer-hint');
            if (currentHint && freshHint) currentHint.replaceWith(document.importNode(freshHint, true));
            else if (currentHint) currentHint.remove();
            else if (freshHint) currentPane.appendChild(document.importNode(freshHint, true));
            const newArea = currentPane.querySelector('.composer textarea[name="body"]');
            if (newArea && draft) newArea.value = draft;
            bindComposer(currentPane);
          }
        }
      } catch (_) {
        // Uma falha de rede moment\u00e2nea n\u00e3o interfere na conversa em exibi\u00e7\u00e3o.
      } finally {
        refreshing = false;
      }
    };
    window.setInterval(refreshInbox, 12000);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') refreshInbox(); });
  }
}

document.querySelectorAll('[data-totp-qr]').forEach(container => {
  const uri = container.dataset.totpQr || '';
  if (!uri || typeof window.qrcode !== 'function') {
    container.textContent = 'N\u00e3o foi poss\u00edvel carregar o gerador do QR code. Use a chave secreta abaixo para configurar manualmente.';
    return;
  }
  try {
    const qr = window.qrcode(0, 'M'); qr.addData(uri, 'Byte'); qr.make();
    container.innerHTML = qr.createImgTag(5, 4, 'QR code da autentica\u00e7\u00e3o em duas etapas');
    const image = container.querySelector('img');
    if (image) { image.alt = 'QR code para configurar autentica\u00e7\u00e3o em duas etapas'; image.className = 'mfa-qr-image'; }
  } catch (_) {
    container.textContent = 'N\u00e3o foi poss\u00edvel gerar o QR code. Use a chave secreta abaixo para configurar manualmente.';
  }
});
