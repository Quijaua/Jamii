<?php
$config = require __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha enviada — <?= htmlspecialchars($config['app_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="brand-header">
    <div class="container">
        <h4>Ficha de Qualificação dos Membros Fundadores</h4>
    </div>
</header>
<main class="container">
    <div class="card">
        <div class="card-content center-align" style="padding: 48px 16px;">
            <i class="material-icons green-text" style="font-size: 64px;">check_circle</i>
            <h5>Ficha enviada com sucesso!</h5>
            <p>Obrigado por preencher suas informações. Elas foram registradas e serão utilizadas exclusivamente
                para os procedimentos de constituição e registro da associação.</p>
        </div>
    </div>
</main>
</body>
</html>
