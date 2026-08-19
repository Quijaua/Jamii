<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$config = require __DIR__ . '/../config/config.php';
$pdo = getDB();

$sucesso = null;   // texto de confirmação da última ação
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfValidar();

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'meta') {
        $meta = (int)($_POST['meta_associados'] ?? 0);
        if ($meta < 0) {
            $erro = 'A meta de associados não pode ser negativa.';
        } else {
            updateSettings(['meta_associados' => $meta]);
            $sucesso = 'Meta atualizada com sucesso.';
        }

    } elseif ($acao === 'formulario') {
        updateSettings([
            'formulario_aberto' => isset($_POST['formulario_aberto']) ? 1 : 0,
            'mensagem_fechado'  => trim($_POST['mensagem_fechado'] ?? ''),
        ]);
        $sucesso = isset($_POST['formulario_aberto'])
            ? 'Formulário público aberto — o link já está aceitando fichas.'
            : 'Formulário público fechado — quem abrir o link verá a mensagem de encerramento.';

    } elseif ($acao === 'assembleia') {
        $data = trim($_POST['data_assembleia'] ?? '');
        $modo = $_POST['fundadores_modo'] ?? 'auto';

        if ($data !== '' && !DateTimeImmutable::createFromFormat('Y-m-d', $data)) {
            $erro = 'Data da assembleia inválida.';
        } elseif (!in_array($modo, ['auto', 'aberto', 'encerrado'], true)) {
            $erro = 'Opção de encerramento inválida.';
        } else {
            updateSettings(['data_assembleia' => $data, 'fundadores_modo' => $modo]);
            $sucesso = 'Configuração da assembleia atualizada.';
        }
    }
}

$settings = getSettings();
$meta = (int)$settings['meta_associados'];

$totalPreenchidas = (int)$pdo->query('SELECT COUNT(*) AS total FROM members')->fetch()['total'];
$totalAssinadas   = (int)$pdo->query('SELECT COUNT(*) AS total FROM members WHERE declaracao_aceite = 1')->fetch()['total'];

// A meta se refere aos membros fundadores; quem entrou como associado depois da
// assembleia não conta para ela (ver contaComoFundador() em includes/inscricao.php).
$stmtF = $pdo->prepare('SELECT COUNT(*) AS total FROM members WHERE COALESCE(vinculo, ?) <> ?');
$stmtF->execute([VINCULO_FUNDADOR, VINCULO_ASSOCIADO]);
$totalFundadores = (int)$stmtF->fetch()['total'];

$stmtFA = $pdo->prepare('SELECT COUNT(*) AS total FROM members WHERE declaracao_aceite = 1 AND COALESCE(vinculo, ?) <> ?');
$stmtFA->execute([VINCULO_FUNDADOR, VINCULO_ASSOCIADO]);
$fundadoresAssinados = (int)$stmtFA->fetch()['total'];

$porVinculo = [];
foreach ($pdo->query("SELECT COALESCE(NULLIF(vinculo, ''), 'Não informado') AS v, COUNT(*) AS total FROM members GROUP BY v ORDER BY total DESC") as $linha) {
    $porVinculo[$linha['v']] = (int)$linha['total'];
}

$percentualPreenchido = $meta > 0 ? min(100, round(($totalFundadores / $meta) * 100)) : 0;
$percentualAssinado   = $meta > 0 ? min(100, round(($fundadoresAssinados / $meta) * 100)) : 0;

$metaAtingida = $meta > 0 && $fundadoresAssinados >= $meta;

$aberto = formularioAberto($settings);
$encerrouFundadores = fundadoresEncerrados($settings);
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
        <div class="card-panel blue lighten-4 blue-text text-darken-4"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="card-panel red lighten-4 red-text text-darken-4"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-content">
            <span class="card-title">Formulário público</span>
            <p>
                Estado atual:
                <?php if ($aberto): ?>
                    <span class="badge-yes">Aberto</span> — o link está aceitando novas fichas.
                <?php else: ?>
                    <span class="badge-no">Fechado</span> — quem abrir o link vê a mensagem abaixo, sem formulário.
                <?php endif; ?>
            </p>

            <form method="POST">
                <?= csrfCampo() ?>
                <input type="hidden" name="acao" value="formulario">

                <p>
                    <label>
                        <input type="checkbox" name="formulario_aberto" value="1" class="filled-in"
                               <?= $aberto ? 'checked' : '' ?> />
                        <span>Manter o formulário aberto para receber fichas</span>
                    </label>
                </p>

                <div class="input-field">
                    <textarea id="mensagem_fechado" name="mensagem_fechado" class="materialize-textarea"
                              placeholder="<?= htmlspecialchars(MENSAGEM_FECHADO_PADRAO) ?>"><?= htmlspecialchars((string)$settings['mensagem_fechado']) ?></textarea>
                    <label for="mensagem_fechado" class="active">Mensagem exibida com o formulário fechado</label>
                    <span class="helper-text">Se deixar em branco, o sistema usa um texto padrão de
                        "inscrições encerradas".</span>
                </div>

                <button class="btn waves-effect waves-light blue darken-1" type="submit">
                    Salvar <i class="material-icons right">save</i>
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-content">
            <span class="card-title">Assembleia de fundação</span>
            <p>
                Inscrições de membro fundador:
                <?php if ($encerrouFundadores): ?>
                    <span class="badge-no">Encerradas</span>
                <?php else: ?>
                    <span class="badge-yes">Abertas</span>
                <?php endif; ?>
                <br>
                <span class="grey-text"><?= htmlspecialchars(descricaoEstadoFundadores($settings)) ?></span>
            </p>
            <p>Encerradas as inscrições de fundador, o formulário continua no ar, mas passa a
                cadastrar <strong>associados</strong>: a opção "<?= htmlspecialchars(VINCULO_FUNDADOR) ?>"
                dá lugar a "<?= htmlspecialchars(VINCULO_ASSOCIADO) ?>". As fichas já enviadas não mudam.</p>

            <form method="POST" class="row" style="margin-bottom: 0;">
                <?= csrfCampo() ?>
                <input type="hidden" name="acao" value="assembleia">

                <div class="input-field col s12 m5">
                    <input id="data_assembleia" name="data_assembleia" type="date"
                           value="<?= htmlspecialchars((string)$settings['data_assembleia']) ?>">
                    <label for="data_assembleia" class="active">Data da assembleia de fundação</label>
                </div>

                <div class="col s12 m7">
                    <p style="margin-top: 8px;"><strong>Quando encerrar as inscrições de fundador</strong></p>
                    <?php
                    $modoAtual = (string)$settings['fundadores_modo'];
                    $modos = [
                        'auto'      => 'Automaticamente, no dia seguinte à assembleia',
                        'aberto'    => 'Manter abertas (ignorar a data)',
                        'encerrado' => 'Encerrar agora (ignorar a data)',
                    ];
                    foreach ($modos as $valor => $rotulo): ?>
                        <p>
                            <label>
                                <input name="fundadores_modo" type="radio" value="<?= $valor ?>"
                                       <?= $modoAtual === $valor ? 'checked' : '' ?> />
                                <span><?= htmlspecialchars($rotulo) ?></span>
                            </label>
                        </p>
                    <?php endforeach; ?>
                </div>

                <div class="col s12">
                    <button class="btn waves-effect waves-light blue darken-1" type="submit">
                        Salvar <i class="material-icons right">save</i>
                    </button>
                </div>
            </form>
        </div>
    </div>

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
                <?= csrfCampo() ?>
                <input type="hidden" name="acao" value="meta">
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
            <p class="grey-text">A meta conta os membros fundadores. Como o vínculo é de escolha única,
                quem se marcou como Diretoria ou Conselho Fiscal também entra na conta — só
                "<?= htmlspecialchars(VINCULO_ASSOCIADO) ?>" fica de fora.</p>

            <?php if ($meta === 0): ?>
                <p class="grey-text">Defina uma meta acima para ver o progresso em relação ao total esperado.</p>
            <?php endif; ?>

            <div class="row" style="margin-top: 16px;">
                <div class="col s12 m6">
                    <h6>Fichas de fundadores preenchidas</h6>
                    <p><?= $totalFundadores ?><?= $meta > 0 ? " de $meta" : '' ?></p>
                    <?php if ($meta > 0): ?>
                        <div class="progress">
                            <div class="determinate blue darken-1" style="width: <?= $percentualPreenchido ?>%"></div>
                        </div>
                        <p class="grey-text"><?= $percentualPreenchido ?>% do total esperado</p>
                    <?php endif; ?>
                </div>

                <div class="col s12 m6">
                    <h6>Declarações de fundadores assinadas</h6>
                    <p><?= $fundadoresAssinados ?><?= $meta > 0 ? " de $meta" : '' ?></p>
                    <?php if ($meta > 0): ?>
                        <div class="progress">
                            <div class="determinate green darken-1" style="width: <?= $percentualAssinado ?>%"></div>
                        </div>
                        <p class="grey-text"><?= $percentualAssinado ?>% do total esperado</p>
                    <?php endif; ?>
                </div>
            </div>

            <h6 style="margin-top: 24px;">Todas as fichas por vínculo</h6>
            <table class="striped">
                <tbody>
                <?php foreach ($porVinculo as $nome => $qtd): ?>
                    <tr>
                        <td><?= htmlspecialchars($nome) ?></td>
                        <td style="width: 80px;" class="right-align"><?= $qtd ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td><strong>Total</strong></td>
                    <td class="right-align"><strong><?= $totalPreenchidas ?></strong></td>
                </tr>
                <tr>
                    <td class="grey-text">Declarações assinadas (todas as fichas)</td>
                    <td class="right-align grey-text"><?= $totalAssinadas ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
