<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$route = (string)($_GET['r'] ?? '');
if (preg_match('#^api/v1/(contacts|messages|templates|media(?:/\d+)?)$#',$route,$apiMatch)) { handle_api($apiMatch[1]); exit; }
if (str_starts_with($route, 'api/')) { handle_api(substr($route, 4)); exit; }
if ($route === 'webhook') { handle_meta_webhook(); exit; }
if (str_starts_with($route, 'mobile-api/v1/')) {
    if (!installed()) json_response(['error'=>'Instalação incompleta'], 503);
    require __DIR__ . '/app/mobile_api.php';
    mobile_handle(substr($route, strlen('mobile-api/v1/')));
}
if (!installed()) { render_install(); exit; }
if ($route === 'logout') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit('Utilize o botão Sair.');
    }
    csrf_check();
    if (function_exists('wg_remember_signout')) {
        wg_remember_signout();
    } else {
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
    redirect('?r=login');
}
if (!current_user() && !in_array($route,['login','signup'],true)) redirect('?r=login');
if ($route === 'login') { handle_login(); exit; }
if ($route === 'signup') { handle_signup(); exit; }
if (current_user() && in_array(current_user()['role'],['platform_admin','owner','admin'],true) && empty(current_user()['mfa_enabled']) && $route !== 'settings') redirect('?r=settings&mfa=required');
if ($route === 'media') { handle_media(); exit; }
handle_action();
render_app();
