# Playbook de execução — Módulo DRE Financeira

> **Fonte canônica** dos prompts do módulo DRE. Atualizado em 2026-04-22
> após consolidação de `dre-arquitetura.md`, `dre-descoberta.md` e
> `dre-plano-contas-formato.md`. Ver `dre-execucao-status.md` para o
> mapa de quais prompts já foram entregues e divergências da
> implementação real.

**Pré-requisitos (já concluídos):**
- `docs/dre-descoberta.md` — mapeamento do projeto.
- `docs/dre-arquitetura.md` — plano arquitetural aprovado com 25/25 dúvidas fechadas.
- `docs/dre-plano-contas-formato.md` — contrato do XLSX do CIGAM.
- `docs/Plano de Contas.xlsx` — arquivo oficial (1.129 contas + 289 CCs).
- `Action Plan v1.xlsx` — orçamento 2026 (destino §5 do arquitetura).

Este playbook é **só execução**. Cada prompt = um commit revisável. Não usar o playbook como substituto da leitura do arquitetura — o plano continua sendo a fonte da verdade; prompts **citam seções** em vez de duplicar decisões.

---

## Contexto permanente (colar em toda sessão do Claude Code)

```
CONTEXTO:
- v2 em produção. Módulo DRE novo. Stack: Laravel 12, PHP 8.2, React 18 (SEM TypeScript, .jsx), Inertia 2, MySQL 8+, stancl/tenancy 3.10, fila `database`, cache `database`.
- Arquitetura em `docs/dre-arquitetura.md`. Descoberta em `docs/dre-descoberta.md`. Formato do XLSX em `docs/dre-plano-contas-formato.md`.

CONVENÇÕES DO PROJETO (não inventar — seguir as do descoberta §1-§5):
- Tabelas plural snake_case, FKs {model_singular}_id, sem prefixo por módulo (exceto central_, hd_, chat_).
- Soft delete MANUAL (3 colunas: deleted_at, deleted_by_user_id, deleted_reason). NUNCA trait SoftDeletes do Eloquent.
- Trait `Auditable` (app/Traits/Auditable.php) para rastrear mudanças em activity_logs.
- audit user: created_by_user_id, updated_by_user_id.
- Services flat em app/Services/ (87+ classes). DRE recebe subpasta App\Services\DRE\ (decisão §3 arquitetura).
- Controllers na raiz de app/Http/Controllers/ (sem subpasta por módulo).
- SEM DTOs formais. Filtros via FormRequest + array associativo (decisão §12.2 #8 arquitetura).
- SEM Policies. Autorização via middleware `permission:PERM` (decisão §8.3 arquitetura).
- Frontend: .jsx, sem PropTypes, sem JSDoc, strings PT inline COM acentuação completa (ç, á, ã, é — ver memória feedback_frontend_accents).
- Componentes padrão: DataTable, StandardModal, StatisticsGrid, StatusBadge, FormSection.
- Hooks: usePermissions, useMasks, useModalManager, useConfirm, useTenant.
- Gráficos: recharts. Ícones: @heroicons/react/24/outline. Toasts: react-toastify via AuthenticatedLayout.
- Filtros de período+lojas: query string via router.get({ preserveState, preserveScroll }). Nunca localStorage.
- Multi-tenancy: migrations das tabelas de negócio vão em database/migrations/tenant/.
- Validação: server-side via FormRequest. Frontend confia no shape do Inertia.

CONVENÇÃO DE IDIOMA:
- CÓDIGO em inglês (tabelas, colunas, classes, métodos, rotas, componentes).
- PT-BR em: comentários, docstrings, docs/, textos de UI, rótulos de seeds (nomes de contas, rubricas DRE).
- CIGAM permanece como `stores.code` (já é assim no projeto — não renomear).

REGRAS:
- Proponha antes de executar. Nunca rode migration em produção.
- Ao fim de cada prompt: listar pendências e o que você precisa de input meu.
- Um commit por prompt.
```

---

## Prompt 1 — Migrations, models, factories, seeders esqueleto

Referência: arquitetura §2, §11 linha #1.

```
Implemente a fundação de dados do módulo DRE conforme `docs/dre-arquitetura.md §2`.
Todas as migrations de negócio vão em `database/migrations/tenant/` (multi-tenancy).

## Migrations (nesta ordem)

1. `rename_accounting_classes_to_chart_of_accounts`
   - Schema::rename('accounting_classes', 'chart_of_accounts').
   - Renomear FKs apontando pra essa tabela em: budget_items.accounting_class_id → chart_of_account_id;
     management_classes.accounting_class_id → chart_of_account_id; cost_centers.default_accounting_class_id
     → default_chart_of_account_id. Cada rename em migration separada para reversão granular.
   - Ver §12.2 #2 do arquitetura — breaking change aprovado.

2. `extend_chart_of_accounts_for_dre`
   - Adicionar colunas: reduced_code (varchar 20 unique nullable inicial), type (enum synthetic/analytical),
     account_group (tinyInteger nullable inicial), classification_level (tinyInteger default 0),
     balance_nature (char 1 nullable), is_result_account (boolean default false),
     default_management_class_id (foreignId nullable — §arquitetura 2.1 e §5),
     external_source (varchar 20 nullable), imported_at (timestamp nullable).
   - Índices compostos conforme §2.1 arquitetura.
   - NÃO apagar colunas antigas (nature, accepts_entries, dre_group) — serão usadas como fonte de backfill.

3. `backfill_chart_of_accounts_derived_columns`
   - Migration que lê as 80 contas existentes e preenche: reduced_code (quando possível derivar do code),
     type (analytical se accepts_entries=true, senão synthetic),
     classification_level (contar pontos em code),
     account_group (primeiro segmento do code),
     is_result_account (true se account_group in 3,4,5),
     balance_nature (mapear enum nature D/C).
   - Não depende do XLSX — só dos dados atuais. Seeder do XLSX vem no prompt 2.

4. `drop_legacy_columns_from_chart_of_accounts`
   - Depois do backfill: drop accepts_entries (virou type), drop nature (virou balance_nature).
   - MANTER dre_group (arquitetura §2.1: preservado como dica para seed inicial do mapping).

5. `extend_cost_centers_for_dre` — §2.2 arquitetura.
   - Adicionar reduced_code (varchar 20 unique nullable), external_source, imported_at.

6. `create_dre_management_lines_table` — §2.3 arquitetura.
   - Colunas exatas de §2.3: id, code (20 unique), sort_order (smallint unique),
     is_subtotal (bool default false), accumulate_until_sort_order (smallint nullable),
     level_1 (150), level_2..4 (150 nullable), nature (enum revenue/expense/subtotal),
     is_active (bool default true), notes (text nullable), created_by_user_id,
     updated_by_user_id, deleted_at, deleted_by_user_id, deleted_reason, timestamps.
   - SEM coluna version (§arquitetura 12.2 #5 — decisão confirmada).

7. `create_dre_mappings_table` — §2.4 arquitetura.
   - Atenção ao UNIQUE parcial e ao gotcha MySQL com NULL em unique (§2.4 arquitetura).

8. `create_dre_actuals_table` — §2.5 arquitetura.
   - store_id nullable (despesas corporativas — §12.4 #18 confirmado).
   - Sem soft delete (imutável). Adicionar reported_in_closed_period (bool default false)
     — §2.8 arquitetura (relatório de reabertura).

9. `create_dre_budgets_table` — §2.6 arquitetura.
   - Sem soft delete. budget_upload_id nullable (entrada manual permitida).

10. `create_dre_period_closings_table` — §2.8 arquitetura.

11. `create_dre_period_closing_snapshots_table` — §2.8 arquitetura.

12. `add_sale_chart_of_account_id_to_stores` — §12.4 #17 arquitetura.
    - foreignId nullable referenciando chart_of_accounts.id.
    - Preencher fallback via seed no prompt 8 (SaleToDreProjector).

## Models em app/Models/ (raiz, não subpasta — convenção do projeto)

- `ChartOfAccount` (renomear de AccountingClass.php — git mv preserva histórico).
  - Fillable, casts (type/balance_nature para enum se criado), relationships:
    parent(), children(), budgetItems(), managementClasses(), dreMappings(),
    dreActuals(), dreBudgets(), defaultManagementClass().
  - Scopes: scopeAnalytical, scopeSynthetic, scopeActive, scopeByGroup($n),
    scopeChildrenOf($code), scopeWithoutMapping($date).
  - Trait Auditable aplicada.
  - Soft delete manual via trait HasManualSoftDelete se existir no projeto; senão,
    implementar os 3 campos à mão conforme convenção.
  - Docblock em PT explicando: "Plano contábil do ERP. 1.129 linhas em 5 níveis..."

- `CostCenter` — atualizar: renomear default_accounting_class_id → default_chart_of_account_id,
  atualizar belongsTo, adicionar scope scopeImported.

- `DreManagementLine` — trait Auditable. Relationships: mappings(). Scopes: scopeOrdered,
  scopeActive, scopeSubtotals, scopeAnalytical.

- `DreMapping` — trait Auditable. Relationships: chartOfAccount, costCenter, managementLine,
  createdBy, updatedBy. Scopes: scopeEffectiveAt(Carbon $date), scopeForAccount($id),
  scopeForCostCenter($id|null), scopeActive.
  - Método static resolve($accountId, $costCenterId, $date) apenas para uso em testes;
    o resolver "oficial" é a classe DreMappingResolver (§arquitetura 3).

- `DreActual` — SEM soft delete. SEM Auditable (imutável). Relationships polimórficos:
  source() morphTo usando source_type + source_id. Scopes: scopeForPeriod,
  scopeForStore, scopeForAccount, scopeBySource($enum).

- `DreBudget` — SEM soft delete. Relationships: chartOfAccount, costCenter, store,
  budgetUpload. Scopes: scopeForPeriod, scopeForVersion($label).

- `DrePeriodClosing` — trait Auditable. Relationships: closedBy, reopenedBy, snapshots().
  Método static lastClosedUpTo() helper de validação.

- `DrePeriodClosingSnapshot` — sem soft delete (destruído na reopen).

- Atualizar `Store`: adicionar belongsTo saleChartOfAccount via sale_chart_of_account_id.

## Enums em app/Enums/

- `AccountType`: synthetic, analytical. Com labels PT.
- `DreActualSource`: ORDER_PAYMENT, SALE, MANUAL_IMPORT, CIGAM_BALANCE. Com labels.
- `DreLineNature`: revenue, expense, subtotal. Com labels + cores (verde/vermelho/cinza) pra UI.
- Preservar o enum existente `DreGroup` (vamos usar no seed).

## Factories em database/factories/

- `ChartOfAccountFactory` com estados: synthetic(), analytical(), atLevel($n), inGroup($n),
  withReducedCode(), active(), inactive().
- `CostCenterFactory` — idem padrão.
- `DreManagementLineFactory` com estados: subtotal($until), analytical(), unclassified().
- `DreMappingFactory` com estado effectiveBetween($from, $to), wildcardCostCenter().
- `DreActualFactory` com estados: fromOrderPayment($op), fromSale($sale), manualImport().
- `DreBudgetFactory` com estado fromBudgetUpload($upload).
- `DrePeriodClosingFactory` + `DrePeriodClosingSnapshotFactory`.

## Seeders em database/seeders/

- `DreManagementLineSeeder` — seed inicial de 16 linhas DRE-BR derivadas do enum DreGroup
  (§arquitetura decisões/opção C). Estrutura:
  ```php
  // Seed de 16 linhas derivadas de App\Enums\DreGroup + 1 linha-fantasma "(!) Não classificado".
  // O CFO completa as 3-4 linhas executivas restantes via UI em /dre/management-lines
  // após o prompt #4 (Headcount, Marketing e Corporativo, EBITDA formal, Lucro Líquido s/ Cedro).
  $lines = [
      // sort_order com gaps de 10 para permitir inserção via UI sem renumeração
      ['code' => 'L01', 'sort_order' => 10,   'is_subtotal' => false, 'level_1' => '(+) Receita Bruta',                  'nature' => 'revenue',  'accumulate_until_sort_order' => null],
      ['code' => 'L02', 'sort_order' => 20,   'is_subtotal' => false, 'level_1' => '(-) Deduções da Receita Bruta',      'nature' => 'expense',  'accumulate_until_sort_order' => null],
      ['code' => 'L03', 'sort_order' => 30,   'is_subtotal' => true,  'level_1' => '(=) Receita Líquida',                'nature' => 'subtotal', 'accumulate_until_sort_order' => 20],
      ['code' => 'L04', 'sort_order' => 40,   'is_subtotal' => false, 'level_1' => '(-) Custos (CMV/CPV/CSV)',           'nature' => 'expense',  'accumulate_until_sort_order' => null],
      ['code' => 'L05', 'sort_order' => 50,   'is_subtotal' => true,  'level_1' => '(=) Lucro Bruto',                    'nature' => 'subtotal', 'accumulate_until_sort_order' => 40],
      // ... derivar restantes do enum (DreGroup::cases())
      ['code' => 'L99_UNCLASSIFIED', 'sort_order' => 9990, 'is_subtotal' => false,
       'level_1' => '(!) Não classificado', 'nature' => 'expense', 'accumulate_until_sort_order' => null],
  ];
  ```
  IMPORTANTE: use DreGroup::cases() + DreGroup::dreOrder() para gerar os labels/ordem
  automaticamente, não hardcode 16 registros duplicados. Apenas o UNCLASSIFIED é manual.

- `ChartOfAccountSeeder` — STUB apenas. Conteúdo vai vir via import (prompt 2). Comentar:
  ```php
  // População vem do ChartOfAccountsImporter a partir de docs/Plano de Contas.xlsx.
  // Rode `php artisan dre:import-chart docs/Plano\ de\ Contas.xlsx` após migrations.
  ```

- Registrar `DreManagementLineSeeder` no DatabaseSeeder. `ChartOfAccountSeeder` stub NÃO é
  registrado (não roda em migrate:fresh --seed até o import existir).

## Executar

- `php artisan migrate:fresh --seed` em ambiente local.
- Reportar: DreManagementLine::count() (esperado: 17 = 16 + 1 unclassified),
  DreManagementLine::subtotals()->count() (esperado: 5 na DRE-BR padrão antes do CFO
  completar — Receita Líquida, Lucro Bruto, Lucro Operacional, Resultado Antes Impostos,
  Lucro Líquido).

## NÃO fazer neste prompt

- Não criar controllers, services, rotas.
- Não criar React pages.
- Não tocar em observers (OrderPayment/Sale) — vem no prompt 8.
- Não criar DrePolicy (não usamos policies — ver §8.3 arquitetura).
- Não criar permissions ainda (vem no prompt 4/5 com rotas).
- Não criar arquivos .ts/.tsx.

## Ao final

Liste pendências + o que ficou em aberto no meu input. Em especial: confirme se existe
trait `HasManualSoftDelete` no projeto ou se os 3 campos são implementados manualmente
em cada model.
```

---

## Prompt 2 — ChartOfAccountsImporter + pré-popular `default_management_class_id`

Referência: arquitetura §3 (ChartOfAccountsImporter), §11 linha #2.

```
Implemente o importador do plano de contas e centros de custo conforme
`docs/dre-plano-contas-formato.md` + `docs/dre-arquitetura.md §3`.

## App\Services\DRE\ChartOfAccountsImporter

Métodos:
- `import(string $filePath, string $source = 'CIGAM', bool $dryRun = false): ChartImportReport`
- Lê via Maatwebsite\Excel padrão do projeto (implements ToCollection, WithHeadingRow,
  WithChunkReading — ver BudgetImport como referência em app/Imports/).
- Ignora linha-mestre (§1 do formato).
- Roteia por V_Grupo: 1..5 → chart_of_accounts; 8 → cost_centers.
- Upsert por reduced_code (chave estável). Em DB_TRANSACTION.
- Segunda passada resolve parent_id a partir do prefixo do code (§3 do formato).
- Third pass: marca is_active=false em registros com external_source=$source
  que NÃO estão no arquivo (desativação por sumiço — §5 do formato).
- Retorna ChartImportReport com: total_rows_read, accounts_created, accounts_updated,
  accounts_deactivated, cost_centers_created, cost_centers_updated, errors[].
- Em dry_run: faz tudo mas rollback no final, retorna relatório igual sem persistir.

## App\Services\DRE\CostCentersImporter

Separado mas invocado pelo ChartOfAccountsImporter na linha V_Grupo=8 (evita reler o XLSX).
Método público `importFromRows(Collection $rows, string $source, bool $dryRun): CostCenterImportReport`.

## Pré-população de `default_management_class_id` a partir do Action Plan

Ponto sutil: o ChartOfAccountsImporter NÃO lê Action Plan. Fazemos isso em etapa separada
pra manter responsabilidades claras:

- `App\Services\DRE\ActionPlanHintImporter`
- Método `populateDefaultManagementClass(string $actionPlanPath): HintReport`
- Lê o XLSX (3861 linhas × 9 colunas — §arquitetura decisões).
- Extrai pares únicos (conta_code, management_class_code). Deduplica.
- Para cada par: encontra ChartOfAccount por code + ManagementClass por code; se ambos
  existirem, seta chart_of_accounts.default_management_class_id (apenas se NULL — não
  sobrescreve valor já manual).
- Retorna HintReport com: pairs_found, accounts_updated, accounts_skipped (já tinham),
  accounts_not_found, management_classes_not_found.
- Execução defensiva: se o arquivo não existir no path, retorna report com
  file_not_found=true sem erro. Assim o prompt 2 roda mesmo sem o Action Plan presente.

## Command artisan

- `php artisan dre:import-chart {path} {--source=CIGAM} {--dry-run}` →
  chama ChartOfAccountsImporter + exibe relatório em PT.
- `php artisan dre:import-action-plan-hints {path}` →
  chama ActionPlanHintImporter. Output em PT.

Output esperado (PT, emoji sóbrios):
```
📊 Importando plano de contas de docs/Plano de Contas.xlsx...
   Linhas lidas: 1131 (1 linha-mestre ignorada)
   Contas (grupos 1-5): criadas 840, atualizadas 0, desativadas 0
   Centros de custo (grupo 8): criados 289, atualizados 0
   Erros: 0
✅ Concluído em 12.3s.
```

## Tela de upload (preparação apenas — UI completa no prompt 9)

Criar pageStub `resources/js/Pages/DRE/Imports/Chart.jsx` COMENTADA dizendo
"implementação completa no prompt 9". Só um placeholder com título.

Criar:
- Rota: POST /dre/imports/chart → DreImportController@chart (stub que valida XLSX,
  salva em storage temporário, dispara job ImportChartOfAccountsJob).
- `App\Jobs\DRE\ImportChartOfAccountsJob` — chama o service, armazena relatório em
  cache (chave dre:import:chart:{user_id}:{job_id}).

## Permission nova

- `IMPORT_DRE_CHART` → SUPER_ADMIN e ADMIN financeiro. Adicionar em App\Enums\Permission
  e em App\Enums\Role::permissions().

## Testes

`tests/Feature/Imports/DRE/ChartOfAccountsImportTest.php`:
1. Fixture pequena (30-50 linhas) importa sem erro; contagens conferem.
2. Re-import idempotente (mesmo arquivo 2x → 0 duplicatas).
3. Conta removida do arquivo vira is_active=false (não é deletada).
4. V_Grupo=8 roteia para cost_centers, não para chart_of_accounts.
5. parent_id resolvido em segunda passada (código 1.1.1.01.00016 → pai 1.1.1.01).
6. Linha-mestre (V_Grupo vazio) ignorada.
7. Dry-run não persiste nada mas retorna contagens corretas.
8. ActionPlanHintImporter: popula default_management_class_id só em NULL.
9. ActionPlanHintImporter: arquivo ausente retorna report.file_not_found=true sem exception.

## Executar contra o arquivo real

Ao final, rode `php artisan dre:import-chart docs/Plano\ de\ Contas.xlsx` e reporte:
- Contagens exatas por account_group (esperado: G1=219, G2=361, G3=50, G4=204, G5=6, G8=289).
- Lista de erros se houver.

E `php artisan dre:import-action-plan-hints <caminho/Action Plan v1.xlsx>` se o arquivo
estiver disponível. Senão, skip silencioso.

## NÃO fazer

- Não implementar UI completa de upload (prompt 9).
- Não criar DreActualsImporter (prompt 10).
- Não criar DreMatrixService (prompt 6).
```

---

## Prompt 3 — CostCentersImporter + limpeza dos CCs legados (421–457)

Referência: arquitetura §2.2, §12.2 #3, §11 linha #3.

```
Finalize a limpeza dos CCs legados conforme `docs/dre-arquitetura.md §2.2 e §12.2 #3`.

Contexto: os CCs atuais (códigos 421..457) são na verdade stores.code, não centros de
custo departamentais. O grupo 8 do Excel traz os CCs reais (8.1.01..8.1.11 + filhos).

## Migration de limpeza

`soft_delete_legacy_cost_centers`:
- Para cada cost_center com code LIKE 'Z%' ou code REGEXP '^[0-9]+$' (herdados),
  e com FKs não apontando pra lugar nenhum crítico:
  - Soft delete (preenchendo deleted_at, deleted_by_user_id=1 system, deleted_reason='migração DRE — CC legado era store.code').
- Contagem esperada: 24 soft deletes.

ANTES de rodar o soft delete, verificar se algum budget_item ou management_class aponta
para esses cost_center_id. Se sim, ABORTAR a migration com erro claro em PT:
"Existem {N} budget_items e {M} management_classes apontando para CCs legados.
Migre os dados antes de rodar esta migration. Use `php artisan dre:check-legacy-cc-refs`
para listar."

## Command de verificação

`php artisan dre:check-legacy-cc-refs`:
- Lista (tabela, id, cost_center_id, cost_center.code) com todas as referências ativas
  para CCs legados.
- Se zero: "Nenhuma referência a CCs legados. Pode rodar a migration de limpeza com segurança."

## Re-verificar o import dos CCs do Excel (prompt 2 já importou)

Garanta que após a migration de limpeza + re-import, a query
`CostCenter::whereNull('deleted_at')->count()` devolva 289 (os do grupo 8).

## Observer em ChartOfAccount para alimentar fila de pendências

`App\Observers\ChartOfAccountObserver`:
- Evento `created`: se type=analytical E account_group ∈ {3,4,5}, dispara evento
  `AnalyticalAccountCreated` (criar em app/Events/).
- Listener `MarkAccountAsPendingMapping` registra um cache warm ou flag em algum
  local leve — NÃO criamos tabela só pra isso. A query da tela "Pendências" (prompt 5)
  vai usar LEFT JOIN em dre_mappings em tempo real.
- Registrar o observer em AppServiceProvider.

Motivação: quando re-importarmos o plano e 50 contas novas entrarem, queremos que
apareçam na tela de pendências automaticamente.

## Eventos

- `App\Events\AnalyticalAccountCreated` — carrega ChartOfAccount.
- Reservado para futuros listeners (email, toast ao admin, etc. — fora do MVP).

## Testes

1. Migration de limpeza: 24 CCs legados vão pra soft delete (preenchendo todos os 3 campos).
2. Se houver referência órfã, migration falha com mensagem em PT.
3. Command de verificação lista referências corretamente.
4. Observer: criar ChartOfAccount analytical/G3 dispara AnalyticalAccountCreated.
5. Observer: criar ChartOfAccount synthetic NÃO dispara evento.
6. Observer: criar ChartOfAccount analytical/G1 NÃO dispara evento (grupos 1-2 não vão
   pra DRE).

## NÃO fazer

- Não criar UI da tela de Pendências (prompt 5).
- Não tocar em management_classes (não vamos mais estender ele — usamos o
  default_management_class_id em chart_of_accounts — §arquitetura §5).
```

---

## Prompt 4 — CRUD do plano gerencial (19 linhas) — o CFO completa as 3–4 executivas aqui

Referência: arquitetura §2.3, §9.5, §11 linha #4.

```
CRUD completo de `dre_management_lines` conforme `docs/dre-arquitetura.md §2.3 e §9.5`.
Este prompt destrava a opção C do seed — o CFO acessa a UI e completa as linhas
executivas restantes (Headcount, Marketing e Corporativo, EBITDA formal, Lucro Líquido
s/ Cedro).

## Permissions novas

Adicionar em App\Enums\Permission:
- `VIEW_DRE` — acesso de leitura (matriz, estrutura).
- `MANAGE_DRE_STRUCTURE` — CRUD das linhas.
- `EXPORT_DRE` — reserva (usado no prompt 13).

Atribuir conforme §arquitetura 8.2:
| Permission             | SUPER_ADMIN | ADMIN | SUPPORT | USER |
| VIEW_DRE               | ✓           | ✓     | ✓       | escopo |
| MANAGE_DRE_STRUCTURE   | ✓           | ✓     | ✗       | ✗ |
| EXPORT_DRE             | ✓           | ✓     | ✓       | ✗ |

Atualizar App\Enums\Role::permissions() (driver DB pode continuar vazio — o enum é a
fonte via CentralRoleResolver fallback).

## App\Services\DRE\DreManagementLineService

Métodos:
- `list(): Collection` — ordenado por sort_order. Cachear via dre:cache_version (prompt 12
  fará o cache; aqui só invocar).
- `listForPicker(): Collection` — para selects na UI de mappings. Agrupa por subtotal
  intermediário (visual: "Até Receita Líquida" → [L01, L02]; "Até Lucro Bruto" → [L04];
  etc.). Retorna estrutura otimizada pro React.
- `create(array $data): DreManagementLine` — valida:
  - code único.
  - sort_order único.
  - se is_subtotal=true, accumulate_until_sort_order obrigatório e <= sort_order.
  - se is_subtotal=false, accumulate_until_sort_order deve ser null.
  - nature consistente com is_subtotal.
- `update(DreManagementLine $line, array $data): DreManagementLine`.
  - IMPEDIR alterar sort_order se o sort_order atual é referenciado por
    accumulate_until_sort_order de outra linha.
- `delete(DreManagementLine $line): void` — só se não houver mapping ativo apontando,
  e não estiver referenciada em accumulate_until_sort_order de outra linha.
- `reorder(array $lineIds): void` — recalcula sort_order em batch usando gaps de 10
  (10, 20, 30...). Transacional.

## FormRequest

- `StoreDreManagementLineRequest`, `UpdateDreManagementLineRequest` — mensagens PT.
- Exemplo de regra: "A linha não pode ser subtotal sem informar 'acumula até'."

## Controller

`App\Http\Controllers\DreManagementLineController` — métodos resource + custom reorder.
Middleware: permission:VIEW_DRE em index/show; permission:MANAGE_DRE_STRUCTURE em outros.
Middleware tenant.module:dre em todo o grupo.

## Rotas

```php
Route::middleware(['auth', 'verified', 'tenant.module:dre'])
    ->prefix('dre')->name('dre.')
    ->group(function () {
        Route::resource('management-lines', DreManagementLineController::class)
            ->names('management-lines');
        Route::post('management-lines/reorder', [DreManagementLineController::class, 'reorder'])
            ->name('management-lines.reorder');
    });
```

## Pages React (.jsx)

`resources/js/Pages/DRE/ManagementLines/Index.jsx`:
- Usa DataTable do projeto.
- Colunas: "Ordem", "Código", "Linha", "Natureza" (StatusBadge), "Subtotal?" (StatusBadge),
  "Acumula até", "Ativa" (StatusBadge), Ações (ActionButtons).
- Cabeçalho com título "Estrutura da DRE Gerencial" + botão "Nova Linha" (gate em
  MANAGE_DRE_STRUCTURE via usePermissions).
- Ordenação só permitida entre linhas adjacentes via setas ↑/↓ que chamam POST reorder
  (evita drag-and-drop para consistência com o resto do projeto).
- Banner informativo no topo em amarelo-suave: "A estrutura da DRE é única e vigente.
  Mudanças são auditadas em Activity Log." (§arquitetura 2.3 auditoria).

`resources/js/Pages/DRE/ManagementLines/Form.jsx` (usado por Create e Edit):
- StandardModal com:
  - Field code (text, hint: "Identificador estável — não mude depois de criado").
  - Field level_1 (text, placeholder: "(+) Faturamento Bruto").
  - Field level_2..4 (collapsible "Detalhamento drill").
  - Select nature (revenue / expense / subtotal) com StatusBadge preview.
  - Checkbox is_subtotal.
  - Field accumulate_until_sort_order (visível condicional a is_subtotal=true; select
    com todas as linhas anteriores existentes).
  - Field notes (textarea).
- Rótulos em PT com acentuação completa.

## Testes

`tests/Feature/DRE/ManagementLineControllerTest.php`:
1. Usuário sem VIEW_DRE → 403 em GET index.
2. USER sem MANAGE_DRE_STRUCTURE → 403 em POST.
3. ADMIN cria linha válida → 200 + persiste.
4. ADMIN cria linha com sort_order duplicado → 422 PT.
5. ADMIN cria subtotal sem accumulate_until → 422 PT.
6. ADMIN atualiza linha referenciada em accumulate_until → bloqueado.
7. ADMIN deleta linha usada em mapping ativo → bloqueado com mensagem PT.
8. ADMIN reorder retorna sort_orders recalculados em 10, 20, 30...

## Ação manual esperada após este prompt

O CFO acessa /dre/management-lines e adiciona 3-4 linhas:
- Headcount (is_subtotal=false, nature=expense, sort_order=? entre despesas admin).
- Marketing e Corporativo (is_subtotal=false, nature=expense).
- EBITDA (is_subtotal=true, nature=subtotal, accumulate_until_sort_order=? inclui
  Headcount, exclui D&A/Financeiras).
- Lucro Líquido s/ Cedro (is_subtotal=true, accumulate_until_sort_order=? = sort_order
  do Lucro Líquido + ajuste de Cedro).

## NÃO fazer

- Não implementar versionamento (§arquitetura decisão #5 — não versionamos).
- Não criar CRUD de mappings ainda (prompt 5).
- Não implementar drag-and-drop (fugir do padrão).
```

---

## Prompt 5 — CRUD do mapeamento + tela de Pendências + bulk + botão "Usar sugestão"

Referência: arquitetura §2.4, §9.4, §11 linha #5.

```
Implemente o CRUD do de-para + tela de Pendências conforme `docs/dre-arquitetura.md §2.4 e §9.4`.
Esta é a tela mais usada pelo time financeiro no dia a dia.

## Permissions novas

- `MANAGE_DRE_MAPPINGS`
- `VIEW_DRE_PENDING_ACCOUNTS`

Atribuir: ADMIN financeiro e SUPER_ADMIN em ambas. SUPPORT em VIEW_DRE_PENDING_ACCOUNTS.

## App\Services\DRE\DreMappingService

- `list(array $filters, int $perPage = 25): LengthAwarePaginator`
  Filtros: search (code/reduced_code/name), account_group, cost_center_id,
  management_line_id, only_unmapped (bool), only_expired (bool), effective_on (date).
- `create(array $data): DreMapping` — valida: conta é analítica;
  effective_from > DrePeriodClosing::lastClosedUpTo(); sem overlap com mapping ativo
  pra mesmo (account_id, cost_center_id).
- `update(DreMapping $mapping, array $data): DreMapping` — idem, bloqueia alteração
  se afeta período fechado.
- `delete(DreMapping $mapping): void` — soft delete; bloqueado se effective_from <=
  lastClosedUpTo e está vigente.
- `expire(DreMapping $mapping, Carbon $expireAt): DreMapping` — seta effective_to.
- `duplicate(DreMapping $source, array $overrides): DreMapping` — cópia para UX "nova
  classificação baseada nesta".
- `bulkAssign(array $accountIds, ?int $costCenterId, int $managementLineId,
  Carbon $effectiveFrom, ?Carbon $effectiveTo, string $notes): BulkAssignReport` —
  transacional, retorna contagem de criados + lista de falhas (ex: conta sintética).

## App\Services\DRE\UnmappedAccountsFinder

- `find(Carbon $asOf = null): Collection`
- Retorna contas analíticas ativas cujos account_group ∈ {3,4,5}, SEM mapping vigente
  em $asOf (default today).
- Eager load default_management_class para permitir "usar sugestão" (§arquitetura §5).
- Usado pela tela de pendências e pelo badge do sidebar.

## FormRequests

`StoreDreMappingRequest`, `UpdateDreMappingRequest`, `BulkAssignDreMappingRequest`.

Mensagens PT:
- "Esta conta é sintética e não pode ser mapeada. Mapeie as contas analíticas filhas."
- "Data inicial não pode ser anterior ao último fechamento (31/01/2026)."
- "Já existe mapeamento ativo para esta conta e centro de custo nessa data."

## Controller

`App\Http\Controllers\DreMappingController` com actions:
- index, store, update, destroy (resource padrão).
- pending (GET /dre/mappings/pending) — lista pendências via UnmappedAccountsFinder,
  Inertia::render('DRE/Mappings/Pending', [...]).
- bulk (POST /dre/mappings/bulk) — bulkAssign.
- expire (PATCH /dre/mappings/{id}/expire).

## Rotas

```php
Route::prefix('dre')->name('dre.')->group(function () {
    Route::get('mappings/pending', [DreMappingController::class, 'pending'])
        ->name('mappings.pending')
        ->middleware('permission:VIEW_DRE_PENDING_ACCOUNTS');
    Route::post('mappings/bulk', [DreMappingController::class, 'bulk'])
        ->name('mappings.bulk')
        ->middleware('permission:MANAGE_DRE_MAPPINGS');
    Route::patch('mappings/{mapping}/expire', [DreMappingController::class, 'expire'])
        ->name('mappings.expire')
        ->middleware('permission:MANAGE_DRE_MAPPINGS');
    Route::resource('mappings', DreMappingController::class)
        ->names('mappings')
        ->middleware('permission:MANAGE_DRE_MAPPINGS');
});
```

## Pages React

### `Pages/DRE/Mappings/Index.jsx`

Layout:
- Header com título "Mapeamento Contábil → DRE".
- Filtros em card (expandir/colapsar): busca, account_group select, cost_center
  select, management_line select, toggle "só pendentes", toggle "só expirados".
- Botão primário "Nova Classificação" abre Form modal.
- DataTable com colunas:
  - Conta (code + name, indentação visual por classification_level).
  - CC (badge ou traço — "Qualquer CC" para mapping coringa).
  - Linha DRE (badge colorido por nature).
  - Vigência (effective_from → effective_to ou "Vigente").
  - Ações: editar, duplicar, expirar.
- Click em row abre detail modal via useModalManager.

### `Pages/DRE/Mappings/Form.jsx` (modal compartilhado Create/Edit/Duplicate)

Campos:
- AccountAutocomplete (Components/DRE/AccountAutocomplete.jsx — ver abaixo).
  Busca por code/reduced_code/name. Filtra só analíticas ativas.
- CostCenterAutocomplete com opção explícita "Deixar vazio = vale para qualquer CC".
- ManagementLinePicker (Components/DRE/ManagementLinePicker.jsx).
- effective_from (date, default today+1), effective_to (date nullable).
- notes (textarea).
- BOTÃO "Usar sugestão" — aparece se a conta selecionada tem default_management_class_id
  mas não corresponde a uma linha DRE diretamente; nesse caso, mostra a management_class
  sugerida para o usuário considerar manualmente (é apenas um hint visual, não preenche
  automaticamente).

### `Pages/DRE/Mappings/Pending.jsx`

Tela de fila de trabalho. Layout diferente do Index:
- StatisticsGrid no topo: "Contas pendentes" (contador), "Pendentes em grupo 3 (Receitas)",
  "Grupo 4 (Despesas)", "Grupo 5 (Resultado)".
- Botão "Classificar selecionadas em lote" (habilita com seleção > 0).
- DataTable com selectable=true:
  - Conta (code + name + dre_group badge).
  - Sugestão do Action Plan (se default_management_class existe): badge com nome +
    botão "Usar sugestão" que abre Form modal pré-preenchido.
  - Ação rápida: "Classificar agora" abre Form modal.
- Bulk modal: Components/DRE/BulkAssignModal.jsx — recebe accountIds selecionados, pede
  CC (opcional), linha DRE, vigência, notes. Submete para /dre/mappings/bulk.

### Componentes compartilhados

`Components/DRE/AccountAutocomplete.jsx`:
- Debounce 300ms. Fetch GET /dre/chart-of-accounts/search?q=...&type=analytical
  (criar esse endpoint leve no controller ChartOfAccountsController — read-only).
- Render mostra code monospace + name truncado + account_group badge.

`Components/DRE/ManagementLinePicker.jsx`:
- Carrega listForPicker() na montagem. Select agrupado visualmente por subtotais
  intermediários. Exibe label_1 + nature badge.

`Components/DRE/BulkAssignModal.jsx`:
- StandardModal. Form com AccountsList (readonly, mostra N contas selecionadas),
  CC opcional, ManagementLinePicker, vigência, notes.
- Confirmação: "Mapear {N} contas para {LinhaDRE}?"
- Após submit, mostra resumo com falhas (se houver).

## Badge do sidebar

No AuthenticatedLayout (ou onde mora o menu), item "DRE" ganha badge numérico
vermelho se UnmappedAccountsFinder::find()->count() > 0. Valor injetado via
shared props do Inertia (HandleInertiaRequests@share).

## Testes

`tests/Feature/DRE/MappingControllerTest.php`:
1. Criar mapping para conta sintética → 422 PT.
2. Criar mapping sobreposto → 422 PT.
3. Criar mapping com effective_from <= lastClosedUpTo → 422 PT.
4. Bulk assign 50 contas → todas criadas, contagem OK.
5. Bulk assign com 1 sintética no meio → resto criado, falha reportada.
6. findUnmappedAccounts retorna só G3/G4/G5.
7. findUnmappedAccounts exclui contas já mapeadas vigentes.
8. Sidebar badge reflete contagem.

## NÃO fazer

- Não implementar DreMappingResolver (prompt 6).
- Não implementar DreMatrixService (prompt 6).
- Não projetar OrderPayment/Sale (prompt 8).
- Não mexer em chart_of_accounts CRUD ainda (leitura via endpoint search é ok).
```

---

## Prompt 6 — Núcleo: `DreMatrixService` + `DreMappingResolver` + `DreSubtotalCalculator`

Referência: arquitetura §3, §4 (SQL), §5 (subtotais), §11 linha #6.

```
Implemente o núcleo de cálculo da DRE conforme `docs/dre-arquitetura.md §3, §4 e §5`.
Esta é a classe mais testada do módulo — a precedência de mapping e a lógica de
subtotal são onde bugs silenciosos viram números errados em relatório do CFO.

Estratégia SQL: **Opção B** (§4.2 arquitetura) — resolve mapping em PHP, agrega em SQL.
Não negociar com MySQL 8 window functions aqui. Mantém compatibilidade com SQLite
in-memory dos testes.

## DTO-like (array associativo, não classe formal — §12.2 #8)

Filter validado via `DreMatrixRequest`:
```
start_date, end_date (Y-m-d), store_ids[] (nullable),
network_ids[] (nullable), budget_version (default 'v1'),
scope (enum 'general'|'network'|'store'), include_unclassified (bool default true),
compare_previous_year (bool default true)
```

## App\Services\DRE\DreMappingResolver

Isolado. Unit-testável sem DB em cenários puros, mas recebe os mappings em RAM no
construtor para permitir teste de integração.

```
class DreMappingResolver {
    public function __construct(private array $mappings) {
        // $mappings = array of ['chart_of_account_id' => int, 'cost_center_id' => ?int,
        //                       'dre_management_line_id' => int, 'effective_from' => date,
        //                       'effective_to' => ?date]
        // Construtor indexa para lookup O(1) por account_id.
    }

    public static function loadForPeriod(Carbon $from, Carbon $to): self {
        // Carrega DreMapping::whereNull('deleted_at')
        //     ->where('effective_from', '<=', $to)
        //     ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
        //     ->get()
        //     ->map(...)
        return new self($rows);
    }

    public function resolve(int $accountId, ?int $costCenterId, Carbon $date): int {
        // Precedência: específico com effective válido > coringa com effective válido > LINE_UNCLASSIFIED_ID.
        // Implementação em §4.3 arquitetura.
    }

    public function resolveMany(array $keys): array {
        // Batch lookup. $keys = [[account_id, cost_center_id, date], ...]
        // Retorna array na mesma ordem com line_ids.
    }
}
```

Cache estático do UNCLASSIFIED_LINE_ID na primeira chamada (busca DreManagementLine
onde code='L99_UNCLASSIFIED') para não rodar query N vezes.

## App\Services\DRE\DreSubtotalCalculator

```
class DreSubtotalCalculator {
    /**
     * Recebe matriz analítica (line_id => [year_month => ['actual' => x, 'budget' => y, 'previous_year' => z]])
     * e devolve matriz completa com subtotais preenchidos conforme §5 arquitetura.
     */
    public function calculate(array $analyticalMatrix, Collection $managementLines): array {
        // 1. Para cada linha ordenada por sort_order:
        // 2.   Se is_subtotal=true:
        //        soma campos actual/budget/previous_year de todas linhas analíticas
        //        cujo sort_order <= accumulate_until_sort_order.
        //      Se is_subtotal=false:
        //        copia valor analítico (ou zera se não vem).
        // 3. Retorna array final na mesma estrutura.
    }
}
```

Motivação isolada (§5 arquitetura): comentário em PT no topo referenciando
`FILTER(ALL(D_Contabil), D_Contabil[Ordem] <= ordem_atual)` do DAX original.

## App\Services\DRE\DreMatrixService

Orquestrador.

```
public function matrix(array $filter): array {
    // 1. Autoriza escopo: aplica $this->restrictStores($filter) validando user->allowedStores().
    // 2. Lê mapping em RAM: $resolver = DreMappingResolver::loadForPeriod(...);
    // 3. 3 queries agregadas paralelas (actual período, budget período, actual período -1 ano):
    //    SELECT chart_of_account_id, cost_center_id, YEAR(entry_date), MONTH(entry_date),
    //           SUM(amount)
    //    FROM dre_actuals (ou dre_budgets)
    //    WHERE entry_date BETWEEN :from AND :to
    //      AND store_id IN (:store_ids) -- conforme scope
    //    GROUP BY chart_of_account_id, cost_center_id, y, m
    // 4. Itera resultados, resolve line_id via $resolver, acumula em matriz analítica.
    // 5. Chama DreSubtotalCalculator.
    // 6. Se $filter['include_unclassified']=true, mantém linha UNCLASSIFIED; senão remove.
    // 7. Se scope=NETWORK ou STORE, adiciona agrupamento extra (retorna matriz por network_id/store_id).
    // 8. Retorna estrutura: [
    //      'lines' => [[sort_order, code, level_1, is_subtotal, nature, months[1..12]{actual,budget,previous_year,variance}], ...],
    //      'totals' => [actual, budget, previous_year],
    //      'generated_at' => now()->toIso8601String(),
    //    ]
}

public function drill(int $lineId, array $filter): array {
    // Para uma linha específica, retorna lista de (chart_of_account.code, cost_center.code, total)
    // que contribuem para ela. Usa mesmo resolver.
    // Útil para o StandardModal de drill-through da matriz.
}

public function kpis(array $filter): array {
    // Reusa matrix() internamente. Extrai linhas-chave via code ou sort_order conhecido.
    // Calcula % margens. Retorna estrutura para StatisticsGrid.
}
```

Importante: respeitar fechamento (§2.8 arquitetura). Se qualquer parte do $filter cair
em período fechado, ler do snapshot via DrePeriodClosingService (o prompt 11 implementa
o service; aqui, interface `ClosedPeriodReader` injetada permite mock; implementação real
vem no prompt 11).

## Testes unitários em tests/Unit/Services/DRE/

`DreMappingResolverTest.php` — COBRIR COMPLETO (§4.3 arquitetura):
1. Só específico → específico.
2. Só coringa → coringa.
3. Ambos existem → específico ganha.
4. Nenhum → UNCLASSIFIED_LINE_ID.
5. Específico expirou na data → cai em coringa.
6. 2 específicos em períodos adjacentes → cada data pega o certo.
7. Overlap inválido → no resolver, pega o primeiro (validação é no service).
8. account_id desconhecido → UNCLASSIFIED.

`DreSubtotalCalculatorTest.php`:
1. Subtotal simples: soma 2 analíticas.
2. Subtotal NÃO inclui outros subtotais (teste #3 do plano antigo — caso clássico).
3. Subtotal só soma linhas com sort_order <= accumulate_until_sort_order, ignorando
   posteriores.
4. Linha analítica sem valor → 0.
5. EBITDA acumulando 1..13 E Lucro Líquido acumulando 1..17 não duplicam.
6. previous_year e budget seguem mesma lógica.

`DreMatrixServiceTest.php` (com RefreshDatabase, SQLite in-memory):
1. End-to-end: 5 lançamentos → matriz correta com 1 subtotal.
2. Lançamento sem mapping cai em UNCLASSIFIED.
3. Mapping coringa captura quando não há específico.
4. Mapping específico ganha do coringa.
5. Filtro store_ids isola corretamente.
6. previous_year pega mesmo período -1 ano.
7. budget_version isola (criar v1 e v2 iguais, filtrar por v1).
8. scope='network' agrupa por network_id (1 network com 2 lojas).
9. scope='store' agrupa por store_id.
10. Lançamento em período fechado usa ClosedPeriodReader mockado.
11. include_unclassified=false remove linha fantasma.
12. Drill retorna contas analíticas contribuintes corretas.

## NÃO fazer

- Não criar controller (prompt 7).
- Não criar React matriz (prompts 7 stub, 9 completo).
- Não implementar cache (prompt 12).
- Não implementar fechamento real (prompt 11). Usar interface injetada.
```

---

## Prompt 7 — Camada HTTP da matriz + stub da page React

Referência: arquitetura §7.1, §7.2, §11 linha #7.

```
Expor a matriz DRE via HTTP com stub de page React conforme `docs/dre-arquitetura.md §7`.

## FormRequest

`App\Http\Requests\DRE\DreMatrixRequest`:
- Regras: start_date e end_date (required, date_format:Y-m-d, after_or_equal entre si),
  store_ids (array nullable, integer, exists:stores,id), network_ids (array nullable,
  integer, exists:networks,id), budget_version (string nullable), scope (in:general,network,store
  default general), include_unclassified (bool default true), compare_previous_year
  (bool default true).
- Mensagens PT: "A data final deve ser maior ou igual à inicial.", etc.
- authorize(): valida que user tem VIEW_DRE; em scope='store', filtra store_ids ao
  allowedStores() (403 se tentou forçar loja fora).

## Controller

`App\Http\Controllers\DreMatrixController`:
- `show(DreMatrixRequest $request)` — Inertia::render('DRE/Matrix', [
    'filters' => $request->validated(),
    'matrix' => $service->matrix($request->validated()),
    'kpis' => $service->kpis($request->validated()),
    'availableStores' => auth()->user()->allowedStores()->get(['id','code','name','network_id']),
    'availableNetworks' => Network::orderBy('name')->get(['id','name']),
    'availableBudgetVersions' => DreBudget::distinct('budget_version')->pluck('budget_version'),
    'closedPeriods' => DrePeriodClosing::orderByDesc('closed_up_to_date')->take(12)->get(),
  ]).
- `drill(DreMatrixRequest $request, DreManagementLine $line)` — response()->json(
    $service->drill($line->id, $request->validated())).

## Rotas

```php
Route::prefix('dre')->name('dre.')->middleware('tenant.module:dre')->group(function () {
    Route::get('matrix', [DreMatrixController::class, 'show'])
        ->name('matrix.show')->middleware('permission:VIEW_DRE');
    Route::get('matrix/drill/{line}', [DreMatrixController::class, 'drill'])
        ->name('matrix.drill')->middleware('permission:VIEW_DRE');
});
```

## Registro do módulo

- `config/modules.php` ganha entrada `dre` conforme §arquitetura 8.5.
- Seed em `tenant_modules` para ativar em tenants específicos (ver memória
  module_registration_gotchas).
- Menu sidebar via migration central adicionando em central_menus (posição após Budgets).

## Page React stub

`resources/js/Pages/DRE/Matrix.jsx` — apenas validação de wiring:

```jsx
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Matrix({ filters, matrix, kpis, availableStores, availableNetworks, availableBudgetVersions, closedPeriods }) {
    return (
        <AuthenticatedLayout>
            <Head title="DRE Gerencial" />
            <div className="p-6">
                <h1 className="text-2xl font-semibold mb-4">DRE Gerencial</h1>
                <p className="text-sm text-gray-600 mb-4">
                    Esta página será implementada no prompt 9. Hoje apenas confere o wiring.
                </p>
                <pre className="bg-gray-50 p-4 text-xs overflow-auto max-h-96">
                    {JSON.stringify({ filters, matrix, kpis, availableStores: availableStores?.length, closedPeriods: closedPeriods?.length }, null, 2)}
                </pre>
            </div>
        </AuthenticatedLayout>
    );
}
```

## Testes

`tests/Feature/DRE/MatrixControllerTest.php`:
1. Não autenticado → 302 /login.
2. Autenticado sem VIEW_DRE → 403.
3. Autenticado com VIEW_DRE → 200, estrutura Inertia correta.
4. end_date < start_date → 422 PT.
5. scope='store' com store_ids fora de allowedStores → 403 (não filtra silenciosamente
   neste módulo — preferimos erro visível).
6. Tenant sem módulo dre → 403 (middleware tenant.module).
7. Drill endpoint retorna JSON com estrutura esperada.

## NÃO fazer

- Não implementar a UI completa da matriz (prompt 9).
- Não implementar export (prompt 13).
- Não implementar cache (prompt 12).
```

---

## Prompt 8 — Projetores de `OrderPayment` e `Sale` para `dre_actuals` + rebuild command

Referência: arquitetura §2.5 (projetores), §11 linha #8.

```
Alimentar dre_actuals via observers em OrderPayment e Sale conforme
`docs/dre-arquitetura.md §2.5`.

## Pré-requisitos

- Ler `app/Models/OrderPayment.php` inteiro antes de começar. Confirmar campos:
  status (enum), accounting_class_id (será chart_of_account_id após prompt 1),
  cost_center_id, store_id, competence_date, total_value, number_nf, description.
- Ler `app/Models/Sale.php` inteiro. Confirmar: store_id, date_sales, total_sales.

## App\Services\DRE\OrderPaymentToDreProjector

```
class OrderPaymentToDreProjector {
    public function project(OrderPayment $op): ?DreActual {
        // Retorna null se status != 'done'.
        // Upsert em dre_actuals por (source_type=App\Models\OrderPayment, source_id=$op->id).
        // amount = convertSign($op->total_value, $op->chartOfAccount->account_group).
        // Campos: entry_date=$op->competence_date, chart_of_account_id, cost_center_id,
        //         store_id, document=$op->number_nf, description=$op->description,
        //         source=DreActualSource::ORDER_PAYMENT, source_type, source_id.
        // Marca reported_in_closed_period=true se entry_date <= DrePeriodClosing::lastClosedUpTo().
    }

    public function unproject(OrderPayment $op): void {
        // Remove dre_actual correspondente.
    }

    public function rebuild(): RebuildReport {
        // Truncate onde source=ORDER_PAYMENT. Re-project todos OrderPayment::where('status','done').
        // Em chunks de 500. Usado pelo command dre:rebuild-actuals.
    }

    private function convertSign(float $amount, int $accountGroup): float {
        // G3 (Receitas) positivo. G4, G5 negativo. G1, G2 inválido (lança exception com mensagem PT).
    }
}
```

## App\Services\DRE\SaleToDreProjector

```
class SaleToDreProjector {
    public function project(Sale $sale): DreActual {
        // chart_of_account_id = $sale->store->sale_chart_of_account_id ?? config('dre.default_sale_account_id').
        // Se ambos null, log warning e SKIP (não quebra a criação do Sale).
        // cost_center_id = null (Sales não têm CC).
        // amount = +abs($sale->total_sales).
        // entry_date = $sale->date_sales.
        // source = DreActualSource::SALE.
    }

    public function unproject(Sale $sale): void { /* ... */ }
    public function rebuild(): RebuildReport { /* ... */ }
}
```

## Observers

`App\Observers\OrderPaymentDreObserver` — registrado no AppServiceProvider:
- `saving`: se status=done agora e estava diferente antes → project().
- `saving`: se status != done agora mas era done antes → unproject().
- `updating` com status=done: se campos relevantes mudam (chart_of_account_id,
  cost_center_id, store_id, competence_date, total_value) → project() (upsert substitui).
- `deleting`: se status=done → unproject().

CUIDADO: o OrderPayment pode ter outros observers no projeto. NÃO sobrescrever; adicionar
o novo observer ao array de observers do model (ver padrão em app/Observers/).

`App\Observers\SaleDreObserver`:
- `created`: project().
- `updating` em campos relevantes: re-project.
- `deleted`: unproject().

## Comando artisan

`dre:rebuild-actuals {--source=}`:
- Com --source=ORDER_PAYMENT ou SALE ou all (default all).
- Invoca rebuild() dos projetores. Output PT com progress bar.
- Confirmação obrigatória antes de rodar (--force pula em script).

Scheduled em routes/console.php: `dre:rebuild-actuals --source=ORDER_PAYMENT --force`
domingo 03:00 (defensiva, caso observer tenha falhado em algum saving).

## Seed do fallback de sale_chart_of_account_id

Migration separada `seed_default_sale_chart_of_account`:
- Cria config option em config/dre.php: 'default_sale_account_code' => '3.1.1.01.00001'.
- Em boot, o SaleToDreProjector resolve o id uma vez e cacheia.
- Para lojas sem sale_chart_of_account_id, usa o default.

Não é database config (evita migration só pra isso). Se time quiser mudar, altera
config + deploy.

## Testes

`tests/Feature/Projectors/OrderPaymentProjectorTest.php`:
1. OrderPayment com status=pending → nenhum dre_actual criado.
2. Transita para done → 1 dre_actual criado com source correto.
3. Reverte para pending → dre_actual removido.
4. Atualiza competence_date em OP done → dre_actual atualizado.
5. Deleta OP done → dre_actual removido.
6. Conta de grupo G1/G2 → exception com mensagem PT (não projeta).
7. Convenção de sinal: G3 positivo, G4 negativo.
8. reported_in_closed_period=true quando entry_date cai em período fechado.

`tests/Feature/Projectors/SaleProjectorTest.php`:
1. Sale criada em loja com sale_chart_of_account_id → dre_actual com aquela conta.
2. Sale em loja sem a config → usa fallback do config/dre.php.
3. Sale em loja sem config E fallback inexistente → SKIP com log warning (sem quebrar).

`tests/Feature/Commands/RebuildActualsCommandTest.php`:
1. Rebuild de ORDER_PAYMENT sincroniza. Popula actuals pra todos OP done.
2. Rebuild all executa ambas fontes.

## NÃO fazer

- Não tocar em frontend. UI continua stub (prompt 9).
- Não implementar import manual (prompt 10).
- Não implementar snapshot de fechamento (prompt 11).
```

---

## Prompt 9 — Frontend completo da matriz + drill + filtros + export

Referência: arquitetura §9.3 (UI matriz), §11 linha #9.

```
Implementar UI completa da matriz conforme `docs/dre-arquitetura.md §9.3`.
Stub do prompt 7 vira tela real.

## Pages

### `Pages/DRE/Matrix.jsx`

Layout conforme §9.3:
- Header: "DRE Gerencial" + botões Exportar XLSX (prompt 13), Exportar PDF (prompt 13).
- Seletor de nível (Geral / Rede / Loja) — radio buttons estilizados (usar Headless UI
  RadioGroup, padrão do projeto).
- Filtros (linha abaixo): DateRange period, MultiSelect lojas (condicional se
  scope=store ou network), Select budget_version, Checkbox "Comparar com ano anterior",
  Toggle "Mostrar não classificadas".
- Todos os filtros sincronizados com URL via router.get com preserveState=true e
  preserveScroll=true.
- Banner amarelo se algum mês do filtro está fechado: "{mês}/{ano} está fechado
  (valores são snapshot imutável)".
- StatisticsGrid com 4 cards KPIs: Faturamento Líquido, EBITDA, Margem Líquida (%),
  Não Classificado (destacado vermelho se > 0). Valores vêm de `kpis` prop.
- Tabs Headless UI: "Matriz Mensal", "Consolidado Ano", "Gráficos" (prompt 13).

### Components DRE

#### `Components/DRE/MatrixTable.jsx`

Tabela com sticky headers verticalmente e horizontalmente:
- Coluna esquerda fixa: "Linha" (com ícone → se expansível; código em monospace pequeno
  + label em tamanho normal).
- 12 colunas mensais + Total + Orçado + %Ating + (condicional) Ano Anterior + Var%.
- Subtotais: bold, fundo gray-50, borda superior mais escura.
- Unclassified line: vermelho se valor > 0; sumida se = 0 (mesmo quando
  include_unclassified=true no filtro, UI pode esconder o zero).
- Click em célula mensal não-subtotal → abre drill modal.
- Valores negativos em vermelho, formatados com useMasks.maskMoney.
- Variações: setas ↑↓ em verde/vermelho conforme nature da linha (favorável/desfavorável).

#### `Components/DRE/DrillModal.jsx`

StandardModal.Section:
- Contexto: "{Linha DRE} — {mês}/{ano} — Loja/Rede: {scope descriptor}".
- DataTable leve com:
  - Conta (code + name).
  - CC.
  - Documento / Descrição.
  - Valor.
  - Link "Ver origem" que abre em nova aba o OrderPayment/Sale (resolvendo por source_type).

#### `Components/DRE/KpiCards.jsx`

Reutiliza StatisticsGrid do projeto. Cada card:
- Título PT, valor formatado, delta vs budget e delta vs ano anterior (StatusBadge
  verde/vermelho conforme classifyVariance).

### Helpers

`resources/js/lib/dre.js`:
- `formatCurrency(value)` — usa useMasks.maskMoney (wrapper pra facilitar).
- `formatPercentage(value, decimals = 1)`.
- `classifyVariance(value, lineNature)` → 'favorable' | 'unfavorable' | 'neutral'.
  Regra: receita com variação positiva = favorável; despesa com variação positiva =
  desfavorável.
- `monthLabel(month)` → 'Jan', 'Fev'... PT.
- `yearMonthKey(date)` → 'YYYY-MM'.

## Regras obrigatórias

- Filtros via URL. Nunca localStorage/sessionStorage.
- Expansão de drill em state local (useState).
- Acessibilidade: cells clicáveis com role="button" + aria-label.
- PT com acentuação completa ("Março", "Maio", "Período", "Orçado", "Classificação").
- Usar recharts para eventuais mini-sparklines nos KpiCards (fora do escopo principal).
- ~20 linhas × 14 colunas = não precisa virtualização.

## Testes manuais

1. /dre/matrix abre sem filtros → DRE do mês atual.
2. Trocar scope=Loja exibe MultiSelect de lojas.
3. Click em célula de "(-) Despesas Gerais" abre drill com contas contribuintes.
4. Alternar "Comparar com ano anterior" faz fetch novo e mostra colunas A.A.
5. Banner aparece quando período filtrado tem mês fechado.

## NÃO fazer

- Não implementar export (prompt 13).
- Não implementar cache (prompt 12).
- Não implementar import manual (prompt 10).
- Não alterar scroll behavior fora do padrão Inertia.
```

---

## Prompt 10 — Importação manual de actuals/budgets + BudgetToDreProjector

Referência: arquitetura §3 (importers), §2.6 (projetor), §11 linha #10.

```
Importação manual + ponte Budgets → DRE conforme `docs/dre-arquitetura.md §2.6 e §3`.

## App\Services\DRE\DreActualsImporter

- Lê XLSX com colunas: entry_date, store_code, account_code, cost_center_code (opt),
  amount, document (opt), description (opt), external_id (opt).
- Valida cada linha:
  - store_code existe em stores.code → resolve store_id.
  - account_code existe e é analytical.
  - cost_center_code se preenchido existe.
  - amount numérico.
  - entry_date > DrePeriodClosing::lastClosedUpTo() (§arquitetura 2.8).
- Converte sinal conforme account_group.
- Upsert por (source=MANUAL_IMPORT, external_id) quando external_id presente.
- Chunks de 500. Erros acumulados (não para no primeiro).

## App\Services\DRE\DreBudgetsImporter

Análogo, tabela dre_budgets. budget_version vem do form (label informado).

## App\Services\DRE\BudgetToDreProjector

```
public function project(BudgetUpload $upload): ProjectReport {
    // Só projeta se $upload->is_active=true.
    // DELETE WHERE budget_upload_id = <anterior_ativo_mesmo_scope> (desativa versão antiga).
    // Explode cada budget_item em 12 linhas (uma por month_XX_value não-zero).
    // Converte sinal por account_group.
    // budget_version = $upload->version_label.
}
```

Observer em BudgetUpload: saved ou updated com is_active flipping → invoca project() em
job assíncrono (ProjectBudgetToDreJob).

## Jobs

- `App\Jobs\DRE\ImportDreActualsJob`, `App\Jobs\DRE\ImportDreBudgetsJob`,
  `App\Jobs\DRE\ProjectBudgetToDreJob`.
- Progresso em Cache (key dre:import:{user_id}:{job_id}).

## Controllers e rotas

`DreImportController`:
- chart (implementado prompt 2 — estender com polling de status).
- actuals (POST /dre/imports/actuals).
- budgets (POST /dre/imports/budgets).
- status (GET /dre/imports/{jobId}/status) → JSON com progresso.

## Permissions novas

- `IMPORT_DRE_ACTUALS`
- `IMPORT_DRE_BUDGETS`

Atribuir conforme §arquitetura 8.2.

## Pages

`Pages/DRE/Imports/Actuals.jsx`, `Budgets.jsx`, `Chart.jsx` (completar).
- Upload drag-drop (componente próprio se não existir; padrão Maatwebsite input se
  existir no projeto).
- Polling status a cada 2s enquanto "processing".
- Tabela de erros em PT: "Linha 47: conta '1.1.1.01.99999' não encontrada no plano."
  "Linha 89: entry_date dentro de período fechado (2026-01-31)."

## Formatos

Documentar em `docs/dre-imports-formatos.md`:
- dre-actuals.xlsx: colunas obrigatórias e opcionais, convenção de sinal.
- dre-budgets.xlsx: idem.
- Exemplos de 5 linhas para cada.

## Testes

`tests/Feature/Imports/DRE/DreActualsImportTest.php`:
1. Upload válido cria linhas.
2. Conta inexistente → erro PT, linha pulada.
3. Conta sintética → erro PT.
4. entry_date em período fechado → erro PT, linha pulada.
5. Re-import com external_id igual → upsert.
6. Observer de BudgetUpload invalida cache.

`tests/Feature/Projectors/BudgetToDreProjectorTest.php`:
1. Ativar BudgetUpload projeta todas as linhas.
2. Ativar novo upload do mesmo ano/scope remove o anterior.
3. Convenção de sinal aplicada.

## NÃO fazer

- Não importar Action Plan (prompt 10.5).
- Não implementar period closings (prompt 11).
```

---

## Prompt 10.5 — Importar `Action Plan v1.xlsx` como primeiro `dre_budgets` 2026

Referência: arquitetura §decisões (Action Plan), §11 linha #10.5.

```
Import específico do primeiro orçamento 2026 conforme arquitetura.

## App\Services\DRE\ActionPlanImporter

- `import(string $path, string $budgetVersionLabel = 'action_plan_v1'): ActionPlanReport`
- Lê 3861 linhas do XLSX. Estrutura: loja × conta × classe_gerencial × mês.
- Resolve FKs: stores.code, chart_of_accounts.code, management_classes.code.
- Insere em dre_budgets com budget_version='action_plan_v1'.
- Idempotente: chave de upsert `(entry_date, chart_of_account_id, cost_center_id, store_id,
  budget_version)`. Re-rodar = upsert, sem duplicatas.
- Linhas com FK não encontrada → acumula erros. Não rompe o import.

## Command

`php artisan dre:import-action-plan {--file=} {--version=action_plan_v1} {--dry-run}`:
- Path default: `storage/app/imports/Action Plan v1.xlsx`.
- Output em PT: "Processando 3861 linhas... 3715 importadas, 146 com erros (ver relatório)."

## Testes

1. Fixture pequena importa.
2. Re-import idempotente.
3. Nova versão (--version=action_plan_v2) coexiste com v1.
4. Linha com store_code inexistente → erro PT + skip.

## NÃO fazer

- Não é rota HTTP (execução via SSH/queue). Se o CFO quiser UI, vira request futura.
```

---

## Prompt 11 — Fechamento de períodos + snapshots + enforcement + reabertura

Referência: arquitetura §2.8, §11 linha #11.

```
Implementar fechamento com imutabilidade via snapshot conforme §2.8 arquitetura.

## App\Services\DRE\DrePeriodClosingService

```
public function close(Carbon $closedUpToDate, User $closedBy, ?string $notes): DrePeriodClosing {
    // Valida: data >= último fechamento + 1 dia.
    // Cria DrePeriodClosing.
    // Para cada (scope, scope_id, year_month, line) no período: computa matriz live e
    // insere snapshot em dre_period_closing_snapshots.
    //   - scope GENERAL: 1 linha por (year_month × line).
    //   - scope NETWORK: 1 linha por (network_id × year_month × line).
    //   - scope STORE: 1 linha por (store_id × year_month × line).
    // Em transaction. Se falhar em qualquer snapshot, rollback completo.
    // Incrementa cache version.
}

public function reopen(DrePeriodClosing $closing, User $reopenedBy, string $reason): ReopenReport {
    // Obriga reason non-empty.
    // Computa diff: matriz live atual × snapshot existente. Retorna lista de diferenças
    //   por (scope, line, year_month): [snapshot_actual, current_actual, delta].
    // Marca DrePeriodClosing como reaberto (reopened_at, reopened_by, reopen_reason).
    // Deleta snapshots do fechamento.
    // Dispara e-mail pra users com MANAGE_DRE_PERIODS informando diffs (se houver).
    // Retorna ReopenReport com diffs.
}
```

## ClosedPeriodReader (interface usada pelo DreMatrixService)

Implementação concreta `App\Services\DRE\DrePeriodSnapshotReader`:
- Recebe filter e devolve matriz lida do snapshot para meses fechados.
- DreMatrixService mescla: meses fechados (snapshot) + meses abertos (live).

## Enforcement em services existentes

- `DreMappingService::create/update/delete/expire` → lança ValidationException PT se
  effective_from <= lastClosedUpTo. Já preparado no prompt 5, aqui testamos de verdade
  com DrePeriodClosing existente.
- `DreActualsImporter` → bloqueia entry_date <= lastClosedUpTo em source=MANUAL_IMPORT.
- `DreBudgetsImporter` → idem.
- Projetores (OrderPayment/Sale) → continuam projetando (fonte canônica), mas marcam
  reported_in_closed_period=true e NÃO afetam snapshot.

## Controller

`DrePeriodClosingController`:
- index (GET /dre/periods).
- store (POST /dre/periods — fechar).
- reopen (PATCH /dre/periods/{id}/reopen).

## Rotas

```php
Route::resource('dre/periods', DrePeriodClosingController::class)
    ->names('dre.periods')
    ->middleware('permission:MANAGE_DRE_PERIODS');
Route::patch('dre/periods/{period}/reopen', [DrePeriodClosingController::class, 'reopen'])
    ->name('dre.periods.reopen')
    ->middleware('permission:MANAGE_DRE_PERIODS');
```

## Permission

Adicionar `MANAGE_DRE_PERIODS` (apenas ADMIN + SUPER_ADMIN).

## Page React

`Pages/DRE/Periods/Index.jsx`:
- DataTable com fechamentos passados (closed_up_to_date, closed_by, closed_at,
  reopened_at nullable, reason).
- Botão "Fechar mês atual" com ConfirmDialog ("Isso criará snapshot imutável dos
  valores até {data}. Você pode reabrir depois. Confirma?").
- Botão "Reabrir último fechamento" (só no último não reaberto) com modal pedindo
  justificativa obrigatória + preview de diffs (fetch preview antes de confirmar).

## Notification

`App\Notifications\DrePeriodReopenedNotification`:
- Enviada via mail para users com MANAGE_DRE_PERIODS.
- Conteúdo: quem reabriu, data, reason, lista de diffs (consolidada por linha/mês).

## Testes

1. Close: cria DrePeriodClosing + N snapshots. Contagem confere.
2. Matriz de período fechado lê do snapshot (criar cenário: após close, adicionar
   dre_actual retroativo, matriz deve manter valor antigo).
3. Matriz híbrido (meio fechado, meio aberto) combina corretamente.
4. DreMappingService.create com effective_from <= lastClosed → bloqueado.
5. Importer manual com entry_date <= lastClosed → erro PT.
6. Projetor de OrderPayment em período fechado: projeta, marca
   reported_in_closed_period=true, NÃO altera snapshot.
7. Reopen exige reason → 422 sem reason.
8. Reopen dispara notification para MANAGE_DRE_PERIODS com diffs.
9. Reopen deleta snapshots do período.
10. Re-close após reopen cria snapshots atualizados.

## NÃO fazer

- Não implementar workflow de aprovação (§12.4 #19 — fora do MVP).
- Não implementar export (prompt 13).
```

---

## Prompt 12 — Cache (version key) + warm-up command + schedule

Referência: arquitetura §6, §11 linha #12.

```
Cache com version key (driver database não suporta tags) conforme `docs/dre-arquitetura.md §6`.

## Implementação

- Chave global `dre:cache_version` (int, começa em 1).
- Helper `App\Support\DreCacheVersion::current()` / `invalidate()`.
- `DreMatrixService::matrix()` wrapea em Cache::remember('dre:matrix:v' .
  DreCacheVersion::current() . ':' . md5(json_encode(normalizedFilter)), 600, fn() =>
  $this->compute($filter)).
- Normalizar filter antes do hash: sort stores_ids, format dates Y-m-d, sort keys.

## Observers de invalidação

Cada saved/deleted dispara DreCacheVersion::invalidate():
- DreMapping
- DreManagementLine
- DreActual
- DreBudget
- DrePeriodClosing
- BudgetUpload
- ChartOfAccount (quando default_management_class_id muda)

Criar trait `InvalidatesDreCacheOnChange` aplicada nesses models. Lean — observer roda
post-save com `->unless($model->wasChanged(['updated_at']))` para evitar thrashing.

## Warm-up command

`php artisan dre:warm-cache`:
- Calcula matriz para: mês atual + 11 meses anteriores + cada loja ativa (ou só general
  + cada network se preferir — decidir com §arquitetura 6 warm-up).
- Chamado no scheduler diário às 05:50 (antes do movements:sync das 06:00).

Adicionar em routes/console.php:
```php
Schedule::command('dre:warm-cache')
    ->dailyAt('05:50')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/dre-warm.log'));
```

## Testes

1. Cache hit: primeira chamada computa, segunda vem do cache (mock Cache::remember).
2. Salvar DreMapping → DreCacheVersion incrementa.
3. Normalizer: mesmos filtros em ordens diferentes geram mesma chave.
4. Warm-up command não quebra sem dados.

## NÃO fazer

- Não introduzir Redis (projeto usa database).
- Não tagear (driver não suporta).
```

---

## Prompt 13 — Gráficos, StatisticsGrid + export XLSX/PDF + polimento

Referência: arquitetura §9.6, §11 linha #13.

```
Entregas finais de UX e export conforme `docs/dre-arquitetura.md §9.6`.

## Frontend — Tab "Gráficos" em Matrix.jsx

Recharts:
- BarChart empilhada: Receita × Despesa × EBITDA por mês (12 barras).
- LineChart: evolução de margens (realizado × orçado × ano anterior).
- PieChart: distribuição de despesas por linha DRE no período.

## Export XLSX

`App\Exports\DRE\DreMatrixExport`:
- WithMultipleSheets. Abas: "Matriz Geral", "Matriz por Rede", "Matriz por Loja",
  "KPIs", "Detalhamento".
- Styles conforme convenção do projeto (verificar BudgetExport para reutilizar).
- Cabeçalho com metadata (período, filtros aplicados, gerado em, usuário).

## Export PDF

Via dompdf (pacote já instalado):
- Template em resources/views/exports/dre/matrix.blade.php.
- Layout A4 landscape.

## Controller actions

- `DreMatrixController@exportXlsx` (GET /dre/matrix/export/xlsx)
- `DreMatrixController@exportPdf` (GET /dre/matrix/export/pdf)

Middleware permission:EXPORT_DRE.

## Polimento

- StatusBadge coerente em toda UI do módulo.
- EmptyState em listas vazias (ex: Pendências com 0 itens → mensagem
  "Todas as contas estão mapeadas. 🎉").
- LoadingSpinner em drill modal.

## Testes

- Export XLSX gera arquivo válido (Maatwebsite \assertDownloaded).
- Export PDF renderiza sem erro.
- Permissions aplicadas.

## NÃO fazer

- Não reinventar componentes — usar os do projeto.
```

---

## Prompt 14 — Testes de integração ponta-a-ponta + seed realista

```
Testes E2E + seed realista para ambiente local conforme `docs/dre-arquitetura.md §11 linha #14`.

## Testes de integração

`tests/Feature/DRE/EndToEndTest.php`:
1. Cenário completo: importar plano de contas → criar mappings → criar OrderPayment →
   observer projeta → matriz DRE mostra valor correto.
2. Cenário de fechamento: close período → dre_actual retroativo → matriz histórica
   imutável → reopen → diff reportado.
3. Cenário multi-loja: Sales em 3 lojas → scope=Loja isola; scope=Rede agrega.
4. Cenário budget: ativar BudgetUpload → projeta em dre_budgets → matriz mostra
   coluna Orçado.

## Seed realista

`database/seeders/DreDevSeeder.php` (não auto-registra — use via
`php artisan db:seed --class=DreDevSeeder`):
- Usa factories.
- Cria 5 lojas em 2 redes.
- 50 OrderPayments distribuídos nos últimos 6 meses.
- 30 Sales.
- 3 mappings cobrindo casos (específico, coringa, expirado).
- 1 fechamento de mês passado.
- Orçamento fictício via dre_budgets.

Garante que /dre/matrix carregue com dados realistas para testar UI em dev.

## Documentação final

- `docs/dre-README.md` — one-pager descrevendo o módulo.
- Atualizar `docs/dre-arquitetura.md` se alguma decisão mudou durante execução.

## NÃO fazer

- Não adicionar features novas. Só polimento, testes, docs.
```

---

## Dicas de iteração entre prompts

**Saída grande demais:** "entregue em etapas — primeiro as migrations, depois os models,
depois factories. Confirme cada etapa."

**Padrão diferente do projeto:** "veja `app/Http/Controllers/BudgetController.php` — é
esse o padrão. Refaça nesse molde."

**Criou `.ts/.tsx`:** "o projeto NÃO usa TypeScript. Converta para `.jsx` e remova
types/interfaces. Sem PropTypes, sem JSDoc (§dre-descoberta §4.4)."

**Confusão entre `management_classes` e `dre_management_lines`:** "são entidades DIFERENTES.
`management_classes` é plano gerencial operacional (169 nós, recebe budget_items).
`dre_management_lines` é a DRE executiva (19 linhas, com subtotais). Releia
`docs/dre-arquitetura.md §1.2`."

**Tentou criar Policy:** "o projeto NÃO usa Policies. Autorização via middleware
`permission:PERM` (§arquitetura 8.3)."

**Tentou soft delete via trait Eloquent:** "NÃO. Soft delete é manual: colunas
deleted_at, deleted_by_user_id, deleted_reason (§dre-descoberta §1.3)."

**Ignorou multi-tenancy:** "migrations de negócio vão em `database/migrations/tenant/`,
não em `database/migrations/`."

**Fez mais do que pedi:** "pare. Estamos no prompt {N}. Não crie {X} ainda — está no
prompt {M}."

**Ao fim de cada prompt:** "liste pendências e o que você precisa de input meu."

**Commits:** um por prompt. Voltar 1 prompt é barato; voltar o módulo inteiro, não.

---

## Status pós-arquitetura

Pendências bloqueantes restantes (§12.1 arquitetura):
- **Lista definitiva das linhas executivas** (Headcount, Marketing e Corporativo, EBITDA
  formal, Lucro Líquido s/ Cedro) — o CFO completa via UI após prompt 4. O seed entrega
  só as 16 DRE-BR padrão do enum DreGroup.

Decisões em abertas que ainda precisam de input do usuário durante execução:
- Confirmar existência de trait `HasManualSoftDelete` (prompt 1).
- Confirmar formato esperado do export XLSX/PDF (§12.4 #22 — pedir amostra antes de
  implementar prompt 13).
- Evolução multi-CNPJ (Cedro — §12.3 #15) permanece fora do MVP.
