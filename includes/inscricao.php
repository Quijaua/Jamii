<?php
/**
 * Regras de abertura do formulário público e de vínculo do membro.
 *
 * Tudo aqui é função pura: recebe o array de configurações (getSettings()) e
 * responde. Assim a mesma regra vale para o formulário (index.php), para a
 * gravação (submit.php) e para o painel — sem risco de uma tela achar que o
 * formulário está aberto enquanto a outra acha que está fechado.
 */

const VINCULO_FUNDADOR  = 'Fundador(a)';
const VINCULO_ASSOCIADO = 'Associado(a)';
const VINCULO_DIRETORIA = 'Diretoria';
const VINCULO_CONSELHO  = 'Conselho Fiscal';

const MENSAGEM_FECHADO_PADRAO =
    'As inscrições estão encerradas no momento. Se você precisa enviar sua ficha, '
    . 'entre em contato com a secretaria da associação.';

/**
 * Data de hoje no fuso configurado. Servidores costumam rodar em UTC, o que
 * viraria o dia às 21h no horário de Brasília e encerraria as inscrições de
 * fundador um dia antes da hora.
 */
function dataDeHoje(): string
{
    static $hoje = null;

    if ($hoje === null) {
        $config = require __DIR__ . '/../config/config.php';
        $fuso = $config['timezone'] ?? 'America/Sao_Paulo';

        try {
            $zona = new DateTimeZone($fuso);
        } catch (Exception $e) {
            $zona = new DateTimeZone('America/Sao_Paulo');
        }

        $hoje = (new DateTimeImmutable('now', $zona))->format('Y-m-d');
    }

    return $hoje;
}

/**
 * A chave geral: o formulário público aceita novas fichas?
 */
function formularioAberto(array $settings): bool
{
    return (int)($settings['formulario_aberto'] ?? 1) === 1;
}

/**
 * Texto mostrado a quem abrir o link com o formulário fechado.
 */
function mensagemFormularioFechado(array $settings): string
{
    $mensagem = trim((string)($settings['mensagem_fechado'] ?? ''));

    return $mensagem !== '' ? $mensagem : MENSAGEM_FECHADO_PADRAO;
}

/**
 * As inscrições de membro FUNDADOR já se encerraram?
 *
 * 'auto'      → segue a data da assembleia: encerra no dia seguinte a ela
 *               (no dia da assembleia ainda dá para assinar).
 * 'encerrado' → encerrado à força, independentemente da data.
 * 'aberto'    → mantido aberto à força (útil se a assembleia for adiada).
 */
function fundadoresEncerrados(array $settings): bool
{
    $modo = (string)($settings['fundadores_modo'] ?? 'auto');

    if ($modo === 'encerrado') {
        return true;
    }
    if ($modo === 'aberto') {
        return false;
    }

    $data = trim((string)($settings['data_assembleia'] ?? ''));
    if ($data === '') {
        return false; // sem data cadastrada, nada encerra sozinho
    }

    return dataDeHoje() > $data;
}

/**
 * Explicação curta do estado atual, para exibir no painel.
 */
function descricaoEstadoFundadores(array $settings): string
{
    $modo = (string)($settings['fundadores_modo'] ?? 'auto');
    $data = trim((string)($settings['data_assembleia'] ?? ''));

    if ($modo === 'encerrado') {
        return 'Encerrado manualmente, independentemente da data.';
    }
    if ($modo === 'aberto') {
        return 'Mantido aberto manualmente, independentemente da data.';
    }
    if ($data === '') {
        return 'Seguindo a data da assembleia — mas nenhuma data foi cadastrada, '
             . 'então as inscrições de fundador continuam abertas.';
    }

    $formatada = dataBrasileira($data);

    return fundadoresEncerrados($settings)
        ? "Encerrado automaticamente: a assembleia de $formatada já passou."
        : "Aberto até o fim do dia da assembleia ($formatada).";
}

/**
 * Vínculos que o formulário público deve oferecer neste momento.
 * Depois da assembleia, "Fundador(a)" dá lugar a "Associado(a)".
 */
function vinculosDisponiveis(array $settings): array
{
    $primeiro = fundadoresEncerrados($settings) ? VINCULO_ASSOCIADO : VINCULO_FUNDADOR;

    return [$primeiro, VINCULO_DIRETORIA, VINCULO_CONSELHO];
}

/**
 * Todos os vínculos possíveis — usado na edição de fichas pelo painel, onde o
 * administrador precisa poder corrigir qualquer registro.
 */
function todosOsVinculos(): array
{
    return [VINCULO_FUNDADOR, VINCULO_ASSOCIADO, VINCULO_DIRETORIA, VINCULO_CONSELHO];
}

/**
 * Quem participou da fundação da associação. Como o vínculo é de escolha única,
 * quem se marcou como Diretoria ou Conselho Fiscal antes da assembleia também é
 * membro fundador — só "Associado(a)" fica de fora.
 */
function contaComoFundador(?string $vinculo): bool
{
    return trim((string)$vinculo) !== VINCULO_ASSOCIADO;
}

/**
 * Título do formulário, que muda depois que a fundação se encerra.
 */
function tituloFormulario(array $settings): string
{
    return fundadoresEncerrados($settings)
        ? 'Ficha de Qualificação de Associados'
        : 'Ficha de Qualificação dos Membros Fundadores';
}

/**
 * Converte AAAA-MM-DD em DD/MM/AAAA para exibição.
 */
function dataBrasileira(string $data): string
{
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $data);

    return $d ? $d->format('d/m/Y') : $data;
}
