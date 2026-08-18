<?php
/**
 * Conexão PDO com SQLite + criação automática das tabelas.
 */

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/../config/config.php';

        $pdo = new PDO('sqlite:' . $config['db_path']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS members (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                nome_completo        TEXT NOT NULL,
                nacionalidade        TEXT,
                estado_civil         TEXT,
                profissao            TEXT,
                cpf                  TEXT,
                rg_numero            TEXT,
                rg_orgao             TEXT,
                cin                  TEXT,
                email                TEXT,
                telefone             TEXT,
                logradouro           TEXT,
                numero               TEXT,
                complemento          TEXT,
                bairro               TEXT,
                cidade               TEXT,
                estado               TEXT,
                cep                  TEXT,
                local_data           TEXT,
                declaracao_aceite    INTEGER DEFAULT 0,
                created_at           TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at           TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Compatibilidade com bancos criados antes da coluna 'cin' existir
        $colunas = $pdo->query("PRAGMA table_info(members)")->fetchAll();
        $temCin = false;
        foreach ($colunas as $col) {
            if ($col['name'] === 'cin') {
                $temCin = true;
                break;
            }
        }
        if (!$temCin) {
            $pdo->exec("ALTER TABLE members ADD COLUMN cin TEXT");
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                email          TEXT UNIQUE NOT NULL,
                password_hash  TEXT NOT NULL,
                created_at     TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id                INTEGER PRIMARY KEY CHECK (id = 1),
                meta_associados   INTEGER NOT NULL DEFAULT 0
            )
        ");
        $pdo->exec("INSERT OR IGNORE INTO settings (id, meta_associados) VALUES (1, 0)");
    }

    return $pdo;
}

/**
 * Retorna as configurações atuais (atualmente, apenas a meta de associados).
 */
function getSettings(): array
{
    $pdo = getDB();
    $row = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
    return $row ?: ['id' => 1, 'meta_associados' => 0];
}

/**
 * Atualiza a meta de associados configurada.
 */
function updateMetaAssociados(int $meta): void
{
    $pdo = getDB();
    $stmt = $pdo->prepare('UPDATE settings SET meta_associados = ? WHERE id = 1');
    $stmt->execute([$meta]);
}
