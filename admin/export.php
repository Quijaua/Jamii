<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/XLSXWriter.php';
requireLogin();

$pdo = getDB();
$membros = $pdo->query("SELECT * FROM members ORDER BY created_at DESC")->fetchAll();

$headers = [
    'Nome completo', 'Nacionalidade', 'Estado civil', 'Profissão', 'CPF', 'RG (número)', 'RG (órgão emissor)', 'CIN',
    'E-mail', 'Telefone', 'CEP', 'Logradouro', 'Número', 'Complemento', 'Bairro', 'Cidade', 'Estado',
    'Local e data', 'Declaração aceita', 'Recebido em',
];

$rows = [];
foreach ($membros as $m) {
    $rows[] = [
        $m['nome_completo'],
        $m['nacionalidade'],
        $m['estado_civil'],
        $m['profissao'],
        $m['cpf'],
        $m['rg_numero'],
        $m['rg_orgao'],
        $m['cin'] ?? '',
        $m['email'],
        $m['telefone'],
        $m['cep'],
        $m['logradouro'],
        $m['numero'],
        $m['complemento'],
        $m['bairro'],
        $m['cidade'],
        $m['estado'],
        $m['local_data'],
        $m['declaracao_aceite'] ? 'Sim' : 'Não',
        $m['created_at'],
    ];
}

$tmpFile = tempnam(sys_get_temp_dir(), 'membros_') . '.xlsx';
$writer = new XLSXWriter($headers, $rows);
$writer->save($tmpFile);

$filename = 'membros_fundadores_' . date('Y-m-d_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');

readfile($tmpFile);
unlink($tmpFile);
exit;
