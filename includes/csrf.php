<?php
/**
 * Proteção contra CSRF (Cross-Site Request Forgery).
 *
 * Sem isso, um site malicioso poderia fazer o navegador de um administrador
 * já logado enviar, sem que ele perceba, um POST para o backoffice — por
 * exemplo, excluindo uma ficha ou alterando dados de um membro.
 *
 * Como usar:
 *   1. Dentro de todo <form method="POST"> do backoffice, chame:  <?= csrfCampo() ?>
 *   2. No topo do arquivo que processa o POST, chame:              csrfValidar();
 */

require_once __DIR__ . '/session.php';

/**
 * Devolve o token da sessão atual, criando-o na primeira chamada.
 */
function csrfToken(): string
{
    iniciarSessao();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Campo oculto pronto para colar dentro de um formulário.
 */
function csrfCampo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES) . '">';
}

/**
 * Token no formato de parâmetro de URL (para links de ação, como o de sair).
 */
function csrfParametro(): string
{
    return 'csrf_token=' . urlencode(csrfToken());
}

/**
 * Confere se o token enviado bate com o da sessão. Se não bater, interrompe a
 * requisição com 403 e uma página explicativa — em vez de executar a ação.
 *
 * Por padrão só valida requisições POST; passe $sempre = true para validar
 * também um GET que executa ação (ex.: logout.php).
 */
function csrfValidar(bool $sempre = false): void
{
    iniciarSessao();

    if (!$sempre && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $enviado = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    $esperado = $_SESSION['csrf_token'] ?? '';

    if ($esperado === '' || !is_string($enviado) || !hash_equals($esperado, $enviado)) {
        csrfRecusar();
    }
}

/**
 * Página de erro 403 exibida quando o token é inválido ou ausente.
 */
function csrfRecusar(): void
{
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Requisição não autorizada</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    </head>
    <body style="background:#eef1f5;">
    <main class="container" style="max-width: 620px; margin-top: 64px;">
        <div class="card">
            <div class="card-content">
                <span class="card-title">Não foi possível concluir esta ação</span>
                <p>A verificação de segurança do formulário falhou. Isso normalmente acontece quando:</p>
                <ul class="browser-default">
                    <li>a página ficou aberta por muito tempo e a sessão expirou;</li>
                    <li>você entrou novamente em outra aba do navegador;</li>
                    <li>o envio partiu de fora do sistema.</li>
                </ul>
                <p>Faça login novamente e repita a operação.</p>
                <a href="login.php" class="btn blue darken-1">Ir para o login</a>
            </div>
        </div>
    </main>
    </body>
    </html>
    <?php
    exit;
}
