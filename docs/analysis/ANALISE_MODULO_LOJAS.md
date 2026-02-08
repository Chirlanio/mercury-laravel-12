# Análise Completa do Módulo de Lojas (Stores)

**Data:** 16 de Janeiro de 2026
**Versão:** 1.0
**Status:** Legado - Requer Refatoração

---

## 1. Visão Geral

O módulo de Lojas gerencia o cadastro e manutenção das lojas do grupo. É um módulo fundamental pois serve como FK para diversos outros módulos do sistema (Holiday Payment, Turn List, Delivery, Personnel Movements, etc.).

### 1.1. Funcionalidades

| Funcionalidade | Status | Observação |
|----------------|--------|------------|
| Listagem | ✅ Implementada | Paginação funcional |
| Cadastro | ✅ Implementada | Formulário completo |
| Edição | ✅ Implementada | Formulário completo |
| Visualização | ✅ Implementada | Read-only view |
| Exclusão | ✅ Implementada | Sem confirmação modal |
| Pesquisa | ❌ Não existe | Precisa implementar |
| Filtros | ❌ Não existe | Precisa implementar |
| Estatísticas | ❌ Não existe | Opcional |

---

## 2. Estrutura Atual de Arquivos

### 2.1. Controllers

| Arquivo | Classe | Método Principal |
|---------|--------|------------------|
| `Lojas.php` | `Lojas` | `listarLojas($PageId)` |
| `CadastrarLoja.php` | `CadastrarLoja` | `cadLoja()` |
| `EditarLoja.php` | `EditarLoja` | `editLoja($DadosId)` |
| `VerLoja.php` | `VerLoja` | `verLoja($DadosId)` |
| `ApagarLoja.php` | `ApagarLoja` | `apagarLoja($DadosId)` |

### 2.2. Models

| Arquivo | Classe | Métodos |
|---------|--------|---------|
| `AdmsListarLojas.php` | `AdmsListarLojas` | `listarLojas()`, `getResult()` |
| `AdmsCadastrarLoja.php` | `AdmsCadastrarLoja` | `cadLoja()`, `inserirLoja()`, `listarCadastrar()` |
| `AdmsEditarLoja.php` | `AdmsEditarLoja` | `verLoja()`, `altLoja()`, `updateEditLojas()`, `listarCadastrar()` |
| `AdmsVerLoja.php` | `AdmsVerLoja` | `verLoja()` |
| `AdmsApagarLoja.php` | `AdmsApagarLoja` | `apagarLoja()`, `getResultado()` |

### 2.3. Views

| Arquivo | Tipo | Descrição |
|---------|------|-----------|
| `listarLojas.php` | Lista | Tabela paginada |
| `cadLoja.php` | Formulário | Cadastro de loja |
| `editarLojas.php` | Formulário | Edição de loja |
| `verLoja.php` | Detalhes | Visualização read-only |

### 2.4. JavaScript

**Não existe arquivo JS dedicado para o módulo.**

### 2.5. Testes

**Não existem testes automatizados para o módulo.**

---

## 3. Estrutura do Banco de Dados

### 3.1. Diagrama ER

```
┌─────────────────────┐     ┌─────────────────────┐
│    tb_redes         │     │  tb_status_loja     │
├─────────────────────┤     ├─────────────────────┤
│ PK id               │     │ PK id               │
│    nome             │     │    nome             │
│    created          │     │    adms_cor_id      │
│    modified         │     │    created          │
└────────┬────────────┘     │    modified         │
         │                  └──────────┬──────────┘
         │ 1                           │ 1
         │                             │
         │ N                           │ N
┌────────┴─────────────────────────────┴──────────┐
│                    tb_lojas                      │
├──────────────────────────────────────────────────┤
│ PK id_loja (AUTO_INCREMENT)                      │
│    id (VARCHAR 4) - Código da loja               │
│    nome (VARCHAR 60)                             │
│    cnpj (VARCHAR 14)                             │
│    razao_social (VARCHAR 120)                    │
│    ins_estadual (VARCHAR 9)                      │
│    endereco (VARCHAR 255)                        │
│ FK rede_id → tb_redes.id                         │
│ FK func_id → adms_employees.id (gerente)         │
│ FK super_id → adms_employees.id (supervisor)     │
│ FK status_id → tb_status_loja.id                 │
│    created (DATETIME)                            │
│    modified (DATETIME)                           │
└──────────────────────────────────────────────────┘
         │ N                           │ N
         │                             │
         │ 1                           │ 1
┌────────┴─────────────────────────────┴──────────┐
│               adms_employees                     │
├──────────────────────────────────────────────────┤
│ PK id                                            │
│    name_employee                                 │
│    position_id                                   │
│    adms_status_employee_id                       │
│    ...                                           │
└──────────────────────────────────────────────────┘
```

### 3.2. Tabela Principal: `tb_lojas`

```sql
CREATE TABLE `tb_lojas` (
    `id_loja` INT(11) NOT NULL AUTO_INCREMENT,
    `id` VARCHAR(4) NOT NULL COMMENT 'Código da loja (ex: Z421)',
    `nome` VARCHAR(60) NOT NULL COMMENT 'Nome da loja',
    `cnpj` VARCHAR(14) NOT NULL COMMENT 'CNPJ sem formatação',
    `razao_social` VARCHAR(120) NOT NULL COMMENT 'Razão social',
    `ins_estadual` VARCHAR(9) DEFAULT NULL COMMENT 'Inscrição estadual',
    `endereco` VARCHAR(255) DEFAULT NULL COMMENT 'Endereço completo',
    `rede_id` INT(11) NOT NULL COMMENT 'FK: tb_redes',
    `func_id` INT(11) DEFAULT NULL COMMENT 'FK: adms_employees (gerente)',
    `super_id` INT(11) DEFAULT NULL COMMENT 'FK: adms_employees (supervisor)',
    `status_id` INT(11) NOT NULL COMMENT 'FK: tb_status_loja',
    `created` DATETIME DEFAULT NULL COMMENT 'Data de criação',
    `modified` DATETIME DEFAULT NULL COMMENT 'Data de modificação',
    PRIMARY KEY (`id_loja`),
    UNIQUE KEY `uk_cnpj` (`cnpj`),
    KEY `idx_rede_id` (`rede_id`),
    KEY `idx_status_id` (`status_id`),
    KEY `idx_func_id` (`func_id`),
    KEY `idx_super_id` (`super_id`),
    CONSTRAINT `fk_lojas_rede` FOREIGN KEY (`rede_id`) REFERENCES `tb_redes` (`id`),
    CONSTRAINT `fk_lojas_status` FOREIGN KEY (`status_id`) REFERENCES `tb_status_loja` (`id`),
    CONSTRAINT `fk_lojas_gerente` FOREIGN KEY (`func_id`) REFERENCES `adms_employees` (`id`),
    CONSTRAINT `fk_lojas_supervisor` FOREIGN KEY (`super_id`) REFERENCES `adms_employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Total de Registros:** ~26 lojas

### 3.3. Tabela de Status: `tb_status_loja`

```sql
CREATE TABLE `tb_status_loja` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `nome` VARCHAR(20) NOT NULL COMMENT 'Nome do status',
    `adms_cor_id` INT(11) DEFAULT NULL COMMENT 'Cor para exibição',
    `created` DATETIME DEFAULT NULL,
    `modified` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Dados:**

| id | nome | adms_cor_id |
|----|------|-------------|
| 1 | Aberta | 1 |
| 2 | Fechada | 2 |
| 3 | Em Abertura | 3 |

### 3.4. Tabela de Redes: `tb_redes`

```sql
CREATE TABLE `tb_redes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `nome` VARCHAR(50) NOT NULL COMMENT 'Nome da rede/marca',
    `created` DATETIME DEFAULT NULL,
    `modified` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Dados:**

| id | nome |
|----|------|
| 1 | AREZZO |
| 2 | ANACAPRI |
| 3 | MEIA SOLA |
| 4 | SCHUTZ |
| 5 | MS OFF |
| 6 | E-COMMERCE |
| 7 | ADMINISTRATIVO |

### 3.5. Queries Principais

#### Listagem de Lojas
```sql
SELECT
    lj.*,
    r.nome AS rede,
    st.nome AS status
FROM tb_lojas lj
INNER JOIN tb_status_loja st ON st.id = lj.status_id
INNER JOIN tb_redes r ON r.id = lj.rede_id
ORDER BY lj.id_loja ASC
LIMIT :limit OFFSET :offset;
```

#### Visualização de Loja
```sql
SELECT
    lj.*,
    r.nome AS rede,
    sit.nome AS sit_lj
FROM tb_lojas lj
INNER JOIN tb_redes r ON r.id = lj.rede_id
INNER JOIN tb_status_loja sit ON sit.id = lj.status_id
WHERE lj.id_loja = :id_loja
LIMIT 1;
```

#### Selects para Formulários
```sql
-- Status
SELECT id AS sit_id, nome AS sit FROM tb_status_loja ORDER BY id ASC;

-- Redes
SELECT id AS rede_id, nome AS rede FROM tb_redes ORDER BY id ASC;

-- Gerentes (position_id=2, status=2)
SELECT id AS func_id, name_employee AS func
FROM adms_employees
WHERE position_id = :cargo_id AND adms_status_employee_id = :status_id
ORDER BY name_employee ASC;

-- Supervisores (cargo nível 1, status=2)
SELECT f.id AS super_id, f.name_employee AS super
FROM adms_employees f
LEFT JOIN tb_cargos c ON c.id = f.position_id
WHERE c.adms_niv_cargo_id = :niv_cargo AND f.adms_status_employee_id = :status_id
ORDER BY f.name_employee ASC;
```

---

## 4. Análise de Código

### 4.1. Controllers

#### Lojas.php (Listagem)
```php
class Lojas {
    private $Dados;
    private $PageId;

    public function listarLojas($PageId = null) {
        // Carrega botões, menu, lista e paginação
        // Renderiza view completa
    }
}
```

**Problemas identificados:**
- ❌ Sem type hints
- ❌ Sem PHPDoc
- ❌ Nomenclatura em português
- ❌ Não segue padrão `list()` do projeto
- ❌ Variáveis em PascalCase ($Dados, $PageId)

#### CadastrarLoja.php (Adicionar)
```php
class CadastrarLoja {
    public function cadLoja() {
        // Processa POST
        // Redireciona após sucesso
    }
}
```

**Problemas identificados:**
- ❌ Nomenclatura em português
- ❌ Não retorna JSON para AJAX
- ❌ Página completa ao invés de modal
- ❌ Não usa LoggerService
- ❌ Sem validação de permissões

### 4.2. Models

#### AdmsListarLojas.php
```php
class AdmsListarLojas {
    public function listarLojas($PageId = null) {
        // SQL com colunas inexistentes: network_order, order_store
        $query = "SELECT ... ORDER BY network_order ASC, order_store ASC";
    }
}
```

**Problemas CRÍTICOS:**
- 🔴 **BUG:** Query referencia colunas inexistentes (`network_order`, `order_store`)
- ❌ Sem type hints
- ❌ Nomenclatura incorreta (deveria ser `AdmsListStores`)
- ❌ Não usa `use` statements
- ❌ Propriedades sem visibilidade explícita

#### AdmsCadastrarLoja.php
```php
class AdmsCadastrarLoja {
    private $Resultado;
    private $Dados;

    public function cadLoja(array $Dados) {
        // Validação com AdmsCampoVazio
        // Insere com AdmsCreate
        // Flash message via $_SESSION['msg']
    }
}
```

**Problemas identificados:**
- ❌ Usa `$_SESSION['msg']` para mensagens (deveria usar NotificationService)
- ❌ Não usa LoggerService
- ❌ Não tem `getError()` method

### 4.3. Views

#### listarLojas.php
- ✅ Usa htmlspecialchars para XSS prevention
- ✅ Responsivo com classes Bootstrap
- ❌ Sem container para AJAX refresh
- ❌ Sem área de filtros/busca
- ❌ Exclusão sem modal de confirmação

#### cadLoja.php / editarLojas.php
- ✅ CSRF protection com `csrf_field()`
- ✅ Validação HTML5 (required)
- ❌ Páginas completas ao invés de modais
- ❌ IDs duplicados em elementos
- ❌ Sem feedback visual de loading

---

## 5. Problemas Identificados

### 5.1. Problemas Críticos (Bugs)

| # | Problema | Arquivo | Linha | Impacto |
|---|----------|---------|-------|---------|
| 1 | SQL com colunas inexistentes | `AdmsListarLojas.php` | 34 | Query pode falhar |
| 2 | Verificação URL inconsistente | `AdmsVerLoja.php` | 5 | Usa `URL` ao invés de `URLADM` |
| 3 | Verificação URL inconsistente | `AdmsApagarLoja.php` | 5 | Usa `URL` ao invés de `URLADM` |

### 5.2. Problemas de Padrão (Naming)

| Atual | Esperado | Tipo |
|-------|----------|------|
| `Lojas` | `Store` | Controller |
| `CadastrarLoja` | `AddStore` | Controller |
| `EditarLoja` | `EditStore` | Controller |
| `VerLoja` | `ViewStore` | Controller |
| `ApagarLoja` | `DeleteStore` | Controller |
| `AdmsListarLojas` | `AdmsListStores` | Model |
| `AdmsCadastrarLoja` | `AdmsAddStore` | Model |
| `AdmsEditarLoja` | `AdmsEditStore` | Model |
| `AdmsVerLoja` | `AdmsViewStore` | Model |
| `AdmsApagarLoja` | `AdmsDeleteStore` | Model |

### 5.3. Problemas de Arquitetura

| # | Problema | Solução |
|---|----------|---------|
| 1 | Sem arquitetura AJAX/modal | Implementar modais |
| 2 | Sem LoggerService | Adicionar logging |
| 3 | Sem NotificationService | Usar para feedback |
| 4 | Sem JavaScript dedicado | Criar `store.js` |
| 5 | Sem testes automatizados | Criar testes |
| 6 | Sem funcionalidade de busca | Implementar |

### 5.4. Problemas de Segurança (Resolvidos)

- ✅ CSRF protection implementada
- ✅ htmlspecialchars em outputs
- ✅ Prepared statements em queries
- ⚠️ Falta validação de permissões em alguns controllers

---

## 6. Dependências

### 6.1. Módulos que Usam tb_lojas

| Módulo | Uso |
|--------|-----|
| Holiday Payment | Selecionar loja do pagamento |
| Turn List (Lista da Vez) | Fila por loja |
| Delivery Routing | Roteamento de entregas |
| Personnel Movements | Movimentação de pessoal |
| Users | Loja do usuário |
| Employees | Loja do funcionário |
| Ecommerce Orders | Pedidos por loja |

### 6.2. Tabelas Relacionadas

- `tb_redes` - Redes/marcas
- `tb_status_loja` - Status da loja
- `adms_employees` - Gerente e supervisor

---

## 7. Métricas de Código

### 7.1. Linhas de Código

| Arquivo | Linhas |
|---------|--------|
| Controllers (5 arquivos) | ~170 |
| Models (5 arquivos) | ~200 |
| Views (4 arquivos) | ~400 |
| **Total** | **~770** |

### 7.2. Cobertura de Testes

- **Unit Tests:** 0%
- **Integration Tests:** 0%
- **Feature Tests:** 0%

---

## 8. Referências

### 8.1. URLs do Módulo

| Ação | URL Atual |
|------|-----------|
| Listar | `/lojas/listar-lojas` |
| Cadastrar | `/cadastrar-loja/cad-loja` |
| Editar | `/editar-loja/edit-loja/{id}` |
| Ver | `/ver-loja/ver-loja/{id}` |
| Apagar | `/apagar-loja/apagar-loja/{id}` |

### 8.2. Botões de Permissão

| Chave | Controller | Método |
|-------|------------|--------|
| `cad_loja` | `cadastrar-loja` | `cad-loja` |
| `vis_loja` | `ver-loja` | `ver-loja` |
| `edit_loja` | `editar-loja` | `edit-loja` |
| `del_loja` | `apagar-loja` | `apagar-loja` |
| `list_loja` | `lojas` | `listar-lojas` |

---

## 9. Conclusão

O módulo de Lojas é um módulo legado que funciona mas não segue os padrões atuais do projeto Mercury. A refatoração é recomendada para:

1. **Padronização:** Alinhar nomenclatura com o padrão do projeto
2. **Manutenibilidade:** Manter models separados seguindo nomenclatura padrão
3. **UX:** Implementar arquitetura modal-based com AJAX
4. **Auditoria:** Adicionar LoggerService para rastreabilidade
5. **Qualidade:** Adicionar testes automatizados
6. **Bug Fix:** Corrigir SQL com colunas inexistentes

### Prioridade

**MÉDIA-ALTA** - Módulo crítico usado por muitos outros módulos, mas funcional.

---

*Documento gerado em: 16/01/2026*
*Próximo passo: Ver PLANO_REFATORACAO_LOJAS.md*
