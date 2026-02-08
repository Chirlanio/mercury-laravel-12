# Plano de Implementacao: Opcoes de Resultado do Atendimento

**Data:** 03/02/2026
**Modulo:** Lista da Vez (Turn List)
**Status:** Planejamento

---

## 1. VISAO GERAL

### Objetivo
Adicionar opcoes de resultado (outcome) ao finalizar um atendimento na Lista da Vez para metrificar a qualidade do atendimento e a taxa de conversao de vendas.

### Opcoes de Resultado Propostas
| ID | Nome | Descricao | Cor | Icone |
|----|------|-----------|-----|-------|
| 1 | Venda Realizada | Cliente comprou | success | fa-shopping-bag |
| 2 | Pesquisa | Cliente apenas pesquisando | info | fa-search |
| 3 | Produto Indisponivel | Loja nao trabalha com o produto | warning | fa-box-open |
| 4 | Entrou e Saiu | Cliente entrou e saiu rapidamente | secondary | fa-door-open |
| 5 | Preco | Cliente desistiu pelo preco | warning | fa-tag |
| 6 | Tamanho/Modelo | Nao tinha tamanho/modelo desejado | warning | fa-ruler |
| 7 | Troca/Devolucao | Atendimento para troca ou devolucao | info | fa-exchange-alt |
| 8 | Outro | Outros motivos | secondary | fa-question-circle |

### Impacto
- **Metricas:** Taxa de conversao por consultora, loja, periodo
- **Analise:** Identificar motivos de nao-conversao
- **Relatorios:** Dashboard com estatisticas de outcomes

---

## 2. ESTRUTURA DE BANCO DE DADOS

### 2.1. Nova Tabela: ldv_attendance_outcomes

```sql
-- ============================================================================
-- TABLE: ldv_attendance_outcomes
-- Description: Outcome options for finished attendances
-- ============================================================================
CREATE TABLE IF NOT EXISTS ldv_attendance_outcomes (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COMMENT 'Nome do resultado',
    description VARCHAR(255) NULL COMMENT 'Descricao detalhada',
    color_class VARCHAR(20) NOT NULL DEFAULT 'secondary' COMMENT 'Classe Bootstrap',
    icon VARCHAR(30) NOT NULL DEFAULT 'fas fa-circle' COMMENT 'Icone FontAwesome',
    is_conversion TINYINT(1) UNSIGNED DEFAULT 0 COMMENT '1=Conta como conversao',
    display_order TINYINT UNSIGNED DEFAULT 0 COMMENT 'Ordem de exibicao',
    is_active TINYINT(1) UNSIGNED DEFAULT 1 COMMENT '1=Ativo, 0=Inativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_display_order (display_order),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default outcomes
INSERT INTO ldv_attendance_outcomes
    (id, name, description, color_class, icon, is_conversion, display_order)
VALUES
    (1, 'Venda Realizada', 'Cliente realizou compra', 'success', 'fas fa-shopping-bag', 1, 1),
    (2, 'Pesquisa', 'Cliente apenas pesquisando precos/produtos', 'info', 'fas fa-search', 0, 2),
    (3, 'Produto Indisponivel', 'Loja nao trabalha com o produto procurado', 'warning', 'fas fa-box-open', 0, 3),
    (4, 'Entrou e Saiu', 'Cliente entrou e saiu rapidamente', 'secondary', 'fas fa-door-open', 0, 4),
    (5, 'Preco', 'Cliente desistiu pelo preco', 'warning', 'fas fa-tag', 0, 5),
    (6, 'Tamanho/Modelo', 'Nao tinha tamanho ou modelo desejado', 'warning', 'fas fa-ruler', 0, 6),
    (7, 'Troca/Devolucao', 'Atendimento para troca ou devolucao', 'info', 'fas fa-exchange-alt', 0, 7),
    (8, 'Outro', 'Outros motivos nao listados', 'secondary', 'fas fa-question-circle', 0, 99);
```

### 2.2. Alteracao na Tabela: ldv_attendances

```sql
-- Add outcome_id column to ldv_attendances
ALTER TABLE ldv_attendances
    ADD COLUMN outcome_id TINYINT UNSIGNED NULL
    COMMENT 'FK to ldv_attendance_outcomes.id - Resultado do atendimento'
    AFTER return_to_queue,
    ADD INDEX idx_outcome_id (outcome_id),
    ADD CONSTRAINT fk_attendance_outcome
        FOREIGN KEY (outcome_id) REFERENCES ldv_attendance_outcomes(id)
        ON DELETE SET NULL ON UPDATE CASCADE;
```

### 2.3. Alteracao na Tabela: ldv_attendance_history (Opcional - Fase 2)

```sql
-- Add conversion stats to history (for reporting)
ALTER TABLE ldv_attendance_history
    ADD COLUMN total_conversions INT UNSIGNED DEFAULT 0
    COMMENT 'Total de vendas realizadas'
    AFTER total_attendances,
    ADD COLUMN conversion_rate DECIMAL(5,2) DEFAULT 0.00
    COMMENT 'Taxa de conversao (percentual)'
    AFTER total_conversions;
```

---

## 3. ARQUIVOS A MODIFICAR

### 3.1. Backend (PHP)

| Arquivo | Tipo | Acao |
|---------|------|------|
| `app/adms/Models/AdmsAttendance.php` | Model | Adicionar outcome_id no finish() |
| `app/adms/Controllers/AttendanceConsultant.php` | Controller | Receber outcome_id do POST |
| `app/adms/Models/AdmsAttendanceOutcome.php` | Model | **NOVO** - CRUD de outcomes |
| `app/adms/Services/FormSelectRepository.php` | Service | Adicionar getOutcomes() |

### 3.2. Frontend (Views/JS)

| Arquivo | Tipo | Acao |
|---------|------|------|
| `app/adms/Views/turnList/partials/_confirm_action_modal.php` | View | Adicionar select de outcomes |
| `assets/js/turn-list.js` | JS | Enviar outcome_id no form |

### 3.3. Database

| Arquivo | Tipo | Acao |
|---------|------|------|
| `database/migrations/ldv_attendance_outcomes.sql` | Migration | **NOVO** - Script de criacao |

---

## 4. DETALHAMENTO DAS ALTERACOES

### 4.1. AdmsAttendance.php - Metodo finish()

**Alteracao:** Adicionar parametro `$outcomeId` e salvar no banco.

```php
// ANTES
public function finish(int $attendanceId, int $returnToQueue = 1, ?string $notes = null): bool

// DEPOIS
public function finish(
    int $attendanceId,
    int $returnToQueue = 1,
    ?string $notes = null,
    ?int $outcomeId = null
): bool
{
    // ... codigo existente ...

    // Adicionar outcome_id ao updateData
    $updateData = [
        'status_id' => 2,
        'finished_at' => date('Y-m-d H:i:s'),
        'duration_seconds' => $this->duration,
        'return_to_queue' => $returnToQueue,
        'outcome_id' => $outcomeId,  // NOVO
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by_user_id' => $_SESSION['usuario_id'] ?? null
    ];

    // ... resto do codigo ...
}
```

### 4.2. AttendanceConsultant.php - Metodo finish()

**Alteracao:** Receber outcome_id do POST e passar para o model.

```php
public function finish(): void
{
    // Inputs existentes
    $attendanceId = filter_input(INPUT_POST, 'attendance_id', FILTER_VALIDATE_INT);
    $returnToQueue = filter_input(INPUT_POST, 'return_to_queue', FILTER_VALIDATE_INT) ?? 1;
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // NOVO: outcome_id
    $outcomeId = filter_input(INPUT_POST, 'outcome_id', FILTER_VALIDATE_INT);

    // ... validacoes existentes ...

    // Chamar model com novo parametro
    $result = $attendance->finish($attendanceId, $returnToQueue, $notes, $outcomeId);

    // ... resto do codigo ...
}
```

### 4.3. _confirm_action_modal.php

**Alteracao:** Adicionar select de outcomes antes do textarea de notas.

```php
<!-- NOVO: Resultado do Atendimento -->
<div class="form-group">
    <label for="finish-outcome">
        <i class="fas fa-clipboard-check mr-1"></i>
        Resultado do Atendimento <span class="text-danger">*</span>
    </label>
    <select class="form-control" id="finish-outcome" required>
        <option value="">Selecione o resultado...</option>
        <?php foreach ($this->Dados['outcomes'] ?? [] as $outcome): ?>
            <option value="<?= $outcome['id'] ?>"
                    data-color="<?= $outcome['color_class'] ?>"
                    data-icon="<?= $outcome['icon'] ?>">
                <?= htmlspecialchars($outcome['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <small class="form-text text-muted">
        Informe o resultado do atendimento para analise de conversao.
    </small>
</div>
```

### 4.4. turn-list.js - handleFinishAttendance()

**Alteracao:** Coletar e enviar outcome_id.

```javascript
async function handleFinishAttendance() {
    const attendanceId = document.getElementById('finish-attendance-id').value;
    const returnToQueue = document.getElementById('finish-return-to-queue').checked ? 1 : 0;
    const notes = document.getElementById('finish-notes').value;

    // NOVO: outcome_id
    const outcomeId = document.getElementById('finish-outcome').value;

    // Validar outcome obrigatorio
    if (!outcomeId) {
        showToast('warning', 'Por favor, selecione o resultado do atendimento.');
        return;
    }

    if (!attendanceId) return;

    try {
        const formData = new FormData();
        formData.append('attendance_id', attendanceId);
        formData.append('return_to_queue', returnToQueue);
        formData.append('notes', notes);
        formData.append('outcome_id', outcomeId);  // NOVO
        formData.append('_csrf_token', config.csrfToken);

        // ... resto do codigo existente ...
    }
}
```

### 4.5. FormSelectRepository.php

**Alteracao:** Adicionar metodo para buscar outcomes.

```php
/**
 * Busca opcoes de resultado de atendimento
 *
 * @return array
 */
public function getAttendanceOutcomes(): array
{
    $read = new AdmsRead();
    $read->fullRead(
        "SELECT id, name, description, color_class, icon, is_conversion
         FROM ldv_attendance_outcomes
         WHERE is_active = 1
         ORDER BY display_order ASC"
    );

    return $read->getResult() ?: [];
}
```

### 4.6. TurnList.php - Carregar outcomes

**Alteracao:** Carregar outcomes na pagina inicial.

```php
private function loadInitialPage(): void
{
    // ... codigo existente ...

    // NOVO: Carregar outcomes para o modal
    $formRepository = new FormSelectRepository();
    $this->data['outcomes'] = $formRepository->getAttendanceOutcomes();

    // ... resto do codigo ...
}
```

---

## 5. FASES DE IMPLEMENTACAO

### FASE 1: Banco de Dados (Prioridade: CRITICA)

**Objetivo:** Criar tabela e alterar estrutura existente

**Tarefas:**
1. Criar arquivo de migration `ldv_attendance_outcomes.sql`
2. Executar migration em ambiente de desenvolvimento
3. Testar integridade das FKs
4. Verificar dados existentes em ldv_attendances

**Verificacao:**
- [ ] Tabela ldv_attendance_outcomes criada
- [ ] Coluna outcome_id adicionada a ldv_attendances
- [ ] FK funcionando corretamente
- [ ] Dados de outcomes inseridos

**Risco:** Baixo - adiciona coluna nullable, nao quebra funcionamento atual

---

### FASE 2: Backend - Model e Controller (Prioridade: ALTA)

**Objetivo:** Atualizar logica de finalizacao

**Tarefas:**
1. Atualizar `AdmsAttendance::finish()` com parametro outcome_id
2. Atualizar `AttendanceConsultant::finish()` para receber outcome_id
3. Adicionar `FormSelectRepository::getAttendanceOutcomes()`
4. Atualizar logging para incluir outcome

**Verificacao:**
- [ ] Model salva outcome_id corretamente
- [ ] Controller valida e passa outcome_id
- [ ] FormSelectRepository retorna outcomes
- [ ] Logs incluem outcome_id

**Risco:** Baixo - parametro opcional com valor null

---

### FASE 3: Frontend - View e JavaScript (Prioridade: ALTA)

**Objetivo:** Adicionar UI para selecao de outcome

**Tarefas:**
1. Atualizar TurnList controller para carregar outcomes
2. Atualizar modal com select de outcomes
3. Atualizar JavaScript para enviar outcome_id
4. Adicionar validacao client-side

**Verificacao:**
- [ ] Select aparece no modal
- [ ] Opcoes carregam corretamente
- [ ] Validacao funciona
- [ ] Dados enviados corretamente

**Risco:** Medio - alteracao de UI visivel ao usuario

---

### FASE 4: Testes e Validacao (Prioridade: ALTA)

**Objetivo:** Garantir funcionamento completo

**Tarefas:**
1. Testar finalizacao com cada outcome
2. Testar retorno a fila com outcome
3. Verificar dados salvos no banco
4. Testar responsividade do modal

**Verificacao:**
- [ ] Todos outcomes funcionam
- [ ] Dados persistem corretamente
- [ ] UI responsiva
- [ ] Nenhuma regressao

---

### FASE 5: Relatorios (Prioridade: MEDIA - FUTURO)

**Objetivo:** Criar relatorios de conversao

**Tarefas:**
1. Criar view/stored procedure para estatisticas
2. Adicionar cards de conversao no dashboard
3. Criar relatorio detalhado por periodo/loja/consultora

**Verificacao:**
- [ ] Taxa de conversao calculada
- [ ] Dashboard atualizado
- [ ] Relatorios funcionando

---

## 6. MIGRATION SQL COMPLETA

```sql
-- ============================================================================
-- MIGRATION: Turn List Attendance Outcomes
-- Version: 1.0
-- Date: February 2026
-- Author: Grupo Meia Sola
-- ============================================================================
-- IMPORTANTE: Executar em ambiente de desenvolvimento primeiro!
-- ============================================================================

-- ============================================================================
-- FASE 1: Criar tabela de outcomes
-- ============================================================================

CREATE TABLE IF NOT EXISTS ldv_attendance_outcomes (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COMMENT 'Nome do resultado',
    description VARCHAR(255) NULL COMMENT 'Descricao detalhada',
    color_class VARCHAR(20) NOT NULL DEFAULT 'secondary' COMMENT 'Classe Bootstrap',
    icon VARCHAR(30) NOT NULL DEFAULT 'fas fa-circle' COMMENT 'Icone FontAwesome',
    is_conversion TINYINT(1) UNSIGNED DEFAULT 0 COMMENT '1=Conta como conversao',
    display_order TINYINT UNSIGNED DEFAULT 0 COMMENT 'Ordem de exibicao',
    is_active TINYINT(1) UNSIGNED DEFAULT 1 COMMENT '1=Ativo, 0=Inativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_display_order (display_order),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FASE 2: Inserir outcomes padrao
-- ============================================================================

INSERT INTO ldv_attendance_outcomes
    (id, name, description, color_class, icon, is_conversion, display_order)
VALUES
    (1, 'Venda Realizada', 'Cliente realizou compra', 'success', 'fas fa-shopping-bag', 1, 1),
    (2, 'Pesquisa', 'Cliente apenas pesquisando precos/produtos', 'info', 'fas fa-search', 0, 2),
    (3, 'Produto Indisponivel', 'Loja nao trabalha com o produto procurado', 'warning', 'fas fa-box-open', 0, 3),
    (4, 'Entrou e Saiu', 'Cliente entrou e saiu rapidamente', 'secondary', 'fas fa-door-open', 0, 4),
    (5, 'Preco', 'Cliente desistiu pelo preco', 'warning', 'fas fa-tag', 0, 5),
    (6, 'Tamanho/Modelo', 'Nao tinha tamanho ou modelo desejado', 'warning', 'fas fa-ruler', 0, 6),
    (7, 'Troca/Devolucao', 'Atendimento para troca ou devolucao', 'info', 'fas fa-exchange-alt', 0, 7),
    (8, 'Outro', 'Outros motivos nao listados', 'secondary', 'fas fa-question-circle', 0, 99)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description);

-- ============================================================================
-- FASE 3: Adicionar coluna outcome_id na tabela ldv_attendances
-- ============================================================================

-- Verificar se coluna ja existe antes de adicionar
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ldv_attendances'
    AND COLUMN_NAME = 'outcome_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE ldv_attendances
        ADD COLUMN outcome_id TINYINT UNSIGNED NULL
        COMMENT ''FK to ldv_attendance_outcomes.id - Resultado do atendimento''
        AFTER return_to_queue',
    'SELECT ''Column outcome_id already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- FASE 4: Adicionar indice e FK (se nao existir)
-- ============================================================================

-- Adicionar indice
SET @index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ldv_attendances'
    AND INDEX_NAME = 'idx_outcome_id'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE ldv_attendances ADD INDEX idx_outcome_id (outcome_id)',
    'SELECT ''Index idx_outcome_id already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar FK
SET @fk_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ldv_attendances'
    AND CONSTRAINT_NAME = 'fk_attendance_outcome'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE ldv_attendances
        ADD CONSTRAINT fk_attendance_outcome
        FOREIGN KEY (outcome_id) REFERENCES ldv_attendance_outcomes(id)
        ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT ''FK fk_attendance_outcome already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- FASE 5: Criar view para estatisticas de conversao
-- ============================================================================

CREATE OR REPLACE VIEW vw_ldv_conversion_stats AS
SELECT
    a.store_id,
    DATE(a.started_at) AS attendance_date,
    COUNT(*) AS total_attendances,
    SUM(CASE WHEN o.is_conversion = 1 THEN 1 ELSE 0 END) AS total_conversions,
    ROUND(
        (SUM(CASE WHEN o.is_conversion = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100,
        2
    ) AS conversion_rate,
    o.id AS outcome_id,
    o.name AS outcome_name,
    COUNT(a.outcome_id) AS outcome_count
FROM ldv_attendances a
LEFT JOIN ldv_attendance_outcomes o ON a.outcome_id = o.id
WHERE a.status_id = 2
GROUP BY a.store_id, DATE(a.started_at), o.id, o.name
ORDER BY a.store_id, attendance_date DESC;

-- ============================================================================
-- VERIFICACAO FINAL
-- ============================================================================

-- Verificar tabela criada
SELECT 'ldv_attendance_outcomes' AS table_name, COUNT(*) AS record_count
FROM ldv_attendance_outcomes;

-- Verificar coluna adicionada
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'ldv_attendances'
AND COLUMN_NAME = 'outcome_id';

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
```

---

## 7. RISCOS E MITIGACOES

| Risco | Impacto | Probabilidade | Mitigacao |
|-------|---------|---------------|-----------|
| Coluna outcome_id quebra inserts existentes | Alto | Baixo | Coluna nullable, valor default NULL |
| Modal nao carrega outcomes | Medio | Baixo | Validacao no controller, fallback vazio |
| JS nao envia outcome_id | Medio | Baixo | Testes manuais, validacao backend |
| FK falha com dados invalidos | Alto | Muito Baixo | ON DELETE SET NULL |
| Usuarios esquecem de selecionar | Medio | Medio | Tornar campo obrigatorio no frontend |

---

## 8. CHECKLIST DE VALIDACAO

### Pre-Deploy
- [ ] Migration testada em dev
- [ ] Backup do banco de producao
- [ ] Codigo revisado

### Pos-Deploy
- [ ] Tabela ldv_attendance_outcomes existe
- [ ] Coluna outcome_id em ldv_attendances
- [ ] Modal carrega opcoes
- [ ] Finalizacao salva outcome_id
- [ ] Dados persistem corretamente
- [ ] Nenhuma regressao nas funcoes existentes

---

## 9. ROLLBACK (Se Necessario)

```sql
-- Remover FK
ALTER TABLE ldv_attendances DROP FOREIGN KEY fk_attendance_outcome;

-- Remover indice
ALTER TABLE ldv_attendances DROP INDEX idx_outcome_id;

-- Remover coluna
ALTER TABLE ldv_attendances DROP COLUMN outcome_id;

-- Remover view
DROP VIEW IF EXISTS vw_ldv_conversion_stats;

-- Remover tabela (CUIDADO: perde dados)
DROP TABLE IF EXISTS ldv_attendance_outcomes;
```

---

**Aprovado por:** [Pendente]
**Data de Aprovacao:** [Pendente]
