<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$config = require __DIR__ . '/../config/config.php';

$pdo = getDB();
$busca = trim($_GET['busca'] ?? '');

if ($busca !== '') {
    $stmt = $pdo->prepare("SELECT * FROM members WHERE nome_completo LIKE :busca OR cpf LIKE :busca OR email LIKE :busca ORDER BY created_at DESC");
    $stmt->execute([':busca' => "%$busca%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM members ORDER BY created_at DESC");
}
$membros = $stmt->fetchAll();

$settings = getSettings();
$meta = (int)$settings['meta_associados'];
$totalPreenchidas = count($pdo->query('SELECT id FROM members')->fetchAll());
$totalAssinadas = (int)$pdo->query('SELECT COUNT(*) AS total FROM members WHERE declaracao_aceite = 1')->fetch()['total'];
$percentualAssinado = $meta > 0 ? min(100, round(($totalAssinadas / $meta) * 100)) : 0;
$metaAtingida = $meta > 0 && $totalAssinadas >= $meta;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel administrativo — <?= htmlspecialchars($config['app_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="brand-header">
    <div class="container">
        <div class="row valign-wrapper" style="margin-bottom: 0;">
            <div class="col s8">
                <h4>Painel administrativo</h4>
                <p>Logado como: <?= htmlspecialchars($_SESSION['admin_email']) ?></p>
            </div>
            <div class="col s4 right-align">
                <a href="config.php" class="btn-flat white-text">
                    <i class="material-icons left">settings</i> Configurações
                </a>
                <a href="logout.php" class="btn-flat white-text">
                    Sair <i class="material-icons right">exit_to_app</i>
                </a>
            </div>
        </div>
    </div>
</header>

<main class="container" style="max-width: 1100px;">

    <?php if ($metaAtingida): ?>
        <div class="card-panel green lighten-4 green-text text-darken-4">
            <i class="material-icons left" style="vertical-align: middle;">celebration</i>
            <strong>Meta atingida! Todos os <?= $meta ?> associados fundadores já assinaram a declaração.</strong>
        </div>
    <?php elseif ($meta > 0): ?>
        <div class="card">
            <div class="card-content">
                <span class="card-title" style="font-size: 1.1rem;">Progresso de assinaturas</span>
                <p><?= $totalAssinadas ?> de <?= $meta ?> associados já assinaram a declaração (<?= $percentualAssinado ?>%)</p>
                <div class="progress">
                    <div class="determinate green darken-1" style="width: <?= $percentualAssinado ?>%"></div>
                </div>
                <p class="grey-text" style="margin-bottom: 0;">
                    <a href="config.php">Ver detalhes e ajustar meta</a>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <div class="row admin-nav">
        <div class="col s12 m8">
            <form method="GET" class="input-field" style="margin-bottom:0;">
                <input type="text" name="busca" placeholder="Buscar por nome, CPF ou e-mail" value="<?= htmlspecialchars($busca) ?>">
            </form>
        </div>
        <div class="col s12 m4 right-align">
            <a href="export.php" class="btn waves-effect waves-light green darken-1">
                Baixar XLSX <i class="material-icons right">download</i>
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-content">
            <span class="card-title">Membros fundadores (<?= count($membros) ?>)</span>

            <table class="striped highlight responsive-table">
                <thead>
                <tr>
                    <th>Nome completo</th>
                    <th>CPF</th>
                    <th>CIN</th>
                    <th>E-mail</th>
                    <th>Cidade/UF</th>
                    <th>Recebido em</th>
                    <th>Declaração</th>
                    <th>Ações</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($membros)): ?>
                    <tr><td colspan="8">Nenhuma ficha encontrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($membros as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['nome_completo']) ?></td>
                        <td><?= htmlspecialchars($m['cpf']) ?></td>
                        <td><?= htmlspecialchars($m['cin'] ?? '') ?></td>
                        <td><?= htmlspecialchars($m['email']) ?></td>
                        <td><?= htmlspecialchars($m['cidade'] . '/' . $m['estado']) ?></td>
                        <td><?= htmlspecialchars($m['created_at']) ?></td>
                        <td>
                            <?php if ($m['declaracao_aceite']): ?>
                                <span class="badge-yes">Sim</span>
                            <?php else: ?>
                                <span class="badge-no">Não</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a href="view.php?id=<?= (int)$m['id'] ?>"><i class="material-icons">edit</i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
