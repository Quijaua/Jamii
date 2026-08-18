<?php
/**
 * Script de linha de comando para criar (ou redefinir a senha de) um administrador.
 *
 * Uso:
 *   php setup_admin.php email@exemplo.com "SenhaForte123"
 *
 * IMPORTANTE: remova ou proteja este arquivo após o uso em produção,
 * pois ele permite criar/alterar credenciais de acesso ao backoffice.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Este script deve ser executado via linha de comando (CLI), por segurança.');
}

require_once __DIR__ . '/includes/db.php';

if ($argc < 3) {
    echo "Uso: php setup_admin.php email@exemplo.com \"SenhaForte123\"\n";
    exit(1);
}

$email = trim($argv[1]);
$senha = $argv[2];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "E-mail inválido.\n";
    exit(1);
}

if (strlen($senha) < 6) {
    echo "A senha deve ter pelo menos 6 caracteres.\n";
    exit(1);
}

$pdo = getDB();
$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {
    $update = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE email = ?');
    $update->execute([$hash, $email]);
    echo "Senha atualizada com sucesso para o administrador: $email\n";
} else {
    $insert = $pdo->prepare('INSERT INTO admins (email, password_hash) VALUES (?, ?)');
    $insert->execute([$email, $hash]);
    echo "Administrador criado com sucesso: $email\n";
}
