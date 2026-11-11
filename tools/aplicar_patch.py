#!/usr/bin/env python3
"""Aplica o patch de sessao ao gateway V1 EXISTENTE. Faz backups; nao sobrescreve configuracoes.

Execucao local na raiz da V1 depois de copiar app/persistent_login.php, SQL e JS.
Se qualquer ancora do codigo antigo mudar, aborta sem regravar os arquivos originais.
"""
import pathlib
import shutil
import sys
import datetime

root = pathlib.Path.cwd()
files = [root / 'index.php', root / 'app/bootstrap.php', root / 'app/views/layout.php']
if not all(p.is_file() for p in files):
    raise SystemExit('Rode a partir da pasta principal da V1 (index.php, app/bootstrap.php).')
if not (root / 'app/persistent_login.php').exists():
    raise SystemExit('Copie app/persistent_login.php ANTES de executar.')
if not (root / 'assets/js/session-alive.js').exists():
    raise SystemExit('Copie assets/js/session-alive.js ANTES de executar.')

content = {p:p.read_text(encoding='utf-8') for p in files}
idx, boot, layout = files

def replace_once(p, old, new):
    if new in content[p]:
        return
    count = content[p].count(old)
    if count != 1:
        raise RuntimeError('Ancora incompatível em '+str(p.relative_to(root))+' ('+str(count)+' ocorrencias); nada sera gravado.')
    content[p] = content[p].replace(old, new, 1)

replace_once(idx,
 "require __DIR__ . '/app/bootstrap.php';",
 "require __DIR__ . '/app/bootstrap.php';\nrequire __DIR__ . '/app/persistent_login.php';")
replace_once(idx,
 "if ($route === 'logout') { session_destroy(); redirect('?r=login'); }",
 """if ($route === 'logout') {
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
if ($route === 'login' && current_user()) redirect('?r=dashboard');""")
replace_once(boot,
 "if (session_status() !== PHP_SESSION_ACTIVE) {",
 "if (session_status() !== PHP_SESSION_ACTIVE) {\n    ini_set('session.gc_maxlifetime', '86400');\n    ini_set('session.use_strict_mode', '1');")
replace_once(boot,
 "unset($row['password_hash'],$row['mfa_secret_enc']);$_SESSION['user']=$row;audit('login');redirect('?r=dashboard');",
 """try { wg_remember_issue($row); } catch (Throwable $e) { error_log('Ponte: falha ao criar login persistente.'); }
                unset($row['password_hash'],$row['mfa_secret_enc']);$_SESSION['user']=$row;audit('login');redirect('?r=dashboard');""")
replace_once(boot,
 "session_regenerate_id(true);\n                unset($user['password_hash'],$user['mfa_secret_enc']);",
 """session_regenerate_id(true);
                try { wg_remember_issue($user); } catch (Throwable $e) { error_log('Ponte: falha ao criar login persistente no cadastro.'); }
                unset($user['password_hash'],$user['mfa_secret_enc']);""")
replace_once(layout,
 '<script defer src="assets/js/app.js"></script>',
 '<script defer src="assets/js/app.js"></script><script defer src="assets/js/session-alive.js?v=20261111-1"></script>')
# Logout requer clique em formulário POST autenticado, não um link GET que qualquer site possa disparar.
replace_once(layout,
 '<a class="logout" title="Sair" href="?r=logout">↪</a>',
 '<form method="post" action="?r=logout" style="margin:0"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><button class="logout" type="submit" title="Sair" aria-label="Sair" style="border:0;background:transparent">↪</button></form>')

# Fase dois: only after all checks pass, write with backups.
stamp = datetime.datetime.now().strftime('%Y%m%d_%H%M%S')
for p in files:
    if p.read_text(encoding='utf-8') == content[p]:
        continue
    backup = p.with_name(p.name + '.before-persistent-' + stamp + '.bak')
    shutil.copy2(p, backup)
    p.write_text(content[p], encoding='utf-8')
    print('Atualizado:', p.relative_to(root), '| backup:', backup.name)
print('Pronto. Rode php -l nos arquivos atualizados e execute a migracao SQL.')
