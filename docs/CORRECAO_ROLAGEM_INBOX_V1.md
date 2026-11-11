# Correção de rolagem da Central de Conversas — V1 (revisão 2)

O problema anterior era: **a rolagem aparecia na página inteira**, deixando o histórico e a caixa de digitação saírem da área útil.

Agora a tela `?r=inbox` ocupa a altura da janela, e **apenas o histórico de mensagens e a lista de conversas** têm barras de rolagem independentes.

## Arquivos atualizados no GitHub

- `assets/css/inbox-scroll.css`
- `assets/js/inbox-scroll.js`

**Importante:** a hospedagem de produção tem outras alterações que ainda não estão inteiramente no repositório da V1. Portanto, não substitua o `app.css`, `app.js`, `inbox.php` ou `layout.php` por versões antigas.

## Atualização no servidor

1. Faça backup dos dois arquivos atuais em `assets/css/` e `assets/js/`.
2. Baixe os dois arquivos novos do repositório e envie ao mesmo caminho:
   - `assets/css/inbox-scroll.css`
   - `assets/js/inbox-scroll.js`
3. No arquivo **`app/views/pages/inbox.php` existente no servidor**, mantenha **somente uma** inclusão do script. Atualize a querystring para forçar versão nova:

```html
<script defer src="assets/js/inbox-scroll.js?v=20261111-2"></script>
```

4. Abra `https://prodatastelecom.com.br/sites/whatsapp_gateway/?r=inbox` e pressione **Ctrl+F5**.

O JS injeta automaticamente o CSS versão `20261111-2` quando a área de chat existe.

## Critérios de teste

- Nenhuma barra de rolagem vertical na página inteira da Central de Conversas.
- A lista de contatos continua rolando separadamente.
- A área direita, no `#message-stream`, tem barra de rolagem própria.
- Cabeçalho do contato, aviso de 24 horas e caixa de digitação permanecem visíveis.
- A conversa abre na última mensagem, inclusive quando há imagens/áudios carregados.
- Se o usuário rolar para cima para ler histórico, novas mídias não o trazem de volta para baixo; a seta ↓ permite retornar.
- Nenhuma alteração em Webhooks, Graph API, banco de dados ou V2.

## Verificação local

Teste estrutural com o CSS original disponível, Chromium headless e 55 mensagens, em resoluções 1440×900, 720×900 e 1440×540: altura do documento igual à janela e `scrollHeight > clientHeight` apenas no histórico.

**A atualização ainda precisa ser publicada no FTP para alterar o site real.** 
