/* Ponte V1: mantem a sessao web ativa sem deslogar por inatividade.
   Nao interfere no webhook nem altera o intervalo de chegada das mensagens. */
(() => {
  'use strict';
  let running = false;
  async function renew() {
    if (document.visibilityState !== 'visible' || running) return;
    running = true;
    try {
      const response = await fetch('?r=session-ping', {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'application/json' },
      });
      if (!response.ok) return;
      const data = await response.json();
      if (data && data.authenticated && typeof data.csrf === 'string') {
        // A sessao PHP pode ser restaurada a partir do cookie persistente.
        // Atualize formularios ja abertos para evitar erro 419 apos restauracao.
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', data.csrf);
        document.querySelectorAll('input[name="_csrf"]').forEach(input => {
          input.value = data.csrf;
        });
      }
    } catch (_) {
      // Falha momentanea de rede nao encerra login nem oculta mensagens.
    } finally {
      running = false;
    }
  }
  window.setInterval(renew, 90 * 1000);
  window.addEventListener('focus', renew);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') renew();
  });
  renew();
})();
