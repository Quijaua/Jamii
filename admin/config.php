<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$config = require __DIR__ . '/../config/config.php';
$pdo = getDB();

$sucesso = false;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meta = (int)($_POST['meta_associados'] ?? 0);
    if ($meta < 0) {
        $erro = 'A meta de associados não pode ser negativa.';
    } else {
        updateMetaAssociados($meta);
        $sucesso = true;
    }
}

$settings = getSettings();
$meta = (int)$settings['meta_associados'];

$totalPreenchidas = (int)$pdo->query('SELECT COUNT(*) AS total FROM members')->fetch()['total'];
$totalAssinadas   = (int)$pdo->query('SELECT COUNT(*) AS total FROM members WHERE declaracao_aceite = 1')->fetch()['total'];

$percentualPreenchido = $meta > 0 ? min(100, round(($totalPreenchidas / $meta) * 100)) : 0;
$percentualAssinado   = $meta > 0 ? min(100, round(($totalAssinadas / $meta) * 100)) : 0;

$metaAtingida = $meta > 0 && $totalAssinadas >= $meta;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações — <?= htmlspecialchars($config['app_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="brand-header">
    <div class="container">
        <div class="row valign-wrapper" style="margin-bottom: 0;">
            <div class="col s8">
                <h4>Configurações</h4>
                <p>Meta de associados e acompanhamento</p>
            </div>
            <div class="col s4 right-align">
                <a href="dashboard.php" class="btn-flat white-text">
                    <i class="material-icons left">arrow_back</i> Voltar
                </a>
            </div>
        </div>
    </div>
</header>

<main class="container" style="max-width: 900px;">

    <?php if ($metaAtingida): ?>
        <div class="card-panel green lighten-4 green-text text-darken-4">
            <i class="material-icons left" style="vertical-align: middle;">celebration</i>
            <strong>Meta atingida! Todos os <?= $meta ?> associados fundadores já preencheram e assinaram a declaração.</strong>
        </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="card-panel blue lighten-4 blue-text text-darken-4">Meta atualizada com sucesso.</div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="card-panel red lighten-4 red-text text-darken-4"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-content">
            <span class="card-title">Envio de e-mail</span>
            <p>As credenciais de SMTP ficam no arquivo <code>config/config.php</code> (por segurança, não
                ficam em uma tela web). Depois de configurá-las, use a ferramenta abaixo para confirmar que
                está tudo funcionando.</p>
            <a href="test_email.php" class="btn waves-effect waves-light blue darken-1">
                Testar envio de e-mail <i class="material-icons right">mail</i>
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-content">
            <span class="card-title">Meta de associados fundadores</span>
            <p>Defina quantos associados devem preencher a ficha. O sistema acompanha automaticamente
                quantos já preencheram e quantos já assinaram (aceitaram) a declaração.</p>

            <form method="POST" class="row" style="margin-bottom: 0;">
                <div class="input-field col s12 m6">
                    <input id="meta_associados" name="meta_associados" type="number" min="0"
                           value="<?= $meta ?>">
                    <label for="meta_associados" class="active">Quantidade total de associados esperada</label>
                </div>
                <div class="col s12 m6" style="padding-top: 20px;">
                    <button class="btn waves-effect waves-light blue darken-1" type="submit">
                        Salvar meta <i class="material-icons right">save</i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-content">
            <span class="card-title">Progresso atual</span>

            <?php if ($meta === 0): ?>
                <p class="grey-text">Defina uma meta acima para ver o progresso em relação ao total esperado.</p>
            <?php endif; ?>

            <div class="row" style="margin-top: 16px;">
                <div class="col s12 m6">
                    <h6>Fichas preenchidas</h6>
                    <p><?= $totalPreenchidas ?><?= $meta > 0 ? " de $meta" : '' ?></p>
                    <?php if ($meta > 0): ?>
                        <div class="progress">
                            <div class="determinate blue darken-1" style="width: <?= $percentualPreenchido ?>%"></div>
                        </div>
                        <p class="grey-text"><?= $percentualPreenchido ?>% do total esperado</p>
                    <?php endif; ?>
                </div>

                <div class="col s12 m6">
                    <h6>Declarações assinadas</h6>
                    <p><?= $totalAssinadas ?><?= $meta > 0 ? " de $meta" : '' ?></p>
                    <?php if ($meta > 0): ?>
                        <div class="progress">
                            <div class="determinate green darken-1" style="width: <?= $percentualAssinado ?>%"></div>
                        </div>
                        <p class="grey-text"><?= $percentualAssinado ?>% do total esperado</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
