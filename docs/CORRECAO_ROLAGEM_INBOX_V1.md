# Correção de rolagem — Central de Conversas (V1)

Arquivos já adicionados neste repositório:
- `assets/css/inbox-scroll.css`
- `assets/js/inbox-scroll.js`

## Instalação na hospedagem

A hospedagem tem alterações mais recentes que o repositório base. **Não substitua o layout inteiro.**

1. Faça backup de `app/views/pages/inbox.php` no servidor.
2. Envie os dois arquivos acima para as pastas correspondentes em `prodatastelecom.com.br/sites/whatsapp_gateway`.
3. Acrescente ao **final do arquivo `app/views/pages/inbox.php` já existente**:

```html
<script defer src="assets/js/inbox-scroll.js?v=20261111-1"></script>
```

4. Entre em `https://prodatastelecom.com.br/sites/whatsapp_gateway/?r=inbox` e pressione **Ctrl+F5**.

## Como funciona

- A lista de mensagens fica com rolagem vertical independente, sem ultrapassar a altura do painel.
- Ao selecionar a conversa, o painel vai para a mensagem mais recente.
- Mesmo com imagens ou áudios carregados depois, mantém a última mensagem visível.
- Se o atendente subir para ler histórico, o chat não o puxa enquanto ele estiver lendo; aparece um botão ↓ para ir ao final.
- A atualização automática a cada 12 segundos já existente no `app.js` é preservada.
- Não altera banco, autenticação, webhook, Android, arquivos atuais do servidor nem a V2.

## Cuidados

Não substitua o `inbox.php`, `app.css`, `app.js` ou `bootstrap.php` inteiros por arquivos antigos. Este patch foi feito como **adição isolada** para evitar regressões da V1 em produção.

Teste primeiro em uma cópia/ambiente de homologação caso seu sistema tenha customizações de layout.
