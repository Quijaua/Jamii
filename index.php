<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
$config = require __DIR__ . '/config/config.php';

iniciarSessao();

// Recupera dados de uma tentativa anterior que falhou (sem perder o que a pessoa digitou)
$dadosAnteriores = $_SESSION['flash_dados'] ?? [];
$faltantes       = $_SESSION['flash_faltantes'] ?? [];
$semAceite       = $_SESSION['flash_sem_aceite'] ?? false;
$rotulos         = $_SESSION['flash_rotulos'] ?? [];
$erroTecnico     = $_SESSION['flash_erro_tecnico'] ?? false;

// Consome a sessão (mostra uma única vez; se a pessoa recarregar a página em branco, começa do zero)
unset($_SESSION['flash_dados'], $_SESSION['flash_faltantes'], $_SESSION['flash_sem_aceite'],
      $_SESSION['flash_rotulos'], $_SESSION['flash_erro_tecnico']);

$mostrarErro = isset($_GET['erro']);

$settings   = getSettings();
$aberto     = formularioAberto($settings);
$vinculos   = vinculosDisponiveis($settings);
$titulo     = tituloFormulario($settings);
$encerrouFundadores = fundadoresEncerrados($settings);

function val(array $dados, string $campo): string
{
    return htmlspecialchars($dados[$campo] ?? '');
}

function classeErro(array $faltantes, string $campo): string
{
    return in_array($campo, $faltantes, true) ? 'invalid' : '';
}

function selecionado(array $dados, string $campo, string $valorOpcao): string
{
    return (($dados[$campo] ?? '') === $valorOpcao) ? 'selected' : '';
}

function marcado(array $dados, string $campo, string $valorOpcao): string
{
    return (($dados[$campo] ?? '') === $valorOpcao) ? 'checked' : '';
}
?>
<?php if (!$aberto): ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrições encerradas — <?= htmlspecialchars($config['app_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="brand-header">
    <div class="container">
        <h4><?= htmlspecialchars($titulo) ?></h4>
    </div>
</header>
<main class="container">
    <div class="card">
        <div class="card-content center-align" style="padding: 48px 16px;">
            <i class="material-icons grey-text text-darken-1" style="font-size: 64px;">lock</i>
            <h5>Inscrições encerradas</h5>
            <p style="max-width: 520px; margin: 16px auto 0; white-space: pre-line;"><?= htmlspecialchars(mensagemFormularioFechado($settings)) ?></p>
        </div>
    </div>
</main>
</body>
</html>
<?php exit; endif; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['app_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="brand-header">
    <div class="container">
        <h4><?= htmlspecialchars($titulo) ?></h4>
        <?php if (!$encerrouFundadores): ?>
            <p><?= htmlspecialchars($config['evento']) ?></p>
        <?php endif; ?>
    </div>
</header>

<main class="container">
    <div class="card form-card">
        <div class="card-content">
            <?php if ($encerrouFundadores): ?>
                <p>A assembleia de fundação já foi realizada, então este formulário passou a receber
                    o cadastro de <strong>novos associados</strong>. Preencha as informações abaixo
                    conforme exigência para o registro da associação.</p>
            <?php else: ?>
                <p>Solicitamos que todos os membros fundadores preencham as informações abaixo, conforme exigência
                    para o registro da associação.</p>
            <?php endif; ?>
            <p><span class="red-text">*</span> Campos obrigatórios.</p>

            <?php if ($mostrarErro && $erroTecnico): ?>
                <div class="card-panel red lighten-4 red-text text-darken-4">
                    Não foi possível salvar sua ficha no momento por um problema técnico no servidor.
                    Seus dados preenchidos foram mantidos abaixo — por favor, tente enviar novamente em
                    alguns instantes. Se o problema continuar, avise o responsável pelo sistema.
                </div>
            <?php elseif ($mostrarErro && (!empty($faltantes) || $semAceite)): ?>
                <div class="card-panel red lighten-4 red-text text-darken-4">
                    <strong>Por favor, corrija os itens abaixo antes de enviar:</strong>
                    <ul style="margin: 8px 0 0 20px;">
                        <?php foreach ($faltantes as $campo): ?>
                            <li><?= htmlspecialchars($rotulos[$campo] ?? $campo) ?></li>
                        <?php endforeach; ?>
                        <?php if ($semAceite): ?>
                            <li>É necessário aceitar a declaração no final do formulário.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="submit.php" method="POST">

                <h5 class="section-title">Vínculo com a associação</h5>
                <div class="vinculo-box <?= in_array('vinculo', $faltantes, true) ? 'red lighten-5' : '' ?>">
                    <p style="margin-top: 0;">Marque como você participa da associação
                        <span class="red-text">*</span></p>
                    <?php foreach ($vinculos as $opcao): ?>
                        <p>
                            <label>
                                <input name="vinculo" type="radio" value="<?= htmlspecialchars($opcao) ?>"
                                       required <?= marcado($dadosAnteriores, 'vinculo', $opcao) ?> />
                                <span><?= htmlspecialchars($opcao) ?></span>
                            </label>
                        </p>
                    <?php endforeach; ?>
                    <?php if ($encerrouFundadores): ?>
                        <p class="grey-text" style="margin-bottom: 0; font-size: .9rem;">
                            As inscrições de membro fundador se encerraram com a assembleia de fundação.
                        </p>
                    <?php endif; ?>
                </div>

                <h5 class="section-title">Dados pessoais</h5>
                <div class="row">
                    <div class="input-field col s12">
                        <input id="nome_completo" name="nome_completo" type="text"
                               class="validate <?= classeErro($faltantes, 'nome_completo') ?>"
                               value="<?= val($dadosAnteriores, 'nome_completo') ?>" required>
                        <label for="nome_completo" class="<?= $dadosAnteriores ? 'active' : '' ?>">Nome completo <span class="red-text">*</span></label>
                    </div>

                    <div class="input-field col s12 m6">
                        <input id="nacionalidade" name="nacionalidade" type="text"
                               class="validate <?= classeErro($faltantes, 'nacionalidade') ?>"
                               value="<?= val($dadosAnteriores, 'nacionalidade') ?>" required>
                        <label for="nacionalidade" class="<?= $dadosAnteriores ? 'active' : '' ?>">Nacionalidade <span class="red-text">*</span></label>
                    </div>

                    <div class="input-field col s12 m6">
                        <select id="estado_civil" name="estado_civil" class="<?= classeErro($faltantes, 'estado_civil') ?>" required>
                            <option value="" disabled <?= empty($dadosAnteriores['estado_civil'] ?? '') ? 'selected' : '' ?>>Selecione</option>
                            <option value="Solteiro(a)" <?= selecionado($dadosAnteriores, 'estado_civil', 'Solteiro(a)') ?>>Solteiro(a)</option>
                            <option value="Casado(a)" <?= selecionado($dadosAnteriores, 'estado_civil', 'Casado(a)') ?>>Casado(a)</option>
                            <option value="Divorciado(a)" <?= selecionado($dadosAnteriores, 'estado_civil', 'Divorciado(a)') ?>>Divorciado(a)</option>
                            <option value="Viúvo(a)" <?= selecionado($dadosAnteriores, 'estado_civil', 'Viúvo(a)') ?>>Viúvo(a)</option>
                            <option value="União estável" <?= selecionado($dadosAnteriores, 'estado_civil', 'União estável') ?>>União estável</option>
                        </select>
                        <label>Estado civil <span class="red-text">*</span></label>
                    </div>

                    <div class="input-field col s12 m6">
                        <input id="profissao" name="profissao" type="text"
                               class="validate <?= classeErro($faltantes, 'profissao') ?>"
                               value="<?= val($dadosAnteriores, 'profissao') ?>" required>
                        <label for="profissao" class="<?= $dadosAnteriores ? 'active' : '' ?>">Profissão <span class="red-text">*</span></label>
                    </div>

                    <div class="input-field col s12 m6">
                        <input id="cpf" name="cpf" type="text"
                               class="validate <?= classeErro($faltantes, 'cpf') ?>"
                               value="<?= val($dadosAnteriores, 'cpf') ?>" required placeholder="000.000.000-00">
                        <label for="cpf" class="<?= $dadosAnteriores ? 'active' : '' ?>">CPF <span class="red-text">*</span></label>
                    </div>

                    <div class="input-field col s12 m6">
                        <input id="rg_numero" name="rg_numero" type="text"
                               class="validate <?= classeErro($faltantes, 'rg_numero') ?>"
                               value="<?= val($dadosAnteriores, 'rg_numero') ?>" required>
                        <label for="rg_numero" class="<?= $dadosAnteriores ? 'active' : '' ?>">RG (número) <span class="red-text">*</span></label>
                    </div>

                    <div class="input-field col s12 m6">
                        <input id="rg_orgao" name="rg_orgao" type="text"
                               class="validate <?= classeErro($faltantes, 'rg_orgao') ?>"
                               value="<?= val($dadosAnteriores, 'rg_orgao') ?>" required placeholder="Ex: SSP/MG">
                        <label for="rg_orgao" class="<?= $dadosAnteriores ? 'active' : '' ?>">RG (órgão emissor) <span class="red-text">*</span></label>
                    </div>

                    <div class="input-field col s12 m6">
                        <input id="cin" name="cin" type="text" class="validate"
                               value="<?= val($dadosAnteriores, 'cin') ?>" placeholder="Se já possuir a nova identidade">
                        <label for="cin" class="<?= $dadosAnteriores ? 'active' : '' ?>">CIN — Carteira de Identidade Nacional (opcional)</label>
                    </div>

                    <div class="input-field col s12 m6">
                        <input id="email" name="email" type="email"
                               class="validate <?= classeErro($faltantes, 'email') ?>"
                               value="<?= val($dadosAnteriores, 'email') ?>" required>
                        <label for="email" class="<?= $dadosAnteriores ? 'active' : '' ?>">E-mail <span class="red-text">*</span></label>
                    </div>

                    <div class="input-field col s12 m6">
                        <input id="telefone" name="telefone" type="text" class="validate"
                               value="<?= val($dadosAnteriores, 'telefone') ?>">
                        <label for="telefone" class="<?= $dadosAnteriores ? 'active' : '' ?>">Telefone (opcional)</label>
                    </div>
                </div>

                <h5 class="section-title">Endereço residencial</h5>
                <div class="row">
                    <div class="input-field col s12 m4">
                        <input id="cep" name="cep" type="text"
                               class="validate <?= classeErro($faltantes, 'cep') ?>"
                               value="<?= val($dadosAnteriores, 'cep') ?>" required placeholder="00000-000">
                        <label for="cep" class="<?= $dadosAnteriores ? 'active' : '' ?>">CEP <span class="red-text">*</span></label>
                        <span id="cep-status" class="helper-text"></span>
                    </div>

                    <div class="input-field col s12 m8">
                        <input id="logradouro" name="logradouro" type="text"
                               class="validate <?= classeErro($faltantes, 'logradouro') ?>"
                               value="<?= val($dadosAnteriores, 'logradouro') ?>" required>
                        <label for="logradouro" class="<?= $dadosAnteriores ? 'active' : '' ?>">Logradouro (rua, avenida etc.) <span class="red-text">*</span></label>
                    </div>

                    <div class="input-field col s12 m4">
                        <input id="numero" name="numero" type="text"
                               class="validate <?= classeErro($faltantes, 'numero') ?>"
                               value="<?= val($dadosAnteriores, 'numero') ?>" required>
                        <label for="numero" class="<?= $dadosAnteriores ? 'active' : '' ?>">Número <span class="red-text">*</span></label>
                    </div>

                    <div class="input-field col s12 m6">
                        <input id="complemento" name="complemento" type="text" class="validate"
                               value="<?= val($dadosAnteriores, 'complemento') ?>">
                        <label for="complemento" class="<?= $dadosAnteriores ? 'active' : '' ?>">Complemento</label>
                    </div>

                    <div class="input-field col s12 m6">
                        <input id="bairro" name="bairro" type="text"
                               class="validate <?= classeErro($faltantes, 'bairro') ?>"
                               value="<?= val($dadosAnteriores, 'bairro') ?>" required>
                        <label for="bairro" class="<?= $dadosAnteriores ? 'active' : '' ?>">Bairro <span class="red-text">*</span></label>
                    </div>

                    <div class="input-field col s12 m8">
                        <input id="cidade" name="cidade" type="text"
                               class="validate <?= classeErro($faltantes, 'cidade') ?>"
                               value="<?= val($dadosAnteriores, 'cidade') ?>" required>
                        <label for="cidade" class="<?= $dadosAnteriores ? 'active' : '' ?>">Cidade <span class="red-text">*</span></label>
                    </div>

                    <div class="input-field col s12 m4">
                        <select id="estado" name="estado" class="<?= classeErro($faltantes, 'estado') ?>" required>
                            <option value="" disabled <?= empty($dadosAnteriores['estado'] ?? '') ? 'selected' : '' ?>>UF</option>
                            <?php
                            $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                            foreach ($ufs as $uf) {
                                $sel = selecionado($dadosAnteriores, 'estado', $uf);
                                echo "<option value=\"$uf\" $sel>$uf</option>";
                            }
                            ?>
                        </select>
                        <label>Estado <span class="red-text">*</span></label>
                    </div>
                </div>


                <h5 class="section-title">Declaração</h5>
                <div class="declaracao-box <?= $semAceite ? 'red lighten-5' : '' ?>">
                    <p>Declaro que as informações acima são verdadeiras e autorizo sua utilização exclusivamente
                        para os procedimentos de constituição e registro da associação, bem como para os atos
                        legais e administrativos decorrentes.</p>

                    <label>
                        <input type="checkbox" name="declaracao_aceite" value="1" required class="filled-in" />
                        <span>Li e concordo com a declaração acima. <span class="red-text">*</span></span>
                    </label>
                    <?php if ($semAceite): ?>
                        <p class="red-text" style="margin-top: 8px;">É necessário marcar esta declaração para enviar a ficha.</p>
                    <?php endif; ?>
                </div>

                <div class="row">
                    <div class="input-field col s12 m6">
                        <input id="local_data" name="local_data" type="text"
                               class="validate <?= classeErro($faltantes, 'local_data') ?>"
                               value="<?= val($dadosAnteriores, 'local_data') ?>" required placeholder="Ex: Juiz de Fora, 23/07/2026">
                        <label for="local_data" class="<?= $dadosAnteriores ? 'active' : '' ?>">Local e data <span class="red-text">*</span></label>
                    </div>
                </div>

                <button class="btn waves-effect waves-light blue darken-1" type="submit">
                    Enviar ficha
                    <i class="material-icons right">send</i>
                </button>
            </form>
        </div>
    </div>

    <p class="footer-note">Seus dados serão utilizados exclusivamente para fins de constituição e registro da associação.</p>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var elems = document.querySelectorAll('select');
        M.FormSelect.init(elems);
        M.updateTextFields();

        // Substitui as mensagens padrão do navegador (em inglês) por mensagens em português
        var camposObrigatorios = document.querySelectorAll('[required]');
        camposObrigatorios.forEach(function (campo) {
            campo.addEventListener('invalid', function () {
                if (campo.validity.valueMissing) {
                    if (campo.type === 'checkbox') {
                        campo.setCustomValidity('Você precisa marcar esta opção para continuar.');
                    } else if (campo.tagName === 'SELECT') {
                        campo.setCustomValidity('Por favor, selecione uma opção.');
                    } else {
                        campo.setCustomValidity('Por favor, preencha este campo.');
                    }
                } else if (campo.validity.typeMismatch && campo.type === 'email') {
                    campo.setCustomValidity('Por favor, informe um e-mail válido.');
                } else {
                    campo.setCustomValidity('Por favor, corrija este campo.');
                }
            });

            // Limpa a mensagem customizada assim que a pessoa começar a corrigir o campo
            ['input', 'change'].forEach(function (evento) {
                campo.addEventListener(evento, function () {
                    campo.setCustomValidity('');
                });
            });
        });

        // Integração com o ViaCEP: ao preencher o CEP, busca e preenche o endereço automaticamente
        var campoCep = document.getElementById('cep');
        var statusCep = document.getElementById('cep-status');
        var camposEndereco = {
            logradouro: document.getElementById('logradouro'),
            bairro: document.getElementById('bairro'),
            cidade: document.getElementById('cidade'),
            estado: document.getElementById('estado')
        };

        function buscarCep() {
            var cepLimpo = campoCep.value.replace(/\D/g, '');
            if (cepLimpo.length !== 8) {
                return;
            }

            statusCep.textContent = 'Buscando endereço...';
            statusCep.classList.remove('red-text');
            statusCep.classList.add('grey-text');

            fetch('https://viacep.com.br/ws/' + cepLimpo + '/json/')
                .then(function (resposta) { return resposta.json(); })
                .then(function (dados) {
                    if (dados.erro) {
                        statusCep.textContent = 'CEP não encontrado. Preencha o endereço manualmente.';
                        statusCep.classList.remove('grey-text');
                        statusCep.classList.add('red-text');
                        return;
                    }

                    if (dados.logradouro) {
                        camposEndereco.logradouro.value = dados.logradouro;
                    }
                    if (dados.bairro) {
                        camposEndereco.bairro.value = dados.bairro;
                    }
                    if (dados.localidade) {
                        camposEndereco.cidade.value = dados.localidade;
                    }
                    if (dados.uf) {
                        camposEndereco.estado.value = dados.uf;
                        M.FormSelect.init(camposEndereco.estado);
                    }

                    M.updateTextFields();
                    statusCep.textContent = 'Endereço preenchido automaticamente. Confira e complete o número/complemento.';
                    statusCep.classList.remove('red-text');
                    statusCep.classList.add('grey-text');
                })
                .catch(function () {
                    statusCep.textContent = 'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.';
                    statusCep.classList.remove('grey-text');
                    statusCep.classList.add('red-text');
                });
        }

        if (campoCep) {
            campoCep.addEventListener('blur', buscarCep);
        }
    });
</script>
</body>
</html>
