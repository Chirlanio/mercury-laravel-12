# ANÁLISE DE SEEDERS - CONFLITOS DE FOREIGN KEY

**Data:** 07 de Novembro de 2025
**Análise:** Identificação de potenciais violações de foreign key constraints

---

## RESUMO EXECUTIVO

Foram identificados **5 seeders com risco de violação de foreign key** devido ao uso de IDs hardcoded que podem não existir no banco de dados.

### ✅ SEEDERS CORRIGIDOS (2/5)
- **EmploymentContractSeeder** - ✅ **CORRIGIDO EM 07/11/2025**
- **AccessLevelPageSeeder** - ✅ **CORRIGIDO EM 07/11/2025**

### ❌ SEEDERS PENDENTES (3/5)
- **PageSeeder** - ❌ **REQUER CORREÇÃO**
- **StoreSeeder** - ❌ **REQUER CORREÇÃO**
- **PositionSeeder** - ❌ **REQUER CORREÇÃO**

### ✅ STATUS OK
- SuperAdminSeeder - Usa `firstOrCreate` com email
- MenuSeeder - Busca IDs dinamicamente
- DatabaseSeeder - Apenas orquestra outros seeders

---

## 1. EMPLOYMENT CONTRACT SEEDER ✅ CORRIGIDO

**Arquivo:** `database/seeders/EmploymentContractSeeder.php`
**Status:** ✅ **CORRIGIDO EM 07/11/2025**

### Problema Original
```php
['employee_id' => 197, ...] // ❌ Funcionário ID 197 pode não existir
['employee_id' => 635, ...] // ❌ Funcionário ID 635 pode não existir
```

### Solução Aplicada
```php
$existingEmployeeIds = DB::table('employees')->pluck('id')->toArray();

foreach ($contracts as $contract) {
    if (in_array($contract['employee_id'], $existingEmployeeIds)) {
        // Inserir contrato
    } else {
        echo "⚠️  Contrato ignorado - Funcionário não existe\n";
    }
}
```

---

## 2. ACCESS LEVEL PAGE SEEDER ✅ CORRIGIDO

**Arquivo:** `database/seeders/AccessLevelPageSeeder.php`
**Status:** ✅ **CORRIGIDO EM 07/11/2025**

### Problema Original
```php
// ❌ Inserção sem verificar se menu_id, access_level_id e page_id existem
DB::table('access_level_pages')->updateOrInsert([...]);
```

**Risco:** Se `menus` (ID 1, 2, 4, 6), `access_levels` (ID 1, 2) ou `pages` (ID 1-18) não existirem, o seeder falharia com erro de foreign key constraint.

### Solução Aplicada
```php
// Buscar todos os IDs existentes
$existingMenuIds = DB::table('menus')->pluck('id')->toArray();
$existingAccessLevelIds = DB::table('access_levels')->pluck('id')->toArray();
$existingPageIds = DB::table('pages')->pluck('id')->toArray();

foreach ($accessLevelPages as $accessLevelPage) {
    // Verificar se todas as foreign keys existem
    if (!in_array($accessLevelPage['menu_id'], $existingMenuIds)) {
        echo "⚠️  AccessLevelPage ignorado - menu_id {$accessLevelPage['menu_id']} não existe\n";
        continue;
    }

    if (!in_array($accessLevelPage['access_level_id'], $existingAccessLevelIds)) {
        echo "⚠️  AccessLevelPage ignorado - access_level_id {$accessLevelPage['access_level_id']} não existe\n";
        continue;
    }

    if (!in_array($accessLevelPage['page_id'], $existingPageIds)) {
        echo "⚠️  AccessLevelPage ignorado - page_id {$accessLevelPage['page_id']} não existe\n";
        continue;
    }

    // Inserir apenas se todas as foreign keys existirem
    DB::table('access_level_pages')->updateOrInsert([...]);
}
```

### Benefícios da Correção
- ✅ Previne erros de foreign key constraint
- ✅ Valida 3 foreign keys antes de inserir
- ✅ Logs informativos para debug
- ✅ Seeds executam sem falhas

---

---

## 3. PAGE SEEDER ❌ REQUER CORREÇÃO

**Arquivo:** `database/seeders/PageSeeder.php`
**Status:** ❌ **RISCO MÉDIO**

### Foreign Keys Utilizadas
- `page_group_id` (valores: 1-7)

### Exemplo de Dados
```php
['page_name' => 'Home', 'page_group_id' => 1, ...]
['page_name' => 'Usuários', 'page_group_id' => 1, ...]
['page_name' => 'Login', 'page_group_id' => 7, ...]
```

### Risco
Se `page_groups` (ID 1-7) não existirem, o seeder falhará.

### Dependências (Ordem no DatabaseSeeder)
1. ✅ PageGroupSeeder (linha 31) - Roda ANTES
2. ✅ PageSeeder (linha 32) - Roda DEPOIS

**Análise:** Dependências respeitadas, mas IDs podem variar.

---

## 4. STORE SEEDER ❌ REQUER CORREÇÃO

**Arquivo:** `database/seeders/StoreSeeder.php`
**Status:** ❌ **RISCO MUITO ALTO**

### Foreign Keys Utilizadas
- `network_id` (valores: 1-8)
- `manager_id` (valores: 1214, 1296, 1437, 44, 137, 730, etc.)
- `supervisor_id` (valores: 295, 1385, 664, 194, 214)
- `status_id` (valores: 1, 2)

### Exemplo de Dados
```php
['code' => 'Z421', 'network_id' => 4, 'manager_id' => 1214, 'supervisor_id' => 295, 'status_id' => 1]
['code' => 'Z422', 'network_id' => 1, 'manager_id' => 1296, 'supervisor_id' => 295, 'status_id' => 1]
```

### Risco
- `manager_id` e `supervisor_id` referenciam `employees` ou `managers`
- Se esses IDs não existirem, o seeder falhará
- **RISCO MUITO ALTO** porque há muitos IDs hardcoded

### Dependências (Ordem no DatabaseSeeder)
1. ✅ StatusSeeder (linha 20) - Roda ANTES
2. ✅ NetworkSeeder (linha 34) - Roda ANTES
3. ✅ ManagerSeeder (linha 25) - Roda ANTES
4. ✅ StoreSeeder (linha 35) - Roda DEPOIS
5. ❌ EmployeeSeeder (linha 36) - Roda **DEPOIS** de Store

**Análise:** ⚠️ **PROBLEMA!** `StoreSeeder` referencia `manager_id` e `supervisor_id` que podem vir de `EmployeeSeeder`, mas `StoreSeeder` roda ANTES!

---

## 5. POSITION SEEDER ❌ REQUER CORREÇÃO

**Arquivo:** `database/seeders/PositionSeeder.php`
**Status:** ❌ **RISCO MÉDIO**

### Foreign Keys Utilizadas
- `level_category_id` (valores: 1, 2)
- `status_id` (valores: 1)

### Exemplo de Dados
```php
['name' => 'Consultor(a) de Vendas', 'level_category_id' => 2, 'status_id' => 1]
['name' => 'Gerente', 'level_category_id' => 1, 'status_id' => 1]
```

### Risco
Se `position_levels` (ID 1, 2) ou `statuses` (ID 1) não existirem, o seeder falhará.

### Dependências (Ordem no DatabaseSeeder)
1. ✅ StatusSeeder (linha 20) - Roda ANTES
2. ✅ PositionLevelSeeder (linha 29) - Roda ANTES
3. ✅ PositionSeeder (linha 30) - Roda DEPOIS

**Análise:** Dependências respeitadas.

---

## 6. EMPLOYEE SEEDER ⚠️ VERIFICAR

**Arquivo:** `database/seeders/EmployeeSeeder.php`
**Status:** ⚠️ **REQUER ANÁLISE**

### Foreign Keys Utilizadas (Exemplo)
- `position_id` (valores: 2, 36, 34, 3, 1, 7, etc.)
- `store_id` (valores: 'Z999', 'Z423', 'Z429', 'Z430', etc.)
- `education_level_id` (valores: 8, 6, 4, etc.)
- `gender_id` (valores: 2, 1, etc.)
- `area_id` (valores: 12, 8, 10, 9, etc.)
- `status_id` (valores: 2, 3, etc.)

### Risco
Muitas foreign keys referenciadas. Precisa verificar se todas as tabelas foram populadas antes.

---

## ORDEM CORRETA DE EXECUÇÃO (DatabaseSeeder.php)

```php
$this->call([
    1.  SuperAdminSeeder::class,           // ✅ Sem dependências
    2.  EmailConfigurationSeeder::class,   // ✅ Sem dependências
    3.  ColorThemeSeeder::class,           // ✅ Sem dependências
    4.  StatusSeeder::class,               // ✅ Sem dependências
    5.  PageStatusSeeder::class,           // ✅ Sem dependências
    6.  EmploymentRelationshipSeeder::class, // ✅ Sem dependências
    7.  EducationLevelSeeder::class,       // ✅ Sem dependências
    8.  GenderSeeder::class,               // ✅ Sem dependências
    9.  ManagerSeeder::class,              // ✅ Sem dependências
    10. SectorSeeder::class,               // ✅ Sem dependências
    11. MenuSeeder::class,                 // ✅ Sem dependências
    12. AdditionalAccessLevelsSeeder::class, // ✅ Sem dependências
    13. PositionLevelSeeder::class,        // ✅ Sem dependências
    14. PositionSeeder::class,             // ⚠️ Depende: status, position_levels
    15. PageGroupSeeder::class,            // ✅ Sem dependências
    16. PageSeeder::class,                 // ⚠️ Depende: page_groups
    17. AccessLevelPageSeeder::class,      // ⚠️ Depende: menus, access_levels, pages
    18. NetworkSeeder::class,              // ✅ Sem dependências
    19. StoreSeeder::class,                // ⚠️ Depende: network, managers, status
                                           //    ❌ PROBLEMA: usa manager_id/supervisor_id
    20. EmployeeSeeder::class,             // ⚠️ Depende: position, store, education, gender, area, status
    21. EmploymentContractSeeder::class,   // ✅ CORRIGIDO: verifica employee_id
]);
```

---

## PROBLEMA CRÍTICO IDENTIFICADO

### ⚠️ StoreSeeder vs EmployeeSeeder

**Ordem Atual:**
1. StoreSeeder (linha 35)
2. EmployeeSeeder (linha 36)

**Problema:**
- `StoreSeeder` usa `manager_id` e `supervisor_id`
- Estes IDs podem referenciar funcionários (employees)
- Mas `EmployeeSeeder` roda **DEPOIS** de `StoreSeeder`!

**Possíveis Soluções:**
1. ✅ `manager_id` e `supervisor_id` referenciam tabela `managers` (não `employees`)
2. ❌ Se referenciam `employees`, a ordem está errada
3. ✅ Tornar `manager_id` e `supervisor_id` nullable e preencher depois

---

## SOLUÇÕES RECOMENDADAS

### Solução 1: Verificação Condicional (Recomendada)

Aplicar o mesmo padrão do `EmploymentContractSeeder` em todos os seeders:

```php
public function run(): void
{
    // Buscar IDs existentes
    $existingMenuIds = DB::table('menus')->pluck('id')->toArray();
    $existingAccessLevelIds = DB::table('access_levels')->pluck('id')->toArray();
    $existingPageIds = DB::table('pages')->pluck('id')->toArray();

    $data = [ /* ... */ ];

    foreach ($data as $item) {
        // Verificar todas as foreign keys
        if (!in_array($item['menu_id'], $existingMenuIds)) {
            echo "⚠️  Item ignorado - menu_id {$item['menu_id']} não existe\n";
            continue;
        }

        if (!in_array($item['access_level_id'], $existingAccessLevelIds)) {
            echo "⚠️  Item ignorado - access_level_id {$item['access_level_id']} não existe\n";
            continue;
        }

        if (!in_array($item['page_id'], $existingPageIds)) {
            echo "⚠️  Item ignorado - page_id {$item['page_id']} não existe\n";
            continue;
        }

        // Inserir apenas se todas as foreign keys existirem
        DB::table('access_level_pages')->updateOrInsert(...);
    }
}
```

### Solução 2: Foreign Keys Opcionais (Alternativa)

Tornar as foreign keys `nullable` e preencher em uma segunda passada:

```php
// 1ª passada: Criar registros sem foreign keys
DB::table('stores')->insert([
    'code' => 'Z421',
    'name' => 'Schutz Riomar Recife',
    // manager_id e supervisor_id ficam NULL
]);

// 2ª passada (após EmployeeSeeder): Atualizar foreign keys
DB::table('stores')
    ->where('code', 'Z421')
    ->update(['manager_id' => ...]);
```

### Solução 3: Buscar por Identificador Único (Mais Robusta)

Ao invés de IDs hardcoded, usar identificadores únicos (CPF, code, name):

```php
// ❌ Evitar
['employee_id' => 197]

// ✅ Melhor
$employee = DB::table('employees')->where('cpf', '12345678901')->first();
if ($employee) {
    ['employee_id' => $employee->id]
}
```

---

## PRIORIZAÇÃO DE CORREÇÕES

### 🔴 PRIORIDADE CRÍTICA (Fazer Agora)
1. ✅ **EmploymentContractSeeder** - JÁ CORRIGIDO
2. ✅ **AccessLevelPageSeeder** - JÁ CORRIGIDO
3. ❌ **StoreSeeder** - Verificar manager_id/supervisor_id

### 🟠 PRIORIDADE ALTA (Fazer em 1 semana)
4. ❌ **PageSeeder** - Verificar page_group_id
5. ❌ **PositionSeeder** - Verificar level_category_id, status_id
6. ❌ **EmployeeSeeder** - Verificar todas as foreign keys

### 🟡 PRIORIDADE MÉDIA (Fazer em 2-4 semanas)
7. ⚠️ Criar testes automatizados para seeders
8. ⚠️ Adicionar validação de foreign keys em todos os seeders
9. ⚠️ Documentar ordem de dependências

---

## CHECKLIST DE VALIDAÇÃO

Para cada seeder com foreign keys:

- [ ] Identificar todas as foreign keys utilizadas
- [ ] Verificar se as tabelas referenciadas são populadas antes
- [ ] Implementar verificação condicional (como EmploymentContractSeeder)
- [ ] Adicionar logs informativos para registros ignorados
- [ ] Testar em banco de dados limpo
- [ ] Documentar dependências

---

## IMPACTO ESTIMADO

| Seeder | Registros | Risco | Esforço |
|--------|-----------|-------|---------|
| EmploymentContractSeeder | 46 | ✅ Corrigido | 0h |
| AccessLevelPageSeeder | 46 | ✅ Corrigido | 0h |
| StoreSeeder | 26 | 🔴 Muito Alto | 3h |
| PageSeeder | 93 | 🟠 Médio | 1h |
| PositionSeeder | 85 | 🟡 Baixo | 1h |
| EmployeeSeeder | ? | ⚠️ Verificar | 2h |

**Total Estimado:** ~7 horas de desenvolvimento

---

## CONCLUSÃO

Foram identificados **5 seeders com potencial de violação de foreign key**. Até o momento, **2 seeders foram corrigidos** (`EmploymentContractSeeder` e `AccessLevelPageSeeder`), estabelecendo um padrão de validação que deve ser replicado nos seeders restantes.

**Recomendação:** Aplicar verificação condicional de foreign keys nos 3 seeders pendentes, priorizando `StoreSeeder` (risco muito alto) seguido de `PageSeeder` e `PositionSeeder`.

---

**Documentado por:** Claude Code
**Data:** 07 de Novembro de 2025
**Versão:** 1.0
