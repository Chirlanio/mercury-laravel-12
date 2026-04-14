# Helpdesk — Entrada de e-mails via IMAP

| | |
|---|---|
| **Status atual** | 🟡 **Implementado, aguardando ativação em produção** |
| **Implementado em** | 2026-04-14 |
| **Depende de** | Deploy do Mercury v2 (Laravel) na VPS + contas de e-mail criadas na Hostinger |
| **Não depende de** | Nenhum serviço externo (sem Postmark, sem Mailgun, sem SendGrid) |
| **Provider escolhido** | IMAP polling direto contra Hostinger (decisão consciente — ver [contexto](#contexto-da-decisão)) |

> ⚠ **Este documento é a referência para o dia do deploy.** O código já está implementado, testado e mergeado. Nada precisa ser feito agora além de manter este documento atualizado. As ações descritas na seção [Checklist de ativação em produção](#checklist-de-ativação-em-produção) só devem ser executadas quando o Mercury v2 for para produção de verdade.

---

## Índice

1. [Contexto da decisão](#contexto-da-decisão)
2. [Arquitetura](#arquitetura)
3. [Componentes implementados](#componentes-implementados)
4. [Checklist de ativação em produção](#checklist-de-ativação-em-produção) ← **começar por aqui no dia do deploy**
5. [Configuração Hostinger (referência)](#configuração-hostinger-referência)
6. [Troubleshooting](#troubleshooting)
7. [Upgrade para queue worker real](#upgrade-para-queue-worker-real)
8. [Referência rápida de arquivos e comandos](#referência-rápida-de-arquivos-e-comandos)

---

## Contexto da decisão

A primeira implementação usou **Postmark Inbound** (webhook), que é o provider "padrão enterprise" para e-mail transacional. Ao detalhar o setup, descobrimos que o Grupo Meia Sola já tem contas de e-mail ativas na Hostinger (`ti@meiasola.com.br`, `rh@...`), e apontar o MX pro Postmark **quebraria os endereços existentes**. As alternativas avaliadas foram:

| Opção | Conclusão |
|---|---|
| **A — Subdomínio dedicado** (`helpdesk.meiasola.com.br` → Postmark) | Funciona, mas exige que os usuários aprendam endereços novos (`ti@helpdesk.` em vez de `ti@`). Rejeitada por atrito de adoção. |
| **B — IMAP poll** (mantém contas atuais, Mercury busca via IMAP a cada minuto) | **Escolhida.** Zero mudança pros usuários, zero mudança de DNS, zero custo contínuo, funciona sem depender de SaaS externo. Trade-off: lag médio de ~30s em vez de webhook instantâneo — aceitável pra helpdesk. |
| **C — Forward para Postmark** | Rejeitada. Quebra a identificação do solicitante (o `From` vira "encaminhado por ti@"), inviabilizando o match com `users.email`. |

O caminho Postmark **não foi descartado** — o `EmailIntakeDriver` é agnóstico de provider. O código do Postmark (controller, job, rota, env var) fica mantido e pode ser reativado no futuro sem reescrever o driver se um dia o cenário mudar.

---

## Arquitetura

```
┌───────────────────┐      ┌──────────────────┐      ┌─────────────────────┐
│  Caixa Hostinger  │      │  Mercury         │      │  Helpdesk           │
│  ti@empresa...    │◀────▶│  helpdesk:       │─────▶│  hd_tickets         │
│  rh@empresa...    │ IMAP │  imap-fetch      │      │  hd_interactions    │
│  (outras)         │      │  (cron: 1 min)   │      │  hd_attachments     │
└───────────────────┘      └──────────────────┘      └─────────────────────┘
```

Fluxo de uma mensagem:

1. `helpdesk:imap-fetch` roda no scheduler a cada 1 minuto
2. Para cada tenant, itera as contas IMAP cadastradas em `hd_channels.config.imap_accounts`
3. Conecta na caixa e lista mensagens **não lidas** (`UNSEEN`)
4. Para cada mensagem, verifica dedup por `Message-ID` contra `hd_interactions.external_id` — se já processada, pula (safety net contra falhas de move)
5. Normaliza headers/corpo/anexos via `ImapMessageNormalizer`
6. Chama `HelpdeskIntakeService::handle('email', $payload)` — o `EmailIntakeDriver` decide entre:
   - **Criar ticket novo** (assunto sem `[#ID]`, nenhum reply match)
   - **Anexar como interação** num ticket existente (detecta replies via `[#ID]` no assunto ou `In-Reply-To` header)
   - **Criar ticket novo** mesmo sendo reply, se o ticket original está em status terminal (RESOLVED/CLOSED/CANCELLED)
7. Move a mensagem pra `INBOX.Processados` (criada automaticamente se não existir)
8. Se o move falha, marca como `\Seen` — o dedup por `Message-ID` garante idempotência

Decisões de design:

- **Match de solicitante**: `from_email` é comparado contra `users.email`. Se bate → vira o `requester_id` do ticket. Se não → fallback para o bot user `email-bot@system.local` (mesma filosofia do driver WhatsApp).
- **Departamento**: cada conta IMAP é mapeada explicitamente para um departamento no admin. Sem adivinhação.
- **Anexos**: cap padrão de 10 MB por arquivo (configurável por canal). Acima disso, anexo ignorado + nota interna no ticket.
- **Senhas**: criptografadas em repouso via `Crypt::encryptString()`. Nunca trafegam para o frontend. O admin só mostra um flag `has_password`.

---

## Componentes implementados

### Backend

| Arquivo | Função |
|---|---|
| `app/Services/Helpdesk/ImapAccountService.php` | CRUD de contas em `hd_channels.config.imap_accounts`, criptografia, teste de conexão |
| `app/Services/Helpdesk/ImapMessageNormalizer.php` | Adapter Webklex Message → driver payload. Separado em `extractRaw()` (integração) + `normalizeFromRaw()` (pura, testada) |
| `app/Console/Commands/HelpdeskImapFetchCommand.php` | Orquestrador: itera tenants → contas → mensagens, com dedup + move/seen |
| `app/Services/Intake/Drivers/EmailIntakeDriver.php` | Driver agnóstico de provider (Postmark e IMAP alimentam o mesmo) |
| `app/Http/Controllers/HdEmailAccountsController.php` | Admin UI backend (permissão `MANAGE_HD_DEPARTMENTS`) |
| `routes/console.php` | `Schedule::command('helpdesk:imap-fetch')->everyMinute()->withoutOverlapping()` |
| `routes/tenant-routes.php` | 5 rotas sob `/helpdesk/admin/email-accounts` |

### Frontend

| Arquivo | Função |
|---|---|
| `resources/js/Pages/Helpdesk/EmailAccounts.jsx` | Admin UI: DataTable + StandardModal + botão "Testar" + DeleteConfirmModal |
| `resources/js/Pages/Helpdesk/Index.jsx` | Item "Contas de E-mail" no menu admin do helpdesk |

### Migrations

| Arquivo | Efeito |
|---|---|
| `database/migrations/tenant/2026_04_17_100001_seed_hd_channel_email.php` | Insere a linha do canal email em `hd_channels` (idempotente) |

### Testes (todos verdes)

| Arquivo | Cobertura |
|---|---|
| `tests/Feature/Helpdesk/ImapAccountServiceTest.php` | 9 testes — CRUD, criptografia, teste de conexão |
| `tests/Feature/Helpdesk/ImapMessageNormalizerTest.php` | 10 testes — transformação pura de dict → payload |
| `tests/Feature/Helpdesk/HelpdeskImapFetchCommandTest.php` | 2 testes — smoke do comando |
| `tests/Feature/Helpdesk/EmailIntakeTest.php` | 17 testes — driver (compartilhado com o path Postmark) |

### Dependências

```json
"webklex/php-imap": "^6.2"
```

Pacote puro PHP — **não** requer `ext-imap` (que foi movida pra PECL no PHP 8.4). Já incluído no `composer.json`.

---

## Checklist de ativação em produção

> **Quando executar:** no dia que o Mercury v2 entrar em produção na VPS, depois de todos os outros módulos pendentes estarem prontos. Execute os passos **em ordem**, verificando cada um antes de ir pro próximo.

### Pré-requisitos (confirmar antes de começar)

- [ ] Mercury v2 (Laravel) deployado e acessível na VPS
- [ ] Banco MySQL da produção com as tenant databases criadas
- [ ] Pelo menos 1 tenant configurado e funcional
- [ ] Pelo menos 1 departamento criado no helpdesk por tenant
- [ ] Contas de e-mail existentes na Hostinger e com senha conhecida
- [ ] Acesso SSH à VPS com permissão de editar o crontab do usuário do Mercury

### 1. Instalar dependências PHP (se ainda não estiverem na VPS)

O `webklex/php-imap` já está declarado em `composer.json`. Um `composer install --no-dev` normal na VPS já pega.

```bash
cd /caminho/para/mercury
composer install --no-dev --optimize-autoloader
```

**Verificar:**

```bash
composer show webklex/php-imap
# deve mostrar a versão ^6.2 instalada
```

### 2. Rodar as migrations

```bash
php artisan tenants:migrate
```

Isso aplica a migration `2026_04_17_100001_seed_hd_channel_email` em cada tenant, inserindo a linha do canal email em `hd_channels`. É idempotente — pode rodar múltiplas vezes sem duplicar.

**Verificar em cada tenant:**

```bash
php artisan tinker --execute="\
    App\Models\Tenant::all()->each(fn(\$t) => \$t->run(fn() => \
        print(\$t->id . ': ' . (App\Models\HdChannel::findBySlug('email') ? 'ok' : 'MISSING') . PHP_EOL)\
    ));\
"
```

Todas as linhas devem imprimir `tenant_id: ok`.

### 3. Configurar `QUEUE_CONNECTION`

Vários componentes do helpdesk disparam jobs (AI classification, notifications, broadcasts). Se **não houver queue worker** rodando na VPS:

```env
# .env na VPS
QUEUE_CONNECTION=sync
```

Com `sync`, todos os jobs rodam inline no processo que os despachou. Trade-off aceitável enquanto o volume é baixo. Quando chegar na hora de escalar, veja [Upgrade para queue worker real](#upgrade-para-queue-worker-real).

Se já houver um queue worker configurado via supervisor/systemd, deixe `QUEUE_CONNECTION=database` (ou `redis`) e **não** precisa mexer aqui — pula pro próximo passo.

### 4. Adicionar o cron do Laravel na VPS

O scheduler do Laravel precisa rodar a cada minuto. Edite o crontab do usuário do Mercury (normalmente `www-data` ou o usuário do deploy):

```bash
# Conectar via SSH
ssh usuario@vps-mercury.com.br

# Editar crontab
crontab -e

# Adicionar (substitua o caminho real):
* * * * * cd /var/www/mercury && php artisan schedule:run >> /dev/null 2>&1
```

**Verificar que o cron está rodando:**

```bash
sudo systemctl status cron      # deve estar "active (running)"
php artisan schedule:list       # lista todos os comandos agendados
```

O `schedule:list` deve incluir:

```
* * * * *  php artisan helpdesk:imap-fetch
```

### 5. Cadastrar as contas IMAP no admin

Pra cada caixa que o helpdesk vai monitorar, acesse:

**`https://[seu-mercury]/helpdesk/admin/email-accounts`**

Permissão necessária: `MANAGE_HD_DEPARTMENTS` (Admin ou SuperAdmin do tenant).

Clique em **Nova conta** e preencha com os valores da [seção Hostinger](#configuração-hostinger-referência) abaixo.

**Antes de sair**, clique em **Testar** na listagem — o botão conecta de verdade e mostra sucesso/falha inline. Não prossiga sem ver o ícone verde.

### 6. Enviar e-mail de teste

Mande um e-mail de qualquer outra conta (Gmail pessoal serve) para uma das caixas cadastradas. Aguarde até 1 minuto.

**Resultados esperados:**

1. Em `/helpdesk` aparece um ticket novo no departamento configurado
2. O `from_email` é o remetente; se o remetente bate com algum `users.email`, o ticket fica com `requester_id` correto (senão, com o bot)
3. O e-mail original some da inbox da Hostinger e aparece em `INBOX.Processados` (visível via webmail)
4. O log `storage/logs/helpdesk-imap.log` mostra a linha `Concluído: 1 processados · 0 já conhecidos · 0 falhas`

### 7. Testar continuidade de thread

Responda o e-mail de notificação do ticket (ou mande um novo com `[#ID]` no assunto, onde `ID` é o número do ticket criado).

**Esperado:** a resposta vira uma **interação** no ticket existente, não um ticket novo.

### 8. (Opcional) Rodar dry-run pra validar múltiplas contas sem criar tickets

```bash
php artisan helpdesk:imap-fetch --dry-run
```

Lista tudo que seria processado sem efetivar. Útil se você quiser ver o que está na caixa antes de deixar o cron rodando automaticamente.

---

## Configuração Hostinger (referência)

### Valores do IMAP

| Campo | Valor |
|---|---|
| **Servidor** | `imap.hostinger.com` |
| **Porta** | `993` |
| **Criptografia** | `SSL` |
| **Usuário** | O próprio endereço de e-mail completo (`ti@seudominio.com.br`) |
| **Senha** | A senha da caixa, configurada no hPanel da Hostinger |
| **Pasta de processados** | `INBOX.Processados` (será criada automaticamente no primeiro run) |
| **Validar certificado** | ✅ Sim (recomendado) |

### Criar uma caixa de e-mail na Hostinger (quando precisar)

1. Acesse hPanel → **E-mails** → **Contas de E-mail**
2. Clique em **Criar conta de e-mail**
3. Preencha endereço e senha
4. **Importante**: anote a senha em local seguro — ela será pedida no admin do Mercury e criptografada no banco

### Liberar IP da VPS (se aparecer erro de conexão)

Algumas políticas da Hostinger bloqueiam IMAP de IPs desconhecidos. Se o teste de conexão no admin retornar erro de autenticação mesmo com senha correta:

1. Acesse hPanel → **Segurança** → **IPs permitidos**
2. Adicione o IP público da VPS

---

## Troubleshooting

### Comando roda mas nenhuma mensagem aparece

1. **Teste a conexão no admin.** Se falhar, verifique host/porta/senha. Hostinger às vezes bloqueia IPs desconhecidos — libere o IP da VPS no painel.
2. **A caixa tem mensagens não lidas?** O comando só pega `UNSEEN`. Se você abriu antes no webmail, já estão lidas.
3. **Rode manualmente com `--dry-run`:**
   ```bash
   php artisan helpdesk:imap-fetch --dry-run
   ```
   Conecta e lista o que encontraria, sem criar tickets.

### Tickets duplicados

Dedup é por `Message-ID`. Se um cliente responde um ticket antigo e o helpdesk abre um novo em vez de anexar:

- **Subject está intacto?** O parser procura `[#123]` ou `#123` no assunto. Se o MUA do cliente removeu o token, o fallback é `In-Reply-To` — confira se está preenchido.
- **Ticket original está terminal?** (`RESOLVED`, `CLOSED`, `CANCELLED`) Nesse caso, novo ticket é criado intencionalmente. Use "reabrir" no helpdesk se precisar continuar o mesmo fio.

### Anexo não aparece

- **Tamanho**: o limite padrão é 10 MB por arquivo, em `hd_channels.config.max_attachment_size_mb`. Acima disso → ignorado + nota interna no ticket.
- **Encoding**: se corrompido, pode ser base64. Log em `storage/logs/helpdesk-imap.log`.

### Pasta `Processados` não é criada

Alguns servidores usam delimitador diferente (`INBOX/Processados` em vez de `INBOX.Processados`). Se a criação falhar:

1. Crie manualmente via webmail
2. Ou ajuste "Pasta de processados" no admin pro caminho correto

Se o move falhar, o fallback marca como `\Seen` — funciona, mas deixa mensagens antigas na inbox.

### Rate limit da Hostinger

Se aparecerem erros de `LOGIN_FAILED` ou `CONNECTION_REFUSED` intermitentes, aumente o intervalo em `routes/console.php`:

```php
Schedule::command('helpdesk:imap-fetch')
    ->everyFiveMinutes()  // era everyMinute()
    ->withoutOverlapping();
```

### Ver logs detalhados

```bash
tail -f storage/logs/helpdesk-imap.log
```

O comando loga: cada tenant visitado, cada conta processada, contadores por execução, erros por conta (isolados — uma conta ruim não trava as outras).

---

## Upgrade para queue worker real

Quando o volume justificar (centenas de tickets/dia), troque `sync` por um worker dedicado.

### 1. Mude `QUEUE_CONNECTION` no `.env`

```env
QUEUE_CONNECTION=database
```

(ou `redis` se tiver um Redis disponível na VPS)

### 2. Rode a migration da tabela `jobs`

```bash
php artisan queue:table
php artisan migrate
```

### 3. Configure o supervisor

```ini
; /etc/supervisor/conf.d/mercury-worker.conf
[program:mercury-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mercury/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/mercury/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mercury-worker:*
```

A partir daí, `helpdesk:imap-fetch` termina em segundos (só despacha jobs) e o worker processa em paralelo.

---

## Referência rápida de arquivos e comandos

### Comandos de operação

```bash
# Rodar o fetch manualmente
php artisan helpdesk:imap-fetch

# Dry-run (lista sem criar tickets)
php artisan helpdesk:imap-fetch --dry-run

# Limitar a um tenant específico
php artisan helpdesk:imap-fetch --tenant=meia-sola

# Limitar a uma conta específica (UUID do admin)
php artisan helpdesk:imap-fetch --account=abc-123-uuid

# Ver logs do último run
tail -f storage/logs/helpdesk-imap.log

# Ver agendamentos registrados
php artisan schedule:list
```

### Onde mexer pra ajustar comportamento

| Quero mudar... | Arquivo |
|---|---|
| Intervalo de polling | `routes/console.php` (`->everyMinute()` → outro) |
| Campos de formulário de conta | `resources/js/Pages/Helpdesk/EmailAccounts.jsx` + `HdEmailAccountsController::validateRequest` |
| Regra de dedup | `app/Services/Intake/Drivers/EmailIntakeDriver.php::findExistingTicket` |
| Regex que detecta `[#ID]` | idem, dentro de `findExistingTicket` |
| Comportamento do bot user | `EmailIntakeDriver::systemBotUserId` |
| Cap de tamanho de anexo (global) | Constante `DEFAULT_MAX_ATTACHMENT_BYTES` em `EmailIntakeDriver` |
| Cap de tamanho de anexo (por canal) | `hd_channels.config.max_attachment_size_mb` via admin |

### Status do caminho Postmark (desativado mas preservado)

O código Postmark continua no projeto caso um dia o cenário mude:

| Arquivo | Status |
|---|---|
| `app/Http/Controllers/Api/PostmarkInboundWebhookController.php` | ✅ Funcional |
| `app/Jobs/Helpdesk/ProcessInboundEmailJob.php` | ✅ Funcional |
| Rota `POST /api/webhooks/helpdesk/email/{tenant}` | ✅ Registrada |
| `config/services.php` → `postmark_inbound.webhook_token` | ✅ Declarado |

Pra reativar o caminho Postmark no futuro: configurar `POSTMARK_INBOUND_WEBHOOK_TOKEN` no `.env`, apontar o webhook no dashboard da Postmark, e popular `hd_channels.config.addresses` (mapa `endereço → department_id`). **Não é necessário mexer no código.** O `EmailIntakeDriver` trata ambos os caminhos sem distinção.
