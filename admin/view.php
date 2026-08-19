<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$config = require __DIR__ . '/../config/config.php';
$pdo = getDB();

$id = (int)($_GET['id'] ?? 0);

// Qualquer POST desta tela (salvar ou excluir) precisa trazer o token da sessão.
csrfValidar();

// Exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM members WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: dashboard.php');
    exit;
}

// Atualização
$sucesso = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
    $campos = ['vinculo','nome_completo','nacionalidade','estado_civil','profissao','cpf','rg_numero','rg_orgao','cin',
               'email','telefone','logradouro','numero','complemento','bairro','cidade','estado','cep','local_data'];

    $valores = [];
    foreach ($campos as $c) {
        $valores[$c] = trim($_POST[$c] ?? '');
    }

    // O vínculo tem que ser um dos valores conhecidos; qualquer outra coisa vira vazio.
    if (!in_array($valores['vinculo'], todosOsVinculos(), true)) {
        $valores['vinculo'] = '';
    }
    $declaracao = isset($_POST['declaracao_aceite']) ? 1 : 0;

    $set = implode(', ', array_map(fn($c) => "$c = :$c", $campos));
    $stmt = $pdo->prepare("UPDATE members SET $set, declaracao_aceite = :declaracao_aceite, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $valores['declaracao_aceite'] = $declaracao;
    $valores['id'] = $id;
    $stmt->execute($valores);
    $sucesso = true;
}

$stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
$stmt->execute([$id]);
$m = $stmt->fetch();

if (!$m) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar membro — <?= htmlspecialchars($config['app_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="brand-header">
    <div class="container">
        <div class="row valign-wrapper" style="margin-bottom: 0;">
            <div class="col s8">
                <h4>Editar membro</h4>
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

    <?php if ($sucesso): ?>
        <div class="card-panel green lighten-4 green-text text-darken-4">Dados atualizados com sucesso.</div>
    <?php endif; ?>

    <div class="card form-card">
        <div class="card-content">
            <form method="POST">
                <?= csrfCampo() ?>
                <input type="hidden" name="acao" value="salvar">

                <h5 class="section-title">Dados pessoais</h5>
                <div class="row">
                    <div class="input-field col s12 m6">
                        <select id="vinculo" name="vinculo">
                            <option value="">— não informado —</option>
                            <?php foreach (todosOsVinculos() as $v): ?>
                                <option value="<?= htmlspecialchars($v) ?>"
                                    <?= trim((string)($m['vinculo'] ?? '')) === $v ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label>Vínculo com a associação</label>
                    </div>
                    <div class="input-field col s12">
                        <input id="nome_completo" name="nome_completo" type="text" value="<?= htmlspecialchars($m['nome_completo']) ?>" required>
                        <label for="nome_completo" class="active">Nome completo</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input id="nacionalidade" name="nacionalidade" type="text" value="<?= htmlspecialchars($m['nacionalidade']) ?>">
                        <label for="nacionalidade" class="active">Nacionalidade</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input id="estado_civil" name="estado_civil" type="text" value="<?= htmlspecialchars($m['estado_civil']) ?>">
                        <label for="estado_civil" class="active">Estado civil</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input id="profissao" name="profissao" type="text" value="<?= htmlspecialchars($m['profissao']) ?>">
                        <label for="profissao" class="active">Profissão</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input id="cpf" name="cpf" type="text" value="<?= htmlspecialchars($m['cpf']) ?>">
                        <label for="cpf" class="active">CPF</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input id="rg_numero" name="rg_numero" type="text" value="<?= htmlspecialchars($m['rg_numero']) ?>">
                        <label for="rg_numero" class="active">RG (número)</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input id="rg_orgao" name="rg_orgao" type="text" value="<?= htmlspecialchars($m['rg_orgao']) ?>">
                        <label for="rg_orgao" class="active">RG (órgão emissor)</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input id="cin" name="cin" type="text" value="<?= htmlspecialchars($m['cin'] ?? '') ?>">
                        <label for="cin" class="active">CIN (Carteira de Identidade Nacional)</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input id="email" name="email" type="email" value="<?= htmlspecialchars($m['email']) ?>">
                        <label for="email" class="active">E-mail</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input id="telefone" name="telefone" type="text" value="<?= htmlspecialchars($m['telefone']) ?>">
                        <label for="telefone" class="active">Telefone</label>
                    </div>
                </div>

                <h5 class="section-title">Endereço residencial</h5>
                <div class="row">
                    <div class="input-field col s12 m4">
                        <input id="cep" name="cep" type="text" value="<?= htmlspecialchars($m['cep']) ?>">
                        <label for="cep" class="active">CEP</label>
                    </div>
                    <div class="input-field col s12 m8">
                        <input id="logradouro" name="logradouro" type="text" value="<?= htmlspecialchars($m['logradouro']) ?>">
                        <label for="logradouro" class="active">Logradouro</label>
                    </div>
                    <div class="input-field col s12 m4">
                        <input id="numero" name="numero" type="text" value="<?= htmlspecialchars($m['numero']) ?>">
                        <label for="numero" class="active">Número</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input id="complemento" name="complemento" type="text" value="<?= htmlspecialchars($m['complemento']) ?>">
                        <label for="complemento" class="active">Complemento</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input id="bairro" name="bairro" type="text" value="<?= htmlspecialchars($m['bairro']) ?>">
                        <label for="bairro" class="active">Bairro</label>
                    </div>
                    <div class="input-field col s12 m5">
                        <input id="cidade" name="cidade" type="text" value="<?= htmlspecialchars($m['cidade']) ?>">
                        <label for="cidade" class="active">Cidade</label>
                    </div>
                    <div class="input-field col s12 m3">
                        <input id="estado" name="estado" type="text" maxlength="2" value="<?= htmlspecialchars($m['estado']) ?>">
                        <label for="estado" class="active">UF</label>
                    </div>
                </div>

                <h5 class="section-title">Declaração</h5>
                <div class="row">
                    <div class="input-field col s12 m6">
                        <input id="local_data" name="local_data" type="text" value="<?= htmlspecialchars($m['local_data']) ?>">
                        <label for="local_data" class="active">Local e data</label>
                    </div>
                    <div class="col s12 m6" style="padding-top: 24px;">
                        <label>
                            <input type="checkbox" name="declaracao_aceite" class="filled-in" <?= $m['declaracao_aceite'] ? 'checked' : '' ?> />
                            <span>Declaração aceita pelo membro</span>
                        </label>
                    </div>
                </div>

                <button class="btn waves-effect waves-light blue darken-1" type="submit">
                    Salvar alterações <i class="material-icons right">save</i>
                </button>
            </form>

            <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta ficha? Esta ação não pode ser desfeita.');" style="margin-top: 24px;">
                <?= csrfCampo() ?>
                <input type="hidden" name="acao" value="excluir">
                <button class="btn waves-effect waves-light red darken-1" type="submit">
                    Excluir ficha <i class="material-icons right">delete</i>
                </button>
            </form>
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
