<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';

iniciarSessao();

/**
 * Parâmetros de segurança do login. Podem ser ajustados em config/config.php,
 * na chave 'seguranca'; os valores abaixo são os padrões usados quando a
 * configuração não define nada (mantém compatibilidade com instalações antigas).
 */
function configSeguranca(): array
{
    static $cache = null;

    if ($cache === null) {
        $config = require __DIR__ . '/../config/config.php';
        $s = $config['seguranca'] ?? [];

        $cache = [
            'max_tentativas_email' => max(1, (int)($s['max_tentativas_email'] ?? 5)),
            'max_tentativas_ip'    => max(1, (int)($s['max_tentativas_ip'] ?? 20)),
            'janela_minutos'       => max(1, (int)($s['janela_minutos'] ?? 15)),
        ];
    }

    return $cache;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['admin_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * IP de origem da requisição. Usa apenas REMOTE_ADDR de propósito: cabeçalhos
 * como X-Forwarded-For podem ser forjados pelo cliente e permitiriam driblar o
 * limite de tentativas. Atrás de proxy reverso, todos os acessos podem aparecer
 * com o mesmo IP — por isso o limite por IP é bem mais folgado que o por e-mail.
 */
function ipDoCliente(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'desconhecido');
}

function chaveEmail(string $email): string
{
    return mb_strtolower(trim($email));
}

/**
 * Grava uma tentativa de login (bem ou mal sucedida) e limpa registros antigos.
 */
function registrarTentativaLogin(string $email, bool $sucesso): void
{
    $pdo = getDB();

    $stmt = $pdo->prepare('INSERT INTO login_attempts (email, ip, sucesso, tentado_em) VALUES (?, ?, ?, ?)');
    $stmt->execute([chaveEmail($email), ipDoCliente(), $sucesso ? 1 : 0, gmdate('Y-m-d H:i:s')]);

    // Descarta o histórico com mais de 24 horas para a tabela não crescer sem limite.
    $corte = $pdo->prepare('DELETE FROM login_attempts WHERE tentado_em < ?');
    $corte->execute([gmdate('Y-m-d H:i:s', time() - 86400)]);
}

/**
 * Quantos segundos ainda faltam até que este e-mail/IP possa tentar de novo.
 * Retorna null quando não há bloqueio em vigor.
 */
function loginBloqueado(string $email): ?int
{
    $cfg = configSeguranca();
    $janela = $cfg['janela_minutos'] * 60;
    $limite = gmdate('Y-m-d H:i:s', time() - $janela);

    $esperas = [
        segundosParaLiberar('email', chaveEmail($email), $cfg['max_tentativas_email'], $limite, $janela),
        segundosParaLiberar('ip', ipDoCliente(), $cfg['max_tentativas_ip'], $limite, $janela),
    ];
    $esperas = array_filter($esperas, fn($s) => $s !== null);

    return $esperas ? max($esperas) : null;
}

/**
 * Se houver $maximo ou mais falhas dentro da janela para a coluna informada,
 * o bloqueio só cai quando a $maximo-ésima falha mais recente "envelhecer" e
 * sair da janela — é esse instante que a função calcula.
 */
function segundosParaLiberar(string $coluna, string $valor, int $maximo, string $limite, int $janela): ?int
{
    $pdo = getDB();

    // $coluna é sempre 'email' ou 'ip', definidos no código (nunca vindos do usuário).
    $sql = "SELECT tentado_em FROM login_attempts
            WHERE sucesso = 0 AND $coluna = ? AND tentado_em > ?
            ORDER BY tentado_em DESC
            LIMIT 1 OFFSET " . ($maximo - 1);

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$valor, $limite]);
    $linha = $stmt->fetch();

    if (!$linha) {
        return null;
    }

    $restante = (strtotime($linha['tentado_em'] . ' UTC') + $janela) - time();

    return $restante > 0 ? $restante : null;
}

/**
 * Texto amigável para o tempo de espera ("2 minutos", "40 segundos").
 */
function formatarEspera(int $segundos): string
{
    if ($segundos < 60) {
        return $segundos . ' segundo' . ($segundos === 1 ? '' : 's');
    }

    $minutos = (int)ceil($segundos / 60);

    return $minutos . ' minuto' . ($minutos === 1 ? '' : 's');
}

/**
 * Valida as credenciais. Toda tentativa fica registrada em login_attempts, e a
 * sessão recebe um novo identificador no sucesso (proteção contra session
 * fixation: um ID capturado antes do login deixa de valer depois dele).
 */
function attemptLogin(string $email, string $password): bool
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        registrarTentativaLogin($email, true);

        // Zera o histórico de falhas deste e-mail para não penalizar o acesso seguinte.
        $limpar = $pdo->prepare('DELETE FROM login_attempts WHERE email = ? AND sucesso = 0');
        $limpar->execute([chaveEmail($email)]);

        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];

        // Token de CSRF novo a cada sessão autenticada.
        unset($_SESSION['csrf_token']);
        csrfToken();

        return true;
    }

    registrarTentativaLogin($email, false);

    return false;
}

function logout(): void
{
    $_SESSION = [];

    // Invalida também o cookie no navegador, não só os dados no servidor.
    if (php_sapi_name() !== 'cli' && ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}
