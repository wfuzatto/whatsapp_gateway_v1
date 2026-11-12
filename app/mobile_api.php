<?php
declare(strict_types=1);

/**
 * ZapAPI / Ponte V1 — API de operadores Android.
 * A sessão móvel é independente da sessão do navegador e dos tokens da Meta.
 * Todas as consultas são limitadas pelo tenant do usuário autenticado.
 */

function mobile_fail(int $code, string $message): never {
    json_response(['error' => $message], $code);
}

function mobile_json(): array {
    $type = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($type !== 'application/json') mobile_fail(415, 'Envie Content-Type: application/json.');
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len > 32768) mobile_fail(413, 'Requisição grande demais.');
    $raw = file_get_contents('php://input', false, null, 0, 32769);
    if (!is_string($raw) || strlen($raw) > 32768) mobile_fail(413, 'Requisição grande demais.');
    $json = json_decode($raw, true);
    if (!is_array($json)) mobile_fail(400, 'JSON inválido.');
    return $json;
}

function mobile_authorization(): string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $raw = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!$raw) foreach ($headers as $key => $value) {
        if (strcasecmp((string)$key, 'Authorization') === 0) { $raw = (string)$value; break; }
    }
    if (!preg_match('/^Bearer\s+(wmob_[a-f0-9]{64})$/i', trim($raw), $matches))
        mobile_fail(401, 'Sessão ausente ou inválida. Entre novamente.');
    return $matches[1];
}

function mobile_session(): array {
    $tokenHash = hash('sha256', mobile_authorization());
    $record = q("SELECT ms.id session_id,ms.user_id,ms.tenant_id,u.role,u.name,u.email
                 FROM whatsapp_mobile_sessions ms
                 JOIN whatsapp_users u ON u.id=ms.user_id AND u.tenant_id=ms.tenant_id
                 JOIN whatsapp_tenants t ON t.id=ms.tenant_id
                 WHERE ms.token_hash=? AND ms.revoked_at IS NULL AND ms.expires_at>NOW()
                   AND u.status='active' AND t.status='active' LIMIT 1", [$tokenHash])->fetch();
    if (!$record) mobile_fail(401, 'Sessão expirada ou revogada. Entre novamente.');
    return $record;
}

function mobile_id(string $part): int {
    if (!ctype_digit($part) || $part === '0' || strlen($part) > 18) mobile_fail(404, 'Conversa não encontrada.');
    return (int)$part;
}

function mobile_conversation(array $user, int $id): array {
    $conv = q('SELECT c.*, ct.phone,ct.name,ct.consent_status FROM whatsapp_conversations c
               JOIN whatsapp_contacts ct ON ct.id=c.contact_id AND ct.tenant_id=c.tenant_id
               WHERE c.id=? AND c.tenant_id=? LIMIT 1', [$id,(int)$user['tenant_id']])->fetch();
    if (!$conv) mobile_fail(404, 'Conversa não encontrada nesta empresa.');
    return $conv;
}

function mobile_login(): never {
    $data = mobile_json();
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $password = (string)($data['password'] ?? '');
    $mfa = (string)($data['mfa_code'] ?? '');
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,64);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email)>190 || strlen($password)>512)
        mobile_fail(401, 'Credenciais inválidas.');

    $attempts = (int)q('SELECT COUNT(*) FROM whatsapp_login_attempts
        WHERE email=? AND ip=? AND created_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)', [$email,$ip])->fetchColumn();
    if ($attempts >= 6) mobile_fail(429, 'Muitas tentativas. Tente novamente após 15 minutos.');
    $row = q("SELECT u.*, t.status tenant_status FROM whatsapp_users u
              JOIN whatsapp_tenants t ON t.id=u.tenant_id
              WHERE u.email=? AND u.status='active' AND t.status='active' LIMIT 1", [$email])->fetch();
    $passwordOk = $row && password_verify($password, (string)$row['password_hash']);
    $otpOk = $passwordOk && (!(int)$row['mfa_enabled'] ||
        totp_valid(decrypt_secret((string)$row['mfa_secret_enc']), $mfa));
    if (!$passwordOk || !$otpOk) {
        q('INSERT INTO whatsapp_login_attempts(email,ip,created_at) VALUES(?,?,NOW())', [$email,$ip]);
        mobile_fail(401, 'E-mail, senha ou código autenticador inválido.');
    }
    if ($row['role'] === 'platform_admin' && !(int)$row['mfa_enabled'])
        mobile_fail(403, 'Ative a autenticação em duas etapas no painel web antes de usar o aplicativo.');
    if (!in_array((string)$row['role'], ['platform_admin','owner','admin','supervisor','agent'], true))
        mobile_fail(403, 'Usuário sem permissão para operar o aplicativo.');
    q('DELETE FROM whatsapp_login_attempts WHERE email=? AND ip=?', [$email,$ip]);

    $token = 'wmob_'.bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 86400);
    q('INSERT INTO whatsapp_mobile_sessions(tenant_id,user_id,token_hash,created_at,expires_at)
       VALUES(?,?,?,NOW(),?)', [(int)$row['tenant_id'],(int)$row['id'],hash('sha256',$token),$expires]);
    header('Cache-Control: no-store');
    json_response(['access_token'=>$token, 'token_type'=>'Bearer', 'expires_in'=>86400,
        'user'=>['id'=>(int)$row['id'],'name'=>$row['name'],'role'=>$row['role']]],200);
}

function mobile_handle(string $route): never {
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    try {
        if ($route === 'health') {
            if ($method !== 'GET') mobile_fail(405, 'Método não permitido.');
            json_response(['ok'=>true,'service'=>'ponte-mobile-v1','version'=>1]);
        }
        if ($route === 'auth/login') {
            if ($method !== 'POST') mobile_fail(405,'Use POST com JSON para autenticar.');
            mobile_login();
        }
        $user = mobile_session();
        $tid = (int)$user['tenant_id'];
        $uid = (int)$user['user_id'];
        if ($route === 'auth/logout') {
            if ($method !== 'POST') mobile_fail(405,'Método não permitido.');
            q('UPDATE whatsapp_mobile_sessions SET revoked_at=NOW() WHERE id=? AND tenant_id=?',
                [(int)$user['session_id'],$tid]);
            q('UPDATE whatsapp_mobile_devices SET revoked_at=NOW() WHERE tenant_id=? AND user_id=? AND revoked_at IS NULL', [$tid,$uid]);
            json_response(['ok'=>true]);
        }
        if ($route === 'conversations' && $method === 'GET') {
            $records = q('SELECT c.id,ct.name,ct.phone,c.last_message_preview,c.unread_count,c.updated_at
                          FROM whatsapp_conversations c
                          JOIN whatsapp_contacts ct ON ct.id=c.contact_id AND ct.tenant_id=c.tenant_id
                          WHERE c.tenant_id=? ORDER BY COALESCE(c.last_message_at,c.created_at) DESC LIMIT 200', [$tid])->fetchAll();
            $data = array_map(static fn(array $r): array => [
                'id'=>(string)$r['id'],'name'=>(string)$r['name'],'phone'=>(string)$r['phone'],
                'last_message'=>(string)($r['last_message_preview'] ?? ''),
                'updated_at'=>$r['updated_at']?date('H:i',strtotime($r['updated_at'])):'',
                'unread_count'=>(int)$r['unread_count']], $records);
            json_response(['data'=>$data]);
        }
        if ($route === 'conversations' && $method === 'POST') {
            $body = mobile_json();
            $phone = preg_replace('/\D+/','',(string)($body['phone']??''));
            if (strlen($phone)<10 || strlen($phone)>15) mobile_fail(422,'Use telefone internacional com 10 a 15 dígitos.');
            $meta = q('SELECT phone_number_id FROM whatsapp_tenant_meta WHERE tenant_id=?', [$tid])->fetch();
            if (empty($meta['phone_number_id'])) mobile_fail(409, 'Configure o número da empresa na Meta.');
            q("INSERT INTO whatsapp_contacts (tenant_id,name,phone,consent_status,created_at)
               VALUES(?,?,?,'unknown',NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",[$tid,$phone,$phone]);
            $contactId = (int)db()->lastInsertId();
            $existing = q("SELECT id FROM whatsapp_conversations WHERE tenant_id=? AND contact_id=?
                          AND phone_number_id=? AND status<>'closed' ORDER BY id DESC LIMIT 1",
                          [$tid,$contactId,$meta['phone_number_id']])->fetch();
            if ($existing) json_response(['id'=>(string)$existing['id']]);
            q("INSERT INTO whatsapp_conversations
               (tenant_id,contact_id,phone_number_id,status,unread_count,created_at,updated_at)
               VALUES(?,?,?,'open',0,NOW(),NOW())",[$tid,$contactId,$meta['phone_number_id']]);
            json_response(['id'=>(string)db()->lastInsertId()],201);
        }
        if (preg_match('#^conversations/([0-9]+)/messages$#', $route, $match)) {
            $cid = mobile_id($match[1]);
            $conv = mobile_conversation($user,$cid);
            if ($method === 'GET') {
                $records = q('SELECT id,body,direction,created_at,status,type FROM whatsapp_messages
                          WHERE tenant_id=? AND conversation_id=? ORDER BY id DESC LIMIT 200', [$tid,$cid])->fetchAll();
                $data = array_map(static fn(array $m): array => [
                    'id'=>(string)$m['id'],'text'=>(string)($m['body'] ?? ''),
                    'direction'=>$m['direction']==='out'?'outbound':'inbound',
                    'created_at'=>date('H:i',strtotime((string)$m['created_at'])),
                    'status'=>(string)$m['status'],
                    'type'=>(string)$m['type']], array_reverse($records));
                json_response(['data'=>$data]);
            }
            if ($method !== 'POST') mobile_fail(405, 'Método não permitido.');
            $body = mobile_json();
            $text = trim((string)($body['text']??''));
            if ($text === '' || mb_strlen($text)>4096) mobile_fail(422,'Digite até 4096 caracteres.');
            if ($conv['status']==='closed') mobile_fail(409,'A conversa está encerrada; reabra pelo painel.');
            if ($conv['consent_status']==='opted_out') mobile_fail(409,'O contato optou por não receber mensagens.');
            // Janela de atendimento somente com mensagem real da Meta, nunca com nosso webhook de simulação.
            $last = q("SELECT wamid,body,created_at FROM whatsapp_messages
                      WHERE tenant_id=? AND conversation_id=? AND direction='in'
                      ORDER BY id DESC LIMIT 1", [$tid,$cid])->fetch();
            if (!$last || !$conv['last_inbound_at'] ||
                strtotime((string)$conv['last_inbound_at']) < time()-86400 ||
                !str_starts_with((string)($last['wamid']??''),'wamid.') ||
                str_contains((string)($last['wamid']??''),'TESTE_SIMULADO') ||
                str_starts_with((string)($last['body']??''),'[SIMULAÇÃO LOCAL]'))
                mobile_fail(409,'Janela de 24 horas fechada ou conversa simulada. Utilize template aprovado.');
            $m = meta_config($tid);
            if (empty($m['phone_number_id']) || $conv['phone_number_id'] !== $m['phone_number_id'])
                mobile_fail(409,'O número desta conversa difere do configurado para a empresa.');
            $res = meta_call($tid,'POST',(string)$m['phone_number_id'].'/messages',[
                'messaging_product'=>'whatsapp', 'recipient_type'=>'individual',
                'to'=>$conv['phone'],'type'=>'text','text'=>['preview_url'=>false,'body'=>$text]
            ]);
            $wamid = (string)($res['messages'][0]['id'] ?? '');
            q("INSERT INTO whatsapp_messages
                (tenant_id,conversation_id,direction,type,body,wamid,status,created_at)
                VALUES(?,?,'out','text',?,?,'accepted',NOW())",[$tid,$cid,$text,$wamid?:null]);
            $newId = (int)db()->lastInsertId();
            q('UPDATE whatsapp_conversations SET last_message_at=NOW(),last_message_preview=?,updated_at=NOW()
               WHERE id=? AND tenant_id=?',[mb_substr($text,0,240),$cid,$tid]);
            queue_tenant_event($tid,'message.sent',['message_id'=>$wamid,'phone'=>$conv['phone'],
                'text'=>$text,'type'=>'text','status'=>'accepted']);
            json_response(['id'=>(string)$newId,'status'=>'accepted']);
        }
        if ($route === 'devices' && $method === 'POST') {
            $body = mobile_json();
            $token = (string)($body['fcm_token']??'');
            if (($body['platform']??'')!=='android' || strlen($token)<40 || strlen($token)>4096)
                mobile_fail(422,'Dispositivo Android ou token FCM inválido.');
            $hash=hash('sha256',$token);
            q("INSERT INTO whatsapp_mobile_devices
               (tenant_id,user_id,fcm_token_hash,fcm_token_enc,created_at,updated_at)
               VALUES(?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE
               tenant_id=VALUES(tenant_id),user_id=VALUES(user_id),fcm_token_enc=VALUES(fcm_token_enc),
               revoked_at=NULL,updated_at=NOW()", [$tid,$uid,$hash,encrypt_secret($token)]);
            json_response(['ok'=>true]);
        }
        mobile_fail(404, 'Rota móvel não encontrada.');
    } catch (PDOException $error) {
        // Nunca imprimir SQL, credenciais ou detalhes de conexão para o aplicativo.
        error_log('mobile-api SQL: '.$error->getCode());
        mobile_fail(503,'Banco de dados indisponível ou migração móvel pendente.');
    } catch (Throwable $error) {
        error_log('mobile-api: '.get_class($error));
        mobile_fail(502,'Não foi possível concluir a operação. Consulte o log privado do servidor.');
    }
}
