# Página NPS avulsa — teste de botão de template (V1)

Esta página é **independente** do painel Ponte e da integração da Meta, destinada exclusivamente a testar o botão "Responder pesquisa" de um template de WhatsApp.

## URL pública prevista (após enviar ao FTP)

https://prodatastelecom.com.br/sites/whatsapp_gateway/nps-teste/

## Instalar

Copie a pasta `nps-teste/` para dentro da pasta já publicada do `whatsapp_gateway` na hospedagem. Acesse a URL acima em navegador anônimo, sem precisar de login.

Não altere `index.php`, `.htaccess`, banco de dados ou arquivos da V2.

## Testar no WhatsApp / Meta

Para um botão de **URL de site** no template, a URL de destino pode ser:

`https://prodatastelecom.com.br/sites/whatsapp_gateway/nps-teste/`

Texto sugerido do botão: **Responder pesquisa**.

Um botão interativo de **resposta rápida** é um recurso diferente: nesse caso, a Meta envia um evento ao webhook e **não abre a página**. Para testar a página, configure um **botão de URL**.

## O que o teste faz

- Pergunta NPS de 0 a 10.
- Mostra opção de comentário (facultativo).
- Recebe o formulário via POST e mostra a nota/classificação (0-6 detrator, 7-8 neutro, 9-10 promotor).
- **Não salva resposta, telefone, IP, comentário ou qualquer dado no banco.**
- Não chama API Graph/Meta, não envia notificações e não interfere nas conversas.

O resultado só aparece na resposta HTTP da própria pessoa, sem persistência. Isso serve para validar visual, abertura do link e clique no botão; **não é uma pesquisa de produção**.

## Validação

`php -l nps-teste/index.php`

Verifique GET no navegador e POST com nota `8` e comentário. Não há exigência de autenticação ou sessões.
