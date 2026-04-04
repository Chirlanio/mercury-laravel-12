# Plano de Ação — Correções e Melhorias do Projeto Mercury

**Data:** 22 de Março de 2026
**Baseado em:** `docs/ANALISE_COMPLETA_PROJETO_2026_MAR.md`
**Objetivo:** Elevar o score do projeto de 5.6/10 para 7.5+/10

---

## Visão Geral das Fases

| Fase | Foco | Prazo | Score Esperado |
|------|------|-------|----------------|
| **1** | Segurança Crítica | 1-2 semanas | 5.6 → 6.5 |
| **2** | Validação e Integridade de Dados | 2-4 semanas | 6.5 → 7.0 |
| **3** | Logging, Testes e Qualidade | 3-6 semanas | 7.0 → 7.5 |
| **4** | Modernização de Controllers Legacy | 4-8 semanas | 7.5 → 8.0 |
| **5** | DevOps, Documentação e Excelência | 6-12 semanas | 8.0 → 8.5 |

---

## FASE 1 — Segurança Crítica (1-2 semanas)

> **Prioridade:** URGENTE
> **Impacto:** Proteger o sistema contra vulnerabilidades ativas
> **Score alvo:** 5.6 → 6.5

---

### 1.1 Rotacionar Credenciais Expostas no .env

**Risco:** CRÍTICO
**Esforço:** 2-4 horas
**Arquivos:** `.env`, `.env.example`

O `.env` contém 6 segredos expostos. Embora o `.gitignore` já exclua `*.env`, as credenciais devem ser rotacionadas como precaução:

**Credenciais a rotacionar:**

| Credencial | Linha | Ação |
|-----------|-------|------|
| `GOOGLE_API_KEY` | 1 | Regenerar no Google Cloud Console |
| `GROK_API_KEY` | 2 | Regenerar no painel Grok/xAI |
| `GOOGLE_CLIENT_SECRET` | 6 | Regenerar no Google Cloud Console → Credentials |
| `MAIL_PASS` | 67 | Alterar senha do serviço SMTP |
| `JWT_SECRET` | 72 | Gerar novo: `openssl rand -base64 32` |
| `CIGAM_PASS` | 95 | Alterar junto à equipe do ERP Cigam |

**Passos:**
1. Gerar novas credenciais nos respectivos painéis
2. Atualizar `.env` em todos os ambientes (dev, staging, prod)
3. Verificar que `.env.example` NÃO contém valores reais (já está OK)
4. Verificar que `.env` está no `.gitignore` (já está: `*.env`)
5. Invalidar sessões JWT ativas após trocar `JWT_SECRET`
6. Testar login, email, sync Cigam, e Google OAuth após a troca

**Validação:**
- [ ] Login funciona com novo JWT_SECRET
- [ ] Envio de email funciona com novo MAIL_PASS
- [ ] Sync Cigam conecta com novo CIGAM_PASS
- [ ] Google OAuth funciona com novo CLIENT_SECRET

---

### 1.2 Remover Debug Controllers

**Risco:** CRÍTICO — expõem estrutura do banco, queries SQL, dados de sessão
**Esforço:** 30 minutos
**Arquivos:**

```
app/adms/Controllers/DebugMenu.php
app/adms/Controllers/DebugMenuDetailed.php
app/adms/Controllers/DebugViewCoupon.php
```

**O que expõem:**
- `DebugMenu.php` — sessão (user ID, access level, session ID), menu completo com rotas
- `DebugMenuDetailed.php` — queries SQL, JOINs do banco, estrutura hierárquica de menus em JSON
- `DebugViewCoupon.php` — queries PDO diretas, hashes de cupons, execução de controllers

**Passos:**
1. Deletar os 3 arquivos de controller
2. Remover rotas correspondentes de `adms_paginas`:
   ```sql
   DELETE FROM adms_nivacs_pgs WHERE adms_pagina_id IN (
       SELECT id FROM adms_paginas WHERE nome_pagina IN ('DebugMenu', 'DebugMenuDetailed', 'DebugViewCoupon')
   );
   DELETE FROM adms_paginas WHERE nome_pagina IN ('DebugMenu', 'DebugMenuDetailed', 'DebugViewCoupon');
   ```
3. Verificar que as URLs retornam 404

**Validação:**
- [ ] URLs `/debug-menu`, `/debug-menu-detailed`, `/debug-view-coupon` retornam 404
- [ ] Nenhuma referência aos controllers restante no código

---

### 1.3 Implementar Validação de Upload de Arquivos

**Risco:** ALTO — upload de arquivos maliciosos
**Esforço:** 4-6 horas
**FileUploadService já existe** com validação MIME + extensão + path traversal, mas **NÃO é utilizado** nos 10 pontos de upload.

**Pontos de upload e status atual:**

| # | Arquivo | Validação Atual | Ação |
|---|---------|----------------|------|
| 1 | `AddChatBroadcast.php:279` | ✅ ALLOWED_TYPES (MIME) | Manter |
| 2 | `EditChatBroadcast.php:251` | ✅ ALLOWED_TYPES (MIME) | Manter |
| 3 | `SendFileChat.php:340` | ✅ ALLOWED_TYPES (MIME) | Manter |
| 4 | `AddTraining.php:169` | ❌ Só extensão via pathinfo | **Migrar para FileUploadService** |
| 5 | `EditTraining.php:192` | ❌ Só extensão via pathinfo | **Migrar para FileUploadService** |
| 6 | `ImportProductPrices.php:89` | ❌ Só extensão [csv,xlsx,xls] | **Adicionar MIME check** |
| 7 | `ImportOrderControl.php:91` | ❌ Só extensão [csv,txt,xlsx,xls] | **Adicionar MIME check** |
| 8 | `ImportStockAuditCount.php:215` | ❌ Só extensão [csv,txt,xlsx,xls] | **Adicionar MIME check** |
| 9 | `AdmsAddRelocation.php:~360` | ❌ Nenhuma validação | **Migrar para FileUploadService** |
| 10 | `AdmsBudgetUpload.php:226` | ❌ Só extensão | **Migrar para FileUploadService** |

**Abordagem — criar helper de validação reutilizável para imports:**

```php
// Em cada controller de import, antes de move_uploaded_file:
private function validateUploadedFile(array $file, array $allowedExtensions, array $allowedMimeTypes): bool
{
    // 1. Verificar se upload foi bem-sucedido
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $this->errorMessage = 'Erro no upload do arquivo.';
        return false;
    }

    // 2. Validar extensão
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        $this->errorMessage = "Extensão '{$extension}' não permitida. Aceitas: " . implode(', ', $allowedExtensions);
        return false;
    }

    // 3. Validar MIME type
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedMimeTypes)) {
        $this->errorMessage = "Tipo de arquivo não permitido: {$mimeType}";
        LoggerService::warning('UPLOAD_INVALID_MIME', 'Tentativa de upload com MIME inválido', [
            'filename' => $file['name'],
            'mime' => $mimeType,
            'user_id' => SessionContext::getUserId()
        ]);
        return false;
    }

    // 4. Validar tamanho (10MB default)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $this->errorMessage = 'Arquivo excede o tamanho máximo permitido (10MB).';
        return false;
    }

    return true;
}
```

**MIME types para imports CSV/Excel:**
```php
$csvMimeTypes = [
    'text/csv',
    'text/plain',
    'application/csv',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];
```

**Para Training (upload de arquivos gerais), migrar para FileUploadService:**
```php
$uploadService = new FileUploadService();
$config = new UploadConfig(
    allowedExtensions: ['pdf', 'doc', 'docx', 'ppt', 'pptx'],
    allowedMimeTypes: ['application/pdf', 'application/msword', ...],
    maxFileSize: 10 * 1024 * 1024,
    uploadDir: 'uploads/training/'
);
$result = $uploadService->upload($_FILES['file'], $config);
```

**Validação:**
- [ ] Testar upload de arquivo `.php` disfarçado de `.csv` — deve ser rejeitado
- [ ] Testar upload de CSV válido — deve funcionar normalmente
- [ ] Testar upload de imagem em Training — deve funcionar normalmente
- [ ] Testar upload acima de 10MB — deve ser rejeitado

---

### 1.4 Auditar XSS em Módulos Financeiros Críticos

**Risco:** ALTO
**Esforço:** 4-6 horas
**Contexto:** 60% das views (522 arquivos) não usam `htmlspecialchars`. Priorizar módulos que exibem dados financeiros e de usuário.

**Módulos prioritários para auditoria XSS:**

| Prioridade | Módulo | Views | Por quê |
|-----------|--------|-------|---------|
| 1 | Sales | `views/sales/*.php` | Dados financeiros, nomes de consultores |
| 2 | Adjustments | `views/adjustments/*.php` | Valores monetários, observações de usuário |
| 3 | Transfers | `views/transfers/*.php` | Valores, nomes de lojas |
| 4 | OrderPayments | `views/orderPayments/*.php` | Pagamentos, nomes |
| 5 | HolidayPayment | `views/holidayPayment/*.php` | Dados pessoais, valores |
| 6 | Employees | `views/employees/*.php` | CPF, nomes, dados pessoais |
| 7 | Helpdesk | `views/helpdesk/*.php` | Texto livre de tickets |
| 8 | Chat | `views/chat/*.php` | Mensagens de usuário |

**Padrão a aplicar em TODA saída de dado dinâmico:**
```php
<!-- ANTES (vulnerável) -->
<?= $row['nome'] ?>
<?= $row['observations'] ?>

<!-- DEPOIS (seguro) -->
<?= htmlspecialchars($row['nome'], ENT_QUOTES, 'UTF-8') ?>
<?= htmlspecialchars($row['observations'], ENT_QUOTES, 'UTF-8') ?>
```

**Checklist por módulo:**
- [ ] Buscar todos `<?=` e `<?php echo` sem `htmlspecialchars`
- [ ] Verificar atributos HTML: `value="<?= ... ?>"` → precisa escaping
- [ ] Verificar URLs dinâmicas: `href="<?= ... ?>"` → precisa escaping
- [ ] Testar com input contendo `<script>alert('xss')</script>`

---

### Checkpoint Fase 1

**Critérios de conclusão:**
- [ ] Todas as credenciais rotacionadas e testadas
- [ ] Debug controllers removidos e rotas limpas
- [ ] 7 pontos de upload corrigidos com validação MIME
- [ ] 8 módulos financeiros auditados para XSS
- [ ] Nenhuma regressão nos testes existentes

**Score esperado após Fase 1: 6.5/10**

---

## FASE 2 — Validação e Integridade de Dados (2-4 semanas)

> **Prioridade:** ALTA
> **Impacto:** Eliminar o anti-padrão AdmsCampoVazio nos módulos mais críticos
> **Score alvo:** 6.5 → 7.0

---

### 2.1 Substituir AdmsCampoVazio — Módulos Financeiros (35 models)

**Risco:** ALTO — validação inadequada em operações que movimentam dinheiro
**Esforço:** 3-5 dias
**Anti-padrão:** `AdmsCampoVazio` só verifica se algum campo é vazio. Não sabe QUAIS campos são obrigatórios, não valida tipos, formatos ou regras de negócio.

**Padrão de referência a seguir:**

Usar `AdmsAddSales.php` e `AdmsVacationPeriod.php` como referência (já usam validação explícita).

```php
// PADRÃO: método dedicado de validação
private function validateRequiredFields(): bool
{
    $required = ['campo1', 'campo2', 'campo3'];

    foreach ($required as $field) {
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errorMessage = "Campo obrigatório não preenchido: {$field}";
            $this->result = false;
            return false;
        }
    }

    // Validações de tipo
    $id = filter_var($this->data['entity_id'], FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        $this->errorMessage = 'ID inválido.';
        $this->result = false;
        return false;
    }

    // Validações de negócio (datas, ranges, existência no banco)
    // ...

    return true;
}
```

**Ordem de migração (por criticidade financeira):**

#### Lote 1 — Ajustes e Vendas (mais movimentação)
| # | Model | Campos obrigatórios esperados |
|---|-------|------------------------------|
| 1 | `AdmsAddAdjustments.php` | store_id, products[], observations |
| 2 | `AdmsEditAdjustments.php` | id, store_id, status |
| 3 | `AdmsAddSales.php` | store_id, cpf_employee, date_sales, total_sales |
| 4 | `AdmsEditSales.php` | id, valores editáveis |

#### Lote 2 — Transferências e Estornos
| # | Model | Campos obrigatórios esperados |
|---|-------|------------------------------|
| 5 | `AdmsAddTransfer.php` | store_origin, store_destination, products[] |
| 6 | `AdmsEditTransfer.php` | id, status, observations |
| 7 | `AdmsAddReversal.php` | sale_id, reason, products[] |
| 8 | `AdmsEditReversal.php` | id, status |

#### Lote 3 — Pedidos e Pagamentos
| # | Model | Campos obrigatórios esperados |
|---|-------|------------------------------|
| 9 | `AdmsAddOrderControl.php` | store_id, supplier, items[] |
| 10 | `AdmsEditOrderControl.php` | id, status |
| 11 | `AdmsAddOrderControlItems.php` | order_id, product, quantity |
| 12 | `AdmsEditOrderControlItem.php` | id, quantity, price |

#### Lote 4 — Cupons, E-commerce, Consignações
| # | Model | Campos obrigatórios esperados |
|---|-------|------------------------------|
| 13 | `AdmsAddCoupon.php` | code, discount_type, value, dates |
| 14 | `AdmsEditCoupon.php` | id, valores editáveis |
| 15 | `AdmsAddEcommerceOrder.php` | customer, items[], payment |
| 16 | `AdmsEditEcommerceOrder.php` | id, status |
| 17 | `AdmsAddConsignment.php` | store_id, products[], dates |
| 18 | `AdmsEditConsignment.php` | id, status |

#### Lote 5 — Entregas, Materiais, Promoções
| # | Model | Campos obrigatórios esperados |
|---|-------|------------------------------|
| 19-35 | Restantes do grupo financeiro | Ver categorização na análise |

**Processo por model:**
1. Ler o model atual e identificar quais campos o AdmsCampoVazio está validando
2. Criar método `validateRequiredFields()` com campos explícitos
3. Adicionar validação de tipo (`filter_var`) para IDs e valores numéricos
4. Adicionar validação de negócio (existência no banco, permissões, duplicatas)
5. Chamar o novo método ANTES do AdmsCampoVazio (fase transicional)
6. Testar com dados válidos e inválidos
7. Remover a chamada ao AdmsCampoVazio
8. Adicionar LoggerService se ausente

**Validação por lote:**
- [ ] Todos os campos obrigatórios validados explicitamente
- [ ] IDs validados com `FILTER_VALIDATE_INT`
- [ ] Valores monetários validados (positivos, formato correto)
- [ ] Datas validadas (formato, não futura quando aplicável)
- [ ] Mensagens de erro específicas por campo
- [ ] Testes unitários para cada validação

---

### 2.2 Substituir AdmsCampoVazio — Módulos RH (29 models)

**Risco:** ALTO — dados de funcionários, compliance legal
**Esforço:** 2-3 dias

**Ordem de migração:**

#### Lote 1 — Controle de Ausências e Horas
| # | Model | Campos críticos |
|---|-------|-----------------|
| 1 | `AdmsAddAbsenceControl.php` | employee_id, type, date_start, date_end |
| 2 | `AdmsEditAbsenceControl.php` | id, status |
| 3 | `AdmsAddOvertimeControl.php` | employee_id, date, hours, justification |
| 4 | `AdmsEditOvertimeControl.php` | id, status, hours |
| 5 | `AdmsAddMedicalCertificate.php` | employee_id, date_start, days, CID |
| 6 | `AdmsEditMedicalCertificate.php` | id, status |

#### Lote 2 — Cadastro e Movimentações
| # | Model | Campos críticos |
|---|-------|-----------------|
| 7 | `AdmsAddEmployee.php` | nome, cpf, date_admission, store_id, cargo_id |
| 8 | `AdmsEditEmployee.php` | id, campos editáveis |
| 9 | `AdmsAddPersonnelMoviments.php` | employee_id, type, date, reason |
| 10 | `AdmsEditPersonnelMoviments.php` | id, status |

#### Lote 3 — Treinamento e Vagas
| # | Model |
|---|-------|
| 11-29 | Restantes do grupo RH |

**Validações específicas para RH:**
- CPF: validar dígito verificador (Módulo 11)
- Datas de admissão: não pode ser futura
- Horas extras: validar range (0-24h)
- Atestados: CID deve existir no catálogo

---

### 2.3 Substituir AdmsCampoVazio — Módulos Config (70 models)

**Risco:** MÉDIO — dados de referência
**Esforço:** 3-5 dias (volume alto, baixa complexidade por model)

**Abordagem:**
Muitos destes módulos já migraram ou podem migrar para **AbstractConfigController**, que já possui validação embutida. Para os restantes:

1. Agrupar por tipo (Status, Tipo, Cadastro genérico)
2. Criar template de validação por grupo
3. Aplicar em massa com ajustes mínimos

**Template para módulos de configuração simples:**
```php
private function validateConfigData(): bool
{
    if (empty(trim($this->data['name'] ?? ''))) {
        $this->errorMessage = 'Nome é obrigatório.';
        return false;
    }

    if (strlen($this->data['name']) > 255) {
        $this->errorMessage = 'Nome não pode exceder 255 caracteres.';
        return false;
    }

    // Verificar unicidade
    $read = new AdmsRead();
    $read->fullRead(
        "SELECT id FROM {$this->table} WHERE name = :name AND id != :id LIMIT 1",
        "name={$this->data['name']}&id=" . ($this->data['id'] ?? 0)
    );
    if ($read->getResult()) {
        $this->errorMessage = 'Já existe um registro com este nome.';
        return false;
    }

    return true;
}
```

---

### Checkpoint Fase 2

**Critérios de conclusão:**
- [ ] 35 models financeiros com validação explícita
- [ ] 29 models RH com validação explícita
- [ ] 70 models config com validação explícita (ou migrados para AbstractConfigController)
- [ ] 0 chamadas a AdmsCampoVazio em módulos financeiros/RH
- [ ] Testes unitários para validações críticas
- [ ] Todas as mensagens de erro em português com acentos

**Score esperado após Fase 2: 7.0/10**

---

## FASE 3 — Logging, Testes e Qualidade (3-6 semanas)

> **Prioridade:** ALTA
> **Impacto:** Auditoria completa e cobertura de testes
> **Score alvo:** 7.0 → 7.5

---

### 3.1 Expandir LoggerService para Operações de Escrita

**Risco:** ALTO — gap de auditoria em 80% dos models
**Esforço:** 5-8 dias

**Regra:** Todo `create`, `update`, `delete` DEVE ter log correspondente.

**Padrão a seguir:**
```php
// Após create bem-sucedido
LoggerService::info('ENTITY_CREATED', 'Descrição da entidade criada', [
    'entity_id' => $id,
    'store_id' => $storeId,
    'created_by' => SessionContext::getUserId()
]);

// Após update
LoggerService::info('ENTITY_UPDATED', 'Descrição da atualização', [
    'entity_id' => $id,
    'changes' => $changedFields,  // array com campos alterados
    'updated_by' => SessionContext::getUserId()
]);

// Após delete
LoggerService::info('ENTITY_DELETED', 'Descrição da exclusão', [
    'entity_id' => $id,
    'data_before_delete' => $beforeData,  // snapshot dos dados excluídos
    'deleted_by' => SessionContext::getUserId()
]);

// Em caso de erro
LoggerService::error('ENTITY_OPERATION_FAILED', 'Mensagem de erro', [
    'context' => $relevantData,
    'user_id' => SessionContext::getUserId()
]);
```

**Ordem de prioridade:**

| Prioridade | Grupo | Models (~) | Por quê |
|-----------|-------|-----------|---------|
| 1 | Financeiro (create/update/delete) | ~35 | Compliance, auditoria |
| 2 | RH (create/update/delete) | ~29 | Trabalhista, legal |
| 3 | Inventário (create/update/delete) | ~15 | Controle de estoque |
| 4 | Helpdesk/Tickets | ~10 | SLA, rastreabilidade |
| 5 | Config/Admin | ~30 | Segurança |
| **Total** | | **~119** | |

**Métricas:**
- Antes: 118 models com LoggerService (20%)
- Meta: 237+ models com LoggerService (40%+)

---

### 3.2 Expandir Cobertura de Testes

**Risco:** ALTO — 58% dos módulos sem testes
**Esforço:** 10-15 dias

**Módulos prioritários sem testes (por impacto no negócio):**

| # | Módulo | Controllers | Criticidade | Tipo de Teste |
|---|--------|------------|-------------|---------------|
| 1 | MaterialRequest | 2 | Alta (supply chain) | Unit + Integration |
| 2 | ServiceOrder | 2 | Alta (operations) | Unit + Integration |
| 3 | OvertimeControl | 2 | Alta (payroll) | Unit + Integration |
| 4 | MedicalCertificate | 2 | Alta (legal) | Unit + Integration |
| 5 | WorkSchedule | 1 | Alta (scheduling) | Unit + Integration |
| 6 | InternalTransfer | 1 | Média (inventory) | Unit |
| 7 | Relocation | 1 | Média (inventory) | Unit |
| 8 | Returns | 1 | Média (refunds) | Unit |
| 9 | VacancyOpening | 2 | Média (HR) | Unit |
| 10 | FixedAssets | 2 | Média (accounting) | Unit |

**Padrão de teste a seguir (baseado nos testes existentes):**

```php
class AdmsAddMaterialRequestTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        $this->pdo = new PDO(/* test DB connection */);

        SessionContext::setTestData([
            'usuario_id' => 1,
            'ordem_nivac' => SUPADMPERMITION,
            'usuario_loja' => 'A001'
        ]);
    }

    protected function tearDown(): void
    {
        // Cleanup test data
        $this->pdo->exec("DELETE FROM adms_material_requests WHERE created_by_user_id = 1 AND observations LIKE 'TEST_%'");
    }

    public function testCreateWithValidData(): void
    {
        $model = new AdmsAddMaterialRequest();
        $model->create([
            'adms_store_id' => 'A001',
            'observations' => 'TEST_valid_request',
            'items' => [/* test items */]
        ]);

        $this->assertTrue($model->getResult());
    }

    public function testCreateWithMissingRequiredFields(): void
    {
        $model = new AdmsAddMaterialRequest();
        $model->create([
            // Missing store_id
            'observations' => 'TEST_missing_fields'
        ]);

        $this->assertFalse($model->getResult());
        $this->assertNotNull($model->getErrorMessage());
    }

    public function testCreateWithInvalidStorePermission(): void
    {
        SessionContext::setTestData([
            'usuario_id' => 99,
            'ordem_nivac' => 5,  // Not super admin
            'usuario_loja' => 'B002'  // Different store
        ]);

        $model = new AdmsAddMaterialRequest();
        $model->create([
            'adms_store_id' => 'A001',  // Not user's store
            'observations' => 'TEST_wrong_store'
        ]);

        $this->assertFalse($model->getResult());
    }
}
```

**Meta de testes:**
- Antes: 309 arquivos, 3.899 testes, 42% cobertura
- Meta: 350+ arquivos, 4.500+ testes, 60%+ cobertura
- Cada módulo novo: mínimo 5 testes (happy path + 4 edge cases)

---

### 3.3 Limpar Código Morto e Arquivos Obsoletos

**Esforço:** 2-4 horas

**Arquivos para remover:**
| Arquivo | Razão |
|---------|-------|
| `app/adms/Models/AdmsAddPersonnelMoviments_OLD_BACKUP.php` | Backup antigo |
| `app/adms/Models/AdmsUpdateChecklistAnswer.php.bak` | Backup |
| `assets/js/customCreate.js` | Marcado como obsoleto no header |

**Código comentado para limpar (exemplos):**
- `AddFixedAssets.php` — `//var_dump($this->Dados);`
- `CadastrarArq.php` — `//var_dump($this->Dados);`
- `CadastrarProdutos.php` — `//var_dump($this->Dados);` + `//echo "<br><br><br>";`
- `Candidate.php` — `//var_dump($this->Dados);`
- `EditarAjuste.php` — `//var_dump($this->Dados);`

---

### Checkpoint Fase 3

**Critérios de conclusão:**
- [ ] 119+ models com LoggerService em operações de escrita
- [ ] 10 módulos prioritários com testes unitários
- [ ] 4.500+ testes passando
- [ ] Arquivos backup/obsoletos removidos
- [ ] Código comentado limpo

**Score esperado após Fase 3: 7.5/10**

---

## FASE 4 — Modernização de Controllers Legacy (4-8 semanas)

> **Prioridade:** MÉDIA
> **Impacto:** Consistência, manutenibilidade, redução de código
> **Score alvo:** 7.5 → 8.0

---

### 4.1 Remover Controllers Duplicados (20-30 controllers)

**Esforço:** 2-3 dias
**Pré-requisito:** Verificar rotas no banco antes de cada remoção

**3 pares duplicados confirmados:**

#### Par 1: Ajuste → Adjustments
```
REMOVER:                          JÁ EXISTE MODERNO:
├── Ajuste.php                    ├── Adjustments.php
├── ApagarAjuste.php              ├── DeleteAdjustment.php
├── CadastrarAjuste.php           ├── AddAdjustment.php
├── EditarAjuste.php              ├── EditAdjustment.php
└── VerAjuste.php                 └── ViewAdjustment.php
```

#### Par 2: Transferencia → Transfers
```
REMOVER:                          JÁ EXISTE MODERNO:
├── Transferencia.php             ├── Transfers.php
├── ApagarTransf.php              ├── DeleteTransfer.php
├── CadastrarTransf.php           ├── AddTransfer.php
├── EditarTransf.php              ├── EditTransfer.php
└── VerTransf.php                 └── ViewTransfer.php
```

#### Par 3: *Treinamento → Training (parcial)
```
REMOVER:                              JÁ EXISTE MODERNO:
├── ApagarUsuarioTreinamento.php      ├── DeleteTraining.php
├── CadastrarUsuarioTreinamento.php   ├── AddTraining.php
├── EditarUsuarioTreinamento.php      ├── EditTraining.php
├── EditarPerfilTreinamento.php       ├── (merge em EditTraining)
├── HomeTreinamento.php               ├── (merge em Training)
├── LoginTreinamento.php              ├── PublicTraining.php
└── VerUsuarioTreinamento.php         └── (merge em ViewTraining)
```

**Processo por par:**
1. Consultar `adms_paginas` e `adms_nivacs_pgs` para encontrar rotas apontando para o controller legacy
2. Atualizar rotas para apontar para o controller moderno
3. Testar todas as rotas no browser
4. Mover o controller legacy para `_deprecated/` (não deletar imediatamente)
5. Após 2 semanas sem problemas, deletar

```sql
-- Exemplo: migrar rotas de Ajuste → Adjustments
UPDATE adms_paginas
SET nome_pagina = 'Adjustments', obs = 'Migrado de Ajuste em 2026-03'
WHERE nome_pagina = 'Ajuste';

-- Atualizar slugs no menu
UPDATE adms_paginas
SET controller_url = 'adjustments'
WHERE controller_url = 'ajuste';
```

---

### 4.2 Migrar Módulos de Config para AbstractConfigController (40+ controllers)

**Esforço:** 5-8 dias
**Padrão:** Cada módulo Sit*/Tipo*/Config com CRUD simples → 1 controller + MODULE array

**Candidatos prioritários:**

| Módulo Legacy | Controllers a consolidar | Tabela |
|--------------|------------------------|--------|
| SitAj (Status Ajuste) | Sit.php + CadastrarSitAj + EditarSitAj + ApagarSitAj + VerSitAj | `adms_sits_ajuste` |
| SitBalanco | Idem | `adms_sits_balanco` |
| SitDelivery | Idem | `adms_sits_delivery` |
| SitPg | Idem | `adms_sits_pg` |
| SitTransf | Idem | `adms_sits_transferencia` |
| SitTroca | Idem | `adms_sits_troca` |
| TipoPagamento | Idem | `adms_tipos_pagamento` |
| TipoPg | Idem | `adms_tipos_pg` |
| TipoRemanejo | Idem | `adms_tipos_remanejo` |
| Bandeira | Idem | `adms_bandeiras` |
| Ciclo | Idem | `adms_ciclos` |
| Cor | Idem | `adms_cores` |
| Cfop | Idem | `adms_cfops` |
| Cat | Idem | `adms_categorias` |

**Template de migração (AbstractConfigController):**
```php
class AdjustmentStatuses extends AbstractConfigController
{
    public const MODULE = [
        'table' => 'adms_sits_ajuste',
        'listQuery' => "SELECT id, name, created_at FROM adms_sits_ajuste ORDER BY name ASC",
        'routes' => [
            'list'   => ['controller' => 'adjustment-statuses', 'method' => 'list'],
            'create' => ['controller' => 'adjustment-statuses', 'method' => 'create'],
            'edit'   => ['controller' => 'adjustment-statuses', 'method' => 'edit'],
            'update' => ['controller' => 'adjustment-statuses', 'method' => 'update'],
            'view'   => ['controller' => 'adjustment-statuses', 'method' => 'view'],
            'delete' => ['controller' => 'adjustment-statuses', 'method' => 'delete'],
        ],
        'views' => [
            'load' => 'adjustmentStatuses/loadAdjustmentStatuses',
            'list' => 'adjustmentStatuses/listAdjustmentStatuses',
        ],
        'title' => 'Status de Ajustes',
        'icon' => 'fas fa-tags',
    ];

    protected function getConfig(): array
    {
        return self::MODULE;
    }

    // Métodos CRUD herdados automaticamente de AbstractConfigController
    // create(), edit(), update(), view(), delete(), list()
}
```

**Impacto:** ~70 controllers legacy → ~14 controllers modernos (redução de 80%)

---

### 4.3 Modernizar 39 Apagar* Controllers Restantes

**Esforço:** 3-5 dias
**Padrão:** Converter de page-reload para AJAX com JSON response

Os 29 `Apagar*` que NÃO têm equivalente moderno `Delete*` precisam ser refatorados.

**Antes (legacy):**
```php
class ApagarVideo
{
    public function apagarVideo($id = null): void
    {
        $id = (int) $id;
        $model = new AdmsApagarVideo();
        $model->apagarVideo($id);

        if ($model->getResultado()) {
            $_SESSION['msg'] = '<div class="alert alert-success">Vídeo apagado!</div>';
        } else {
            $_SESSION['msg'] = '<div class="alert alert-danger">Erro!</div>';
        }
        header("Location: " . URLADM . "listar-videos/listar");
    }
}
```

**Depois (moderno):**
```php
class DeleteVideo
{
    use JsonResponseTrait;

    public function delete(int|string|null $id = null): void
    {
        $isAjax = $this->isAjaxRequest();
        $id = filter_var($id, FILTER_VALIDATE_INT);

        if (!$id) {
            if ($isAjax) {
                $this->jsonResponse(['success' => false, 'message' => 'ID inválido.'], 400);
            }
            return;
        }

        $model = new AdmsDeleteVideo();
        $model->delete($id);

        if ($model->getResult()) {
            LoggerService::info('VIDEO_DELETED', 'Vídeo excluído', ['id' => $id]);
            if ($isAjax) {
                $this->jsonResponse(['success' => true, 'message' => 'Vídeo excluído com sucesso.']);
            } else {
                SessionContext::setFlashMessage('<div class="alert alert-success">Vídeo excluído!</div>');
                header("Location: " . URLADM . "videos/list");
            }
        } else {
            if ($isAjax) {
                $this->jsonResponse(['success' => false, 'message' => $model->getErrorMessage()], 422);
            }
        }
    }

    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
```

---

### 4.4 Modernizar Controllers de Listagem Legacy (51 controllers)

**Esforço:** 5-10 dias (volume alto)
**Padrão:** Converter `listar*()` para `list()` com match expressions

**Antes:**
```php
class Bandeira
{
    private $Dados;

    public function listarBandeira($PageId = null)
    {
        $this->PageId = (int) $PageId ?: 1;
        // ... lógica de listagem ...
        $carregarView = new \Core\ConfigView("adms/Views/bandeira/listarBandeira", $this->Dados);
        $carregarView->renderizar();
    }
}
```

**Depois:**
```php
class Banners  // ou Brands (renomear de Bandeira)
{
    private array $data = [];
    private int $pageId = 1;

    public function list(int|string|null $pageId = null): void
    {
        $this->pageId = (int) ($pageId ?: 1);
        $requestType = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT);

        match ($requestType) {
            1 => $this->listAll(),
            2 => $this->search(),
            default => $this->loadInitialPage(),
        };
    }

    private function loadInitialPage(): void
    {
        $this->loadButtons();
        $listModel = new AdmsListBanners();
        $listModel->list($this->pageId);
        $this->data['list'] = $listModel->getResult();
        $this->data['pagination'] = $listModel->getPagination();

        $loadView = new \Core\ConfigView("adms/Views/banners/loadBanners", $this->data);
        $loadView->render();
    }
}
```

**Nota:** Esta é a tarefa de maior volume. Priorizar módulos com mais uso e agrupar por similaridade para aplicar em lote.

---

### Checkpoint Fase 4

**Critérios de conclusão:**
- [ ] 20-30 controllers duplicados removidos
- [ ] 14+ módulos config migrados para AbstractConfigController
- [ ] 29 Apagar* convertidos para Delete* (AJAX)
- [ ] 51 controllers com métodos portugueses renomeados
- [ ] Rotas atualizadas em `adms_paginas`
- [ ] Zero regressões

**Score esperado após Fase 4: 8.0/10**

---

## FASE 5 — DevOps, Documentação e Excelência (6-12 semanas)

> **Prioridade:** MÉDIA-BAIXA
> **Impacto:** Sustentabilidade de longo prazo
> **Score alvo:** 8.0 → 8.5

---

### 5.1 Implementar Framework de Migrações

**Esforço:** 3-5 dias
**Situação atual:** 91 migrações manuais, sem rollback, sem tracking

**Opções:**
1. **Phinx** (recomendado — leve, standalone, sem framework dependency)
2. **Custom runner** (simples, mas sem rollback automático)

**Estrutura proposta:**
```
database/
├── migrations/
│   ├── 20260101_000000_create_initial_tables.php  // Timestamped
│   ├── 20260315_000000_stock_audit_heatmap.php
│   └── ...
├── seeds/                    // Dados de teste (mover de migrations/)
│   ├── seed_test_users.php
│   └── seed_lookup_data.php
├── schema.sql                // Dump completo do schema atual
└── migration_status.json     // Tracking de migrações aplicadas
```

**Passos:**
1. Instalar Phinx: `composer require robmorgan/phinx`
2. Criar `phinx.yml` com configurações de ambiente
3. Criar tabela de tracking `adms_migrations`
4. Migrar as 91 migrações existentes para formato Phinx (up + down)
5. Mover seed/test files de `migrations/` para `seeds/`
6. Documentar processo em `docs/DATABASE_MIGRATIONS.md`

---

### 5.2 Criar Documentação Faltante

**Esforço:** 5-8 dias

| Documento | Prioridade | Conteúdo |
|-----------|-----------|----------|
| `docs/ARCHITECTURE.md` | Alta | Visão geral, diagrama de componentes, fluxo de request |
| `docs/DEPLOYMENT.md` | Alta | Processo de deploy, checklist, rollback |
| `docs/TESTING_STRATEGY.md` | Alta | Padrões de teste, como rodar, cobertura esperada |
| `docs/CONTRIBUTING.md` | Média | Workflow, code review, branch naming |
| `docs/DATABASE_SCHEMA.md` | Média | Tabelas principais, relacionamentos, ER diagram |
| `SETUP_ENVIRONMENT.md` | Média | Atualizar (versão de Nov 2025) |

**Documentação a arquivar:**
```
docs/legacy/            ← Mover os 45 arquivos de docs/modules/MODULO_*.md
docs/legacy/            ← Mover docs/analysis/CODE_QUALITY_ANALYSIS.md (Jan 2026)
```

---

### 5.3 Consolidar Email Services

**Esforço:** 3-5 dias
**Situação atual:** 5 services de email separados

**Proposta — consolidar em NotificationService + templates:**

```
ANTES:                              DEPOIS:
├── TrainingEmailService.php        ├── NotificationService.php (core)
├── StoreGoalEmailService.php       │   ├── send(type, recipient, data)
├── ChecklistEmailService.php       │   ├── sendBulk(type, recipients, data)
├── HelpdeskEmailService.php        │   └── templates/
├── NotificationService.php         │       ├── training.php
                                    │       ├── store-goal.php
                                    │       ├── checklist.php
                                    │       └── helpdesk.php
```

---

### 5.4 Atualizar Dependências

| Dependência | Atual | Ação |
|------------|-------|------|
| `cboden/ratchet` | ^0.4 | Avaliar migração para Ratchet 0.5 ou Swoole |
| `ckeditor/ckeditor` | 4.* | Planejar migração para CKEditor 5 |
| `react/http` | ^1.9 | Testar com PHP 8.2+ |

---

### 5.5 Melhorias de Performance

| Melhoria | Impacto | Esforço |
|----------|---------|---------|
| Cache de validação de sessão (5s TTL) | Alto — reduz 1 query/request | 2h |
| CSP headers em ConfigView | Médio — segurança XSS | 2h |
| Índices de banco em queries lentas | Alto — performance | 4h |
| Redis para rate limiting (API) | Médio — escalabilidade | 1 dia |

---

### 5.6 Refatorar JS Monolíticos

| Arquivo | Linhas | Ação |
|---------|--------|------|
| `chat.js` | 5.874 | Dividir em: connection, messages, groups, broadcasts, ui |
| `order-payments.js` | 4.828 | Dividir em: list, form, allocation, transitions |
| `order-control.js` | 2.624 | Dividir em: list, items, import |

---

### Checkpoint Fase 5

**Critérios de conclusão:**
- [ ] Migration framework implementado e documentado
- [ ] 6 documentos novos criados
- [ ] Documentação legacy arquivada
- [ ] Email services consolidados
- [ ] Dependências atualizadas
- [ ] Performance otimizada
- [ ] JS monolíticos refatorados

**Score esperado após Fase 5: 8.5/10**

---

## Resumo de Impacto

### Esforço Total Estimado

| Fase | Esforço | Prioridade | Arquivos Impactados |
|------|---------|-----------|---------------------|
| **1** Segurança | 2-3 dias | URGENTE | ~30 |
| **2** Validação | 8-13 dias | ALTA | ~134 |
| **3** Logging/Testes | 12-19 dias | ALTA | ~130 |
| **4** Modernização | 15-26 dias | MÉDIA | ~120 |
| **5** DevOps/Docs | 14-21 dias | MÉDIA-BAIXA | ~50 |
| **TOTAL** | **~51-82 dias** | | **~464 arquivos** |

### Evolução de Score

```
Atual:      ████████░░░░░░░░░░░░  5.6/10
Fase 1:     █████████████░░░░░░░  6.5/10  (+0.9)
Fase 2:     ██████████████░░░░░░  7.0/10  (+0.5)
Fase 3:     ███████████████░░░░░  7.5/10  (+0.5)
Fase 4:     ████████████████░░░░  8.0/10  (+0.5)
Fase 5:     █████████████████░░░  8.5/10  (+0.5)
```

### Métricas-Chave

| Métrica | Atual | Pós-Fase 2 | Pós-Fase 5 |
|---------|-------|-----------|-----------|
| AdmsCampoVazio | 134 models | 0 financeiro/RH | 0 total |
| LoggerService | 20% models | 20% | 50%+ |
| Testes | 3.899 (42% cobertura) | 4.200+ | 4.500+ (60%) |
| Controllers legacy | 99 (16%) | 99 | ~25 (3%) |
| Views com XSS protection | 40% | 55% | 70%+ |
| Upload validation | 3/10 pontos | 10/10 | 10/10 |

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
**Próxima revisão:** Abril 2026
**Acompanhamento:** Issues/tickets por fase
