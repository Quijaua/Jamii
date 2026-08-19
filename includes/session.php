<?php
/**
 * Inicialização única e endurecida da sessão.
 *
 * Todas as páginas do sistema (públicas e do backoffice) devem chamar
 * iniciarSessao() em vez de session_start() direto, para que o cookie de
 * sessão saia sempre com os atributos de segurança corretos:
 *
 *  - HttpOnly: o cookie não pode ser lido por JavaScript (mitiga roubo de
 *    sessão via XSS).
 *  - SameSite=Lax: o navegador não envia o cookie em requisições vindas de
 *    outros sites, o que já barra a maior parte dos ataques CSRF (a validação
 *    de token em includes/csrf.php cobre o restante).
 *  - Secure: em HTTPS, o cookie nunca trafega por conexão não criptografada.
 */

function estaEmHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    // Servidores atrás de proxy reverso (nginx na frente do PHP-FPM, aaPanel etc.)
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }

    return (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function iniciarSessao(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    // Em CLI (setup_admin.php) não existe sessão de navegador.
    if (php_sapi_name() === 'cli') {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => estaEmHttps(),
    ]);

    // O identificador de sessão só pode vir do cookie (evita session fixation
    // via URL do tipo ?PHPSESSID=...).
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_start();
}
