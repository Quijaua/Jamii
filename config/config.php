<?php
/**
 * Configuração central do sistema.
 * Ajuste os valores abaixo conforme a sua associação.
 */
return [
    // Caminho do banco SQLite (será criado automaticamente na primeira execução)
    'db_path' => __DIR__ . '/../database.sqlite',

    // Nome da aplicação (exibido nos títulos das páginas)
    'app_name' => 'Associação — Ficha de Qualificação dos Membros Fundadores',

    // Configurações de e-mail (notificação enviada a cada nova ficha preenchida)
    'mail' => [
        // E-mail(s) que receberão a notificação de nova ficha. Separe múltiplos com vírgula.
        'to'      => 'seuemail@exemplo.net.br',
        // E-mail remetente (deve, idealmente, ser um domínio que você controla)
        'from'    => 'seuemail@exemplo.net.br',
        'from_name' => 'Sistema de Fichas — Associação',
        'subject' => 'Nova Ficha de Qualificação — Membro Fundador',
    ],

    // Envio via SMTP autenticado (recomendado). Se 'enabled' for false, o sistema
    // usa a função mail() nativa do PHP, que em muitos servidores cai em spam
    // ou nem chega a ser enviada.
    'smtp' => [
        'enabled'    => true, // altere para true depois de preencher os dados abaixo
        'host'       => 'mail.exemplo.net.br',
        'port'       => 465,   // 587 = STARTTLS (mais comum) | 465 = SSL implícito | 25 = sem criptografia
        'encryption' => '', // 'tls', 'ssl' ou '' (vazio = sem criptografia)
        'username'   => 'seuemail@exemplo.net.br',
        'password'   => 'suasenha',
        'timeout'    => 10,
    ],

    // Texto fixo do evento (aparece no topo do formulário)
    'evento' => 'Assembleia de Fundação – 23/07',

    // Proteção do login do backoffice contra tentativas em massa (força bruta).
    // Se algum valor for omitido, o sistema usa o padrão indicado no comentário.
    'seguranca' => [
        // Falhas seguidas permitidas para o MESMO e-mail antes de bloquear (padrão: 5)
        'max_tentativas_email' => 5,
        // Falhas seguidas permitidas para o MESMO IP, somando todos os e-mails (padrão: 20).
        // Mantenha folgado: atrás de proxy reverso vários acessos podem chegar com o mesmo IP.
        'max_tentativas_ip'    => 20,
        // Janela de tempo considerada, em minutos (padrão: 15)
        'janela_minutos'       => 15,
    ],
];
