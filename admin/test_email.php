<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/SmtpMailer.php';
requireLogin();
$config = require __DIR__ . '/../config/config.php';

$resultado = null; // true = sucesso, false = falha, null = ainda não testado
$mensagemErro = null;
$logSmtp = [];
$destinoTeste = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfValidar();

    $destinoTeste = trim($_POST['destino'] ?? '');

    if (!filter_var($destinoTeste, FILTER_VALIDATE_EMAIL)) {
        $resultado = false;
        $mensagemErro = 'Informe um e-mail de destino válido para o teste.';
    } else {
        $assunto = 'Teste de envio — ' . $config['app_name'];
        $corpo = "Este é um e-mail de teste enviado pelo backoffice do sistema de fichas.\n\n"
               . "Se você recebeu esta mensagem, o envio de e-mails está funcionando corretamente.\n\n"
               . 'Método usado: ' . (!empty($config['smtp']['enabled']) ? 'SMTP autenticado' : 'mail() nativo do PHP') . "\n"
               . 'Data/hora do teste: ' . date('d/m/Y H:i:s') . "\n";

        if (!empty($config['smtp']['enabled'])) {
            try {
                $mailer = new SmtpMailer($config['smtp']);
                $mailer->send(
                    $destinoTeste,
                    $config['mail']['from'],
                    $config['mail']['from_name'],
                    $assunto,
                    $corpo
                );
                $resultado = true;
            } catch (Throwable $e) {
                $resultado = false;
                $mensagemErro = $e->getMessage();
                $logSmtp = $mailer->getLog();
            }
        } else {
            $subjectCodificado = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
            $fromName = '=?UTF-8?B?' . base64_encode($config['mail']['from_name']) . '?=';
            $headers  = "From: {$fromName} <{$config['mail']['from']}>\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            $enviado = @mail($destinoTeste, $subjectCodificado, $corpo, $headers);
            $resultado = $enviado;
            if (!$enviado) {
                $mensagemErro = 'A função mail() do PHP retornou falha. Isso geralmente indica que não há um '
                    . 'agente de envio (MTA) configurado no servidor, ou que ele está bloqueando o envio. '
                    . 'Considere habilitar o SMTP autenticado em config/config.php.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testar envio de e-mail — <?= htmlspecialchars($config['app_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="brand-header">
    <div class="container">
        <div class="row valign-wrapper" style="margin-bottom: 0;">
            <div class="col s8">
                <h4>Testar envio de e-mail</h4>
            </div>
            <div class="col s4 right-align">
                <a href="config.php" class="btn-flat white-text">
                    <i class="material-icons left">arrow_back</i> Voltar
                </a>
            </div>
        </div>
    </div>
</header>

<main class="container" style="max-width: 800px;">

    <div class="card">
        <div class="card-content">
            <span class="card-title">Método de envio configurado atualmente</span>
            <?php if (!empty($config['smtp']['enabled'])): ?>
                <p>
                    <span class="badge-yes">SMTP autenticado</span>
                    &nbsp; Servidor: <strong><?= htmlspecialchars($config['smtp']['host']) ?>:<?= htmlspecialchars((string)$config['smtp']['port']) ?></strong>
                    (<?= htmlspecialchars($config['smtp']['encryption'] ?: 'sem criptografia') ?>)
                </p>
            <?php else: ?>
                <p>
                    <span class="badge-no">mail() nativo do PHP</span>
                    &nbsp; Para usar SMTP autenticado (recomendado), configure e habilite em
                    <code>config/config.php</code> → <code>'smtp' => ['enabled' => true, ...]</code>.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-content">
            <span class="card-title">Enviar e-mail de teste</span>
            <p>Envie uma mensagem de teste para conferir se as configurações de e-mail estão funcionando.</p>

            <form method="POST" class="row" style="margin-bottom: 0;">
                <?= csrfCampo() ?>
                <div class="input-field col s12 m8">
                    <input id="destino" name="destino" type="email" required value="<?= htmlspecialchars($destinoTeste) ?>">
                    <label for="destino" class="<?= $destinoTeste ? 'active' : '' ?>">E-mail de destino do teste</label>
                </div>
                <div class="col s12 m4" style="padding-top: 20px;">
                    <button class="btn waves-effect waves-light blue darken-1" type="submit">
                        Enviar teste <i class="material-icons right">send</i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($resultado === true): ?>
        <div class="card-panel green lighten-4 green-text text-darken-4">
            <i class="material-icons left" style="vertical-align: middle;">check_circle</i>
            E-mail de teste enviado com sucesso para <?= htmlspecialchars($destinoTeste) ?>.
            Verifique a caixa de entrada (e a pasta de spam) do destinatário.
        </div>
    <?php elseif ($resultado === false): ?>
        <div class="card-panel red lighten-4 red-text text-darken-4">
            <i class="material-icons left" style="vertical-align: middle;">error</i>
            <strong>Falha ao enviar:</strong> <?= htmlspecialchars($mensagemErro) ?>
        </div>

        <?php if (!empty($logSmtp)): ?>
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Log da conversa SMTP (para diagnóstico)</span>
                    <pre style="background:#f5f5f5; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: .8rem;"><?= htmlspecialchars(implode("\n", $logSmtp)) ?></pre>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</main>
</body>
</html>
