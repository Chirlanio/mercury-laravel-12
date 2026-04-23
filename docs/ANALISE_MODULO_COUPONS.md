# Análise do Módulo — Cupons (Coupons)

**Data de conclusão:** 23/04/2026
**Versão:** 1.0 (v2 completo — paridade v1 `adms_coupons` com refinamentos)
**Testes:** 93 testes / 282 assertions / 8 suites
**Rotas:** 14
**Permissions:** 8
**Commands agendados:** 2
**Config module auxiliar:** SocialMedia (novo)

---

## Visão Geral

Módulo de **solicitação e provisionamento manual de cupons de desconto** para três públicos:

- **Consultor(a)** — colaborador de loja física solicita cupom pra cliente/amiga. Exige `store_code + employee_id`.
- **Influencer** — parceiro digital com público próprio. Exige `city + social_media_id`. Não vinculado a loja.
- **MS Indica** — colaborador de loja **administrativa** (escritório, CD, e-commerce, qualidade) no programa member-get-member. Exige `store_code + employee_id` em loja com `network_id IN [6, 7]` (Z441, Z442, Z443, Z999).

**Fluxo de negócio real:**

1. Usuário solicita o cupom (opcionalmente sugere código em `suggested_coupon`)
2. E-mail/notificação automática → equipe com permissão `ISSUE_COUPON_CODE` (e-commerce)
3. E-commerce gera o código real na plataforma externa (Shopify/Tray/etc — **sem integração automática**) e preenche `coupon_site`
4. Status caminha: `draft → requested → issued → active → expired | cancelled`

**NÃO é cupom fiscal, NÃO é cashback, NÃO transaciona venda** — é workflow de onboarding/provisionamento de códigos promocionais com auditoria completa de quem pediu pra quem.

---

## Arquitetura

### Enums (2)

| Arquivo | Propósito |
|---------|-----------|
| `CouponType.php` | 3 tipos (Consultor/Influencer/MsIndica) + helpers `requiresStoreAndEmployee()`, `requiresInfluencerFields()`, `requiresAdministrativeStore()` |
| `CouponStatus.php` | State machine 6 estados + `allowedTransitions()`, `isTerminal()`, `active()`, `transitionMap()`, `labels()`, `colors()` |

### State machine

```
draft (Rascunho, opcional)
    └─► requested (Solicitado, e-mail disparado)
            ├─► issued (Emitido, coupon_site preenchido)
            │       ├─► active (Ativo — publicado)
            │       │       ├─► expired (valid_until vencido, automático)
            │       │       └─► cancelled
            │       ├─► expired
            │       └─► cancelled
            └─► cancelled
    └─► cancelled
```

Regras adicionais (em `CouponTransitionService`):
- `requested → issued`: exige `ISSUE_COUPON_CODE` + `coupon_site` no context
- `* → cancelled`: exige `EDIT_COUPONS`/`MANAGE_COUPONS`/`DELETE_COUPONS` + motivo
- `* → expired`: automático via command `coupons:expire-stale` (aceita `actor=null`)
- Terminais: `expired`, `cancelled`

### Models (3)

| Arquivo | Propósito |
|---------|-----------|
| `Coupon.php` | Tabela principal com mutator/accessor manuais de CPF (encryption + cpf_hash), 6 scopes, 8 relations, Auditable trait, state helpers |
| `CouponStatusHistory.php` | Audit trail de transições (substitui triggers MySQL da v1) |
| `SocialMedia.php` | Config module auxiliar com `link_type` + `link_placeholder` + método `validateLink()` |

### Services (5)

| Arquivo | Propósito |
|---------|-----------|
| `CouponService.php` | CRUD + `validateTypeRules()` condicional por tipo + `ensureUnique()` (scope variável) + soft delete com bloqueio de cupons já emitidos |
| `CouponTransitionService.php` | Ponto único de mutação de status. Valida transições + permissões + histórico + garante código único em `issued`. Métodos convenience: `request`, `issueCode`, `activate`, `cancel`, `expire` |
| `CouponLookupService.php` | `existingActiveForCpf()` (banner warning), `employeesByStore()`, `employeeDetails()`, `suggestCouponCode()`, `isAdministrativeStore()` |
| `CouponExportService.php` | Export XLSX (listagem com filtros, CPF sempre mascarado — LGPD) + PDF individual via dompdf |
| `CouponImportService.php` | Import XLSX/CSV em 2 passos (`preview` + `import`). Upsert por `(cpf_hash, type, store_code)`. Aceita ~30 variações de header PT-BR. Resolve FKs por nome (employee por loja + rede social por nome) |

### Form Requests (3)

| Arquivo | Propósito |
|---------|-----------|
| `StoreCouponRequest.php` | Rules condicionais: `store_code + employee_id` requiredIf Consultor/MsIndica; `city + social_media_id` requiredIf Influencer |
| `UpdateCouponRequest.php` | Todos opcionais; service valida regras quando type/cpf/store mudam em estados iniciais |
| `TransitionCouponRequest.php` | `coupon_site` requiredIf `to_status=issued`; regex A-Z0-9_- |

### Controller

**`CouponController.php`** — 14 métodos públicos:

| Método | Rota | Propósito |
|--------|------|-----------|
| `index` | `GET /coupons` | Lista paginada + StatisticsGrid (6 cards) + filtros (search/type/status/date) |
| `store` | `POST /coupons` | Criação com `auto_request=true` default (transiciona draft→requested na criação) |
| `show` | `GET /coupons/{id}` | JSON detalhado com timeline |
| `update` | `PUT /coupons/{id}` | Atualização — em estados avançados só campos "soft" |
| `destroy` | `DELETE /coupons/{id}` | Soft delete com motivo obrigatório (bloqueado em issued/active) |
| `transition` | `POST /coupons/{id}/transition` | Mudança de status (cancel/issue/activate) |
| `lookupExisting` | `GET /coupons/lookup/existing` | Banner warning de cupons ativos do CPF |
| `lookupEmployees` | `GET /coupons/lookup/employees` | Autocomplete por loja |
| `lookupEmployeeDetails` | `GET /coupons/lookup/employee-details` | CPF real + store ao selecionar employee |
| `suggestCode` | `GET /coupons/suggest-code` | Gera sugestão determinística (nome + ano) |
| `dashboard` | `GET /coupons/dashboard` | Página analítica com 4 gráficos recharts |
| `export` | `GET /coupons/export` | XLSX com filtros aplicados |
| `exportPdf` | `GET /coupons/{id}/pdf` | Comprovante PDF individual |
| `importPreview` | `POST /coupons/import/preview` | Validação sem persistir |
| `importStore` | `POST /coupons/import` | Persistência com upsert idempotente |

### Policy

**`CouponPolicy.php`** — auto-discovery (Laravel 12 nomeclatura `Coupon → CouponPolicy`, sem registro manual). Métodos:

| Método | Regra |
|--------|-------|
| `viewAny` | `VIEW_COUPONS` |
| `view(coupon)` | `VIEW_COUPONS` + (MANAGE ou store_code do user ou criador do cupom) |
| `create` | `CREATE_COUPONS` |
| `update(coupon)` | `EDIT_COUPONS`/`MANAGE_COUPONS` + view + estado editável (draft/requested sem MANAGE) |
| `delete(coupon)` | `DELETE_COUPONS` + view |
| `issueCode(coupon)` | `ISSUE_COUPON_CODE` (bypassa store scoping — e-commerce global) |
| `cancel(coupon)` | `EDIT_COUPONS`/`MANAGE_COUPONS`/`DELETE_COUPONS` + view |
| `export` | `EXPORT_COUPONS` |

### Eventos e Listeners

| Arquivo | Propósito |
|---------|-----------|
| `CouponStatusChanged.php` | Event disparado post-commit pelo `CouponTransitionService`. `actor` nullable (expiração automática) |
| `NotifyCouponStakeholders.php` | Listener — envia database notification. Auto-discovered pelo Laravel 12 (sem `Event::listen` manual) |

**Matriz de destinatários por transição:**

| Transição | Destinatários |
|-----------|---------------|
| `→ requested` | Usuários com `ISSUE_COUPON_CODE` (equipe e-commerce) |
| `→ issued` | Criador da solicitação |
| `→ active` | Criador |
| `→ cancelled` | Criador (recebe motivo) |
| `→ expired` | Criador (informativo) |

### Notifications

| Arquivo | Tipo | Uso |
|---------|------|-----|
| `CouponStatusChangedNotification.php` | `database` | Sino do frontend a cada transição |

### Commands agendados

| Command | Frequência | Propósito |
|---------|------------|-----------|
| `coupons:expire-stale` | dailyAt 06:00 | Marca como `expired` cupons com `valid_until < today` nos estados `issued`/`active` (idempotente) |
| `coupons:remind-pending` | dailyAt 09:00 | Lembra equipe e-commerce (`ISSUE_COUPON_CODE`) de cupons em `requested` há mais de 3 dias (threshold configurável via `--days=N`) |

### Migrations (6)

| Migration | Tipo | Destaques |
|-----------|------|-----------|
| `2026_04_22_700001_seed_coupons_module_and_permissions.php` | central | `central_modules` + 8 permissions + `tenant_modules` (Professional/Enterprise) + página de config auxiliar |
| `2026_04_22_900001_seed_coupons_page_and_menu.php` | central | Registra `/coupons` em `central_pages` + menu "E-commerce" + limpa cache |
| `tenant/2026_04_22_600001_create_social_media_table.php` | tenant | Config module com 6 redes seedadas (Instagram, TikTok, YouTube, Facebook, X, Outra) + `link_type`/`link_placeholder` |
| `tenant/2026_04_22_700002_create_coupons_table.php` | tenant | Tabela principal (29 colunas + soft delete manual) + índice `idx_coupon_dedup_lookup` |
| `tenant/2026_04_22_700003_create_coupon_status_histories_table.php` | tenant | Audit trail |
| `tenant/2026_04_23_100001_add_link_config_to_social_media.php` | tenant | Adiciona `link_type` + `link_placeholder` a tenants existentes |

---

## Frontend

### Páginas (2)

- **`resources/js/Pages/Coupons/Index.jsx`** (monolítico ~1300 linhas) — lista paginada + `StatisticsGrid` (6 cards clicáveis) + filtros (busca, tipo, status, include_cancelled) + 6 modais inline (`StandardModal`):
  - **Create** — seletor de tipo (3 cards grandes) + seção condicional por tipo + CPF com `onBlur` que dispara banner warning de cupons ativos + botão "Sugerir código" + campanha/validade/notes + checkbox auto_request (default true)
  - **Detail** — 3 seções (Resumo, Código, Observações/Cancelamento) + `StandardModal.Timeline` de histórico + botão "PDF" no header
  - **Edit** — só campos "soft" pós-requested (campaign_name, social_media_link, valid_from/until, max_uses, notes)
  - **Issue** — exclusivo para `ISSUE_COUPON_CODE`. Campo `coupon_site` com sugestão pré-preenchida do solicitante
  - **Cancel** — motivo obrigatório
  - **Delete** — soft delete com motivo + aviso que emitidos devem ser cancelados
  - **Import** — upload XLSX/CSV + preview com cards Válidas/Inválidas/Total + lista de erros por linha + botão confirmar

- **`resources/js/Pages/Coupons/Dashboard.jsx`** (~170 linhas) — página analítica com 4 gráficos recharts em grid 2×2:
  - Line: emissões por mês (últimos 12, formato `abr/2026` PT-BR)
  - Pie: distribuição por status (cores coerentes com o enum)
  - Bar vertical: top 10 lojas solicitantes (com nome da loja, não código)
  - Bar vertical: top 10 influencers por volume

### Hook específico

**`resources/js/Hooks/useCoupons.js`** — encapsula os 4 endpoints AJAX:
- `lookupExisting(cpf, opts)` — banner warning
- `lookupEmployees(storeCode)` — autocomplete
- `fetchEmployeeDetails(id)` — CPF real ao selecionar
- `suggestCode(name, year)` — sugestão

Cada lookup expõe `{ data, loading, error }` pra integração fácil nos modais.

---

## Decisões arquiteturais não-óbvias

### 1. Unicidade varia por tipo — validada no service, não no DB

MySQL não trata `NULL = NULL` como verdadeiro em unique constraints compostos. Se criássemos `UNIQUE INDEX (cpf_hash, type, store_code)` permitiria infinitos Influencer duplicados (onde `store_code IS NULL`).

**Regra implementada em `CouponService::ensureUnique()`:**
- Consultor / MS Indica: `(cpf_hash + type + store_code)` em scope ativo — **permite mesmo CPF em lojas diferentes** (caso de colaborador transferido entre lojas, que abre novo cupom no novo contexto)
- Influencer: `(cpf_hash + type)` — sem loja

Mesmo padrão usado em Reversals.

### 2. CPF — encryption manual + cpf_hash determinístico

**NÃO uso o cast `encrypted` do Laravel.** Uso mutator/accessor manuais (`setCpfAttribute`/`getCpfAttribute`):
- Encripta via `Crypt::encryptString` ao gravar
- Recalcula `cpf_hash = hash_hmac('sha256', digits_only, config('app.key'))` automaticamente
- Decripta ao ler, com `try/catch` (retorna null se corrompido — evita quebrar listagens)

**Por quê não o cast?** Se usar `'cpf' => 'encrypted'` + mutator que escreve em `$this->attributes['cpf']`, Laravel encripta 2x. A única forma de coordenar `cpf` + `cpf_hash` em um único passo é manualmente.

**Regeneração de hashes:** se `APP_KEY` mudar, todos os `cpf_hash` se invalidam e busca/unicidade param de funcionar. Rotação de chave exigiria re-encriptar + recalcular todos os hashes.

### 3. Laravel 12 tem event auto-discovery ATIVO por default

`EventServiceProvider::$shouldDiscoverEvents = true`. Se você fizer `Event::listen(MeuEvento::class, MeuListener::class)` **E** tiver um Listener em `app/Listeners/` com método `handle(MeuEvento $e)`, o handler é registrado **DUAS VEZES** → notificações duplicadas.

**Em Coupons, removi o `Event::listen` explícito** em `AppServiceProvider` — uso apenas auto-discovery. Comentário alertando está lá.

**Validado em tenant real:** 27 usuários → 27 notifications (ratio 1:1). Return/Reversal têm esse bug silencioso em produção (confirmado via `getListeners()` retornar 2 closures cada).

### 4. MS Indica restrito a lojas administrativas

Validação em `CouponLookupService::isAdministrativeStore()` + consumido por `CouponService::validateTypeRules()`.

- `network_id = 6` → E-Commerce (Z441)
- `network_id = 7` → Operacional (Z442 Qualidade, Z443 CD, Z999 Administrativo)

Mudança no mapeamento de networks exige revisão dessa constraint. No `CouponImportService`, a mesma validação é aplicada — linhas MS Indica em loja comercial são marcadas como inválidas no preview.

### 5. Store scoping com fallback pelo criador

Usuário sem `MANAGE_COUPONS` vê apenas cupons da sua loja OU os que **ele mesmo criou** (inclusive Influencer, que não tem `store_code`). Senão gerentes de loja não veriam seus próprios cupons de influencer.

```php
$query->where(function ($q) use ($scopedStoreCode, $user) {
    $q->where('store_code', $scopedStoreCode)
        ->orWhere('created_by_user_id', $user->id);
});
```

### 6. Validação contextual de link por rede social

Cada rede tem `link_type` configurável:
- `username` (Instagram, TikTok, X) — aceita `@usuario`, `usuario` ou URL completa
- `url` (YouTube, Facebook, Outra) — exige URL http(s)

Validação em `SocialMedia::validateLink()` → chamado por `CouponService::validateTypeRules()`. Placeholder do input também vem do banco (`link_placeholder`) — editável via config module `/config/social-media`.

### 7. Reset completo ao trocar tipo no modal

Ao trocar o tipo no modal Create, **todos** os campos type-específicos são resetados:
- Consultor/MsIndica: `store_code`, `employee_id`
- Influencer: `influencer_name`, `city`, `social_media_id`, `social_media_link`
- Compartilhados: `cpf` (puxado do Employee em Consultor/MsIndica, manual em Influencer), `suggested_coupon` (derivado do nome do beneficiário)

Também limpo `createErrors` e `lookup.clearExisting()` — banner warning é contextual ao tipo.

### 8. Dashboard usa `strftime` (SQLite) vs `DATE_FORMAT` (MySQL)

O agregado de "emissões por mês" precisa detectar o driver em runtime (SQLite nos testes, MySQL em produção). `CouponController::buildAnalytics()` usa `Coupon::query()->getConnection()->getDriverName()` — **não** `config('database.default')` (que no tenant mode reflete o driver do central, não do tenant).

### 9. Auto-discovery de policy (Laravel 12)

`App\Models\Coupon` → `App\Policies\CouponPolicy` é descoberto automaticamente. **Não registrei** em `AuthServiceProvider` (seguindo o pattern dos módulos recentes como DRE, Budget).

### 10. `AuthorizesRequests` trait manual

`App\Http\Controllers\Controller` é `abstract class Controller { }` sem traits. `CouponController` usa `$this->authorize(...)`, então adiciona `use AuthorizesRequests;` explícito. Mesmo pattern dos outros controllers recentes.

### 11. Ordem de rotas: `/coupons/dashboard` antes do wildcard

Rota literal precisa vir **antes** da rota com parâmetro (`/coupons/{coupon}`), caso contrário Laravel interpreta "dashboard" como coupon id. O `whereNumber('coupon')` no show ajuda mas não é suficiente sozinho — ordem conta.

---

## Permissions (8)

| Permission | Uso |
|-----------|-----|
| `VIEW_COUPONS` | Ver listagem. Sem MANAGE, aplica store scoping |
| `CREATE_COUPONS` | Criar solicitações. Sem MANAGE, só pra própria loja |
| `EDIT_COUPONS` | Editar cupons em draft/requested (ou qualquer com MANAGE) |
| `DELETE_COUPONS` | Excluir cupons não-emitidos (soft delete) |
| `MANAGE_COUPONS` | Bypass store scoping + edita qualquer estado |
| `ISSUE_COUPON_CODE` | Emitir código (preencher `coupon_site` e transicionar requested → issued) |
| `IMPORT_COUPONS` | Importar planilha (histórico v1 → v2) |
| `EXPORT_COUPONS` | Exportar XLSX/PDF |

**Atribuição por role:**

| Role | Permissões |
|------|-----------|
| super_admin | Todas (8) |
| admin | Todas (8) |
| support | view + create + edit + issue + export (sem delete, sem manage → store scoping) |
| user | view + create (só pra própria loja) |
| finance, accounting, fiscal, drivers | — |

---

## Integração com outros módulos

- **Employee / Store** — FKs normais. `Store.network_id` driva a regra MS Indica
- **SocialMedia** (config module novo) — FK nullable em `coupons`
- **Helpdesk** — não há hook (diferente de Reversals)
- **CIGAM** — sem sincronia (paridade v1)
- **PersonnelMovements** — não auto-expira cupons ao trocar loja. Decisão arquitetural: cupom antigo continua até `valid_until`; modal de criação avisa com banner amarelo "este CPF já tem cupom em outra loja" mas não bloqueia

---

## Testes (93 tests / 282 assertions)

### Estrutura

```
tests/
├── Unit/Coupons/
│   ├── CouponEnumsTest.php          (5 tests) — enums: labels, requirements, state graph
│   └── CouponModelTest.php          (9 tests) — CPF hash/encryption, state machine, scopes
└── Feature/Coupons/
    ├── CouponServiceTest.php        (14 tests) — CRUD, validações por tipo, unicidade, soft delete
    ├── CouponTransitionServiceTest (12 tests) — transições, permissões, histórico
    ├── CouponLookupServiceTest      (9 tests) — existingActiveForCpf, employeesByStore, suggestCode
    ├── CouponControllerTest         (19 tests) — index/show/store/update/destroy, AJAX lookups, scoping
    ├── CouponImportExportTest       (11 tests) — preview, import, export XLSX/PDF, permissions
    └── CouponCommandsTest           (7 tests) — expire-stale idempotente, remind-pending com threshold
    + SocialMediaControllerTest      (7 tests) — config module auxiliar
```

### Helper específico: `ConsoleCommand` output em testes

`$this->output` de um command é `null` quando instanciado via `app(Command::class)` sem passar pelo `artisan()`. Para testar `scanTenant()` direto:

```php
protected function expireCommand(): CouponsExpireStaleCommand
{
    $cmd = app(CouponsExpireStaleCommand::class);
    $input = new ArrayInput([]);
    $cmd->setOutput(new \Illuminate\Console\OutputStyle($input, new BufferedOutput()));
    return $cmd;
}
```

Mesmo pattern usado em ReturnOrderCommandsTest.

---

## Estatísticas finais

| Métrica | Valor |
|---------|-------|
| **Tests** | 93 / 282 assertions |
| **Rotas** | 14 |
| **Permissions** | 8 |
| **Commands** | 2 (dailyAt 06:00 e 09:00) |
| **Services** | 5 |
| **Models** | 3 (Coupon, CouponStatusHistory, SocialMedia) |
| **Enums** | 2 |
| **Migrations** | 6 (3 tenant + 2 central + 1 tenant adicional) |
| **Form Requests** | 3 |
| **Notifications** | 1 database-only |
| **Controller methods** | 14 |
| **Frontend páginas** | 2 (Index + Dashboard) |
| **Frontend hook** | 1 (useCoupons) |
| **Config module auxiliar** | SocialMedia (6 seeds) |
| **Template PDF** | 1 (coupon.blade.php) |

---

## Reference

Documentação viva em `C:\Users\MSDEV\.claude\projects\C--xampp-htdocs-mercury-laravel\memory\coupons_module.md` com todos os gotchas operacionais.
