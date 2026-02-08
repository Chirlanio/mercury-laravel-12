# Análise Completa do Módulo de Marcas

**Data:** 04 de Fevereiro de 2026
**Versão:** 1.1
**Autor:** Equipe Mercury - Grupo Meia Sola
**Módulo de Referência:** Cargos (refatorado em Janeiro/2026)

---

## 1. Resumo Executivo

Existem **dois módulos distintos** para gestão de marcas, cada um com finalidade específica:

| Módulo | Tabela | Finalidade | Usado em |
|--------|--------|------------|----------|
| **Marcas** | `adms_marcas` | Marcas de produtos | Order Control, Service Order, Remanejo |
| **Brands** | `adms_brands_suppliers` | Marcas de fornecedores | Order Payment |

**Decisão:** Ambos os módulos devem ser **mantidos separados** e refatorados individualmente seguindo o padrão do módulo Cargos.

**Plano de Refatoração:**
1. **Fase 1:** Refatorar módulo Marcas → `ProductBrand` (prioridade alta - mais utilizado)
2. **Fase 2:** Refatorar módulo Brands → `SupplierBrand` (prioridade média)

---

## 2. Estrutura Atual dos Arquivos

### 2.1. Implementação "Marcas" (Português - Legado)

```
Controllers/
├── Marcas.php              ❌ Nomenclatura PT, sem type hints
├── CadastrarMarca.php      ❌ Nomenclatura PT, full page reload
├── EditarMarca.php         ❌ Nomenclatura PT, full page reload
├── ApagarMarca.php         ❌ Nomenclatura PT, sem confirmação modal
└── VerMarca.php            ❌ Nomenclatura PT, full page reload

Models/
├── AdmsListarMarca.php     ❌ Nomenclatura PT, sem search
├── AdmsCadastrarMarca.php  ❌ Nomenclatura PT, sem LoggerService
├── AdmsEditarMarca.php     ❌ Nomenclatura PT, sem LoggerService
├── AdmsApagarMarca.php     ❌ Nomenclatura PT, sem LoggerService
└── AdmsVerMarca.php        ❌ Nomenclatura PT

Views/marca/
├── listarMarca.php         ❌ Nomenclatura PT, sem filtros
├── cadMarca.php            ❌ Full page form
├── editarMarca.php         ❌ Full page form
└── verMarca.php            ❌ Full page view
```

### 2.2. Implementação "Brands" (Inglês - Parcial)

```
Controllers/
├── Brands.php              ⚠️ Nomenclatura OK, mas padrão antigo
├── AddBrands.php           ⚠️ Nomenclatura incorreta (deveria ser AddBrand)
├── EditBrands.php          ⚠️ Nomenclatura incorreta
└── DeleteBrands.php        ⚠️ Nomenclatura incorreta

Models/
├── AdmsListBrands.php      ⚠️ Sem search, sem type hints
├── AdmsAddBrands.php       ⚠️ Nomenclatura incorreta
├── AdmsEditBrand.php       ✅ Nomenclatura correta
└── AdmsDeleteBrand.php     ✅ Nomenclatura correta

Views/brand/
├── listBrand.php           ⚠️ Página única, sem AJAX
├── addBrand.php            ⚠️ Full page form
└── editBrand.php           ⚠️ Full page form
```

### 2.3. Estrutura Esperada (Padrão Cargos)

```
Controllers/
├── Brand.php               # Controller principal (singular)
├── AddBrand.php            # Adicionar
├── EditBrand.php           # Editar
├── ViewBrand.php           # Visualizar
└── DeleteBrand.php         # Deletar

Models/
├── AdmsListBrands.php      # Listagem (plural)
├── AdmsAddBrand.php        # CRUD adicionar (singular)
├── AdmsEditBrand.php       # CRUD editar (singular)
├── AdmsViewBrand.php       # Visualização (singular)
└── AdmsDeleteBrand.php     # Exclusão (singular)

Views/brand/
├── loadBrand.php           # Página principal
├── listBrand.php           # Listagem (AJAX)
└── partials/
    ├── _add_brand_modal.php
    ├── _edit_brand_modal.php
    ├── _edit_brand_form.php
    ├── _view_brand_modal.php
    ├── _view_brand_details.php
    └── _delete_brand_modal.php

assets/js/
└── brand.js                # JavaScript AJAX
```

---

## 3. Análise Comparativa Detalhada

### 3.1. Controller Principal

| Aspecto | Marcas (Atual) | Cargos (Referência) | Status |
|---------|----------------|---------------------|--------|
| Nomenclatura | `Marcas` (PT, plural) | `Cargo` (EN, singular) | ❌ |
| Type hints | Não | Sim | ❌ |
| PHPDoc | Básico | Completo | ❌ |
| Match expression | Não | Sim | ❌ |
| Método padrão | `listar()` | `list()` | ❌ |
| Roteamento AJAX | Não | `type=1,2` | ❌ |
| Busca/Filtros | Não | Sim | ❌ |
| Variáveis | `$Dados`, `$PageId` | `$data`, `$pageId` | ❌ |
| Use statements | Inline | Imports | ❌ |

**Código Atual (Marcas.php):**
```php
class Marcas {
    private $Dados;
    private $PageId;

    public function listar($PageId = null) {
        $this->PageId = (int) $PageId ? $PageId : 1;
        // ... código sem type hints, sem match, sem AJAX
    }
}
```

**Código Esperado (Padrão Cargos):**
```php
class Brand
{
    private ?array $data = [];
    private int $pageId;

    public function list(int|string|null $pageId = null): void
    {
        $this->pageId = (int) ($pageId ?: 1);
        $requestType = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT);

        match ($requestType) {
            1 => $this->listAllItems(),
            2 => $this->searchItems(),
            default => $this->loadInitialPage(),
        };
    }
}
```

### 3.2. Controller de Ação (Add)

| Aspecto | CadastrarMarca (Atual) | AddCargo (Referência) | Status |
|---------|------------------------|----------------------|--------|
| Nomenclatura | `CadastrarMarca` (PT) | `AddCargo` (EN) | ❌ |
| Resposta | Redirect (full page) | JSON | ❌ |
| NotificationService | Não | Sim | ❌ |
| LoggerService | Não | Sim | ❌ |
| Validação centralizada | Não | Sim | ❌ |
| Constructor | Não | Sim (DI) | ❌ |

### 3.3. Model de Listagem

| Aspecto | AdmsListarMarca (Atual) | AdmsListCargos (Referência) | Status |
|---------|-------------------------|----------------------------|--------|
| Nomenclatura | `AdmsListarMarca` (PT) | `AdmsListCargos` (EN) | ❌ |
| Type hints | Não | Sim | ❌ |
| Método search | Não | Sim | ❌ |
| Método listFormData | Não | Sim | ❌ |
| Use statements | Inline | Imports | ❌ |
| PHPDoc | Básico | Completo | ❌ |

### 3.4. Views

| Aspecto | marca/ (Atual) | cargo/ (Referência) | Status |
|---------|----------------|---------------------|--------|
| Estrutura | 4 arquivos flat | load + list + partials/ | ❌ |
| Load/List separados | Não | Sim | ❌ |
| Modais | Não | Sim (4 modais) | ❌ |
| AJAX container | Não | `#content_cargos` | ❌ |
| Config div | Não | `#cargo-config` | ❌ |
| Filtros de busca | Não | Card com formulário | ❌ |
| Responsividade | Básica | Completa | ⚠️ |

### 3.5. JavaScript

| Aspecto | Marcas (Atual) | cargo.js (Referência) | Status |
|---------|----------------|----------------------|--------|
| Arquivo JS dedicado | Não existe | Sim (578 linhas) | ❌ |
| AJAX listing | Não | `listCargos()` | ❌ |
| AJAX search | Não | `performSearch()` | ❌ |
| AJAX CRUD | Não | Todas operações | ❌ |
| Event delegation | Não | Sim | ❌ |
| Paginação AJAX | Não | Sim | ❌ |
| Manutenção de estado | Não | `currentPage`, filtros | ❌ |

---

## 4. Problemas Identificados

### 4.1. Problemas Críticos

1. **Duplicidade de Módulos**
   - Duas implementações para funcionalidades similares
   - Confusão sobre qual usar (Marcas vs Brands)
   - Tabelas diferentes (`adms_marcas` vs `adms_brands_suppliers`)

2. **Nomenclatura Inconsistente**
   - Mix de português e inglês
   - Controllers no plural (`Marcas`, `Brands`)
   - Alguns models com nomenclatura correta, outros não

3. **Ausência de Type Hints**
   - Nenhum arquivo usa type hints
   - Parâmetros sem tipagem
   - Retornos sem tipagem

4. **Sem AJAX/Modais**
   - Todas operações CRUD fazem full page reload
   - UX inferior ao padrão moderno
   - Mais requisições ao servidor

### 4.2. Problemas de Manutenibilidade

5. **Sem LoggerService**
   - Operações CRUD não são logadas
   - Impossível rastrear alterações
   - Auditoria comprometida

6. **Sem NotificationService**
   - Mensagens via `$_SESSION['msg']` direto
   - Sem padronização visual
   - Sem auto-dismiss

7. **Sem Busca/Filtros**
   - Apenas listagem paginada
   - Usuário não consegue filtrar
   - Dificulta encontrar registros

### 4.3. Problemas de Código

8. **Variáveis em Estilo Antigo**
   - `$Dados` ao invés de `$data`
   - `$PageId` ao invés de `$pageId`
   - `$Resultado` ao invés de `$result`

9. **Imports Inline**
   - `new \App\adms\Models\...` dentro dos métodos
   - Deveria usar `use` statements

10. **PHPDoc Incompleto**
    - `@copyright (c) year` - placeholder não preenchido
    - Sem `@author`, `@param`, `@return`

---

## 5. Sugestões de Melhorias

### 5.1. Decisão Arquitetural

**Decisão Final: Manter dois módulos distintos**

Os módulos têm finalidades diferentes e são usados em contextos distintos:

| Módulo Atual | Novo Nome | Tabela | Contexto de Uso |
|--------------|-----------|--------|-----------------|
| **Marcas** (PT) | `ProductBrand` | `adms_marcas` | Marcas de produtos (OS, Pedidos) |
| **Brands** (EN) | `SupplierBrand` | `adms_brands_suppliers` | Marcas de fornecedores (Pagamentos) |

**Nomenclatura Sugerida:**
- `ProductBrand` - Mais descritivo que apenas "Brand"
- `SupplierBrand` - Indica claramente que são marcas vinculadas a fornecedores

**Alternativa:** Se preferir manter nomes mais curtos:
- `Brand` para marcas de produtos (mais usado)
- `SupplierBrand` para marcas de fornecedores

### 5.2. Melhorias Técnicas

| Prioridade | Melhoria | Impacto |
|------------|----------|---------|
| Alta | Adicionar type hints em todos arquivos | Segurança, IDE |
| Alta | Implementar AJAX com modais | UX |
| Alta | Adicionar LoggerService | Auditoria |
| Alta | Adicionar NotificationService | UX |
| Média | Implementar busca/filtros | UX |
| Média | Separar load/list views | Performance |
| Média | Criar arquivo JS dedicado | Organização |
| Baixa | Atualizar PHPDoc | Documentação |
| Baixa | Padronizar nomenclatura | Consistência |

### 5.3. Melhorias de UX

1. **Cards de Estatísticas** (como módulo Sales)
   - Total de marcas
   - Marcas ativas/inativas
   - Marcas por fornecedor

2. **Filtros Avançados**
   - Por fornecedor
   - Por status
   - Por nome (busca textual)

3. **Ações em Lote**
   - Ativar/desativar múltiplas marcas
   - Exportar lista

---

## 6. Plano de Ação para Refatoração

> **Nota:** Serão refatorados dois módulos separadamente. Recomenda-se iniciar pelo módulo **Marcas** (ProductBrand) por ser o mais utilizado.

---

### MÓDULO 1: Marcas → ProductBrand (ou Brand)

#### Fase 1.1: Preparação (30 min)

| # | Tarefa | Arquivos |
|---|--------|----------|
| 1.1.1 | Backup dos arquivos atuais | Controllers, Models, Views de Marcas |
| 1.1.2 | Verificar dependências no banco | `adms_paginas`, `adms_nivacs_pgs` |
| 1.1.3 | Mapear todos os usos de `adms_marcas` | Queries em outros módulos |

#### Fase 1.2: Controllers - ProductBrand (2-3 horas)

| # | Tarefa | De | Para |
|---|--------|----|----|
| 1.2.1 | Criar controller principal | `Marcas.php` | `Brand.php` |
| 1.2.2 | Criar AddBrand | `CadastrarMarca.php` | `AddBrand.php` |
| 1.2.3 | Criar EditBrand | `EditarMarca.php` | `EditBrand.php` |
| 1.2.4 | Criar ViewBrand | `VerMarca.php` | `ViewBrand.php` |
| 1.2.5 | Criar DeleteBrand | `ApagarMarca.php` | `DeleteBrand.php` |

**Template Controller Principal (Brand.php):**
```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsMenu;
use App\adms\Models\AdmsBotao;
use App\adms\Models\AdmsListBrands;
use Core\ConfigView;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller de Marcas
 *
 * Gerencia listagem e busca de marcas
 *
 * @author Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class Brand
{
    /** @var array|null Dados para a view */
    private ?array $data = [];

    /** @var int Página atual */
    private int $pageId;

    /**
     * Método principal de listagem
     *
     * @param int|string|null $pageId Número da página
     * @return void
     */
    public function list(int|string|null $pageId = null): void
    {
        $this->pageId = (int) ($pageId ?: 1);

        $requestType = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT);

        match ($requestType) {
            1 => $this->listAllItems(),
            2 => $this->searchItems(),
            default => $this->loadInitialPage(),
        };
    }

    // ... demais métodos seguindo padrão Cargo
}
```

#### Fase 1.3: Models - ProductBrand (2-3 horas)

| # | Tarefa | De | Para |
|---|--------|----|----|
| 1.3.1 | Criar model de listagem | `AdmsListarMarca.php` | `AdmsListBrands.php` |
| 1.3.2 | Criar model de adicionar | `AdmsCadastrarMarca.php` | `AdmsAddBrand.php` |
| 1.3.3 | Criar model de editar | `AdmsEditarMarca.php` | `AdmsEditBrand.php` |
| 1.3.4 | Criar model de visualizar | `AdmsVerMarca.php` | `AdmsViewBrand.php` |
| 1.3.5 | Criar model de deletar | `AdmsApagarMarca.php` | `AdmsDeleteBrand.php` |

> **Importante:** O model `AdmsListBrands.php` já existe para o módulo Brands/SupplierBrand. Avaliar se deve ser renomeado para `AdmsListSupplierBrands.php` para evitar conflito.

**Checklist para cada Model:**
- [ ] Type hints em propriedades
- [ ] Type hints em métodos
- [ ] PHPDoc completo
- [ ] Use statements (não inline)
- [ ] LoggerService em operações CRUD
- [ ] Validação de campos
- [ ] Verificação de duplicidade (onde aplicável)

#### Fase 1.4: Views - ProductBrand (3-4 horas)

| # | Tarefa | Descrição |
|---|--------|-----------|
| 1.4.1 | Criar estrutura de diretórios | `Views/brand/partials/` |
| 1.4.2 | Criar loadBrand.php | Página principal com filtros |
| 1.4.3 | Criar listBrand.php | Tabela para AJAX (substituir existente) |
| 1.4.4 | Criar _add_brand_modal.php | Modal de adicionar |
| 1.4.5 | Criar _edit_brand_modal.php | Modal container de editar |
| 1.4.6 | Criar _edit_brand_form.php | Formulário carregado via AJAX |
| 1.4.7 | Criar _view_brand_modal.php | Modal container de visualizar |
| 1.4.8 | Criar _view_brand_details.php | Detalhes carregados via AJAX |
| 1.4.9 | Criar _delete_brand_modal.php | Modal de confirmação |

#### Fase 1.5: JavaScript - ProductBrand (2-3 horas)

| # | Tarefa | Descrição |
|---|--------|-----------|
| 1.5.1 | Criar brand.js | Arquivo principal |
| 1.5.2 | Implementar listBrands() | Listagem AJAX |
| 1.5.3 | Implementar performSearch() | Busca com filtros |
| 1.5.4 | Implementar setupAddBrandForm() | CRUD adicionar |
| 1.5.5 | Implementar editBrand() | CRUD editar |
| 1.5.6 | Implementar viewBrand() | Visualização |
| 1.5.7 | Implementar deleteBrand() | Exclusão |
| 1.5.8 | Implementar paginação AJAX | Navegação |
| 1.5.9 | Implementar manutenção de estado | Filtros + página |

#### Fase 1.6: Banco de Dados - ProductBrand (1 hora)

| # | Tarefa | Descrição |
|---|--------|-----------|
| 1.6.1 | Atualizar rotas em `adms_paginas` | Novos controllers |
| 1.6.2 | Atualizar permissões em `adms_nivacs_pgs` | Novos métodos |
| 1.6.3 | Atualizar menu em `adms_menus` | Novos links |

**SQL - ProductBrand:**
```sql
-- Atualizar páginas existentes (Marcas → Brand)
UPDATE adms_paginas SET
    controller = 'Brand',
    metodo = 'list'
WHERE controller = 'Marcas';

UPDATE adms_paginas SET controller = 'AddBrand', metodo = 'create' WHERE controller = 'CadastrarMarca';
UPDATE adms_paginas SET controller = 'EditBrand', metodo = 'edit' WHERE controller = 'EditarMarca';
UPDATE adms_paginas SET controller = 'ViewBrand', metodo = 'view' WHERE controller = 'VerMarca';
UPDATE adms_paginas SET controller = 'DeleteBrand', metodo = 'delete' WHERE controller = 'ApagarMarca';
```

#### Fase 1.7: Limpeza - ProductBrand (30 min)

| # | Tarefa | Descrição |
|---|--------|-----------|
| 1.7.1 | Remover arquivos antigos PT | Marcas.php, CadastrarMarca.php, etc. |
| 1.7.2 | Remover views antigas | Views/marca/ |
| 1.7.3 | Verificar referências órfãs | Grep por nomes antigos |
| 1.7.4 | Testar todas funcionalidades | CRUD completo |

---

### MÓDULO 2: Brands → SupplierBrand

#### Fase 2.1: Preparação (30 min)

| # | Tarefa | Descrição |
|---|--------|-----------|
| 2.1.1 | Backup dos arquivos atuais | Controllers, Models, Views de Brands |
| 2.1.2 | Verificar dependências | Order Payment e outros módulos |
| 2.1.3 | Mapear usos de `adms_brands_suppliers` | Queries em outros módulos |

#### Fase 2.2: Controllers - SupplierBrand (2-3 horas)

| # | Tarefa | De | Para |
|---|--------|----|----|
| 2.2.1 | Criar controller principal | `Brands.php` | `SupplierBrand.php` |
| 2.2.2 | Criar AddSupplierBrand | `AddBrands.php` | `AddSupplierBrand.php` |
| 2.2.3 | Criar EditSupplierBrand | `EditBrands.php` | `EditSupplierBrand.php` |
| 2.2.4 | Criar ViewSupplierBrand | (novo) | `ViewSupplierBrand.php` |
| 2.2.5 | Criar DeleteSupplierBrand | `DeleteBrands.php` | `DeleteSupplierBrand.php` |

#### Fase 2.3: Models - SupplierBrand (2-3 horas)

| # | Tarefa | De | Para |
|---|--------|----|----|
| 2.3.1 | Criar model de listagem | `AdmsListBrands.php` | `AdmsListSupplierBrands.php` |
| 2.3.2 | Criar model de adicionar | `AdmsAddBrands.php` | `AdmsAddSupplierBrand.php` |
| 2.3.3 | Criar model de editar | `AdmsEditBrand.php` | `AdmsEditSupplierBrand.php` |
| 2.3.4 | Criar model de visualizar | (novo) | `AdmsViewSupplierBrand.php` |
| 2.3.5 | Criar model de deletar | `AdmsDeleteBrand.php` | `AdmsDeleteSupplierBrand.php` |

#### Fase 2.4: Views - SupplierBrand (3-4 horas)

| # | Tarefa | Descrição |
|---|--------|-----------|
| 2.4.1 | Criar estrutura | `Views/supplierBrand/partials/` |
| 2.4.2 | Criar loadSupplierBrand.php | Página principal |
| 2.4.3 | Criar listSupplierBrand.php | Tabela AJAX |
| 2.4.4 | Criar modais | Add, Edit, View, Delete |

#### Fase 2.5: JavaScript - SupplierBrand (2-3 horas)

| # | Tarefa | Descrição |
|---|--------|-----------|
| 2.5.1 | Criar supplier-brand.js | CRUD completo via AJAX |

#### Fase 2.6: Banco de Dados - SupplierBrand (1 hora)

**SQL - SupplierBrand:**
```sql
-- Atualizar páginas existentes (Brands → SupplierBrand)
UPDATE adms_paginas SET
    controller = 'SupplierBrand',
    metodo = 'list'
WHERE controller = 'Brands';

UPDATE adms_paginas SET controller = 'AddSupplierBrand', metodo = 'create' WHERE controller = 'AddBrands';
UPDATE adms_paginas SET controller = 'EditSupplierBrand', metodo = 'edit' WHERE controller = 'EditBrands';
UPDATE adms_paginas SET controller = 'DeleteSupplierBrand', metodo = 'delete' WHERE controller = 'DeleteBrands';

-- Adicionar página de visualização (nova)
INSERT INTO adms_paginas (nome_pagina, controller, metodo, obs) VALUES
('Visualizar Marca Fornecedor', 'ViewSupplierBrand', 'view', 'Visualiza detalhes da marca do fornecedor');
```

#### Fase 2.7: Limpeza - SupplierBrand (30 min)

| # | Tarefa | Descrição |
|---|--------|-----------|
| 2.7.1 | Remover arquivos antigos | Brands.php, AddBrands.php, etc. |
| 2.7.2 | Remover views antigas | Views/brand/ antigas |
| 2.7.3 | Atualizar referências em Order Payment | Se necessário |

---

### Fase Final: Testes e Documentação (2 horas)

| # | Tarefa | Descrição |
|---|--------|-----------|
| F.1 | Testar ProductBrand (Brand) | CRUD completo |
| F.2 | Testar SupplierBrand | CRUD completo |
| F.3 | Testar módulos dependentes | Order Control, Service Order, Order Payment |
| F.4 | Testar responsividade | Mobile e desktop |
| F.5 | Verificar logs | LoggerService funcionando |
| F.6 | Atualizar documentação | Este documento e CLAUDE.md |

---

## 7. Estimativa de Tempo

### Módulo 1: Marcas → ProductBrand (Brand)

| Fase | Tempo Estimado |
|------|----------------|
| Fase 1.1: Preparação | 30 min |
| Fase 1.2: Controllers | 2-3 horas |
| Fase 1.3: Models | 2-3 horas |
| Fase 1.4: Views | 3-4 horas |
| Fase 1.5: JavaScript | 2-3 horas |
| Fase 1.6: Banco de Dados | 1 hora |
| Fase 1.7: Limpeza | 30 min |
| **Subtotal Módulo 1** | **11-15 horas** |

### Módulo 2: Brands → SupplierBrand

| Fase | Tempo Estimado |
|------|----------------|
| Fase 2.1: Preparação | 30 min |
| Fase 2.2: Controllers | 2-3 horas |
| Fase 2.3: Models | 2-3 horas |
| Fase 2.4: Views | 3-4 horas |
| Fase 2.5: JavaScript | 2-3 horas |
| Fase 2.6: Banco de Dados | 1 hora |
| Fase 2.7: Limpeza | 30 min |
| **Subtotal Módulo 2** | **11-15 horas** |

### Consolidado

| Item | Tempo |
|------|-------|
| Módulo 1 (ProductBrand) | 11-15 horas |
| Módulo 2 (SupplierBrand) | 11-15 horas |
| Testes e Documentação | 2 horas |
| **Total Geral** | **24-32 horas** |

> **Recomendação:** Executar os módulos em sprints separados para reduzir risco e permitir validação incremental.

---

## 8. Checklist Final

### Antes de Iniciar
- [ ] Backup do banco de dados
- [ ] Backup dos arquivos de ambos os módulos
- [ ] Ambiente de desenvolvimento configurado
- [ ] Documentação do módulo Cargos revisada

### Durante Desenvolvimento - Módulo 1 (ProductBrand)
- [ ] Seguir padrão Cargos rigorosamente
- [ ] Type hints em todos arquivos
- [ ] PHPDoc em todos métodos públicos
- [ ] LoggerService em operações CRUD
- [ ] NotificationService em respostas
- [ ] Testes incrementais
- [ ] Verificar que `FormSelectRepository::getBrands()` continua funcionando

### Durante Desenvolvimento - Módulo 2 (SupplierBrand)
- [ ] Seguir padrão Cargos rigorosamente
- [ ] Type hints em todos arquivos
- [ ] PHPDoc em todos métodos públicos
- [ ] LoggerService em operações CRUD
- [ ] NotificationService em respostas
- [ ] Testes incrementais
- [ ] Verificar módulo Order Payment continua funcionando

### Após Conclusão
- [ ] Arquivos antigos de Marcas (PT) removidos
- [ ] Arquivos antigos de Brands (EN) removidos
- [ ] Rotas atualizadas no banco para ambos módulos
- [ ] Permissões configuradas para ambos módulos
- [ ] Menu atualizado
- [ ] Testes funcionais completos em ambos módulos
- [ ] Testes de integração (Order Control, Service Order, Order Payment)
- [ ] Commits com mensagens descritivas

---

## 9. Referências

### Arquivos de Referência (Módulo Cargos)
- `app/adms/Controllers/Cargo.php`
- `app/adms/Controllers/AddCargo.php`
- `app/adms/Controllers/EditCargo.php`
- `app/adms/Controllers/ViewCargo.php`
- `app/adms/Controllers/DeleteCargo.php`
- `app/adms/Models/AdmsListCargos.php`
- `app/adms/Models/AdmsAddCargo.php`
- `app/adms/Views/cargo/loadCargo.php`
- `app/adms/Views/cargo/listCargo.php`
- `app/adms/Views/cargo/partials/*`
- `assets/js/cargo.js`

### Documentação
- `.claude/REGRAS_DESENVOLVIMENTO.md`
- `.claude/CLAUDE.md`
- `docs/PADRONIZACAO.md`

---

## 10. Dependências e Impactos

### Módulo ProductBrand (adms_marcas)

| Módulo Dependente | Arquivo | Tipo de Uso |
|-------------------|---------|-------------|
| Order Control | `AdmsListOrderControl.php` | JOIN |
| Order Control | `AdmsAddOrderControl.php` | Select |
| Order Control | `AdmsEditOrderControl.php` | Validação |
| Order Control | `AdmsViewOrderControl.php` | Exibição |
| Order Control | `AdmsStatisticsOrderControl.php` | Estatísticas |
| Order Control | `AdmsImportOrderControl.php` | Cache |
| Service Order | `AdmsListServiceOrders.php` | JOIN |
| Service Order | `AdmsEditServiceOrder.php` | Select |
| Service Order | `AdmsViewServiceOrder.php` | Exibição |
| Service Order | `AdmsExportServiceOrders.php` | Exportação |
| Dashboard | `AdmsDashboardServiceOrders.php` | Estatísticas |
| **FormSelectRepository** | `getBrands()` | **Método centralizado** |
| Remanejo | `AdmsEditarRemanejo.php` | Select |

### Módulo SupplierBrand (adms_brands_suppliers)

| Módulo Dependente | Arquivo | Tipo de Uso |
|-------------------|---------|-------------|
| Order Payment | `AdmsAddOrderPayment.php` | Select |
| Order Payment | `AdmsEditOrderPayment.php` | Select |
| Pesquisa OS | `CpAdmsPesqOrderService.php` | Select |

---

**Documento gerado em:** 04/02/2026
**Atualizado em:** 04/02/2026 (v1.1 - Separação confirmada dos módulos)
**Próxima revisão:** Após conclusão da refatoração
