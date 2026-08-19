<?php
/**
 * Conexão PDO com SQLite + criação automática das tabelas.
 */

require_once __DIR__ . '/inscricao.php';

/**
 * Cria as colunas que ainda não existirem na tabela e devolve os nomes das que
 * foram efetivamente criadas. É assim que o sistema evolui o banco de bancos
 * antigos sem precisar de migração manual.
 */
function garantirColunas(PDO $pdo, string $tabela, array $colunas): array
{
    $existentes = [];
    foreach ($pdo->query("PRAGMA table_info($tabela)")->fetchAll() as $col) {
        $existentes[$col['name']] = true;
    }

    $criadas = [];
    foreach ($colunas as $nome => $definicao) {
        if (!isset($existentes[$nome])) {
            $pdo->exec("ALTER TABLE $tabela ADD COLUMN $nome $definicao");
            $criadas[] = $nome;
        }
    }

    return $criadas;
}

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

        // Compatibilidade com bancos criados por versões anteriores do sistema
        $criadas = garantirColunas($pdo, 'members', [
            'cin'     => 'TEXT',
            'vinculo' => 'TEXT',
        ]);

        // Todas as fichas que existiam antes do campo 'vínculo' foram preenchidas
        // quando o formulário só aceitava membros fundadores — o backfill roda uma
        // única vez, no exato momento em que a coluna é criada.
        if (in_array('vinculo', $criadas, true)) {
            $stmt = $pdo->prepare("UPDATE members SET vinculo = ? WHERE vinculo IS NULL OR vinculo = ''");
            $stmt->execute([VINCULO_FUNDADOR]);
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

        // Controles do formulário público (ver includes/inscricao.php):
        //  - formulario_aberto:    chave geral liga/desliga do formulário
        //  - mensagem_fechado:     texto exibido a quem abrir o link com ele fechado
        //  - data_assembleia:      AAAA-MM-DD; passada a data, encerra os fundadores
        //  - fundadores_modo:      'auto' segue a data | 'aberto' e 'encerrado' forçam
        garantirColunas($pdo, 'settings', [
            'formulario_aberto' => "INTEGER NOT NULL DEFAULT 1",
            'mensagem_fechado'  => "TEXT",
            'data_assembleia'   => "TEXT",
            'fundadores_modo'   => "TEXT NOT NULL DEFAULT 'auto'",
        ]);

        // Registro de tentativas de login, usado para bloquear ataques de
        // força bruta contra o backoffice (ver includes/auth.php).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                email         TEXT NOT NULL,
                ip            TEXT NOT NULL,
                sucesso       INTEGER NOT NULL DEFAULT 0,
                tentado_em    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_email ON login_attempts (email, tentado_em)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_ip    ON login_attempts (ip, tentado_em)");
    }

    return $pdo;
}

/**
 * Retorna as configurações atuais do sistema (meta, estado do formulário,
 * data da assembleia etc.), já com valores padrão para bancos recém-criados.
 */
function getSettings(): array
{
    $padrao = [
        'id'                => 1,
        'meta_associados'   => 0,
        'formulario_aberto' => 1,
        'mensagem_fechado'  => '',
        'data_assembleia'   => '',
        'fundadores_modo'   => 'auto',
    ];

    $pdo = getDB();
    $row = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();

    return array_merge($padrao, $row ?: []);
}

/**
 * Grava as configurações do formulário público e da assembleia.
 * Só as chaves reconhecidas são aceitas — nada vem direto do $_POST.
 */
function updateSettings(array $valores): void
{
    $permitidas = ['meta_associados', 'formulario_aberto', 'mensagem_fechado',
                   'data_assembleia', 'fundadores_modo'];

    $campos = [];
    $params = [];
    foreach ($permitidas as $chave) {
        if (array_key_exists($chave, $valores)) {
            $campos[] = "$chave = ?";
            $params[] = $valores[$chave];
        }
    }

    if (!$campos) {
        return;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare('UPDATE settings SET ' . implode(', ', $campos) . ' WHERE id = 1');
    $stmt->execute($params);
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
