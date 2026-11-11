# WhatsApp Gateway V1 — permanecer conectado sem timeout de inatividade

**Escopo:** apenas o painel web da V1. Não altera V2, Android, Graph API, webhook Meta, workers ou agendamento das filas.

## Por que ocorria?

No código-base original da V1, a sessão PHP não especifica `gc_maxlifetime` nem persistência de cookie. Em hospedagens comuns, os arquivos de sessão podem ser removidos após ~24 minutos, mesmo sem o usuário clicar em Sair. A página do chat então passa a redirecionar para o login.

Mensagens enviadas pela Meta **não dependem de alguém estar logado** para serem recebidas pelo webhook. Mesmo com o painel fechado, o backend deve continuar gravando essas mensagens. Este patch evita perder a sessão visual do operador; não altera entrega de webhooks.

## Comportamento após a instalação

- Não há logout automático por inatividade enquanto o browser permanece aberto e conectado à Internet.
- Após o PHP perder a sessão, o navegador pode restaurá-la usando cookie persistente com token aleatório e revogável.
- A autenticação em duas etapas continua exigida no **primeiro login**. O token persistente só é emitido depois da validação da senha e, quando habilitado, do MFA.
- O botão **Sair** invalida o token daquele navegador, limpa os cookies e destrói a sessão PHP.
- Cookies são `Secure`, `HttpOnly`, `SameSite=Lax`, restritos à pasta da aplicação. Os tokens são guardados **somente como hash SHA-256 no banco**.
- A persistência tem prazo rolante de **390 dias**, renovado automaticamente ao usar a aplicação, por limitações de cookies de navegador. Uma ausência superior a esse prazo, limpeza dos cookies, revogação administrativa ou alteração da senha poderá exigir novo login. Não é correto prometer sessão eterna e irrevogável.
- O JavaScript faz uma chamada autenticada de manutenção a cada 90 segundos, quando a página está visível, e ao recuperar o foco. Também atualiza os tokens CSRF de formulários abertos.

## Instalação em ordem

1. **Faça backup do banco e dos arquivos de produção** `index.php`, `app/bootstrap.php` e `app/views/layout.php` antes de qualquer atualização.
2. No phpMyAdmin do banco da V1, execute **somente** `database/persistent_login.sql`. A migração cria `whatsapp_persistent_logins` e não altera tabelas existentes.
3. Envie por FTP:
   - `app/persistent_login.php`
   - `assets/js/session-alive.js`
4. **Não substitua os arquivos centrais pelos arquivos de uma versão antiga.** Baixe os três arquivos **atuais** do servidor para uma pasta local, preservando a estrutura `index.php`, `app/bootstrap.php`, `app/views/layout.php`, junto com os arquivos do passo 3.
5. Na pasta local, se tiver Python 3 disponível, rode `python tools/aplicar_patch.py`. A ferramenta:
   - verifica se os pontos do código são compatíveis;
   - cria `.bak` de cada arquivo original;
   - aplica apenas as inserções necessárias;
   - interrompe sem alterar nada se não encontrar os pontos esperados.
6. Confirme a sintaxe (PHP CLI, caso tenha):
   ```
   php -l index.php
   php -l app/bootstrap.php
   php -l app/views/layout.php
   php -l app/persistent_login.php
   ```
7. Envie **somente os três arquivos centrais alterados** para as mesmas pastas de produção. Não envie `.bak` nem as configurações privadas.
8. Saia e faça login novamente **uma vez**, para emitir o novo cookie persistente; depois deixe o painel aberto e verifique se permanece conectado.

### Trechos específicos, caso prefira editar manualmente

**index.php** — imediatamente depois do `require` existente:

```php
require __DIR__ . '/app/persistent_login.php';
```

Troque o bloco antigo:

```php
if ($route === 'logout') { session_destroy(); redirect('?r=login'); }
```

por:

```php
if ($route === 'logout') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('Utilize o botão Sair.'); }
    csrf_check();
    wg_remember_signout();
    redirect('?r=login');
}
wg_remember_restore_or_refresh();
if ($route === 'session-ping') {
    header('Cache-Control: no-store, private');
    json_response(['authenticated'=>(bool)current_user(), 'csrf'=>current_user()?csrf_token():null], current_user()?200:401);
}
if ($route === 'login' && current_user()) redirect('?r=dashboard');
```

**app/bootstrap.php** — dentro do `if (session_status() !== PHP_SESSION_ACTIVE)` *antes de `session_start()`*:

```php
ini_set('session.gc_maxlifetime', '86400');
ini_set('session.use_strict_mode', '1');
```

Após validação bem-sucedida de senha/MFA em `handle_login()`, mas **antes** de remover `password_hash` do array `$row`:

```php
try { wg_remember_issue($row); }
catch (Throwable $e) { error_log('Ponte: falha ao criar login persistente.'); }
```

Repita em `handle_signup()`, usando `$user`, antes de remover `password_hash`.

**app/views/layout.php** — altere o link de logout para um formulário POST protegido por CSRF:

```html
<form method="post" action="?r=logout" style="margin:0"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><button class="logout" type="submit" title="Sair" aria-label="Sair" style="border:0;background:transparent">↪</button></form>
```

Depois adicione uma única vez junto aos scripts finais do `<head>`:

```html
<script defer src="assets/js/session-alive.js?v=20261111-1"></script>
```

## Testes de aceite

1. Entre via HTTPS com senha e MFA, se aplicável.
2. DevTools → Application → Cookies: verificar `wgate_remember` com `Secure` e `HttpOnly` (não copie nem compartilhe o valor).
3. Abra `?r=session-ping` em outra aba: resposta JSON `authenticated:true` (o valor CSRF é segredo da sessão; não compartilhe o JSON em público).
4. Feche e reabra o navegador: a página principal deve abrir sem pedir nova senha, desde que o browser mantenha cookies.
5. Na Central de Conversas, envie e receba mensagens; teste depois de um período longo.
6. Clique **Sair**: `?r=inbox` deverá redirecionar para `?r=login`; mesmo ao abrir novamente não deve restaurar a sessão.
7. Não é necessário alterar o webhook ou o agendamento do worker. Verifique o processamento do webhook separadamente na Auditoria.

## Atenção sobre o ambiente de hospedagem

O GitHub contém código auxiliar da V1, mas **não** todos os arquivos atuais da hospedagem. Portanto, este patch ainda precisa ser aplicado aos **arquivos de produção atualizados** por FTP ou terminal. Não declare a correção ativa antes disso.