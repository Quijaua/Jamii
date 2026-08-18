<?php
/**
 * Cliente SMTP mínimo, sem dependências externas (usa apenas sockets nativos do PHP),
 * com suporte a STARTTLS, SSL implícito e autenticação AUTH LOGIN.
 *
 * Uso:
 *   $mailer = new SmtpMailer($config['smtp']);
 *   $mailer->send(
 *       'destino@exemplo.com',           // pode ser "a@x.com, b@x.com"
 *       'remetente@seudominio.com.br',
 *       'Nome do remetente',
 *       'Assunto',
 *       "Corpo do e-mail em texto puro",
 *       'responder@exemplo.com'          // Reply-To (opcional)
 *   );
 *
 * Lança RuntimeException com uma mensagem descritiva em caso de falha.
 */
class SmtpMailer
{
    private string $host;
    private int $port;
    private string $encryption; // 'tls', 'ssl' ou ''
    private string $username;
    private string $password;
    private int $timeout;

    /** @var resource|null */
    private $socket;

    /** @var string[] log da conversa SMTP, útil para depuração */
    private array $log = [];

    public function __construct(array $smtpConfig)
    {
        $this->host       = $smtpConfig['host'] ?? '';
        $this->port       = (int)($smtpConfig['port'] ?? 587);
        $this->encryption = strtolower($smtpConfig['encryption'] ?? 'tls');
        $this->username   = $smtpConfig['username'] ?? '';
        $this->password   = $smtpConfig['password'] ?? '';
        $this->timeout    = (int)($smtpConfig['timeout'] ?? 10);
    }

    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Envia um e-mail em texto puro.
     *
     * @param string $to        Um ou mais e-mails separados por vírgula
     * @param string $fromEmail
     * @param string $fromName
     * @param string $subject
     * @param string $body
     * @param string|null $replyTo
     * @throws RuntimeException
     */
    public function send(
        string $to,
        string $fromEmail,
        string $fromName,
        string $subject,
        string $body,
        ?string $replyTo = null
    ): void {
        if ($this->host === '') {
            throw new RuntimeException('Host SMTP não configurado.');
        }

        $this->connect();

        try {
            $this->expect($this->readResponse(), [220], 'Banda de boas-vindas do servidor SMTP');

            $hostname = $_SERVER['SERVER_NAME'] ?? 'localhost';
            $this->command("EHLO {$hostname}", [250]);

            if ($this->encryption === 'tls') {
                $this->command('STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Não foi possível estabelecer conexão TLS com o servidor SMTP.');
                }
                // Após o STARTTLS, é necessário enviar o EHLO novamente
                $this->command("EHLO {$hostname}", [250]);
            }

            if ($this->username !== '') {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($this->username), [334]);
                $this->command(base64_encode($this->password), [235]);
            }

            $this->command('MAIL FROM:<' . $this->limpar($fromEmail) . '>', [250]);

            $destinatarios = array_filter(array_map('trim', explode(',', $to)));
            foreach ($destinatarios as $destinatario) {
                $this->command('RCPT TO:<' . $this->limpar($destinatario) . '>', [250, 251]);
            }

            $this->command('DATA', [354]);

            $cabecalhos = [];
            $cabecalhos[] = 'From: ' . $this->codificarCabecalho($fromName) . ' <' . $fromEmail . '>';
            $cabecalhos[] = 'To: ' . $to;
            if ($replyTo) {
                $cabecalhos[] = 'Reply-To: ' . $replyTo;
            }
            $cabecalhos[] = 'Subject: ' . $this->codificarCabecalho($subject);
            $cabecalhos[] = 'MIME-Version: 1.0';
            $cabecalhos[] = 'Content-Type: text/plain; charset=UTF-8';
            $cabecalhos[] = 'Date: ' . date('r');

            // Escapa linhas que começam com "." conforme o protocolo SMTP exige
            $corpoEscapado = preg_replace('/^\./m', '..', $body);

            $mensagem = implode("\r\n", $cabecalhos) . "\r\n\r\n" . $corpoEscapado . "\r\n.";
            $this->command($mensagem, [250]);

            $this->command('QUIT', [221], false);
        } finally {
            $this->disconnect();
        }
    }

    private function connect(): void
    {
        $transporte = $this->encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $enderecoConexao = $transporte . $this->host . ':' . $this->port;

        $contexto = stream_context_create();
        $this->socket = @stream_socket_client(
            $enderecoConexao,
            $codigoErro,
            $mensagemErro,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $contexto
        );

        if (!$this->socket) {
            throw new RuntimeException(
                "Não foi possível conectar ao servidor SMTP {$this->host}:{$this->port} — {$mensagemErro} (código {$codigoErro})"
            );
        }

        stream_set_timeout($this->socket, $this->timeout);
    }

    private function disconnect(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    private function command(string $comando, array $codigosEsperados, bool $esperarResposta = true): void
    {
        $this->log[] = '>>> ' . preg_replace('/\r\n.*/s', ' [dados omitidos]', $comando);
        fwrite($this->socket, $comando . "\r\n");

        if (!$esperarResposta) {
            return;
        }

        $resposta = $this->readResponse();
        $this->expect($resposta, $codigosEsperados, $comando);
    }

    private function readResponse(): string
    {
        $resposta = '';
        while (($linha = fgets($this->socket, 515)) !== false) {
            $resposta .= $linha;
            // Linhas de continuação usam "-" na quarta posição (ex.: "250-"); a última usa espaço (ex.: "250 ")
            if (isset($linha[3]) && $linha[3] === ' ') {
                break;
            }
        }
        $this->log[] = '<<< ' . trim($resposta);
        return $resposta;
    }

    private function expect(string $resposta, array $codigosEsperados, string $contexto): void
    {
        $codigo = (int)substr($resposta, 0, 3);
        if (!in_array($codigo, $codigosEsperados, true)) {
            throw new RuntimeException(
                "Resposta inesperada do servidor SMTP ao executar [{$contexto}]: " . trim($resposta)
            );
        }
    }

    private function limpar(string $email): string
    {
        return str_replace(["\r", "\n", '<', '>'], '', trim($email));
    }

    private function codificarCabecalho(string $texto): string
    {
        return '=?UTF-8?B?' . base64_encode($texto) . '?=';
    }
}
