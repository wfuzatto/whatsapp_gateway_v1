# Correção definitiva de rolagem — Central de Conversas V1 (v4)

## Diagnóstico importante

O fato de **Ajuda / Sair ainda não aparecerem no header** na imagem de produção indica que a revisão JavaScript anterior possivelmente **não foi carregada**. Enviar arquivos por FTP sem incluí-los no `layout.php` não ativa as mudanças.

A revisão v4 não depende apenas da cascata CSS: aplica por JS a altura do chat em pixels com base na altura disponível na janela. Preserva o histórico rolável e fixa o restante da página.

## Arquivos alterados no repositório

- `assets/js/inbox-scroll.js` — revisão v4.
- `assets/css/inbox-scroll.css` — estilos de apoio da revisão v3, ainda reutilizados.

## Instalação no servidor real

1. Faça backup do arquivo `app/views/layout.php` **atual** e do JS/CSS já existentes na hospedagem.
2. Atualize pelo FTP os arquivos:
   - `assets/js/inbox-scroll.js`
   - `assets/css/inbox-scroll.css`
3. Em **`app/views/layout.php`**, dentro da região final de scripts, inclua a linha **uma única vez** (não apenas no `inbox.php`):

```html
<script defer src="/sites/whatsapp_gateway/assets/js/inbox-scroll.js?v=20261111-4"></script>
```

Se já houver uma linha apontando para esse mesmo arquivo, substitua-a pela linha acima. Mantenha a inclusão existente de `app.js`. Não copie uma versão antiga do layout.php sobre a versão de produção.

4. Abra `https://prodatastelecom.com.br/sites/whatsapp_gateway/?r=inbox&id=3` e pressione Ctrl+Shift+R.

## Diagnóstico no navegador (F12 > Console)

Cole apenas estas linhas, que **não modificam dados nem enviam mensagens**:

```js
({
  scriptCarregado: window.__ponteV1ScrollStatus,
  abaRolagem: document.documentElement.scrollHeight - document.documentElement.clientHeight,
  rolagemChat: document.querySelector('#message-stream')?.scrollHeight - document.querySelector('#message-stream')?.clientHeight,
  botoesTopo: [...document.querySelectorAll('.topbar .ponte-header-action')].map(el=>el.textContent.trim())
})
```

Esperado:
- `scriptCarregado.loaded: true`;
- `scriptCarregado.mounted: true`;
- `abaRolagem: 0`;
- `rolagemChat > 0` quando houver mensagens suficientes;
- `botoesTopo` contém `Ajuda` e `Sair`.

Se `scriptCarregado` estiver `undefined`, o problema é **inclusão do script, cache ou rota do arquivo**, e não mais CSS. Abra diretamente a URL do JS para conferir retorno HTTP 200 (JavaScript, não HTML ou redirecionamento).

## Política de rolagem

- A página principal da Inbox não rola.
- O histórico `#message-stream` rola na própria caixa.
- A lista de conversas rola separadamente.
- Ao abrir conversa, vai à última mensagem.
- Se o atendente subir no histórico, novas mensagens não interrompem leitura; a seta ↓ leva ao final.

## Escopo e ressalvas

O GitHub da V1 não contém o `layout.php` atualizado da hospedagem. Portanto, só os arquivos auxiliares foram modificados. A aplicação em produção depende de atualizar **o layout.php que já está no FTP**, sem substituí-lo por uma cópia antiga.

Não modifica a V2, Android, webhook ou autenticação.
