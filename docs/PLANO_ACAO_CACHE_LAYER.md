# Plano de Acao - Cache Layer (Issue #102)

**Data:** 06/03/2026
**Prioridade:** P2 - ALTA
**Estimativa:** 20 horas
**Complexidade:** Media

---

## 1. Diagnostico Atual

### 1.1 Infraestrutura Existente

| Componente | Status | Detalhes |
|-----------|--------|---------|
| `SelectCacheService` | Existe | Cache via `$_SESSION`, TTL 30min, 26 usos no `FormSelectRepository` |
| `FormSelectRepository` | Parcial | 26/117 metodos cacheados (22%), 91 sem cache |
| Cache manual (Sales) | Existe | `AdmsStatisticsSales` usa `SessionContext` com TTL 5min |
| Redis / Memcache / APCu | Nenhum | Nao ha dependencia no `composer.json` |
| File Cache | Nenhum | Nao implementado |

### 1.2 Numeros do Projeto

| Metrica | Valor |
|---------|-------|
| Chamadas `fullRead()` no projeto | **1.795** |
| Models de estatisticas | **33** |
| Estatisticas com `SelectCacheService` | **3** (InternalTransfers, MaterialRequests, MaterialsMarketing) |
| Estatisticas com cache manual | **3** (Sales, StockMovements, ExperienceTracker) |
| Estatisticas SEM cache | **~24** |
| Queries de permissao por page load | **4-10** (`AdmsBotao::valBotao()`) |
| Metodos sem cache no `FormSelectRepository` | **91** |

### 1.3 Gargalos Identificados

**1. Permissoes (AdmsBotao) — Impacto ALTO**
Cada pagina chama `valBotao()` com 4-10 botoes. Cada botao = 1 query ao banco:
```php
// AdmsBotao.php - executa 1 query POR botao
foreach ($this->Botao as $key => $botao_unico) {
    $verBotao = new AdmsRead();
    $verBotao->fullRead("SELECT pg.id FROM adms_paginas pg
        LEFT JOIN adms_nivacs_pgs nivpg ON ...
        WHERE pg.menu_controller = :menu_controller ...");
}
```
- **Problema:** Permissoes mudam raramente, mas sao consultadas em TODA pagina
- **Impacto:** 4-10 queries eliminaveis por page load

**2. Estatisticas/Dashboards — Impacto ALTO**
24 de 33 models de estatisticas nao tem cache nenhum. Queries complexas com JOINs e agregacoes executam a cada reload.

**3. FormSelectRepository — Impacto MEDIO**
91 metodos consultam o banco diretamente. Muitos retornam dados estaticos (lojas, cargos, funcionarios) que mudam raramente.

**4. Cache por Sessao — Limitacao**
O `SelectCacheService` atual armazena em `$_SESSION`:
- Nao compartilhado entre usuarios
- Perdido no logout
- Limite de tamanho (~500KB por sessao)
- Cada usuario faz as mesmas queries na primeira visita

---

## 2. Arquitetura Proposta

### 2.1 Visao Geral

```
┌─────────────────────────────────────────────────┐
│                   CacheService                   │
│          (API unica, TTL configuravel)           │
├─────────────────────────────────────────────────┤
│                                                  │
│  remember($key, $ttl, $callback)                │
│  forget($key)                                    │
│  flush($tag)                                     │
│  tags($tag)->remember(...)                       │
│  stats()                                         │
│                                                  │
├──────────┬──────────┬───────────────────────────┤
│  Driver  │  Driver  │  Driver                    │
│  File    │  APCu    │  Redis                     │
│ (padrao) │(opcional)│ (futuro)                   │
└──────────┴──────────┴───────────────────────────┘
```

### 2.2 Estrategia de Drivers

| Driver | Quando Usar | Compartilhado | Dependencia |
|--------|-------------|---------------|-------------|
| **File** (padrao) | MVP, servidor unico | Sim (entre usuarios) | Nenhuma |
| **APCu** | Producao, servidor unico | Sim (mesmo processo) | Extensao APCu |
| **Redis** | Futuro, multi-servidor | Sim (distribuido) | `predis/predis` |

**Decisao:** Comecar com **File Cache** (zero dependencias), interface preparada para Redis futuro.

### 2.3 Estrutura de Arquivos

```
app/adms/Services/
├── Cache/
│   ├── CacheService.php          # Facade principal (API publica)
│   ├── CacheDriverInterface.php  # Contrato para drivers
│   ├── FileCacheDriver.php       # Driver file-based (padrao)
│   └── SessionCacheDriver.php    # Wrapper do SelectCacheService atual
├── SelectCacheService.php        # Mantido (retrocompativel)
└── FormSelectRepository.php      # Migrar para CacheService
```

```
storage/
└── cache/                        # Diretorio para file cache
    ├── permissions/               # Cache de permissoes
    ├── statistics/                # Cache de estatisticas
    ├── selects/                   # Cache de selects
    └── .gitkeep
```

---

## 3. Fases de Implementacao

### Fase 1: CacheService + File Driver (6h)

**Objetivo:** Criar infraestrutura base com file cache.

**Tarefas:**
- [ ] Criar `CacheDriverInterface` com metodos: `get`, `set`, `has`, `forget`, `flush`, `increment`
- [ ] Criar `FileCacheDriver` com serializacao PHP e TTL por arquivo
- [ ] Criar `CacheService` (facade) com API `remember()`, `forget()`, `flush()`, `tags()`, `stats()`
- [ ] Criar diretorio `storage/cache/` com `.gitignore`
- [ ] Testes unitarios (15-20 testes)

**API Proposta:**
```php
use App\adms\Services\Cache\CacheService;

// Uso basico
$data = CacheService::remember('key', 3600, function() {
    return $this->heavyQuery();
});

// Com tags (para invalidacao em grupo)
$data = CacheService::tags('statistics')->remember('sales_stats', 300, function() {
    return $this->calculateSalesStats();
});

// Invalidar por tag
CacheService::tags('statistics')->flush();

// Invalidar chave especifica
CacheService::forget('key');

// Estatisticas
$stats = CacheService::stats(); // hits, misses, hit_rate, size
```

**FileCacheDriver — Implementacao:**
```php
// Armazena em: storage/cache/{md5($key)}.cache
// Formato: serialize(['data' => $value, 'expires_at' => $timestamp])
// Limpeza: lazy deletion (verifica TTL no get)
```

---

### Fase 2: Cache de Permissoes — AdmsBotao (3h)

**Objetivo:** Eliminar 4-10 queries por page load.

**Tarefas:**
- [ ] Cachear resultado de `valBotao()` com chave baseada em `access_level_id`
- [ ] TTL: 30 minutos (permissoes mudam raramente)
- [ ] Invalidacao: flush ao alterar permissoes em `adms_nivacs_pgs`
- [ ] Otimizar para query unica (batch) ao inves de 1 por botao

**Antes:**
```php
// AdmsBotao.php - 1 query POR botao (4-10 queries por pagina)
foreach ($this->Botao as $key => $botao_unico) {
    $verBotao = new AdmsRead();
    $verBotao->fullRead("SELECT ... WHERE menu_controller = :mc AND menu_metodo = :mm ...");
}
```

**Depois:**
```php
// AdmsBotao.php - 1 query TOTAL (ou 0 se cache hit)
public function valBotao(array $Botao): array
{
    $accessLevel = SessionContext::getAccessLevelId();

    return CacheService::tags('permissions')->remember(
        "buttons_{$accessLevel}_" . md5(serialize(array_keys($Botao))),
        1800, // 30 min
        function() use ($Botao, $accessLevel) {
            return $this->loadButtonPermissions($Botao, $accessLevel);
        }
    );
}

private function loadButtonPermissions(array $Botao, int $accessLevel): array
{
    // Query unica com IN clause para todos os botoes
    $controllers = array_column($Botao, 'menu_controller');
    $methods = array_column($Botao, 'menu_metodo');
    // ... single query
}
```

**Reducao estimada:** 4-10 queries → 0-1 por page load

---

### Fase 3: Cache de Estatisticas (5h)

**Objetivo:** Padronizar cache nos 33 models de estatisticas.

**Tarefas:**
- [ ] Criar trait `StatisticsCacheTrait` com metodo `cachedStatistics()`
- [ ] Migrar os 3 models com `SelectCacheService` para `CacheService`
- [ ] Migrar os 3 models com cache manual para `CacheService`
- [ ] Adicionar cache aos 24 models sem cache
- [ ] Tag `statistics` para invalidacao em grupo

**Trait proposto:**
```php
trait StatisticsCacheTrait
{
    protected function cachedStatistics(string $module, int $ttl, callable $callback, array $params = []): array
    {
        $storeId = method_exists($this, 'getFinancialStoreId')
            ? $this->getFinancialStoreId()
            : SessionContext::getUserStore();

        $cacheKey = "{$module}_" . md5(serialize([$storeId, ...$params]));

        return CacheService::tags('statistics')->remember($cacheKey, $ttl, $callback);
    }
}
```

**TTLs por tipo de dados:**

| Tipo | TTL | Justificativa |
|------|-----|---------------|
| Vendas do dia | 5 min | Muda frequentemente |
| Vendas do mes | 15 min | Agregado, toleravel |
| Resumos anuais | 60 min | Dados historicos |
| Contadores simples (COUNT) | 10 min | Muda moderadamente |
| Dados de lookup | 30 min | Raramente muda |

**Models a migrar (24 sem cache):**

| # | Model | TTL Sugerido |
|---|-------|-------------|
| 1 | AdmsStatisticsStoreGoals | 15 min |
| 2 | AdmsStatisticsTransfers | 10 min |
| 3 | AdmsStatisticsHolidayPayments | 30 min |
| 4 | AdmsStatisticsOrderControl | 10 min |
| 5 | AdmsStatisticsProducts | 30 min |
| 6 | AdmsStatisticsUsers | 30 min |
| 7 | AdmsStatisticsAdjustments | 15 min |
| 8 | AdmsStatisticsConsignments | 15 min |
| 9 | AdmsStatisticsEcommerce | 15 min |
| 10 | AdmsStatisticsReversals | 15 min |
| 11 | AdmsStatisticsHelpdesk | 10 min |
| 12 | AdmsStatisticsTrainings | 30 min |
| 13 | AdmsStatisticsConsultants | 15 min |
| 14 | AdmsStatisticsPageGroups | 60 min |
| 15 | AdmsStatisticsCoupons | 15 min |
| 16 | AdmsStatisticsTravelExpenses | 15 min |
| 17 | AdmsStatisticsDriverDeliveries | 10 min |
| 18 | AdmsStatisticsMenus | 60 min |
| 19 | AdmsStatisticsBudgetsSummaries | 30 min |
| 20 | AdmsStatisticsMedicalCertificates | 30 min |
| 21 | AdmsStatisticsVacancyOpenings | 30 min |
| 22 | AdmsStatisticsServiceOrders | 10 min |
| 23 | AdmsStatisticsOvertimeControls | 15 min |
| 24 | AdmsStatisticsAbsenceControl | 15 min |

---

### Fase 4: Expandir Cache no FormSelectRepository (3h)

**Objetivo:** Cachear os 91 metodos restantes que consultam o banco diretamente.

**Tarefas:**
- [ ] Identificar metodos com dados estaticos (lojas, cargos, situacoes) — cachear com TTL 30min
- [ ] Identificar metodos com filtro por usuario/loja — cachear com chave composta
- [ ] Identificar metodos que NAO devem ser cacheados (dados em tempo real)
- [ ] Migrar de `SelectCacheService::remember()` para `CacheService::tags('selects')->remember()`
- [ ] Manter retrocompatibilidade do `SelectCacheService`

**Classificacao dos metodos:**

| Categoria | Qtd | Cache | TTL |
|-----------|-----|-------|-----|
| Dados estaticos (sizes, routes, situations) | ~26 | Ja cacheados | 30 min |
| Dados semi-estaticos (stores, positions) | ~30 | Cachear | 30 min |
| Dados por usuario/loja (employees, managers) | ~25 | Cachear com chave composta | 15 min |
| Dados dinamicos (por ID especifico) | ~10 | NAO cachear | — |

---

### Fase 5: Invalidacao Inteligente (2h)

**Objetivo:** Garantir que dados cacheados sejam invalidados quando necessario.

**Tarefas:**
- [ ] Implementar invalidacao por tags no `CacheService`
- [ ] Adicionar hooks de invalidacao nos controllers de CRUD
- [ ] Documentar regras de invalidacao

**Regras de Invalidacao:**

| Evento | Tags a Invalidar |
|--------|-----------------|
| Alterar permissoes (`adms_nivacs_pgs`) | `permissions` |
| CRUD de vendas | `statistics` (ou `statistics:sales`) |
| CRUD de funcionarios | `selects:employees` |
| CRUD de lojas | `selects:stores` |
| CRUD de qualquer modulo | `statistics:{modulo}` |
| Logout do usuario | Cache de sessao apenas |

**Implementacao nos Controllers:**
```php
// Exemplo em EditEntityName.php, apos update bem-sucedido:
CacheService::tags('statistics:entity')->flush();
```

---

### Fase 6: Metricas e Monitoramento (1h)

**Objetivo:** Visibilidade sobre performance do cache.

**Tarefas:**
- [ ] Implementar contadores de hit/miss no `CacheService`
- [ ] Criar endpoint admin para visualizar stats: `/cache-stats`
- [ ] Log de cache miss rate > 50% (possivel problema de TTL)
- [ ] Garbage collection periodica para file cache

**Stats disponveis:**
```php
CacheService::stats();
// [
//     'driver' => 'file',
//     'total_keys' => 142,
//     'total_size_kb' => 256,
//     'hits' => 1420,
//     'misses' => 89,
//     'hit_rate' => '94.1%',
//     'tags' => ['permissions' => 12, 'statistics' => 33, 'selects' => 97]
// ]
```

---

## 4. Reducao de Queries Estimada

| Area | Queries Atual/Page | Com Cache | Reducao |
|------|-------------------|-----------|---------|
| Permissoes (AdmsBotao) | 4-10 | 0-1 | ~90% |
| Estatisticas (dashboard) | 5-7 | 0-1 | ~85% |
| Selects de formulario | 3-15 | 0-1 | ~90% |
| **Total por page load** | **12-32** | **0-3** | **~80%** |

**Impacto global estimado:** Reducao de **20-30%** no total de queries ao banco.

---

## 5. Riscos e Mitigacoes

| Risco | Probabilidade | Mitigacao |
|-------|---------------|-----------|
| Dados stale em dashboards | Media | TTLs curtos (5-15min) para dados de vendas |
| File cache crescendo demais | Baixa | Garbage collection periodica + TTL |
| Incompatibilidade com SelectCacheService | Baixa | Manter retrocompativel, migrar gradualmente |
| Permissoes cacheadas apos alteracao | Media | Flush automatico no controller de permissoes |
| Concorrencia de escrita (file cache) | Baixa | `flock()` para prevenir race conditions |

---

## 6. Criterios de Aceite

- [ ] `CacheService` funcional com File Driver
- [ ] `AdmsBotao::valBotao()` cacheado (0-1 query por page load)
- [ ] Pelo menos 20 models de estatisticas usando cache
- [ ] `FormSelectRepository` com 80%+ metodos cacheados
- [ ] Invalidacao por tags funcionando
- [ ] Testes unitarios (30+ testes)
- [ ] Metricas de hit/miss disponiveis
- [ ] Documentacao atualizada
- [ ] Zero regressoes em funcionalidades existentes

---

## 7. Ordem de Execucao Recomendada

```
Fase 1 (CacheService)     ████████████████████░░░░░░░░░░  6h
Fase 2 (Permissoes)        ░░░░░░░░░░░░░░░░░░░████████░░  3h
Fase 3 (Estatisticas)      ░░░░░░░░░░░░░░░░░░░░░░░█████  5h
Fase 4 (FormSelect)        ░░░░░░░░░░░░░░░░░░░░░░░░░███  3h
Fase 5 (Invalidacao)       ░░░░░░░░░░░░░░░░░░░░░░░░░░██  2h
Fase 6 (Metricas)          ░░░░░░░░░░░░░░░░░░░░░░░░░░░█  1h
                                                    Total: 20h
```

**Recomendacao:** Executar Fases 1 e 2 primeiro — maior impacto com menor esforco. A Fase 2 sozinha elimina 4-10 queries por page load em TODAS as paginas do sistema.

---

## 8. Dependencias

- **Nenhuma dependencia externa** para o MVP (File Cache)
- PHP 8.0+ (ja atendido)
- Diretorio `storage/cache/` com permissao de escrita
- Para Redis futuro: `predis/predis` ou extensao `php-redis`

---

**Elaborado por:** Claude Code
**Issue:** #102
**Status:** Aguardando aprovacao
