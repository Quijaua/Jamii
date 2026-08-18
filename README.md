# Jamii
Sistema de gestão de associação

## Ficha de Qualificação dos Membros Fundadores

Sistema simples em PHP para coleta de dados de associados via formulário público,
com gravação em SQLite, notificação por e-mail e um backoffice administrativo
com login e exportação para XLSX.

## O que foi usado

- **PHP puro** (sem framework pesado), compatível com qualquer hospedagem que
  ofereça PHP 7.4+ com as extensões `pdo_sqlite` e `zip` (ambas muito comuns).
- **Materialize CSS** (via CDN) para o visual em Material Design. *Observação:
  o "Material UI" (MUI) verdadeiro é uma biblioteca de componentes React e não
  se aplica a um backend PHP tradicional — o Materialize CSS entrega a mesma
  estética (cores, campos flutuantes, botões, ícones) sem exigir build tools.*
- **SQLite** como banco de dados (arquivo único `database.sqlite`, sem
  necessidade de servidor de banco de dados separado).
- Gerador de **XLSX próprio**, sem dependências do Composer (usa apenas
  `ZipArchive`, nativo do PHP), para funcionar em qualquer hospedagem simples.
- Integração com o **ViaCEP** (API pública e gratuita) para autopreenchimento
  de endereço a partir do CEP.

## Novidades desta versão

- Campo **CIN** (Carteira de Identidade Nacional, o novo documento que está
  substituindo o RG no Brasil) — opcional, já que nem todos os associados
  terão essa carteira ainda.
- **CEP no início** do bloco de endereço, com **preenchimento automático**
  de logradouro, bairro, cidade e estado via ViaCEP assim que a pessoa
  digita o CEP (ela pode revisar/corrigir antes de enviar).
- **Página de Configurações** no backoffice (`admin/config.php`), onde você
  define a meta de associados fundadores esperada e acompanha visualmente
  (barra de progresso) quantos já preencheram e quantos já assinaram a
  declaração — com uma mensagem de destaque quando a meta é atingida.



```
associacao/
├── config/config.php        <- configurações (e-mail, nome do app etc.)
├── includes/
│   ├── db.php                <- conexão SQLite + criação de tabelas
│   ├── auth.php               <- autenticação do backoffice (sessão)
│   └── XLSXWriter.php          <- gerador de planilhas .xlsx
├── assets/style.css           <- ajustes visuais complementares
├── admin/
│   ├── login.php               <- tela de login
│   ├── logout.php
│   ├── dashboard.php           <- listagem de membros + busca + exportar
│   ├── view.php                <- editar/excluir um membro
│   └── export.php              <- gera e baixa o .xlsx
├── index.php                  <- formulário público
├── submit.php                 <- processa o formulário (grava + envia e-mail)
├── obrigado.php               <- página de confirmação
└── setup_admin.php            <- script CLI para criar/redefinir administrador
```

## Instalação

1. **Envie os arquivos** para o seu servidor (via FTP, git, painel de
   hospedagem etc.), mantendo a estrutura de pastas.

2. **Garanta que a pasta do projeto tenha permissão de escrita**, pois o
   arquivo `database.sqlite` é criado automaticamente na primeira execução:
   ```bash
   chmod 755 /caminho/do/projeto
   ```
   O PHP precisa conseguir criar o arquivo `database.sqlite` dentro dessa pasta.

3. **Configure o envio de e-mail** em `config/config.php`. Há duas formas:

   **a) SMTP autenticado (recomendado)** — mais confiável, evita cair em spam
   e funciona mesmo em servidores sem um agente de e-mail (MTA) configurado:
   ```php
   'smtp' => [
       'enabled'    => true,
       'host'       => 'smtp.seuservidor.com.br',
       'port'       => 587,     // 587 = STARTTLS | 465 = SSL implícito | 25 = sem criptografia
       'encryption' => 'tls',   // 'tls', 'ssl' ou '' (vazio)
       'username'   => 'seuusuario@suaassociacao.org.br',
       'password'   => 'sua-senha-smtp',
       'timeout'    => 10,
   ],
   ```
   Essas informações costumam estar no painel do seu provedor de e-mail
   (Locaweb, Zoho, Gmail com senha de app, ou o próprio servidor onde você
   hospeda o site — no aaPanel, por exemplo, geralmente é `smtp.` seguido do
   domínio, porta `587` com STARTTLS).

   **b) mail() nativo do PHP** (padrão, `smtp.enabled = false`) — mais simples,
   mas em muitos servidores cai em spam ou não é entregue, especialmente se
   não houver registros SPF/DKIM configurados para o domínio remetente.

   Depois de configurar, acesse o backoffice → **Configurações** →
   **Testar envio de e-mail** para confirmar que está tudo funcionando. Se
   falhar, a própria tela mostra o motivo (e, no caso do SMTP, o log da
   conversa com o servidor, útil para diagnosticar host/porta/senha errados).

4. **Crie o primeiro usuário administrador** via linha de comando (SSH):
   ```bash
   cd /caminho/do/projeto
   php setup_admin.php seuemail@suaassociacao.org.br "SenhaForte123"
   ```
   Esse comando também pode ser usado depois para redefinir a senha de um
   administrador existente. Depois do primeiro uso em produção, considere
   restringir o acesso a este arquivo (ou removê-lo) por segurança.

5. **Acesse o formulário público** em:
   ```
   https://seudominio.com.br/index.php
   ```
   Envie esse link para os membros fundadores preencherem.

6. **Acesse o backoffice** em:
   ```
   https://seudominio.com.br/admin/login.php
   ```
   Faça login com o e-mail/senha criados no passo 4. No painel você pode:
   - Buscar membros por nome, CPF ou e-mail
   - Visualizar e editar qualquer ficha
   - Excluir uma ficha
   - Baixar todos os dados em uma planilha `.xlsx` (botão "Baixar XLSX")
   - Configurar a meta de associados e testar o envio de e-mail (menu Configurações)

## Segurança — recomendações antes de colocar em produção

- Sirva o site com **HTTPS** (essencial, já que dados sensíveis como CPF e RG
  trafegam pelo formulário).
- Depois de criar o(s) administrador(es), considere **restringir o acesso**
  ao `setup_admin.php` (por exemplo, movendo-o para fora da pasta pública, ou
  bloqueando-o via `.htaccess`/regra do Nginx), já que ele permite criar
  contas administrativas.
- Garanta que o arquivo `database.sqlite` **não fique acessível publicamente**
  via URL. Isso já é resolvido no Apache com o `.htaccess` incluído no pacote,
  mas se você usa Nginx, adicione uma regra bloqueando acesso a `*.sqlite`.
- Como os dados incluem CPF, RG e endereço, trate-os conforme a **LGPD**:
  minimize quem tem acesso ao backoffice e apague os dados quando não forem
  mais necessários para o registro da associação.

## Redirecionamento de /admin para o login

Ao acessar `https://seudominio.com.br/admin` (ou `/admin/`), a pessoa é
redirecionada automaticamente para `admin/login.php`. Isso é feito de duas
formas complementares, já incluídas no pacote:

1. **`.htaccess` da raiz** — usa `mod_rewrite` para redirecionar `/admin` e
   `/admin/` para `/admin/login.php` (redirecionamento 302, visível na URL).
2. **`admin/.htaccess`** — define `login.php` como página padrão da pasta,
   funcionando mesmo em servidores sem `mod_rewrite` habilitado.

**Pré-requisito**: o `mod_rewrite` precisa estar habilitado no Apache e o
`.htaccess` precisa estar autorizado a sobrescrever configurações
(`AllowOverride All` no VirtualHost). Isso já vem habilitado por padrão na
maioria das hospedagens compartilhadas e em painéis como aaPanel/cPanel. Se
o redirecionamento não funcionar, o item 2 acima (`admin/.htaccess`) garante
que pelo menos o conteúdo de `login.php` seja exibido ao acessar `/admin/`,
mesmo sem `mod_rewrite`.

Se o seu servidor usa **Nginx** em vez de Apache, o `.htaccess` não tem
efeito (Nginx não usa esse mecanismo). Incluí um modelo pronto em
[`nginx-associacao.conf.example`](nginx-associacao.conf.example), com as
mesmas três regras (redirecionar `/admin`, bloquear o `.sqlite` e bloquear o
`config.php`), além do bloco padrão de processamento de PHP via PHP-FPM.
Copie o conteúdo relevante para o bloco `server { }` do seu site (no
aaPanel, isso fica no editor de configuração do Nginx do site) e ajuste:
- `server_name` e `root` para o seu domínio e caminho real do projeto;
- o caminho do socket do PHP-FPM (`fastcgi_pass`), que varia conforme a
  versão do PHP instalada — no aaPanel costuma aparecer algo como
  `unix:/tmp/php-cgi-83.sock`.

## Solução de problemas

### Erro: "attempt to write a readonly database"

Esse erro acontece quando o processo do PHP (o usuário do Apache/Nginx, geralmente
`www-data`, `apache` ou o usuário do seu painel como `aapanel`/`www`) não tem
permissão de escrita **na pasta** onde fica o `database.sqlite` — não basta o
arquivo ter permissão, o SQLite também precisa criar arquivos temporários
(`-journal`, `-wal`, `-shm`) na mesma pasta durante a gravação.

Como resolver, dependendo do seu ambiente:

- **Linux / hospedagem com aaPanel/cPanel (produção)**:
  ```bash
  # Descubra o usuário do PHP-FPM/Apache (ex.: www, www-data, apache)
  chown -R www:www /caminho/do/projeto
  chmod -R 755 /caminho/do/projeto
  ```
  Se o arquivo `database.sqlite` já existir e tiver sido criado por outro
  usuário (ex.: você mesmo via SSH), rode também:
  ```bash
  chown www:www /caminho/do/projeto/database.sqlite
  chmod 664 /caminho/do/projeto/database.sqlite
  ```

- **XAMPP/WAMP no Windows (ambiente local, como o `localhost` do seu teste)**:
  Geralmente é o atributo "somente leitura" da pasta, ou o projeto estar em um
  local sem permissão de escrita (ex.: dentro de `Arquivos de Programas`).
  Mova o projeto para uma pasta como `C:\xampp\htdocs\associacao` (fora de
  áreas protegidas do Windows), clique com o botão direito na pasta →
  Propriedades → desmarque "Somente leitura" → aplicar para todas as subpastas
  e arquivos.

- **Linux local/Docker**: confirme que o usuário que roda o `php -S` ou o
  container tem permissão de escrita na pasta do projeto, e que o disco não
  está montado como somente leitura.

- Depois de corrigir a permissão, apague um eventual `database.sqlite`
  corrompido/vazio criado durante as tentativas com erro e deixe o sistema
  recriá-lo automaticamente na próxima tentativa de envio.

### Não perder os dados preenchidos

O sistema já guarda temporariamente (na sessão do navegador) tudo o que a
pessoa digitou. Se faltar preencher algum campo obrigatório ou esquecer de
marcar a declaração, ao voltar para o formulário:
- os campos já preenchidos continuam com os valores digitados;
- os campos que faltaram ficam com sublinhado vermelho;
- aparece uma lista no topo do formulário dizendo exatamente o que falta.



- **Mudar a cor principal**: edite o `#1565c0` em `assets/style.css`.
- **Adicionar mais administradores**: rode `setup_admin.php` novamente com
  outro e-mail.
- **Alterar campos do formulário**: os campos ficam em `index.php` (visual),
  `submit.php` (validação/gravação) e a tabela `members` em `includes/db.php`
  (estrutura do banco) — as três partes precisam ser atualizadas juntas.

---

Qualquer ajuste que precisar (adicionar confirmação por e-mail ao próprio
membro, exportar em outros formatos, múltiplos níveis de permissão no
backoffice etc.), é só pedir.
