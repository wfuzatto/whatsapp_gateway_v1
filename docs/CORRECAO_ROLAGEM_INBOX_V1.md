# Hotfix V1 — Tela fixa, rolagem no histórico e Ajuda/Sair no cabeçalho (v3)

## Motivação

O upload dos arquivos CSS/JS por si só não ativa o JavaScript: a V1 usa `app/views/layout.php` como template global. A CSS anterior não limitava toda a cadeia de altura do painel.

## Instalação segura no servidor

1. Faça backup do `app/views/layout.php` da hospedagem atual.
2. Atualize pelo FTP estes **dois** arquivos, sem trocar `app.css` ou `app.js`:
   - `assets/css/inbox-scroll.css`
   - `assets/js/inbox-scroll.js`
3. Dentro do **layout.php atual do servidor**, adicione **depois** de `<script defer src="assets/js/app.js"></script>`:

```html
<script defer src="assets/js/inbox-scroll.js?v=20261111-3"></script>
```

Se já existir inclusão do mesmo arquivo, atualize a querystring para `20261111-3`. Não substitua o layout por um arquivo antigo. O script é idempotente, mesmo que uma cópia antiga também esteja incluída em `app/views/pages/inbox.php`; é preferível remover a inclusão antiga dali.

4. Na Central de Conversas, pressione Ctrl+F5.

## Resultado esperado

- `/?r=inbox` ocupa toda a janela. **A página principal não rola.**
- `#message-stream` tem rolagem vertical própria à direita. A lista de contatos rola à esquerda, separadamente.
- Cabeçalho e campo de digitação permanecem visíveis.
- Ao selecionar o chat, o histórico vai para a última mensagem. Enquanto o atendente lê mensagens antigas, não força o scroll: aparece uma seta ↓ para voltar ao fim.
- **Ajuda** e **Sair** aparecem à direita dos botões de navegação no header, em todas as páginas que carregam o layout global, usando as mesmas URLs já existentes no menu lateral.

## Verificação no Console (F12)

```js
({
  script: !!window.__ponteV1UiV3,
  paginaFixa: document.documentElement.scrollHeight - document.documentElement.clientHeight,
  historicoRolavel: document.querySelector('#message-stream')?.scrollHeight - document.querySelector('#message-stream')?.clientHeight,
  botoes: [...document.querySelectorAll('.topbar [data-ponte-header-action]')].map(x=>x.textContent.trim())
})
```

Esperado na inbox: `script: true`, `paginaFixa: 0`, `historicoRolavel > 0`, `botoes: ['Ajuda','Sair']`. Se `script:false`, reveja o layout.php e o cache.

## Cuidados

O GitHub da V1 **não contém o código-fonte PHP completo da versão atual em produção**. Por isso, não publicamos `layout.php` integralmente nem substituímos a versão de produção. Apenas assets de correção e instruções. Não altera V2, Graph API, banco ou webhook.

Validado em Chromium com fixture derivado do HTML/CSS da V1, 75 mensagens e quatro resoluções. Necessita publicação no FTP para validar a instalação real.
