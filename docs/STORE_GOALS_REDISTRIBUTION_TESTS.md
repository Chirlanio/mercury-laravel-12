# Store Goals Redistribution - Regras de Negocio e Documentacao Completa

**Modulo:** Store Goals (Metas de Loja)
**Service:** `StoreGoalsRedistributionService`
**Ultima Atualizacao:** 23/01/2026
**Versao:** 2.0

---

## 1. Visao Geral

O sistema de redistribuicao de metas e responsavel por calcular e distribuir as metas individuais das consultoras com base em:

- **Nivel da consultora** (Junior, Pleno, Senior)
- **Dias efetivos trabalhados** no mes de referencia
- **Dias uteis da loja** (business_days) e feriados (non_working_days)
- **Atestados medicos** >= 10 dias
- **Dias de treinamento** (3 dias para novas contratacoes)

### 1.1 Fluxo de Dados

```
Meta da Loja (adms_store_goals)
    │
    ├── goal_meta (valor total da meta)
    ├── business_days (dias uteis no mes)
    └── non_working_days (feriados)
            │
            ▼
StoreGoalsRedistributionService::redistribute()
    │
    ├── getStoreGoal() → busca meta da loja
    ├── getEligibleConsultants() → busca consultoras com contratos ativos
    ├── calculateDistribution() → calcula metas individuais
    │       ├── Aplica ajuste proporcional de feriados
    │       ├── Calcula peso por nivel
    │       ├── Distribui proporcionalmente
    │       └── Ultimo recebe residual (arredondamento inteiro)
    └── updateIndividualGoals() → INSERT/UPDATE/DELETE no banco
            │
            ▼
Metas Individuais (adms_store_consultants_goals)
    ├── individual_goals (meta base)
    ├── super_goal (meta * 1.15)
    ├── hiper_goal (super * 1.15)
    ├── working_days (dias uteis trabalhados)
    ├── business_days (dias uteis da loja)
    └── non_working_days (feriados descontados)
```

---

## 2. Entidades Envolvidas

### Tabelas

| Tabela | Descricao |
|--------|-----------|
| `adms_store_goals` | Meta da loja por mes/ano |
| `adms_store_consultants_goals` | Meta individual de cada consultora |
| `adms_employment_contracts` | Contratos de trabalho |
| `adms_employees` | Dados das funcionarias |
| `adms_medical_certificates` | Atestados medicos |
| `tb_lojas` | Lojas (ID alfanumerico ex: 'Z424') |

### Campos Chave

```
adms_store_id        → string (alfanumerico, ex: 'Z424')
position_id = 1      → Cargo de consultora
nivel                → enum('Júnior','Pleno','Sênior') DEFAULT 'Júnior'
business_days        → Dias uteis da loja no mes (descontando feriados)
non_working_days     → Quantidade de feriados no mes
```

> **IMPORTANTE:** O sistema NAO filtra por `adms_status_employee_id`. A elegibilidade e determinada
> exclusivamente pelas datas do contrato (`date_initial` e `date_final`). Se uma consultora foi demitida,
> o `date_final` do contrato limita seus dias efetivos automaticamente.

---

## 3. Regras de Negocio

### 3.1 Elegibilidade

Uma consultora e elegivel para receber meta quando:

| Regra | Condicao |
|-------|----------|
| Contrato sobrepoe o mes | `date_initial <= ultimo_dia_mes AND (date_final IS NULL OR date_final >= primeiro_dia_mes)` |
| Cargo consultora | `position_id = 1` |
| Loja correta | `adms_store_id = storeId` (do contrato) |
| Dias uteis > 0 | Apos deducoes e ajuste proporcional, deve ter pelo menos 1 dia util |

> **Nota:** O status do funcionario (`adms_status_employee_id`) NAO e utilizado como criterio.
> A elegibilidade e determinada exclusivamente pelas datas do contrato e pelo cargo.

### 3.2 Calculo de Dias Efetivos

```
diasEfetivos = GREATEST(0,
    (data_fim_efetiva - data_inicio_efetiva + 1)
    - diasAtestado
    - diasTreinamento
)
```

Onde:
- `data_inicio_efetiva = MAX(date_initial, primeiro_dia_mes)`
- `data_fim_efetiva = MIN(IFNULL(date_final, ultimo_dia_mes), ultimo_dia_mes)`
- `diasAtestado` = soma dos dias de atestados >= 10 dias sobrepostos ao periodo
- `diasTreinamento` = 3 (se `date_initial >= primeiro_dia_mes`) ou 0

### 3.3 Deducao de Atestados

| Condicao | Acao |
|----------|------|
| `days_away >= 10` | Subtrai dias sobrepostos ao periodo efetivo |
| `days_away < 10` | **NAO** afeta a redistribuicao |
| Atestado fora do periodo | **NAO** afeta (filtrado pela sobreposicao) |

Calculo dos dias de atestado sobrepostos:
```
diasDescontados = SUM(
    DATEDIFF(
        MIN(ends_date, data_fim_efetiva),
        MAX(start_date, data_inicio_efetiva)
    ) + 1
)
```

### 3.4 Deducao de Treinamento

| Condicao | Deducao |
|----------|---------|
| Nova contratacao no mes (`date_initial >= primeiro_dia_mes`) | 3 dias |
| Contrato anterior ao mes de referencia | 0 dias |

### 3.5 Peso por Nivel

| Nivel | Peso |
|-------|------|
| Junior | 0.90 |
| Pleno | 1.00 |
| Senior | 1.15 |

### 3.6 Ajuste Proporcional de Feriados

Os dias efetivos sao convertidos em dias uteis descontando feriados proporcionalmente:

```
diasUteis = ROUND(diasEfetivos * diasUteisLoja / totalDiasMes)
```

Onde:
- `diasEfetivos` = resultado do calculo 3.2 (dias de calendario efetivos)
- `diasUteisLoja` = `business_days` da tabela `adms_store_goals`
- `totalDiasMes` = total de dias do mes (ex: 31 para Janeiro)

**Exemplo:** Janeiro/2026 com 1 feriado (business_days=30):
- Consultora mes completo: `ROUND(31 * 30 / 31)` = 30 dias uteis
- Consultora demitida dia 22: `ROUND(22 * 30 / 31)` = 21 dias uteis
- Consultora admitida dia 10 com treinamento: efetivos=19, `ROUND(19 * 30 / 31)` = 18 dias uteis

### 3.7 Formula de Distribuicao

```
pesoConsultora = (pesoNivel * diasUteis) / diasUteisLoja
proporcao = pesoConsultora / somaPesos
metaIndividual = ROUND(metaLoja * proporcao)   ← inteiro (sem casas decimais)
superMeta = ROUND(metaIndividual * 1.15)        ← inteiro
hiperMeta = ROUND(superMeta * 1.15)             ← inteiro
```

> **IMPORTANTE:** Todas as metas sao arredondadas para inteiro (`round()` sem parametro de casas decimais).
> Isso garante que a exibicao com `number_format($x, 0)` na view corresponda exatamente ao valor armazenado.

### 3.8 Ajuste de Arredondamento (Residual)

A ultima consultora recebe o valor residual para garantir que a soma exata seja igual a meta da loja:
```
metaUltima = ROUND(metaLoja - somaMetasAnteriores)
```

Isso evita diferencas de arredondamento acumuladas. A soma das metas individuais e **sempre** identica a meta da loja.

### 3.9 Gatilhos de Redistribuicao

| Evento | Arquivo | Lojas afetadas | Condicao |
|--------|---------|----------------|----------|
| Criar meta de loja | `AdmsAddStoreGoals.php` | Loja da meta | Sempre |
| Editar meta de loja | `AdmsEditStoreGoal.php` | Loja da meta | Sempre |
| Cadastrar funcionario | `AdmsAddEmployee.php` | Loja do funcionario | Quando cria contrato junto |
| Admissao (contrato tipo 1) | `AdmsAddContract.php` | Loja nova + loja anterior (se diferente) | Sempre |
| Demissao (contrato tipo 2) | `AdmsAddContract.php` | Loja atual + loja anterior (se diferente) | Sempre |
| Transferencia | `AdmsAddContract.php` | Loja nova + loja anterior | Sempre |
| Edicao de contrato | `AdmsEditContract.php` | Loja atual + loja anterior (se mudou) | Sempre |
| Atestado medico (criar) | `AdmsAddMedicalCertificate.php` | Loja da consultora | Somente se `days_away >= 10` |
| Atestado medico (editar) | `AdmsEditMedicalCertificate.php` | Loja da consultora | Somente se `days_away >= 10` |

### 3.10 Restricao Temporal

- So redistribui para o **mes atual e meses futuros**
- Meses passados **NAO** sao recalculados
- Quando nao existe meta cadastrada para o periodo, retorna `true` sem fazer nada

### 3.11 Contratos como Fonte de Verdade

O sistema utiliza as datas do contrato (`date_initial` e `date_final`) como unica fonte de verdade
para determinar a participacao de uma consultora:

- **Consultora ativa:** Contrato com `date_final IS NULL` → participa ate o ultimo dia do mes
- **Consultora demitida:** Contrato com `date_final` no mes → participa ate `date_final`
- **Consultora com contrato encerrado antes do mes:** NAO aparece na query (filtrada por sobreposicao)

> **Nota:** O campo `adms_status_employee_id` NAO e consultado. Se uma consultora e demitida,
> seu contrato recebe um `date_final` que automaticamente limita seus dias efetivos.

---

## 4. Arquivos do Sistema

### 4.1 Service de Redistribuicao

| Arquivo | Responsabilidade |
|---------|-----------------|
| `app/adms/Services/StoreGoalsRedistributionService.php` | Service central de redistribuicao |

### 4.2 Models com Gatilho de Redistribuicao

| Arquivo | Responsabilidade | Metodo Service |
|---------|-----------------|----------------|
| `app/adms/Models/AdmsAddStoreGoals.php` | Criar meta e distribuir | `redistribute()` |
| `app/adms/Models/AdmsEditStoreGoal.php` | Editar meta e redistribuir | `redistribute()` |
| `app/adms/Models/AdmsAddEmployee.php` | Cadastro de funcionario + contrato | `redistributeFromContract()` |
| `app/adms/Models/AdmsAddContract.php` | Cadastro de contrato | `redistributeFromContract()` |
| `app/adms/Models/AdmsEditContract.php` | Edicao de contrato | `redistributeFromContract()` |
| `app/adms/Models/AdmsAddMedicalCertificate.php` | Cadastro de atestado | `redistributeFromMedicalLeave()` |
| `app/adms/Models/AdmsEditMedicalCertificate.php` | Edicao de atestado | `redistributeFromMedicalLeave()` |

### 4.3 Controllers

| Arquivo | Responsabilidade |
|---------|-----------------|
| `app/adms/Controllers/StoreGoals.php` | Listagem e busca |
| `app/adms/Controllers/AddStoreGoals.php` | Criar meta |
| `app/adms/Controllers/EditStoreGoal.php` | Editar meta |
| `app/adms/Controllers/DeleteStoreGoal.php` | Excluir meta |
| `app/adms/Controllers/ViewStoreGoals.php` | Visualizar meta e consultoras |

### 4.4 Models de CRUD

| Arquivo | Responsabilidade |
|---------|-----------------|
| `app/adms/Models/AdmsListStoreGoals.php` | Listagem de metas |
| `app/adms/Models/AdmsViewStoreGoals.php` | Visualizacao detalhada (CTE com metas individuais) |
| `app/adms/Models/AdmsDeleteStoreGoal.php` | Exclusao de meta |
| `app/adms/Models/AdmsStatisticsStoreGoals.php` | Estatisticas de metas |

### 4.5 Views

| Arquivo | Responsabilidade |
|---------|-----------------|
| `app/adms/Views/goals/loadStoreGoals.php` | Pagina principal |
| `app/adms/Views/goals/listStoreGoals.php` | Lista AJAX |
| `app/adms/Views/goals/viewStoreGoals.php` | Visualizacao detalhada |
| `app/adms/Views/goals/partials/_add_store_goals_modal.php` | Modal de adicao |
| `app/adms/Views/goals/partials/_view_store_goals_content.php` | Conteudo da visualizacao |
| `app/adms/Views/goals/partials/_view_store_goal_modal.php` | Modal de visualizacao |
| `assets/js/store-goals.js` | JavaScript do modulo |

### 4.6 Testes

| Arquivo | Responsabilidade |
|---------|-----------------|
| `tests/StoreGoals/StoreGoalsRedistributionServiceTest.php` | Testes do service |
| `tests/StoreGoals/Unit/WeightCalculationTest.php` | Testes de peso |
| `tests/StoreGoals/Unit/GoalDistributionTest.php` | Testes de distribuicao |
| `tests/StoreGoals/Unit/EffectiveWorkingDaysTest.php` | Testes de dias efetivos |
| `tests/StoreGoals/Unit/MedicalLeaveRulesTest.php` | Testes de atestados |
| `tests/StoreGoals/Unit/AffectedMonthsTest.php` | Testes de meses afetados |
| `tests/StoreGoals/Models/*.php` | Testes de models |
| `tests/StoreGoals/Integration/*.php` | Testes de integracao |

---

## 5. Testes Unitarios

### 5.1 StoreGoalsRedistributionService

#### Grupo: Tipos e Assinaturas

| # | Teste | Esperado |
|---|-------|----------|
| U01 | `redistribute()` aceita storeId como string | Nao gera TypeError |
| U02 | `redistributeAllActiveGoals()` aceita storeId como string | Nao gera TypeError |
| U03 | `redistributeFromContract()` aceita storeId como string | Nao gera TypeError |
| U04 | `redistributeFromMedicalLeave()` aceita storeId como string | Nao gera TypeError |
| U05 | `getEmployeeStoreId()` retorna `?string` | Tipo string ou null |
| U06 | Constante `MIN_MEDICAL_LEAVE_DAYS` = 10 | Valor 10 |
| U07 | Propriedade `performanceWeights` tem 3 niveis | Junior=0.90, Pleno=1.00, Senior=1.15 |

#### Grupo: Calculo de Dias Efetivos

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U08 | Consultora mes completo (31 dias) | date_initial=01/01, date_final=NULL, mes=01 | 31 dias |
| U09 | Consultora admitida dia 10 | date_initial=10/01, mes=01 | 22 dias (31-10+1) |
| U10 | Consultora admitida dia 10 com treinamento | date_initial=10/01, mes=01 | 19 dias (22-3) |
| U11 | Consultora demitida dia 15 | date_initial=01/01, date_final=15/01, mes=01 | 15 dias |
| U12 | Consultora com atestado 10 dias (01-10/01) | days_away=10 | 21 dias (31-10) |
| U13 | Consultora com atestado 9 dias | days_away=9 | 31 dias (NAO desconta) |
| U14 | Consultora com atestado parcial no periodo | start=25/01, ends=05/02, mes=01 | Desconta 7 dias (25-31) |
| U15 | Consultora com 2 atestados >= 10 dias | Dois periodos | Soma as deducoes |
| U16 | Nova contratacao + atestado >= 10 dias | date_initial no mes + atestado | Desconta ambos |
| U17 | Dias efetivos negativos (atestado > periodo) | atestado cobrindo todo periodo | GREATEST(0, ...) = 0 |
| U18 | Consultora ja existente (contrato anterior ao mes) | date_initial < primeiro_dia_mes | 0 dias treinamento |

#### Grupo: Calculo de Peso

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U19 | Peso Junior mes completo | 30 dias, mes 30 dias | 0.90 |
| U20 | Peso Pleno mes completo | 30 dias, mes 30 dias | 1.00 |
| U21 | Peso Senior mes completo | 30 dias, mes 30 dias | 1.15 |
| U22 | Peso Pleno metade do mes | 15 dias, mes 30 dias | 0.50 |
| U23 | Peso Junior com 7 dias | 7 dias, mes 30 dias | 0.21 |
| U24 | Peso zero (0 dias efetivos) | 0 dias | 0.00 (excluida) |

#### Grupo: Distribuicao de Metas

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U25 | Meta para 1 consultora Pleno mes completo | meta=500000, 1 Pleno | individual=500000 |
| U26 | Meta para 3 consultoras iguais (Pleno) | meta=300000, 3 Pleno | 100000 cada |
| U27 | Meta para Jr+Pl+Sr mes completo | meta=305000, totalPeso=3.05 | Jr=90000, Pl=100000, Sr=115000 |
| U28 | Soma das metas = meta loja | Qualquer cenario | SUM(individual) == metaLoja |
| U29 | Ultimo recebe residual de arredondamento | meta=100000, 3 consultoras | Soma exata |
| U30 | Super meta = individual * 1.15 | individual=100000 | super=115000 |
| U31 | Hiper meta = super * 1.15 | super=115000 | hiper=132250 |
| U32 | Sem consultoras elegiveis | 0 consultoras | Array vazio, sem inserir |

#### Grupo: Atestados Medicos

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U33 | Atestado 9 dias NAO redistribui | daysAway=9 | Retorna true imediatamente |
| U34 | Atestado 10 dias redistribui | daysAway=10 | Chama redistribute() |
| U35 | Atestado 15 dias redistribui | daysAway=15 | Chama redistribute() |
| U36 | Atestado exatamente no limite | daysAway=10 | Passa verificacao MIN_MEDICAL_LEAVE_DAYS |

#### Grupo: Meses Afetados

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U37 | Periodo no mes atual | start=01/mes_atual, end=15/mes_atual | [mes_atual] |
| U38 | Periodo abrangendo 2 meses | start=15/mes_atual, end=15/mes_seguinte | [mes_atual, mes_seguinte] |
| U39 | Periodo totalmente no passado | start=01/mes_passado, end=28/mes_passado | [] (vazio) |
| U40 | Periodo futuro | start=01/mes_futuro, end=28/mes_futuro | [mes_futuro] |
| U41 | Contrato sem data_final | dateFinal=null | Ate o mes atual |
| U42 | Periodo abrangendo passado e presente | start=3 meses atras, end=mes_atual | Apenas mes_atual |

#### Grupo: Update Individual Goals

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U43 | Meta existente → UPDATE | employee_id ja existe em adms_store_consultants_goals | Atualiza registro |
| U44 | Meta nova → INSERT | employee_id nao existe | Cria novo registro |
| U45 | Consultora nao elegivel → DELETE | employee_id nao esta nos elegiveis | Remove registro orfao |
| U46 | Todas inelegiveis → DELETE ALL | Lista vazia de elegiveis | Remove todas as metas |

---

### 5.2 AdmsAddStoreGoals

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U47 | Campos vazios falha validacao | data incompleta | error='Preencha todos os campos...' |
| U48 | Meta duplicada para mesmo periodo | Mesmo mes/ano/loja | error='A Meta para este periodo ja esta cadastrada!' |
| U49 | Meta com formato brasileiro (10.000,50) | goal_meta='10.000,50' | Converte para 10000.50 |
| U50 | Super meta = goal_meta * 1.15 | goal_meta=100000 | super_meta=115000 |
| U51 | Separacao de reference_month em mes/ano | '2026-01' | reference_year=2026, reference_month=01 |
| U52 | ULID gerado corretamente | - | UUID v7 valido |
| U53 | Chama redistribute() apos inserir | Meta inserida | StoreGoalsRedistributionService::redistribute() chamado |
| U54 | storeId passado como string (alfanumerico) | adms_store_id='Z424' | Sem cast para int |

---

### 5.3 AdmsEditStoreGoal

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U55 | Campos vazios falha validacao | data incompleta | error='Preencha todos os campos...' |
| U56 | Atualiza goal_meta e super_meta | goal_meta alterada | Novos valores no banco |
| U57 | Chama redistribute() apos update | Meta atualizada | Service chamado com storeId string |
| U58 | storeId preservado como string | adms_store_id='Z424' | Sem cast para int |

---

### 5.4 AdmsAddContract

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U59 | Admissao redistribui na loja nova | type_moviment=1 | redistributeFromContract(loja_nova) |
| U60 | Demissao redistribui na loja atual | type_moviment=2 | redistributeFromContract(loja_atual) |
| U61 | Transferencia redistribui loja nova + loja antiga | Loja diferente do contrato anterior | Duas chamadas redistribute |
| U62 | Mesma loja NAO redistribui duas vezes | Loja igual ao contrato anterior | Uma chamada redistribute |
| U63 | storeId passado como string | adms_store_id='Z424' | Sem cast para int |
| U64 | viewContract busca adms_store_id | - | Query inclui adms_store_id |
| U65 | Contrato anterior NULL | Primeira contratacao | Redistribui so loja nova |

---

### 5.5 AdmsAddEmployee

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U63b | Cadastro com contrato redistribui metas | adms_store_id='Z424', date_initial=hoje | Chama redistributeFromContract() apos commit |
| U63c | Cadastro sem loja NAO redistribui | adms_store_id=null | NAO chama service |
| U63d | Cadastro sem date_initial NAO redistribui | date_initial=null | NAO chama service |
| U63e | Erro na redistribuicao NAO afeta cadastro | Service lanca excecao | Funcionario cadastrado normalmente, erro logado |

---

### 5.6 AdmsEditContract

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U66 | Edicao sem mudar loja redistribui 1x | Mesma loja | Uma chamada redistribute |
| U67 | Edicao mudando loja redistribui 2x | Loja diferente | Redistribui loja nova + loja anterior |
| U68 | oldStoreId capturado ANTES do update | - | Busca loja antes de gravar |
| U69 | storeId passado como string | - | Sem cast para int |

---

### 5.7 AdmsAddMedicalCertificate

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U70 | Atestado 9 dias NAO redistribui | days_away=9 | NAO chama service |
| U71 | Atestado 10 dias redistribui | days_away=10 | Chama redistributeFromMedicalLeave() |
| U72 | Atestado 30 dias redistribui | days_away=30 | Chama redistributeFromMedicalLeave() |
| U73 | getEmployeeStoreId retorna string | - | Tipo string passado ao service |
| U74 | Funcionario sem loja NAO redistribui | storeId=null | NAO chama service |

---

### 5.8 AdmsEditMedicalCertificate

| # | Teste | Entrada | Esperado |
|---|-------|---------|----------|
| U75 | Atestado editado para >= 10 dias redistribui | days_away=10 | Chama service |
| U76 | Atestado editado para < 10 dias NAO redistribui | days_away=5 | NAO chama service |
| U77 | getEmployeeStoreId retorna string | - | Tipo string passado ao service |

---

## 6. Testes de Integracao

### 6.1 Fluxo: Criar Meta de Loja

| # | Teste | Setup | Acao | Verificacao |
|---|-------|-------|------|-------------|
| I01 | Criar meta com 3 consultoras ativas | 3 contratos ativos (Jr, Pl, Sr) | addStoreGoals() | 3 registros em adms_store_consultants_goals com soma = meta_loja |
| I02 | Criar meta sem consultoras na loja | Nenhum contrato | addStoreGoals() | 0 registros individuais, result=true |
| I03 | Criar meta com consultora nova no mes | Contrato date_initial no mes | addStoreGoals() | working_days descontando 3 dias treinamento |
| I04 | Criar meta com consultora com atestado 15 dias | Atestado ativo no periodo | addStoreGoals() | working_days descontando dias atestado |
| I05 | Criar meta com consultora com atestado 5 dias | Atestado < 10 dias | addStoreGoals() | working_days SEM descontar atestado |
| I06 | Criar meta duplicada | Meta ja existe para mes/ano/loja | addStoreGoals() | Retorna false, error='Meta ja cadastrada' |

---

### 6.2 Fluxo: Editar Meta de Loja

| # | Teste | Setup | Acao | Verificacao |
|---|-------|-------|------|-------------|
| I07 | Editar valor da meta | Meta existente, 3 consultoras | altGoal(goal_meta novo) | Metas individuais recalculadas, soma = novo valor |
| I08 | Editar meta com consultora demitida | Consultora com date_final < ultimo_dia_mes | altGoal() | Consultora tem working_days reduzidos |
| I09 | Editar meta com consultora com contrato encerrado | date_final antes do mes de referencia | altGoal() | Consultora excluida, meta orfao removida |

---

### 6.3 Fluxo: Cadastro de Funcionario (AdmsAddEmployee)

| # | Teste | Setup | Acao | Verificacao |
|---|-------|-------|------|-------------|
| I10a | Cadastrar funcionario com contrato redistribui | Meta existente, 2 consultoras | addEmployee() com contrato | 3 registros individuais, nova consultora com 3 dias treinamento descontados |
| I10b | Cadastrar funcionario sem contrato NAO redistribui | Meta existente | addEmployee() sem contrato | Metas individuais inalteradas |
| I10c | Erro na redistribuicao NAO impede cadastro | Meta inexistente | addEmployee() com contrato | Funcionario cadastrado normalmente |

---

### 6.4 Fluxo: Admissao (Novo Contrato via AdmsAddContract)

| # | Teste | Setup | Acao | Verificacao |
|---|-------|-------|------|-------------|
| I10 | Admitir consultora em loja com meta | Meta existente, 2 consultoras | addContract(type=1) | 3 registros individuais, metas redistribuidas |
| I11 | Nova consultora recebe 3 dias treinamento | Admissao no meio do mes | addContract(type=1) | working_days = dias_efetivos - 3 |
| I12 | Admissao em loja sem meta | Nenhuma meta cadastrada | addContract(type=1) | Nenhuma redistribuicao, result=true |
| I13 | Admissao no primeiro dia do mes | date_initial = primeiro_dia_mes | addContract(type=1) | working_days = total_dias_mes - 3 |

---

### 6.5 Fluxo: Demissao

| # | Teste | Setup | Acao | Verificacao |
|---|-------|-------|------|-------------|
| I14 | Demitir consultora no dia 15 | Meta existente, 3 consultoras | addContract(type=2, date=15) | Consultora demitida: working_days=15, outras redistribuidas |
| I15 | Demitir consultora: status muda para 3 | Consultora ativa | addContract(type=2) | adms_status_employee_id=3, excluida na proxima redistribuicao |
| I16 | Demissao redistribui na loja | Meta existente | addContract(type=2) | Metas das demais consultoras aumentam |

---

### 6.6 Fluxo: Transferencia

| # | Teste | Setup | Acao | Verificacao |
|---|-------|-------|------|-------------|
| I17 | Transferir consultora para outra loja | Loja A com 3, Loja B com 2, metas ambas | addContract(loja_nova=B) | Loja A: redistribui sem a consultora. Loja B: redistribui com a consultora |
| I18 | Transferencia: loja antiga perde consultora | Contrato anterior em loja A | addContract(loja=B) | Loja A: meta da consultora removida/zerada |
| I19 | Transferencia: loja nova ganha consultora | Transferencia para loja B | addContract(loja=B) | Loja B: nova meta individual criada |
| I20 | Transferencia no meio do mes | date_initial=15/01 | addContract(loja=B) | Loja A: 15 dias. Loja B: 17 dias (16 - 3 treinamento) |

---

### 6.7 Fluxo: Edicao de Contrato

| # | Teste | Setup | Acao | Verificacao |
|---|-------|-------|------|-------------|
| I21 | Editar date_final do contrato | Contrato ativo | altContract(date_final=15/01) | working_days recalculados |
| I22 | Editar loja do contrato | Contrato loja A | altContract(adms_store_id=B) | Redistribui loja A e loja B |
| I23 | Editar contrato sem mudar loja | Mesma loja | altContract() | Redistribui apenas na loja atual |

---

### 6.8 Fluxo: Atestado Medico

| # | Teste | Setup | Acao | Verificacao |
|---|-------|-------|------|-------------|
| I24 | Atestado 10 dias redistribut metas | Meta existente, 3 consultoras | addCertificate(days=10) | Consultora: working_days -10, outras redistribuidas |
| I25 | Atestado 9 dias NAO redistribui | Meta existente | addCertificate(days=9) | Metas individuais inalteradas |
| I26 | Atestado entre meses | start=25/01, ends=05/02 | addCertificate() | Redistribui jan E fev |
| I27 | Editar atestado de 5 para 12 dias | Atestado existente | altCertificate(ends_date ampliada) | Redistribui apos edicao |
| I28 | Editar atestado de 12 para 5 dias | Atestado existente | altCertificate(ends_date reduzida) | NAO redistribui (< 10 dias) |

---

### 6.9 Fluxo: Contratos como Fonte de Verdade

| # | Teste | Setup | Acao | Verificacao |
|---|-------|-------|------|-------------|
| I29 | Consultora com contrato encerrado NAO recebe meta | date_final antes do mes de referencia | redistribute() | Consultora NAO aparece nos elegiveis |
| I30 | Consultora com contrato parcial recebe proporcional | date_final no meio do mes | redistribute() | Dias efetivos limitados ate date_final |
| I31 | Contrato sem date_final = mes completo | date_final IS NULL | redistribute() | Consultora recebe meta proporcional ate ultimo dia do mes |

---

### 6.10 Fluxo: Cenarios de Borda

| # | Teste | Cenario | Esperado |
|---|-------|---------|----------|
| I32 | Consultora com atestado cobrindo mes inteiro | 31 dias atestado | working_days = 0, excluida da distribuicao |
| I33 | Nova contratacao + atestado grande | Admissao dia 10 + atestado 15 dias | GREATEST(0, 22 - 3 - 15) = 4 dias |
| I34 | Meta loja = 0 | goal_meta=0 | Todas as metas individuais = 0 |
| I35 | Apenas 1 consultora elegivel | 1 consultora | Recebe 100% da meta |
| I36 | Todas consultoras com mesmo nivel | 3 Pleno, mes completo | Meta dividida igualmente |
| I37 | storeId alfanumerico 'Z424' | adms_store_id='Z424' | Sistema opera sem cast para int |
| I38 | Multiplos atestados >= 10 dias no mes | 2 atestados distintos | Soma das deducoes |
| I39 | Atestado totalmente fora do periodo | start/ends em outro mes | 0 dias descontados |
| I40 | Contrato que inicia E termina no mesmo mes | date_initial=05/01, date_final=20/01 | 16 dias - 3 treinamento = 13 dias |

---

## 7. Dados de Teste Sugeridos

### Loja de Teste

```sql
INSERT INTO tb_lojas (id, nome, status_id) VALUES ('T001', 'Loja Teste', 1);
```

### Consultoras de Teste

```sql
-- Junior
INSERT INTO adms_employees (id, name_employee, nivel, position_id, adms_store_id, adms_status_employee_id)
VALUES (901, 'Consultora Junior 1', 'Júnior', 1, 'T001', 2);

-- Pleno
INSERT INTO adms_employees (id, name_employee, nivel, position_id, adms_store_id, adms_status_employee_id)
VALUES (902, 'Consultora Pleno 1', 'Pleno', 1, 'T001', 2);

-- Senior
INSERT INTO adms_employees (id, name_employee, nivel, position_id, adms_store_id, adms_status_employee_id)
VALUES (903, 'Consultora Senior 1', 'Sênior', 1, 'T001', 2);
```

### Contratos de Teste

```sql
-- Mes completo
INSERT INTO adms_employment_contracts (adms_employee_id, adms_store_id, adms_position_id, adms_type_moviment_id, date_initial, date_final)
VALUES (901, 'T001', 1, 1, '2026-01-01', NULL);

-- Admissao no meio do mes
INSERT INTO adms_employment_contracts (adms_employee_id, adms_store_id, adms_position_id, adms_type_moviment_id, date_initial, date_final)
VALUES (902, 'T001', 1, 1, '2026-01-10', NULL);

-- Demissao
INSERT INTO adms_employment_contracts (adms_employee_id, adms_store_id, adms_position_id, adms_type_moviment_id, date_initial, date_final)
VALUES (903, 'T001', 1, 2, '2026-01-01', '2026-01-15');
```

### Meta de Teste

```sql
INSERT INTO adms_store_goals (id, adms_store_id, reference_month, reference_year, goal_meta, super_meta, business_days, non_working_days, ulid, created_at)
VALUES (99, 'T001', 1, 2026, 500000.00, 575000.00, 30, 1, 'test-ulid-001', NOW());
```

### Atestado de Teste

```sql
INSERT INTO adms_medical_certificates (adms_employee_id, start_date, ends_date, days_away, predicted_return_date)
VALUES (901, '2026-01-05', '2026-01-15', 11, '2026-01-16');
```

---

## 8. Cenarios de Calculo Detalhado

> **Nota:** Todos os cenarios abaixo consideram Janeiro/2026 = 31 dias totais, 30 dias uteis (1 feriado).
> Formula: `diasUteis = ROUND(diasEfetivos * 30 / 31)`

### Cenario A: 3 Consultoras, Mes Completo

```
Meta da loja: R$ 500.000 | business_days: 30 | non_working_days: 1
```

| Consultora | Nivel | Dias Efetivos | Dias Uteis | Peso | Proporcao | Meta |
|-----------|-------|---------------|-----------|------|-----------|------|
| Junior 1 | Junior | 31 | ROUND(31*30/31)=30 | 0.90*30/30=0.90 | 29.51% | 147,541 |
| Pleno 1 | Pleno | 31 | 30 | 1.00*30/30=1.00 | 32.79% | 163,934 |
| Senior 1 | Senior | 31 | 30 | 1.15*30/30=1.15 | 37.70% | 188,525 |
| **Total** | | | | **3.05** | **100%** | **500,000** |

> Ultimo (Senior) recebe residual: `500000 - 147541 - 163934 = 188525`

### Cenario B: Com Admissao no Dia 10

```
Meta da loja: R$ 500.000 | business_days: 30 | non_working_days: 1
Pleno admitida dia 10/01: diasEfetivos = (31-10+1) - 3 treinamento = 19
```

| Consultora | Nivel | Dias Efetivos | Dias Uteis | Calculo Peso | Peso |
|-----------|-------|---------------|-----------|--------------|------|
| Junior 1 | Junior | 31 | 30 | 0.90*30/30 | 0.900 |
| Pleno 1 | Pleno | 19 | ROUND(19*30/31)=18 | 1.00*18/30 | 0.600 |
| Senior 1 | Senior | 31 | 30 | 1.15*30/30 | 1.150 |

Peso Total = 0.900 + 0.600 + 1.150 = 2.650

### Cenario C: Com Atestado 15 dias (01-15/Jan) para Junior

```
Meta da loja: R$ 500.000 | business_days: 30 | non_working_days: 1
Junior com atestado 15 dias: diasEfetivos = 31 - 15 = 16
```

| Consultora | Nivel | Dias Efetivos | Dias Uteis | Calculo Peso | Peso |
|-----------|-------|---------------|-----------|--------------|------|
| Junior 1 | Junior | 16 | ROUND(16*30/31)=15 | 0.90*15/30 | 0.450 |
| Pleno 1 | Pleno | 31 | 30 | 1.00*30/30 | 1.000 |
| Senior 1 | Senior | 31 | 30 | 1.15*30/30 | 1.150 |

Peso Total = 0.450 + 1.000 + 1.150 = 2.600

### Cenario D: Transferencia (Junior sai da Loja A para Loja B dia 15)

**Loja A (apos transferencia):**

| Consultora | Nivel | Dias Efetivos | Dias Uteis | Calculo |
|-----------|-------|---------------|-----------|---------|
| Junior 1 | Junior | 15 | ROUND(15*30/31)=15 | date_final=15/01 |
| Pleno 1 | Pleno | 31 | 30 | Sem deducoes |

**Loja B (apos transferencia):**

| Consultora | Nivel | Dias Efetivos | Dias Uteis | Calculo |
|-----------|-------|---------------|-----------|---------|
| Junior 1 | Junior | 14 | ROUND(14*30/31)=14 | 17 dias (16-31) - 3 treinamento = 14 |
| Senior 1 | Senior | 31 | 30 | Sem deducoes |

### Cenario E: Cadastro de Novo Funcionario (dia 22/01)

```
Meta da loja: R$ 1.240.000 | business_days: 30 | non_working_days: 1
Nova Junior admitida dia 22/01: diasEfetivos = (31-22+1) - 3 treinamento = 7
```

| Consultora | Nivel | Dias Efetivos | Dias Uteis | Calculo Peso | Peso |
|-----------|-------|---------------|-----------|--------------|------|
| Junior nova | Junior | 7 | ROUND(7*30/31)=7 | 0.90*7/30 | 0.210 |
| ...demais consultoras... | | | | | |

> A redistribuicao e disparada automaticamente por `AdmsAddEmployee::redistributeGoals()`
> usando `redistributeFromContract()` apos o commit da transacao.

---

## 9. Estrutura dos Testes

```
tests/
└── StoreGoals/
    ├── StoreGoalsRedistributionServiceTest.php     # Testes do service (175 testes)
    ├── Unit/
    │   ├── EffectiveWorkingDaysTest.php            # U08-U18 (dias efetivos)
    │   ├── WeightCalculationTest.php               # U19-U24 (peso por nivel)
    │   ├── GoalDistributionTest.php                # U25-U32 (distribuicao + feriados)
    │   ├── MedicalLeaveRulesTest.php               # U33-U36 (regras de atestado)
    │   ├── AffectedMonthsTest.php                  # U37-U42 (meses afetados)
    │   └── IndividualGoalsUpdateTest.php           # U43-U46 (update/insert/delete)
    ├── Models/
    │   ├── AdmsAddStoreGoalsTest.php               # U47-U54
    │   ├── AdmsEditStoreGoalTest.php               # U55-U58
    │   ├── AdmsAddEmployeeRedistributionTest.php   # U63b-U63e
    │   ├── AdmsAddContractRedistributionTest.php   # U59-U65
    │   ├── AdmsEditContractRedistributionTest.php  # U66-U69
    │   ├── AdmsAddMedicalCertificateRedistTest.php # U70-U74
    │   └── AdmsEditMedicalCertificateRedistTest.php# U75-U77
    └── Integration/
        ├── CreateGoalFlowTest.php                  # I01-I06
        ├── EditGoalFlowTest.php                    # I07-I09
        ├── AdmissionFlowTest.php                   # I10-I13
        ├── DismissalFlowTest.php                   # I14-I16
        ├── TransferFlowTest.php                    # I17-I20
        ├── EditContractFlowTest.php                # I21-I23
        ├── MedicalCertificateFlowTest.php          # I24-I28
        ├── ContractBasedEligibilityTest.php        # I29-I31
        └── EdgeCasesFlowTest.php                   # I32-I40
```

---

## 10. Prioridade de Implementacao

### Alta Prioridade (Core da redistribuicao)
- U01-U05: Tipos string do storeId ✅ Implementado
- U08-U18: Calculo de dias efetivos ✅ Implementado
- U19-U32: Peso e distribuicao ✅ Implementado
- I17-I20: Transferencia (loja nova + loja antiga)
- I29-I31: Contratos como fonte de verdade

### Media Prioridade (Gatilhos e regras de negocio)
- U33-U46: Atestados, meses afetados, update
- I01-I06: Criar meta
- I10-I16: Admissao/Demissao
- I24-I28: Atestados

### Baixa Prioridade (Validacao e borda)
- U47-U77: Validacao de entrada em models
- I32-I40: Cenarios de borda

---

## 11. Historico de Alteracoes

| Data | Versao | Alteracao |
|------|--------|-----------|
| 23/01/2026 | 1.0 | Documento original com regras de negocio |
| 23/01/2026 | 2.0 | Atualizacao: remocao filtro status, formula businessDays, ajuste feriados, arredondamento inteiro, AdmsAddEmployee como gatilho |

---

**Total: 81 testes unitarios + 40 testes de integracao = 121 testes propostos**
**Implementados: 175 testes unitarios (StoreGoals suite)**
