# Ponte / WhatsApp Gateway V1 — correção HTTP 302 do Android

## Por que acontece

O APK chama `https://prodatastelecom.com.br/sites/whatsapp_gateway/mobile-api/v1/auth/login`, mas a V1 antiga não tem um roteador para `mobile-api/v1`. O `index.php` redireciona usuários sem cookie do navegador para `?r=login` (HTTP 302). O login do app exige um endpoint JSON com autenticação Bearer própria.

## Instalação (sem sobrescrever a V1 operacional)

1. Faça backup de `index.php` do servidor e do banco MariaDB/MySQL.
2. No phpMyAdmin do banco **da V1**, execute `database/mobile_api_migration.sql`. Ele cria apenas as tabelas `whatsapp_mobile_sessions` e `whatsapp_mobile_devices`.
3. Envie **`app/mobile_api.php`** para a pasta `app/` da mesma instalação da V1, sem substituir `app/bootstrap.php`, `.env`, `storage/config.local.php`, `worker.php` ou o webhook.
4. No `index.php` atual do servidor, inclua **antes** do redirecionamento `if (!current_user() ... ) redirect(...)`:

```php
if (str_starts_with($route, 'mobile-api/v1/')) {
    if (!installed()) json_response(['error'=>'Instalação incompleta'], 503);
    require __DIR__ . '/app/mobile_api.php';
    mobile_handle(substr($route, strlen('mobile-api/v1/')));
}
```

O ZIP inclui um `index.php` atualizado com essa adição, baseado no pacote original enviado em 10/11/2026. **Se o servidor possui alterações posteriores, NÃO substitua o `index.php` inteiro: integre somente o trecho acima.**

5. Não mude `app/bootstrap.php` nem as tabelas de mensagens. `mobile_api.php` usa as funções existentes `q`, `db`, `meta_call`, `meta_config`, `encrypt_secret`, `totp_valid`, `decrypt_secret`, `queue_tenant_event`, `json_response`.

## Teste obrigatório (sem senha)

Abra no navegador:

`https://prodatastelecom.com.br/sites/whatsapp_gateway/mobile-api/v1/health`

Esperado: **HTTP 200**, JSON:

```json
{"ok":true,"service":"ponte-mobile-v1","version":1}
```

Teste a rota do login pelo navegador:

`https://prodatastelecom.com.br/sites/whatsapp_gateway/mobile-api/v1/auth/login`

Esperado: **HTTP 405**, JSON:

```json
{"error":"Use POST com JSON para autenticar."}
```

Se continuar HTTP 302, seu roteador/rewrite ainda não encaminhou a API para o `index.php` atualizado. Teste a rota equivalente com query:

`https://prodatastelecom.com.br/sites/whatsapp_gateway/index.php?r=mobile-api/v1/health`

Se esta funcionar e a bonita não, revise o `.htaccess` e `mod_rewrite` do Apache. Se nenhuma funcionar, o `index.php` do servidor não contém a nova rota ou você atualizou outra pasta.

## Endpoints incluídos

- GET `mobile-api/v1/health` (público, sem credenciais)
- POST `mobile-api/v1/auth/login` (e-mail, senha, `mfa_code` opcional)
- POST `mobile-api/v1/auth/logout` (revoga a sessão e o dispositivo)
- GET `mobile-api/v1/conversations`
- POST `mobile-api/v1/conversations` (abre ou cria contato, sem enviar mensagem)
- GET `mobile-api/v1/conversations/{id}/messages`
- POST `mobile-api/v1/conversations/{id}/messages` (texto livre na janela real de 24h)
- POST `mobile-api/v1/devices` (FCM, token cifrado no banco)

## Segurança

- Use SOMENTE HTTPS, sem redirects automáticos. Não inclua App Secret nem token da Meta no APK.
- Login usa as senhas existentes (`password_verify`) e o MFA existente (`totp_valid`); administradores de plataforma sem MFA não podem entrar.
- Limite de 6 tentativas em 15 minutos por e-mail + IP, usando a tabela existente.
- Sessão móvel com token aleatório de 256 bits, somente hash SHA-256 no banco, validade 24 horas, revogação explícita no logout.
- Toda consulta filtra `tenant_id` a partir da sessão autenticada; não se aceita tenant_id do cliente como autoridade.
- A sessão é invalidada no Android ao trocar URL/servidor.
- Envio de texto livre: não permite contato opt-out, conversa encerrada, conversa simulada, ou última mensagem recebida com mais de 24 horas.
- Este pacote não inclui o disparo FCM pelo servidor: a tabela e registro estão prontos, mas **envio push pelo backend ainda precisa ser implementado**.
- A etapa de MFA precisa ser suportada no APK; a próxima versão Android adiciona campo opcional de código autenticador.

## Observações

O teste anterior demonstrou que o webhook da Meta funciona. **Não o modifique.** Este patch não altera o fluxo de mensagens recebidas.

A API do aplicativo ainda precisa de testes de ponta a ponta no banco e no servidor reais. PHP `-l` verifica sintaxe, não substitui esses testes.
