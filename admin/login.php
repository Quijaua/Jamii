<?php
require_once __DIR__ . '/../includes/auth.php';
$config = require __DIR__ . '/../config/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$erro = null;
$bloqueado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfValidar();

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $espera = loginBloqueado($email);

    if ($espera !== null) {
        // Não tenta validar a senha enquanto o bloqueio estiver em vigor.
        $bloqueado = true;
        $erro = 'Muitas tentativas de login sem sucesso. Aguarde ' . formatarEspera($espera)
              . ' antes de tentar novamente.';
    } elseif (attemptLogin($email, $senha)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $erro = 'E-mail ou senha inválidos.';

        // Avisa quando a próxima falha vai travar o acesso.
        $espera = loginBloqueado($email);
        if ($espera !== null) {
            $bloqueado = true;
            $erro .= ' Por segurança, novas tentativas ficaram bloqueadas por '
                   . formatarEspera($espera) . '.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login administrativo — <?= htmlspecialchars($config['app_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="brand-header">
    <div class="container">
        <h4>Backoffice — Associação</h4>
    </div>
</header>
<main class="container" style="max-width: 480px;">
    <div class="card">
        <div class="card-content">
            <span class="card-title">Acesso administrativo</span>

            <?php if ($erro): ?>
                <div class="card-panel red lighten-4 red-text text-darken-4"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfCampo() ?>
                <div class="input-field">
                    <input id="email" name="email" type="email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <label for="email" class="<?= !empty($_POST['email']) ? 'active' : '' ?>">E-mail</label>
                </div>
                <div class="input-field">
                    <input id="senha" name="senha" type="password" required>
                    <label for="senha">Senha</label>
                </div>
                <button class="btn waves-effect waves-light blue darken-1" type="submit"
                        <?= $bloqueado ? 'disabled' : '' ?>>
                    Entrar
                    <i class="material-icons right">login</i>
                </button>
            </form>
        </div>
    </div>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
