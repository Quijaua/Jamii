<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$config = require __DIR__ . '/../config/config.php';

$pdo = getDB();
$busca = trim($_GET['busca'] ?? '');
$filtroVinculo = trim($_GET['vinculo'] ?? '');

// Só aceita um vínculo conhecido como filtro; qualquer outra coisa é ignorada.
if ($filtroVinculo !== '' && !in_array($filtroVinculo, todosOsVinculos(), true)) {
    $filtroVinculo = '';
}

$where = [];
$params = [];

if ($busca !== '') {
    $where[] = '(nome_completo LIKE :busca OR cpf LIKE :busca OR email LIKE :busca)';
    $params[':busca'] = "%$busca%";
}
if ($filtroVinculo !== '') {
    $where[] = 'vinculo = :vinculo';
    $params[':vinculo'] = $filtroVinculo;
}

$sql = 'SELECT * FROM members';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$membros = $stmt->fetchAll();

$settings = getSettings();
$meta = (int)$settings['meta_associados'];
$aberto = formularioAberto($settings);
$encerrouFundadores = fundadoresEncerrados($settings);

// A meta se refere aos fundadores — associados cadastrados depois da assembleia
// não entram na conta (ver contaComoFundador() em includes/inscricao.php).
$stmtFA = $pdo->prepare('SELECT COUNT(*) AS total FROM members WHERE declaracao_aceite = 1 AND COALESCE(vinculo, ?) <> ?');
$stmtFA->execute([VINCULO_FUNDADOR, VINCULO_ASSOCIADO]);
$fundadoresAssinados = (int)$stmtFA->fetch()['total'];

$percentualAssinado = $meta > 0 ? min(100, round(($fundadoresAssinados / $meta) * 100)) : 0;
$metaAtingida = $meta > 0 && $fundadoresAssinados >= $meta;
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
                <a href="configuracoes.php" class="btn-flat white-text">
                    <i class="material-icons left">settings</i> Configurações
                </a>
                <a href="logout.php?<?= csrfParametro() ?>" class="btn-flat white-text">
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
                <p><?= $fundadoresAssinados ?> de <?= $meta ?> fundadores já assinaram a declaração (<?= $percentualAssinado ?>%)</p>
                <div class="progress">
                    <div class="determinate green darken-1" style="width: <?= $percentualAssinado ?>%"></div>
                </div>
                <p class="grey-text" style="margin-bottom: 0;">
                    <a href="configuracoes.php">Ver detalhes e ajustar meta</a>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card-panel <?= $aberto ? 'blue lighten-5' : 'amber lighten-4' ?>" style="padding: 12px 20px;">
        Formulário público:
        <?php if ($aberto): ?>
            <span class="badge-yes">Aberto</span>
        <?php else: ?>
            <span class="badge-no">Fechado</span>
        <?php endif; ?>
        &nbsp;·&nbsp; Inscrições de fundador:
        <?php if ($encerrouFundadores): ?>
            <span class="badge-no">Encerradas</span>
        <?php else: ?>
            <span class="badge-yes">Abertas</span>
        <?php endif; ?>
        &nbsp; <a href="configuracoes.php">alterar</a>
    </div>

    <form method="GET" class="row admin-nav">
        <div class="input-field col s12 m5">
            <input id="busca" type="text" name="busca" placeholder="Nome, CPF ou e-mail" value="<?= htmlspecialchars($busca) ?>">
            <label for="busca" class="active">Buscar</label>
        </div>
        <div class="input-field col s12 m4">
            <select name="vinculo">
                <option value="" <?= $filtroVinculo === '' ? 'selected' : '' ?>>Todos os vínculos</option>
                <?php foreach (todosOsVinculos() as $v): ?>
                    <option value="<?= htmlspecialchars($v) ?>" <?= $filtroVinculo === $v ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Vínculo</label>
        </div>
        <div class="col s12 m3 right-align" style="padding-top: 20px;">
            <button class="btn waves-effect waves-light blue darken-1" type="submit">
                Filtrar <i class="material-icons right">search</i>
            </button>
            <a href="export.php<?= $filtroVinculo !== '' ? '?vinculo=' . urlencode($filtroVinculo) : '' ?>"
               class="btn waves-effect waves-light green darken-1" style="margin-top: 8px;">
                XLSX <i class="material-icons right">download</i>
            </a>
        </div>
    </form>

    <div class="card">
        <div class="card-content">
            <span class="card-title">Fichas recebidas (<?= count($membros) ?>)</span>

            <table class="striped highlight responsive-table">
                <thead>
                <tr>
                    <th>Nome completo</th>
                    <th>Vínculo</th>
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
                    <tr><td colspan="9">Nenhuma ficha encontrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($membros as $m): ?>
                    <?php $vinculo = trim((string)($m['vinculo'] ?? '')); ?>
                    <tr>
                        <td><?= htmlspecialchars($m['nome_completo']) ?></td>
                        <td>
                            <?php if ($vinculo === ''): ?>
                                <span class="grey-text">—</span>
                            <?php else: ?>
                                <span class="badge-vinculo <?= $vinculo === VINCULO_ASSOCIADO ? 'associado' : '' ?>">
                                    <?= htmlspecialchars($vinculo) ?>
                                </span>
                            <?php endif; ?>
                        </td>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        M.FormSelect.init(document.querySelectorAll('select'));
    });
</script>
</body>
</html>
