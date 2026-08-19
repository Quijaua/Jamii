<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/SmtpMailer.php';
require_once __DIR__ . '/includes/session.php';
$config = require __DIR__ . '/config/config.php';

iniciarSessao();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// O estado do formulário é reconferido aqui: sem isso, um formulário deixado
// aberto numa aba antes do fechamento ainda conseguiria gravar uma ficha.
$settings = getSettings();

if (!formularioAberto($settings)) {
    header('Location: index.php');
    exit;
}

$vinculosPermitidos = vinculosDisponiveis($settings);

function campo(string $nome): string
{
    return trim($_POST[$nome] ?? '');
}

$dados = [
    'vinculo'       => campo('vinculo'),
    'nome_completo' => campo('nome_completo'),
    'nacionalidade' => campo('nacionalidade'),
    'estado_civil'  => campo('estado_civil'),
    'profissao'     => campo('profissao'),
    'cpf'           => campo('cpf'),
    'rg_numero'     => campo('rg_numero'),
    'rg_orgao'      => campo('rg_orgao'),
    'cin'           => campo('cin'),
    'email'         => campo('email'),
    'telefone'      => campo('telefone'),
    'cep'           => campo('cep'),
    'logradouro'    => campo('logradouro'),
    'numero'        => campo('numero'),
    'complemento'   => campo('complemento'),
    'bairro'        => campo('bairro'),
    'cidade'        => campo('cidade'),
    'estado'        => campo('estado'),
    'local_data'    => campo('local_data'),
];

$aceite = isset($_POST['declaracao_aceite']) && $_POST['declaracao_aceite'] === '1';

// Rótulos em português usados nas mensagens de erro
$rotulos = [
    'vinculo'       => 'Vínculo com a associação',
    'nome_completo' => 'Nome completo',
    'nacionalidade' => 'Nacionalidade',
    'estado_civil'  => 'Estado civil',
    'profissao'     => 'Profissão',
    'cpf'           => 'CPF',
    'rg_numero'     => 'RG (número)',
    'rg_orgao'      => 'RG (órgão emissor)',
    'email'         => 'E-mail',
    'logradouro'    => 'Logradouro',
    'numero'        => 'Número',
    'bairro'        => 'Bairro',
    'cidade'        => 'Cidade',
    'estado'        => 'Estado',
    'cep'           => 'CEP',
    'local_data'    => 'Local e data',
];

// Campos obrigatórios (telefone e complemento são opcionais)
$obrigatorios = array_keys($rotulos);

$faltantes = [];
foreach ($obrigatorios as $campo) {
    if ($dados[$campo] === '') {
        $faltantes[] = $campo;
    }
}

$emailInvalido = $dados['email'] !== '' && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL);
if ($emailInvalido && !in_array('email', $faltantes, true)) {
    $faltantes[] = 'email';
}

// Vínculo precisa ser uma das opções válidas AGORA. É o que impede alguém de
// forjar "Fundador(a)" depois que a assembleia já passou.
if ($dados['vinculo'] !== '' && !in_array($dados['vinculo'], $vinculosPermitidos, true)) {
    $dados['vinculo'] = '';
    if (!in_array('vinculo', $faltantes, true)) {
        $faltantes[] = 'vinculo';
    }
}

$semAceite = !$aceite;

if (!empty($faltantes) || $semAceite) {
    // Guarda o que a pessoa já preencheu para não obrigá-la a digitar tudo de novo
    $_SESSION['flash_dados'] = $dados;
    $_SESSION['flash_faltantes'] = $faltantes;
    $_SESSION['flash_sem_aceite'] = $semAceite;
    $_SESSION['flash_rotulos'] = $rotulos;
    header('Location: index.php?erro=1');
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO members (
            vinculo, nome_completo, nacionalidade, estado_civil, profissao, cpf, rg_numero, rg_orgao, cin,
            email, telefone, logradouro, numero, complemento, bairro, cidade, estado, cep,
            local_data, declaracao_aceite
        ) VALUES (
            :vinculo, :nome_completo, :nacionalidade, :estado_civil, :profissao, :cpf, :rg_numero, :rg_orgao, :cin,
            :email, :telefone, :logradouro, :numero, :complemento, :bairro, :cidade, :estado, :cep,
            :local_data, :declaracao_aceite
        )
    ");

    $stmt->execute([
        ':vinculo'       => $dados['vinculo'],
        ':nome_completo' => $dados['nome_completo'],
        ':nacionalidade' => $dados['nacionalidade'],
        ':estado_civil'  => $dados['estado_civil'],
        ':profissao'     => $dados['profissao'],
        ':cpf'           => $dados['cpf'],
        ':rg_numero'     => $dados['rg_numero'],
        ':rg_orgao'      => $dados['rg_orgao'],
        ':cin'           => $dados['cin'],
        ':email'         => $dados['email'],
        ':telefone'      => $dados['telefone'],
        ':logradouro'    => $dados['logradouro'],
        ':numero'        => $dados['numero'],
        ':complemento'   => $dados['complemento'],
        ':bairro'        => $dados['bairro'],
        ':cidade'        => $dados['cidade'],
        ':estado'        => $dados['estado'],
        ':cep'           => $dados['cep'],
        ':local_data'    => $dados['local_data'],
        ':declaracao_aceite' => 1,
    ]);

    // Sucesso: limpa qualquer dado de sessão de tentativa anterior
    unset($_SESSION['flash_dados'], $_SESSION['flash_faltantes'], $_SESSION['flash_sem_aceite'], $_SESSION['flash_rotulos']);

    enviarEmailNotificacao($config, $dados);

    header('Location: obrigado.php');
    exit;

} catch (Exception $e) {
    error_log('Erro ao gravar ficha: ' . $e->getMessage());

    // Mesmo em erro técnico (ex.: banco sem permissão de escrita), preserva o que foi digitado
    $_SESSION['flash_dados'] = $dados;
    $_SESSION['flash_faltantes'] = [];
    $_SESSION['flash_sem_aceite'] = false;
    $_SESSION['flash_rotulos'] = $rotulos;
    $_SESSION['flash_erro_tecnico'] = true;

    header('Location: index.php?erro=1');
    exit;
}

/**
 * Envia o e-mail de notificação. Usa SMTP autenticado (mais confiável) quando
 * configurado em config/config.php ('smtp' => ['enabled' => true, ...]);
 * caso contrário, usa a função mail() nativa do PHP como alternativa.
 */
function enviarEmailNotificacao(array $config, array $dados): void
{
    $to = $config['mail']['to'];
    $subject = $config['mail']['subject'];
    $corpo = montarCorpoEmail($dados);

    if (!empty($config['smtp']['enabled'])) {
        try {
            $mailer = new SmtpMailer($config['smtp']);
            $mailer->send(
                $to,
                $config['mail']['from'],
                $config['mail']['from_name'],
                $subject,
                $corpo,
                $dados['email']
            );
            return;
        } catch (Throwable $e) {
            // Não impede o cadastro da ficha — registra o erro para diagnóstico
            // e tenta o fallback via mail() nativo abaixo.
            error_log('Falha ao enviar e-mail via SMTP: ' . $e->getMessage());
        }
    }

    enviarViaMailNativo($config, $to, $subject, $corpo, $dados['email']);
}

function montarCorpoEmail(array $dados): string
{
    $corpo  = "Uma nova ficha de qualificação foi preenchida:\n\n";
    $corpo .= "Vínculo: {$dados['vinculo']}\n";
    $corpo .= "Nome completo: {$dados['nome_completo']}\n";
    $corpo .= "Nacionalidade: {$dados['nacionalidade']}\n";
    $corpo .= "Estado civil: {$dados['estado_civil']}\n";
    $corpo .= "Profissão: {$dados['profissao']}\n";
    $corpo .= "CPF: {$dados['cpf']}\n";
    $corpo .= "RG: {$dados['rg_numero']} - {$dados['rg_orgao']}\n";
    $corpo .= "CIN: " . ($dados['cin'] !== '' ? $dados['cin'] : '(não informado)') . "\n";
    $corpo .= "E-mail: {$dados['email']}\n";
    $corpo .= "Telefone: {$dados['telefone']}\n\n";
    $corpo .= "CEP: {$dados['cep']}\n";
    $corpo .= "Endereço: {$dados['logradouro']}, {$dados['numero']} {$dados['complemento']}\n";
    $corpo .= "Bairro: {$dados['bairro']}\n";
    $corpo .= "Cidade/UF: {$dados['cidade']}/{$dados['estado']}\n\n";
    $corpo .= "Local e data: {$dados['local_data']}\n";
    $corpo .= "Declaração aceita: Sim\n";
    return $corpo;
}

function enviarViaMailNativo(array $config, string $to, string $subject, string $corpo, string $replyTo): void
{
    $subjectCodificado = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromName = '=?UTF-8?B?' . base64_encode($config['mail']['from_name']) . '?=';

    $headers  = "From: {$fromName} <{$config['mail']['from']}>\r\n";
    $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    @mail($to, $subjectCodificado, $corpo, $headers);
}
