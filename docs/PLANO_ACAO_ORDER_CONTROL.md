# Plano de Ação — Módulo Ordens de Compra (OrderControl)

**Data:** 24/03/2026
**Versão:** 1.0
**Status:** Planejado
**Baseado em:** Análise técnica completa do módulo

---

## Índice

1. [Fase 1 — Correções Críticas (Integridade e Segurança)](#fase-1)
2. [Fase 2 — Refatoração e Padronização](#fase-2)
3. [Fase 3 — Melhorias Funcionais](#fase-3)
4. [Fase 4 — Melhorias UX/UI](#fase-4)
5. [Fase 5 — Testes e Documentação](#fase-5)
6. [Dependências entre Fases](#dependências)
7. [Estimativa de Arquivos por Fase](#arquivos)

---

<a name="fase-1"></a>
## Fase 1 — Correções Críticas (Integridade e Segurança)

### 1A. Transação no Delete com Verificação de Dependências

**Problema:** A exclusão de uma ordem executa dois DELETEs sequenciais (itens e depois ordem) sem transação. Se o segundo falhar, os itens já foram excluídos, criando dados órfãos. Além disso, não verifica se existem pagamentos vinculados (`adms_order_payments`).

**Solução:**
- Usar `AdmsConn::getConn()` para obter PDO e controlar transação manualmente (`beginTransaction`, `commit`, `rollBack`)
- Verificar pagamentos vinculados antes de permitir exclusão
- Oferecer opção de exclusão em cascata apenas se o usuário confirmar

**Arquivos a modificar:**
- `app/adms/Models/AdmsDeleteOrderControl.php` — reescrever `executeDelete()` com transação

**Implementação:**
```php
private function executeDelete(): bool
{
    $conn = AdmsConn::getConn();

    try {
        // Verificar dependências (pagamentos)
        $read = new AdmsRead();
        $read->fullRead(
            "SELECT COUNT(id) AS total FROM adms_order_payments
             WHERE adms_purchase_order_control_id = :order_id",
            "order_id={$this->orderId}"
        );
        $payments = (int) ($read->getResult()[0]['total'] ?? 0);

        if ($payments > 0) {
            $this->message = "Não é possível excluir: existem {$payments} pagamento(s) vinculado(s).";
            $this->result = false;
            return false;
        }

        $conn->beginTransaction();

        // Excluir itens
        $stmt = $conn->prepare("DELETE FROM adms_purchase_order_control_items WHERE adms_purchase_order_control_id = :id");
        $stmt->execute([':id' => $this->orderId]);

        // Excluir ordem
        $stmt = $conn->prepare("DELETE FROM adms_purchase_order_controls WHERE id = :id");
        $stmt->execute([':id' => $this->orderId]);

        if ($stmt->rowCount() > 0) {
            $conn->commit();
            // Log de sucesso...
            return true;
        }

        $conn->rollBack();
        $this->message = 'Erro ao excluir ordem de compra.';
        return false;

    } catch (\Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        // Log de erro...
        return false;
    }
}
```

**Critério de aceite:**
- [ ] Delete com transação: se falhar em qualquer ponto, faz rollback completo
- [ ] Bloqueia exclusão se houver pagamentos vinculados
- [ ] Mensagem de erro clara informando a quantidade de pagamentos
- [ ] Log de erro registrado em caso de rollback

---

### 1B. Implementar Filtro por Loja (StorePermissionTrait)

**Problema:** Qualquer usuário com acesso à página vê ordens de **todas as lojas**, sem filtro por nível de acesso. Módulos como Sales, Returns e OrderPayments já implementam este filtro.

**Solução:** Adicionar `StorePermissionTrait` aos models de listagem e estatísticas, e ao model de busca.

**Arquivos a modificar:**
- `app/adms/Models/AdmsListOrderControl.php` — adicionar trait e filtro na query
- `app/adms/Models/AdmsStatisticsOrderControl.php` — adicionar trait e filtro
- `app/cpadms/Models/CpAdmsSearchOrderControl.php` — adicionar trait e filtro

**Implementação (AdmsListOrderControl):**
```php
class AdmsListOrderControl
{
    use StorePermissionTrait;

    public function list(?int $pageId = null): ?array
    {
        $storeFilter = $this->buildStoreFilter('oc', 'adms_store_id');
        $storeCondition = $storeFilter['condition'] ? " WHERE 1=1{$storeFilter['condition']}" : '';
        $storeParam = $storeFilter['paramPart'];

        // Usar $storeCondition na query de paginação e na query principal
        $pagination->paginacao(
            "SELECT COUNT(id) AS num_result FROM adms_purchase_order_controls oc {$storeCondition}",
            $storeParam ?: null
        );

        // Na query principal, adicionar $storeCondition antes do ORDER BY
        // E concatenar $storeParam aos parâmetros
    }
}
```

**Padrão de referência:** `AdmsListReturns.php` e `AdmsListOrderPayments.php`

**Critério de aceite:**
- [ ] Usuário de loja vê apenas ordens destinadas à sua loja
- [ ] Super Admin (nível 1) e Admin (nível ≤5) veem todas as lojas
- [ ] Estatísticas respeitam o mesmo filtro
- [ ] Busca respeita o mesmo filtro
- [ ] Testes validam comportamento para ambos os cenários

---

### 1C. Validação de `order_number` Único

**Problema:** Não existe verificação de unicidade do `order_number` no CRUD (Create/Edit). O import já faz essa verificação, mas o formulário manual não. Duas ordens com o mesmo número podem ser criadas.

**Solução:**
- Adicionar verificação de unicidade no model de criação (`AdmsAddOrderControl`)
- Adicionar verificação no model de edição (`AdmsEditOrderControl`), excluindo o próprio ID
- Considerar adicionar constraint UNIQUE na tabela (migration)

**Arquivos a modificar:**
- `app/adms/Models/AdmsAddOrderControl.php` — adicionar check em `validate()`
- `app/adms/Models/AdmsEditOrderControl.php` — adicionar check em `validateData()`

**Implementação (Create):**
```php
// Em AdmsAddOrderControl::validate(), após verificação de campos obrigatórios:
$read = new AdmsRead();
$read->fullRead(
    "SELECT id FROM adms_purchase_order_controls WHERE order_number = :order_number LIMIT 1",
    "order_number=" . trim($this->data['order_number'])
);
if ($read->getResult()) {
    $this->error = 'Já existe uma ordem de compra com este número.';
    $this->result = false;
    return false;
}
```

**Implementação (Edit):**
```php
// Em AdmsEditOrderControl::validateData(), excluindo o próprio registro:
$read->fullRead(
    "SELECT id FROM adms_purchase_order_controls WHERE order_number = :order_number AND id != :id LIMIT 1",
    "order_number=" . trim($this->data['order_number']) . "&id={$this->data['id']}"
);
```

**Migration (opcional):**
```sql
ALTER TABLE adms_purchase_order_controls
ADD UNIQUE INDEX idx_unique_order_number (order_number);
```

**Critério de aceite:**
- [ ] Create rejeita número duplicado com mensagem clara
- [ ] Edit permite manter o próprio número mas rejeita duplicatas de outros registros
- [ ] Import continua funcionando corretamente (já possui lógica própria)

---

### 1D. Corrigir Guard Clause Inconsistente

**Problema:** `AdmsAddOrderControlItems.php` usa `if (!defined('URL'))` ao invés de `if (!defined('URLADM'))`.

**Arquivo a modificar:**
- `app/adms/Models/AdmsAddOrderControlItems.php` — linha 8

**Implementação:**
```php
// De:
if (!defined('URL')) {
// Para:
if (!defined('URLADM')) {
```

**Critério de aceite:**
- [ ] Guard clause consistente com todos os outros arquivos do módulo

---

## Fase 1 — Resumo

| Item | Risco | Arquivos | Prioridade |
|------|-------|----------|-----------|
| 1A. Transação no Delete | Integridade de dados | 1 | Crítica |
| 1B. Filtro por Loja | Segurança/Privacidade | 3 | Crítica |
| 1C. Unicidade order_number | Consistência de dados | 2 (+1 migration) | Alta |
| 1D. Guard clause | Bug potencial | 1 | Alta |

---

<a name="fase-2"></a>
## Fase 2 — Refatoração e Padronização

### 2A. Refatorar `AdmsViewOrderControl`

**Problema:** Model mais desatualizado do módulo — propriedades PascalCase, sem use statements, queries em linha única, var_dump comentado, sem LoggerService.

**Arquivo a modificar:**
- `app/adms/Models/AdmsViewOrderControl.php` — reescrita completa

**Implementação:**
```php
<?php

namespace App\adms\Models;

use App\adms\Models\helper\AdmsRead;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Model para visualização de Ordens de Compra
 *
 * @author Chirlanio Silva - Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class AdmsViewOrderControl
{
    /** @var array|null Resultado da consulta */
    private ?array $result = null;

    /**
     * Retorna o resultado da consulta
     */
    public function getResult(): ?array
    {
        return $this->result;
    }

    /**
     * Busca dados completos de uma ordem de compra
     */
    public function viewOrder(int $orderId): ?array
    {
        $viewOrder = new AdmsRead();
        $viewOrder->fullRead(
            "SELECT oc.id AS oc_id,
                    oc.short_description,
                    oc.seasons,
                    oc.colletions,
                    oc.release_name,
                    oc.order_number,
                    oc.order_date,
                    oc.predict_date,
                    oc.payment_type,
                    oc.created_by,
                    m.nome AS brand_name,
                    l.nome AS store_name,
                    sto.description_name AS status_name,
                    cr.cor
             FROM adms_purchase_order_controls oc
             LEFT JOIN adms_marcas m ON m.id = oc.adms_brand_id
             LEFT JOIN tb_lojas l ON l.id = oc.adms_store_id
             LEFT JOIN adms_sits_orders sto ON sto.id = oc.adms_sits_order_id
             LEFT JOIN adms_cors cr ON cr.id = sto.adms_cor_id
             WHERE oc.id = :id
             LIMIT :limit",
            "id={$orderId}&limit=1"
        );

        $this->result = $viewOrder->getResult();
        return $this->result;
    }

    /**
     * Busca itens de uma ordem de compra
     */
    public function viewOrderItems(int $orderId): ?array
    {
        $viewItems = new AdmsRead();
        $viewItems->fullRead(
            "SELECT oci.id,
                    oci.reference,
                    oci.size,
                    oci.unit_cost,
                    oci.pricing,
                    oci.selling_price,
                    oci.quantity_order,
                    pt.type_name,
                    oci.adms_purchase_order_control_id
             FROM adms_purchase_order_control_items oci
             LEFT JOIN adms_product_types pt ON pt.id = oci.adms_type_id
             WHERE oci.adms_purchase_order_control_id = :orderId
             ORDER BY oci.id ASC",
            "orderId={$orderId}"
        );

        $this->result = $viewItems->getResult();
        return $this->result;
    }

    /**
     * Retorna dados para selects do formulário
     * @deprecated Use FormSelectRepository::getOrderControlProductTypes() instead
     */
    public function listAdd(): array
    {
        $list = new AdmsRead();
        $list->fullRead(
            "SELECT id AS tp_id, type_name
             FROM adms_product_types
             WHERE adms_sit_id = :sits
             ORDER BY type_name ASC",
            "sits=1"
        );

        return ['types' => $list->getResult()];
    }
}
```

**Critério de aceite:**
- [ ] Propriedades em camelCase
- [ ] Use statements no topo
- [ ] Queries formatadas e legíveis
- [ ] Sem var_dump ou código de debug
- [ ] PHPDoc atualizado
- [ ] `listAdd()` marcado como deprecated
- [ ] Funcionalidade idêntica (sem mudança de comportamento)

---

### 2B. Unificar Validação Create/Edit

**Problema:** `AdmsAddOrderControl::validate()` e `AdmsEditOrderControl::validateData()` têm regras diferentes. O Create não valida existência de loja/marca; o Edit não valida datas.

**Solução:** Extrair validação para um service reutilizável.

**Arquivo a criar:**
- `app/adms/Services/OrderControlValidationService.php`

**Arquivos a modificar:**
- `app/adms/Models/AdmsAddOrderControl.php` — usar o service
- `app/adms/Models/AdmsEditOrderControl.php` — usar o service

**Implementação:**
```php
<?php

namespace App\adms\Services;

use App\adms\Models\helper\AdmsRead;

/**
 * Service de validação para Ordens de Compra
 *
 * Centraliza regras de validação compartilhadas entre Create e Edit.
 */
class OrderControlValidationService
{
    private ?string $error = null;

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Valida dados de uma ordem de compra (create ou edit)
     *
     * @param array $data Dados do formulário
     * @param int|null $excludeId ID a excluir na verificação de unicidade (para edit)
     * @return bool
     */
    public function validate(array $data, ?int $excludeId = null): bool
    {
        // 1. Campos obrigatórios
        if (!$this->validateRequiredFields($data)) {
            return false;
        }

        // 2. Validação de datas
        if (!$this->validateDates($data)) {
            return false;
        }

        // 3. Existência de loja ativa
        if (!$this->validateStore($data['adms_store_id'])) {
            return false;
        }

        // 4. Existência de marca ativa
        if (!$this->validateBrand($data['adms_brand_id'])) {
            return false;
        }

        // 5. Unicidade do order_number (Fase 1C)
        if (!$this->validateUniqueOrderNumber($data['order_number'], $excludeId)) {
            return false;
        }

        return true;
    }

    private function validateRequiredFields(array $data): bool
    {
        $requiredFields = [
            'short_description' => 'Descrição',
            'seasons' => 'Estação',
            'colletions' => 'Coleção',
            'release_name' => 'Lançamento',
            'order_number' => 'Número da Ordem',
            'adms_store_id' => 'Loja Destino',
            'adms_brand_id' => 'Marca',
            'payment_type' => 'Forma de Pagamento',
            'order_date' => 'Data do Pedido',
            'predict_date' => 'Previsão de Entrega',
        ];

        foreach ($requiredFields as $field => $label) {
            if (empty($data[$field])) {
                $this->error = "O campo '{$label}' é obrigatório.";
                return false;
            }
        }

        return true;
    }

    private function validateDates(array $data): bool
    {
        if (!empty($data['order_date']) && !empty($data['predict_date'])) {
            if (strtotime($data['predict_date']) < strtotime($data['order_date'])) {
                $this->error = 'A data de previsão não pode ser anterior à data do pedido.';
                return false;
            }
        }
        return true;
    }

    private function validateStore(string $storeId): bool
    {
        $read = new AdmsRead();
        $read->fullRead(
            "SELECT id FROM tb_lojas WHERE id = :id AND status_id = :status LIMIT 1",
            "id={$storeId}&status=1"
        );

        if (!$read->getResult()) {
            $this->error = 'Loja destino inválida ou inativa.';
            return false;
        }
        return true;
    }

    private function validateBrand(int $brandId): bool
    {
        $read = new AdmsRead();
        $read->fullRead(
            "SELECT id FROM adms_marcas WHERE id = :id AND status_id = :status LIMIT 1",
            "id={$brandId}&status=1"
        );

        if (!$read->getResult()) {
            $this->error = 'Marca inválida ou inativa.';
            return false;
        }
        return true;
    }

    private function validateUniqueOrderNumber(string $orderNumber, ?int $excludeId = null): bool
    {
        $read = new AdmsRead();
        $query = "SELECT id FROM adms_purchase_order_controls WHERE order_number = :order_number";
        $params = "order_number=" . trim($orderNumber);

        if ($excludeId) {
            $query .= " AND id != :exclude_id";
            $params .= "&exclude_id={$excludeId}";
        }

        $read->fullRead($query . " LIMIT 1", $params);

        if ($read->getResult()) {
            $this->error = 'Já existe uma ordem com este número de pedido.';
            return false;
        }
        return true;
    }
}
```

**Uso no AdmsAddOrderControl:**
```php
private function validate(): bool
{
    $validator = new OrderControlValidationService();
    if (!$validator->validate($this->data)) {
        $this->error = $validator->getError();
        $this->result = false;
        return false;
    }
    return true;
}
```

**Uso no AdmsEditOrderControl:**
```php
private function validateData(): bool
{
    $validator = new OrderControlValidationService();
    if (!$validator->validate($this->data, (int) $this->data['id'])) {
        $this->error = $validator->getError();
        $this->result = false;
        return false;
    }
    return true;
}
```

**Critério de aceite:**
- [ ] Regras de validação idênticas para Create e Edit
- [ ] Validação de datas presente em ambos
- [ ] Validação de loja/marca ativa presente em ambos
- [ ] Validação de unicidade de order_number presente em ambos
- [ ] Testes unitários para cada método de validação

---

### 2C. Adicionar Campo `updated_by`

**Problema:** O `updated_at` é preenchido no Edit, mas não existe `updated_by_user_id` para auditoria completa. O `created_by` existe no Create mas grava o **nome** do usuário ao invés do **ID**.

**Solução:**
- Adicionar coluna `updated_by_user_id` na tabela (migration)
- Preencher no model de edição
- Corrigir `created_by` para gravar o ID (e manter nome separado ou em JOIN)

**Migration:**
```sql
ALTER TABLE adms_purchase_order_controls
ADD COLUMN created_by_user_id INT UNSIGNED NULL AFTER created_by,
ADD COLUMN updated_by_user_id INT UNSIGNED NULL AFTER updated_at;
```

**Arquivos a modificar:**
- `app/adms/Models/AdmsAddOrderControl.php` — adicionar `created_by_user_id`
- `app/adms/Models/AdmsEditOrderControl.php` — adicionar `updated_by_user_id`
- `app/adms/Models/AdmsViewOrderControl.php` — incluir campo no SELECT (JOIN com adms_usuarios para nome)

**Critério de aceite:**
- [ ] Migration executada
- [ ] Create grava `created_by_user_id` com o ID numérico
- [ ] Edit grava `updated_by_user_id` com o ID numérico
- [ ] View exibe nome do criador e do último editor (via JOIN)

---

### 2D. Padronizar Nomenclatura em `AdmsAddOrderControlItems`

**Problema:** Propriedades `$Data`, `$Result`, `$Empty`, `$Error` em PascalCase.

**Arquivo a modificar:**
- `app/adms/Models/AdmsAddOrderControlItems.php`

**Implementação:** Renomear todas as propriedades para camelCase:
- `$Data` → `$data`
- `$Result` → `$result`
- `$Empty` → `$optionalFields`
- `$Error` → `$error`

**Critério de aceite:**
- [ ] Todas as propriedades em camelCase
- [ ] Nenhuma mudança funcional
- [ ] Getters retornam os mesmos valores

---

### 2E. Remover Métodos `listAdd()` Deprecated

**Problema:** 4 métodos `listAdd()` espalhados, 2 marcados deprecated, 2 não. Todos fazem queries que já existem no `FormSelectRepository`.

**Arquivos a modificar:**
- `app/adms/Models/AdmsAddOrderControl.php` — remover `listAdd()` (deprecated)
- `app/adms/Models/AdmsListOrderControl.php` — remover `listAdd()` (deprecated)
- `app/adms/Models/AdmsAddOrderControlItems.php` — marcar deprecated e migrar chamadores
- `app/adms/Models/AdmsViewOrderControl.php` — marcar deprecated e migrar chamadores

**Arquivos a verificar (chamadores):**
- `app/adms/Controllers/ViewOrderControl.php` — linha 107: `$viewModel->listAdd()` → usar `FormSelectRepository`
- Qualquer outro controller que chame `listAdd()`

**Critério de aceite:**
- [ ] Métodos deprecated removidos
- [ ] Métodos restantes marcados deprecated com apontamento para `FormSelectRepository`
- [ ] Nenhum controller chamando métodos deprecated removidos
- [ ] Funcionalidade preservada

---

### 2F. Otimizar Query N+1 na Listagem

**Problema:** Subquery correlacionada para `product_count` executa para cada linha.

**Arquivo a modificar:**
- `app/adms/Models/AdmsListOrderControl.php`

**Implementação:**
```sql
-- De (subquery correlacionada):
(SELECT COUNT(DISTINCT reference) FROM adms_purchase_order_control_items
 WHERE adms_purchase_order_control_id = oc.id) AS product_count

-- Para (LEFT JOIN com subquery):
LEFT JOIN (
    SELECT adms_purchase_order_control_id,
           COUNT(DISTINCT reference) AS product_count
    FROM adms_purchase_order_control_items
    GROUP BY adms_purchase_order_control_id
) ic ON ic.adms_purchase_order_control_id = oc.id
```

E usar `COALESCE(ic.product_count, 0) AS product_count` no SELECT.

**Critério de aceite:**
- [ ] Listagem com mesmos resultados visuais
- [ ] Performance melhorada (mensurável com EXPLAIN)

---

### 2G. Consolidar Queries de Estatísticas

**Problema:** 6 queries sequenciais com mesmos JOINs/WHERE em `AdmsStatisticsOrderControl`.

**Arquivo a modificar:**
- `app/adms/Models/AdmsStatisticsOrderControl.php`

**Implementação:**
```php
// Consolidar em 1 query para totais + 1 para distribuição por status
$queryTotals = "SELECT
    COUNT(DISTINCT oc.id) AS total_orders,
    COUNT(DISTINCT i.reference) AS total_products,
    COALESCE(SUM(i.quantity_order), 0) AS total_units,
    COALESCE(SUM(i.unit_cost * i.quantity_order), 0) AS total_cost,
    COUNT(DISTINCT oc.adms_brand_id) AS total_brands
FROM adms_purchase_order_controls oc
LEFT JOIN adms_marcas b ON b.id = oc.adms_brand_id
LEFT JOIN adms_purchase_order_control_items i ON i.adms_purchase_order_control_id = oc.id
WHERE {$whereClause}";
```

Resultado: de **6 queries** para **2 queries** (totais + distribuição por status).

**Critério de aceite:**
- [ ] Mesmos valores nas estatísticas
- [ ] Apenas 2 queries executadas ao invés de 6
- [ ] Testes existentes continuam passando

---

### 2H. Adicionar Notificação WebSocket no Delete

**Problema:** Create e Edit enviam notificações WebSocket, mas Delete não.

**Arquivo a modificar:**
- `app/adms/Controllers/DeleteOrderControl.php`

**Implementação:** Adicionar método `notifyStoreManagers()` seguindo padrão de `AddOrderControl` e `EditOrderControl`, com categoria `'workflow'`, cor `'danger'` e mensagem "Ordem de compra #ID excluída".

**Critério de aceite:**
- [ ] Notificação enviada após exclusão bem-sucedida
- [ ] Self-notification prevention (exclui usuário atual)
- [ ] Erro na notificação não bloqueia a operação (try/catch)

---

### 2I. Corrigir Flash Messages Bootstrap 4→5

**Problema:** `DeleteOrderControl::deleteRedirect()` usa classes Bootstrap 4 (`data-dismiss`, `close`).

**Arquivo a modificar:**
- `app/adms/Controllers/DeleteOrderControl.php` — linhas 79-87

**Implementação:**
```php
// De:
data-dismiss='alert'  →  data-bs-dismiss='alert'
class='close'         →  class='btn-close'
<span aria-hidden='true'>&times;</span>  →  (remover, btn-close não precisa)
```

**Critério de aceite:**
- [ ] Flash messages usando Bootstrap 5
- [ ] Consistência com restante do módulo

---

## Fase 2 — Resumo

| Item | Impacto | Arquivos | Prioridade |
|------|---------|----------|-----------|
| 2A. Refatorar AdmsViewOrderControl | Manutenibilidade | 1 | Média |
| 2B. Unificar validação | Consistência | 3 (1 novo + 2 mod) | Média |
| 2C. Campo updated_by | Auditoria | 3 + migration | Média |
| 2D. Nomenclatura Items | Padronização | 1 | Baixa |
| 2E. Remover listAdd deprecated | Limpeza | 4-5 | Baixa |
| 2F. Query N+1 | Performance | 1 | Média |
| 2G. Consolidar estatísticas | Performance | 1 | Média |
| 2H. Notificação WS no Delete | Completude | 1 | Média |
| 2I. Bootstrap 4→5 | Consistência | 1 | Baixa |

---

<a name="fase-3"></a>
## Fase 3 — Melhorias Funcionais

### 3A. State Machine de Status (OrderControlStatusTransitionService)

**Problema:** O status pode ser alterado livremente no edit sem regras de transição.

**Arquivos a criar:**
- `app/adms/Services/OrderControlStatusTransitionService.php`
- `app/adms/Models/constants/OrderControlStatus.php`
- Migration para tabela `adms_order_control_status_history`

**Fluxo de Status Proposto:**
```
Pendente(1) → Em Análise(2) → Aprovada(3) → Em Trânsito(4) → Recebida(5) → Finalizada(6)
     ↓            ↓                ↓
  Cancelada(7)  Cancelada(7)   Cancelada(7)
```

**Estrutura do Service (seguindo padrão `OrderPaymentTransitionService`):**
```php
class OrderControlStatusTransitionService
{
    private const TRANSITIONS = [
        1 => [2, 7],       // Pendente → Em Análise, Cancelada
        2 => [1, 3, 7],    // Em Análise → Pendente (devolver), Aprovada, Cancelada
        3 => [4, 7],       // Aprovada → Em Trânsito, Cancelada
        4 => [5],           // Em Trânsito → Recebida
        5 => [6],           // Recebida → Finalizada
    ];

    private const TRANSITION_PERMISSIONS = [
        '1->2' => 5,   // Gerente+
        '2->3' => 2,   // Admin+
        '3->4' => 5,   // Gerente+ (confirmou envio)
        '4->5' => 18,  // Loja (confirmou recebimento)
        '5->6' => 2,   // Admin+ (finaliza)
        'cancel' => 2,  // Admin+ pode cancelar
    ];

    public function canTransition(int $from, int $to, int $userLevel): bool;
    public function executeTransition(int $orderId, int $from, int $to, int $userId, ?string $notes): bool;
    public function getAvailableTransitions(int $currentStatus, int $userLevel): array;
    private function recordStatusHistory(int $orderId, int $from, int $to, int $userId, ?string $notes): void;
    private function handleSideEffects(int $orderId, int $from, int $to, int $userId): void;
}
```

**Migration:**
```sql
CREATE TABLE adms_order_control_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    adms_purchase_order_control_id INT UNSIGNED NOT NULL,
    old_status_id INT UNSIGNED NOT NULL,
    new_status_id INT UNSIGNED NOT NULL,
    changed_by_user_id INT UNSIGNED NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_id (adms_purchase_order_control_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Side Effects por Transição:**
- 1→2: Notificar gestores responsáveis pela análise
- 2→3: Notificar loja destino + comprador original
- 3→4: Notificar loja destino (envio confirmado, preparar recebimento)
- 4→5: Notificar comprador (ordem recebida)
- 5→6: Notificar todos os envolvidos (fluxo concluído)
- Cancelamento: Notificar todos os envolvidos

**Arquivos a modificar:**
- `app/adms/Controllers/EditOrderControl.php` — usar service para alteração de status
- `app/adms/Models/AdmsEditOrderControl.php` — delegar validação de status ao service
- Views de edição — mostrar apenas status permitidos (dropdown filtrado)

**Critério de aceite:**
- [ ] Transições respeitam regras definidas
- [ ] Permissões por nível de acesso
- [ ] Histórico registrado em tabela dedicada
- [ ] Notificações WebSocket por transição
- [ ] UI mostra apenas transições permitidas
- [ ] Testes unitários para todas as transições

---

### 3B. Filtros Avançados (Período, Marca, Loja)

**Problema:** Busca limitada a texto livre e status. Faltam filtros essenciais.

**Arquivos a modificar:**
- `app/adms/Views/orderControl/loadOrderControl.php` — adicionar campos de filtro
- `app/cpadms/Models/CpAdmsSearchOrderControl.php` — suportar novos parâmetros
- `app/adms/Models/AdmsStatisticsOrderControl.php` — suportar novos filtros
- `assets/js/order-control.js` — enviar novos parâmetros no FormData

**Novos filtros:**
```html
<!-- Filtro por Período -->
<div class="col-12 col-sm-6 col-lg-3">
    <label>Data Início</label>
    <input type="date" name="date_from" class="form-control">
</div>
<div class="col-12 col-sm-6 col-lg-3">
    <label>Data Fim</label>
    <input type="date" name="date_to" class="form-control">
</div>

<!-- Filtro por Marca -->
<div class="col-12 col-sm-6 col-lg-3">
    <label>Marca</label>
    <select name="adms_brand_id" class="form-select">
        <option value="">Todas as Marcas</option>
        <!-- options dinâmicas -->
    </select>
</div>

<!-- Filtro por Loja -->
<div class="col-12 col-sm-6 col-lg-3">
    <label>Loja Destino</label>
    <select name="adms_store_id" class="form-select">
        <option value="">Todas as Lojas</option>
        <!-- options dinâmicas -->
    </select>
</div>
```

**Critério de aceite:**
- [ ] Filtro por período funcional (data pedido ou previsão)
- [ ] Filtro por marca funcional
- [ ] Filtro por loja funcional (respeitando StorePermissionTrait)
- [ ] Filtros combináveis entre si
- [ ] Estatísticas atualizam com os filtros
- [ ] Limpar filtros reseta todos os campos

---

### 3C. Histórico de Alterações Visível ao Usuário

**Problema:** Não existe log de alterações visível. Apenas LoggerService registra no servidor.

**Depende de:** 3A (tabela `adms_order_control_status_history`)

**Arquivos a criar:**
- `app/adms/Views/orderControl/partials/_order_history_modal.php`

**Arquivos a modificar:**
- `app/adms/Models/AdmsViewOrderControl.php` — método `getStatusHistory()`
- `app/adms/Views/orderControl/partials/_view_order_control_details.php` — botão "Histórico"
- `assets/js/order-control.js` — handler para modal de histórico

**Implementação (query):**
```sql
SELECT h.old_status_id, h.new_status_id, h.notes, h.created_at,
       u.nome AS changed_by_name,
       os_old.description_name AS old_status_name,
       os_new.description_name AS new_status_name
FROM adms_order_control_status_history h
LEFT JOIN adms_usuarios u ON u.id = h.changed_by_user_id
LEFT JOIN adms_sits_orders os_old ON os_old.id = h.old_status_id
LEFT JOIN adms_sits_orders os_new ON os_new.id = h.new_status_id
WHERE h.adms_purchase_order_control_id = :order_id
ORDER BY h.created_at DESC
```

**Critério de aceite:**
- [ ] Modal com timeline de alterações de status
- [ ] Exibe: status anterior, novo status, quem alterou, quando, observações
- [ ] Acessível via botão no modal de visualização
- [ ] Formatação legível com badges de cor por status

---

### 3D. Duplicação de Ordem

**Problema:** Para pedidos recorrentes, o usuário precisa recriar manualmente todos os dados.

**Arquivos a criar:**
- `app/adms/Controllers/DuplicateOrderControl.php`
- `app/adms/Models/AdmsDuplicateOrderControl.php`

**Arquivos a modificar:**
- `app/adms/Controllers/OrderControl.php` — registrar botão de permissão
- `app/adms/Views/orderControl/listOrderControl.php` — botão "Duplicar"
- `assets/js/order-control.js` — handler de duplicação

**Lógica:**
1. Buscar ordem original e seus itens
2. Criar nova ordem com dados copiados (status = Pendente, `created_at` = agora)
3. Copiar todos os itens para a nova ordem
4. Retornar ID da nova ordem
5. Usar transação para garantir integridade

**Critério de aceite:**
- [ ] Nova ordem criada com todos os campos (exceto datas e status)
- [ ] Todos os itens copiados
- [ ] Status da nova ordem = Pendente
- [ ] Transação garante integridade (se itens falharem, ordem não é criada)
- [ ] Permissão controlada pelo sistema de botões
- [ ] Log e notificação WebSocket

---

### 3E. Exportação PDF

**Problema:** Existe exportação Excel mas não PDF para relatórios formatados.

**Arquivos a criar:**
- `app/adms/Controllers/ExportOrderControlPdf.php`
- `app/adms/Models/AdmsExportOrderControlPdf.php`

**Padrão de referência:** `StockAuditReportService.php` (usa DomPDF, chunking para tabelas grandes)

**Conteúdo do PDF:**
- Cabeçalho: logo + título "Relatório de Ordens de Compra"
- Filtros aplicados
- Tabela de ordens com totais
- Rodapé: data de geração, usuário

**Critério de aceite:**
- [ ] PDF gerado com DomPDF
- [ ] Respeita filtros ativos
- [ ] Chunking de 200 linhas para tabelas grandes
- [ ] Encoding UTF-8 correto (acentos)
- [ ] Totalizadores no rodapé da tabela

---

## Fase 3 — Resumo

| Item | Impacto | Arquivos | Prioridade |
|------|---------|----------|-----------|
| 3A. State Machine | Processo | 3 novos + 3 mod + migration | Média |
| 3B. Filtros Avançados | Usabilidade | 4 modificados | Média |
| 3C. Histórico | Rastreabilidade | 1 novo + 3 mod | Média |
| 3D. Duplicação | Produtividade | 2 novos + 3 mod | Baixa |
| 3E. Exportação PDF | Relatórios | 2 novos | Baixa |

---

<a name="fase-4"></a>
## Fase 4 — Melhorias UX/UI

### 4A. Coluna de Quantidade e Ordenação

**Problema:** `product_count` calculado mas não exibido; sem sort nas colunas.

**Arquivos a modificar:**
- `app/adms/Views/orderControl/listOrderControl.php` — coluna "Itens" + headers clicáveis
- `app/adms/Models/AdmsListOrderControl.php` — aceitar parâmetro `sort`
- `assets/js/order-control.js` — handler de sort

**Implementação de sort:**
```javascript
// Colunas ordenáveis com ícone indicativo
<th class="sortable" data-sort="oc.id" data-direction="desc">
    #ID <i class="fas fa-sort"></i>
</th>
```

**Critério de aceite:**
- [ ] Coluna "Itens" visível com badge numérico
- [ ] Headers clicáveis alternando ASC/DESC
- [ ] Indicador visual da direção de sort ativa
- [ ] Funciona junto com filtros e paginação

---

### 4B. Indicador de Previsão de Entrega Vencida

**Problema:** Ordens atrasadas não têm destaque visual.

**Arquivos a modificar:**
- `app/adms/Views/orderControl/listOrderControl.php` — badge "Atrasada"
- `app/adms/Models/AdmsListOrderControl.php` — incluir `predict_date` na query

**Implementação (na view):**
```php
<?php
$isLate = !empty($order['predict_date'])
    && strtotime($order['predict_date']) < time()
    && ($order['adms_sits_order_id'] ?? 0) < 5; // Ainda não recebida
?>
<?php if ($isLate): ?>
    <span class="badge text-bg-danger" title="Previsão vencida: <?= htmlspecialchars($order['predict_date']) ?>">
        <i class="fas fa-clock"></i> Atrasada
    </span>
<?php endif; ?>
```

**Critério de aceite:**
- [ ] Badge vermelho "Atrasada" visível na listagem
- [ ] Tooltip com a data de previsão original
- [ ] Não exibir para ordens já recebidas/finalizadas
- [ ] Card de estatísticas com contador de ordens atrasadas

---

### 4C. Cards de Estatísticas Clicáveis

**Problema:** Cards são informativos mas não interativos.

**Arquivos a modificar:**
- `app/adms/Views/orderControl/loadOrderControl.php` — `onclick` nos cards de status
- `assets/js/order-control.js` — handler para pré-filtrar ao clicar

**Implementação:**
```javascript
// Ao clicar em um status card, preenche o select e dispara busca
function filterByStatus(statusId) {
    document.getElementById(CONFIG.statusSelect).value = statusId;
    performSearch(1);
}
```

**Critério de aceite:**
- [ ] Clicar no card de status filtra a listagem
- [ ] Select de status atualiza visualmente
- [ ] Estatísticas atualizam para refletir o filtro
- [ ] Cursor pointer nos cards clicáveis

---

### 4D. Empty State Aprimorado

**Problema:** Mensagem genérica "Nenhum registro encontrado".

**Arquivo a modificar:**
- `app/adms/Views/orderControl/listOrderControl.php`

**Implementação:**
```html
<div class="text-center py-5">
    <i class="fas fa-clipboard-list fa-4x text-muted mb-3 d-block"></i>
    <h5 class="text-muted">Nenhuma ordem de compra encontrada</h5>
    <p class="text-muted mb-4">
        Tente ajustar os filtros de busca ou cadastre uma nova ordem.
    </p>
    <?php if (!empty($this->Dados['buttons']['add_order_control'])): ?>
        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addOrderModal">
            <i class="fas fa-plus me-1"></i> Nova Ordem
        </button>
        <a href="<?= URLADM ?>import-order-control/index" class="btn btn-outline-info">
            <i class="fas fa-file-import me-1"></i> Importar Planilha
        </a>
    <?php endif; ?>
</div>
```

**Critério de aceite:**
- [ ] Ícone ilustrativo grande
- [ ] Mensagem descritiva
- [ ] CTAs visíveis (Nova Ordem, Importar) se o usuário tiver permissão

---

### 4E. Breadcrumb na Visualização

**Arquivo a modificar:**
- `app/adms/Views/orderControl/viewOrderControl.php`

**Implementação:**
```html
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= URLADM ?>order-control/list">Ordens de Compra</a>
        </li>
        <li class="breadcrumb-item active">
            Ordem #<?= htmlspecialchars($order['oc_id']) ?> — <?= htmlspecialchars($order['short_description']) ?>
        </li>
    </ol>
</nav>
```

**Critério de aceite:**
- [ ] Breadcrumb presente na página de visualização
- [ ] Link funcional de volta à listagem
- [ ] Exibe ID e descrição da ordem atual

---

## Fase 4 — Resumo

| Item | Impacto | Arquivos | Prioridade |
|------|---------|----------|-----------|
| 4A. Coluna Itens + Sort | Usabilidade | 3 | Média |
| 4B. Indicador Atraso | Visibilidade | 2 | Média |
| 4C. Cards Clicáveis | Interação | 2 | Baixa |
| 4D. Empty State | UX | 1 | Baixa |
| 4E. Breadcrumb | Navegação | 1 | Baixa |

---

<a name="fase-5"></a>
## Fase 5 — Testes e Documentação

### 5A. Testes Unitários para CRUD

**Local:** `tests/OrderControl/`

**Arquivos a criar:**

| Arquivo | Escopo | Testes Estimados |
|---------|--------|-----------------|
| `AdmsAddOrderControlTest.php` | Validação, preparação de dados, criação | ~15 |
| `AdmsEditOrderControlTest.php` | Validação, update, view | ~12 |
| `AdmsDeleteOrderControlTest.php` | Verificação de dependências, cascata | ~10 |
| `OrderControlValidationServiceTest.php` | Todas as regras de validação | ~20 |
| `OrderControlStatusTransitionServiceTest.php` | Todas as transições e permissões | ~25 |
| `AdmsAddOrderControlItemsTest.php` | Single + multiple, size matrix | ~18 |

**Total estimado:** ~100 novos testes

**Padrão de teste (seguindo existente):**
```php
namespace Tests\OrderControl;

use PHPUnit\Framework\TestCase;

class OrderControlValidationServiceTest extends TestCase
{
    public function testValidateRequiredFieldsMissing(): void
    {
        $validator = new OrderControlValidationService();
        $result = $validator->validate([]);
        $this->assertFalse($result);
        $this->assertNotEmpty($validator->getError());
    }

    public function testValidatePredictDateBeforeOrderDate(): void
    {
        $data = $this->validOrderData();
        $data['predict_date'] = '2025-01-01';
        $data['order_date'] = '2026-01-01';

        $validator = new OrderControlValidationService();
        $this->assertFalse($validator->validate($data));
        $this->assertStringContainsString('previsão', $validator->getError());
    }

    public function testValidateUniqueOrderNumber(): void { /* ... */ }
    public function testValidateDuplicateOrderNumberOnEdit(): void { /* ... */ }
    public function testValidateInactiveStore(): void { /* ... */ }
    public function testValidateInactiveBrand(): void { /* ... */ }
}
```

**Critério de aceite:**
- [ ] Cobertura de todos os models de CRUD
- [ ] Cobertura do ValidationService
- [ ] Cobertura do StatusTransitionService
- [ ] Cobertura de addMultipleProducts (size matrix)
- [ ] Todos os testes passando
- [ ] Testes usam `SessionContext::setTestData()` para mock de sessão

---

### 5B. Documentação do Módulo

**Arquivo a criar:**
- `docs/ANALISE_MODULO_ORDER_CONTROL.md`

**Conteúdo:**
1. Visão geral da arquitetura
2. Diagrama de fluxo de status (se 3A implementada)
3. Lista de arquivos com propósito
4. Schema das tabelas
5. Regras de negócio documentadas
6. Guia de manutenção

**Critério de aceite:**
- [ ] Documentação completa e precisa
- [ ] Diagrama de fluxo de status
- [ ] Mapeamento de todos os arquivos

---

## Fase 5 — Resumo

| Item | Impacto | Arquivos | Prioridade |
|------|---------|----------|-----------|
| 5A. Testes CRUD | Confiabilidade | 6 novos | Alta |
| 5B. Documentação | Manutenibilidade | 1 novo | Baixa |

---

<a name="dependências"></a>
## Dependências entre Fases e Itens

```
Fase 1 (sem dependências internas, pode ser paralela)
├── 1A. Transação no Delete ← independente
├── 1B. Filtro por Loja ← independente
├── 1C. Unicidade order_number ← independente
└── 1D. Guard clause ← independente

Fase 2 (depende parcialmente de Fase 1)
├── 2A. Refatorar View ← independente
├── 2B. Unificar validação ← incorpora 1C (unicidade)
├── 2C. Campo updated_by ← independente (migration)
├── 2D. Nomenclatura Items ← independente
├── 2E. Remover listAdd ← depende de 2A (marcar deprecated lá)
├── 2F. Query N+1 ← pode combinar com 1B (mesma query)
├── 2G. Consolidar estatísticas ← pode combinar com 1B
├── 2H. Notificação WS Delete ← independente
└── 2I. Bootstrap 4→5 ← independente

Fase 3 (depende parcialmente de Fases 1 e 2)
├── 3A. State Machine ← independente (cria tabela própria)
├── 3B. Filtros Avançados ← combinar com 1B (StorePermission no search)
├── 3C. Histórico ← depende de 3A (usa tabela de histórico)
├── 3D. Duplicação ← independente
└── 3E. Exportação PDF ← independente

Fase 4 (depende de Fases anteriores para dados)
├── 4A. Coluna + Sort ← depende de 2F (mesma query)
├── 4B. Indicador Atraso ← depende de 2F (predict_date na query)
├── 4C. Cards Clicáveis ← independente (JS apenas)
├── 4D. Empty State ← independente
└── 4E. Breadcrumb ← independente

Fase 5 (depende de tudo anterior)
├── 5A. Testes ← após implementações das fases 1-3
└── 5B. Documentação ← após tudo implementado
```

---

<a name="arquivos"></a>
## Estimativa de Arquivos por Fase

| Fase | Novos | Modificados | Migrations | Total |
|------|-------|-------------|-----------|-------|
| **Fase 1** | 0 | 6 | 0 | 6 |
| **Fase 2** | 1 | 10 | 1 | 12 |
| **Fase 3** | 7 | 8 | 1 | 16 |
| **Fase 4** | 0 | 6 | 0 | 6 |
| **Fase 5** | 7 | 0 | 0 | 7 |
| **Total** | **15** | **30** | **2** | **47** |

---

## Ordem de Execução Recomendada

```
Semana 1:  Fase 1 completa (1A → 1B → 1C → 1D)
Semana 2:  Fase 2A + 2B + 2C (refatoração core + validação + migration)
Semana 3:  Fase 2D-2I (padronização e otimizações)
Semana 4:  Fase 3A (State Machine — item mais complexo)
Semana 5:  Fase 3B + 3C (filtros + histórico)
Semana 6:  Fase 3D + 3E (duplicação + PDF)
Semana 7:  Fase 4 completa (UX/UI)
Semana 8:  Fase 5 completa (testes + documentação)
```

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
**Última Atualização:** 24/03/2026
