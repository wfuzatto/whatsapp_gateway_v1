<?php
declare(strict_types=1);

/**
 * WhatsApp Gateway V1 — sessão persistente e revogável do painel web.
 * Este módulo não é carregado pelas rotas da Meta, nem modifica a Cloud API.
 * É necessário executar a migração database/persistent_login.sql antes da ativação.
 * Somente emitir o cookie depois de senha + MFA validados.
 */
const WG_REMEMBER_COOKIE = 'wgate_remember';
const WG_REMEMBER_DAYS = 390; // renovado automaticamente quando a conta continua ativa

function wg_remember_path(): string {
    $path = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    return ($path === '/' || $path === '.') ? '/' : rtrim($path, '/') . '/';
}

function wg_remember_cookie(string $token, int $expires): void {
    // Produção deve funcionar em HTTPS: cookie persistente nunca trafega em HTTP.
    if (headers_sent()) return;
    setcookie(WG_REMEMBER_COOKIE, $token, [
        'expires' => $expires,
        'path' => wg_remember_path(),
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function wg_remember_clear_cookie(): void {
    wg_remember_cookie('', time() - 86400);
    unset($_COOKIE[WG_REMEMBER_COOKIE]);
}

function wg_remember_hash(): ?string {
    $token = (string)($_COOKIE[WG_REMEMBER_COOKIE] ?? '');
    // Não aceitar conteúdo inválido ou enorme no cookie.
    if (strlen($token) !== 64 || !ctype_xdigit($token)) return null;
    return hash('sha256', strtolower($token));
}

function wg_remember_issue(array $user): void {
    if (empty($user['id']) || empty($user['tenant_id'])) return;
    // O cadastro de sessão persistente somente é permitido via HTTPS.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' &&
         (string)($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1');
    if (!$https) {
        error_log('Ponte: cookie persistente recusado porque HTTPS não foi detectado.');
        return;
    }
    $plain = bin2hex(random_bytes(32));
    $hash = hash('sha256', $plain);
    $stamp = hash('sha256', (string)($user['password_hash'] ?? ''));
    // Invalidar o token anterior deste mesmo navegador se existir.
    $prior = wg_remember_hash();
    if ($prior) q('DELETE FROM whatsapp_persistent_logins WHERE token_hash = ?', [$prior]);
    q('INSERT INTO whatsapp_persistent_logins (user_id, tenant_id, token_hash, password_stamp, created_at, last_seen_at, expires_at) VALUES (?,?,?,?,NOW(),NOW(),DATE_ADD(NOW(),INTERVAL 390 DAY))',
      [(int)$user['id'], (int)$user['tenant_id'], $hash, $stamp]);
    wg_remember_cookie($plain, time() + WG_REMEMBER_DAYS * 86400);
}

/**
 * Execução antes da checagem de current_user() no index.php.
 * Quando o PHP perde sua sessão, o cookie revogável restaura somente usuários ativos.
 */
function wg_remember_restore_or_refresh(): void {
    $hash = wg_remember_hash();
    if (!$hash) return;
    try {
        $sql = "SELECT p.user_id, p.tenant_id, p.password_stamp, p.last_seen_at, u.*, t.name AS tenant_name
                FROM whatsapp_persistent_logins p
                JOIN whatsapp_users u ON u.id = p.user_id AND u.tenant_id = p.tenant_id
                JOIN whatsapp_tenants t ON t.id = p.tenant_id
                WHERE p.token_hash = ? AND p.revoked_at IS NULL AND p.expires_at > NOW()
                      AND u.status = 'active' AND t.status = 'active' LIMIT 1";
        $row = q($sql, [$hash])->fetch();
        if (!$row || !hash_equals((string)$row['password_stamp'], hash('sha256', (string)$row['password_hash']))) {
            // Token revogado / expirado / conta desativada / senha alterada.
            if (current_user()) unset($_SESSION['user']);
            wg_remember_clear_cookie();
            return;
        }
        if (current_user() && ((int)current_user()['id'] !== (int)$row['id'] ||
                               (int)current_user()['tenant_id'] !== (int)$row['tenant_id'])) {
            unset($_SESSION['user']);
        }
        $lastSeen = strtotime((string)($row['last_seen_at'] ?? '')) ?: 0;
        if (!current_user()) {
            session_regenerate_id(true);
            unset($row['password_hash'], $row['mfa_secret_enc'], $row['password_stamp'], $row['last_seen_at']);
            $_SESSION['user'] = $row;
        }
        // Renovar cookie e validade do banco no máximo uma vez por dia; sem timeout de inatividade na operação.
        if ($lastSeen < time() - 86400) {
            q('UPDATE whatsapp_persistent_logins SET last_seen_at=NOW(), expires_at=DATE_ADD(NOW(),INTERVAL 390 DAY) WHERE token_hash=?', [$hash]);
            wg_remember_cookie((string)$_COOKIE[WG_REMEMBER_COOKIE], time() + WG_REMEMBER_DAYS * 86400);
        }
    } catch (Throwable $e) {
        // Preservar login PHP atual se a tabela/DB estiver temporariamente indisponível.
        error_log('Ponte: restauração de sessão persistente indisponível (sem dados sensíveis).');
    }
}

function wg_remember_signout(): void {
    $hash = wg_remember_hash();
    if ($hash) {
        try { q('DELETE FROM whatsapp_persistent_logins WHERE token_hash = ?', [$hash]); }
        catch (Throwable $e) { error_log('Ponte: falha ao revogar token persistente.'); }
    }
    wg_remember_clear_cookie();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 86400,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?: '',
            'secure' => (bool)$params['secure'],
            'httponly' => (bool)$params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}
