# Análise Completa do Módulo Drivers (Motoristas)

**Data:** 04/02/2026
**Versão:** 1.0
**Autor:** Assistente de Desenvolvimento
**Módulo de Referência:** Cargos (refatorado em Janeiro/2026)

---

## 1. Resumo Executivo

O módulo de Motoristas (Drivers) gerencia o cadastro de motoristas do sistema, permitindo operações CRUD básicas. O módulo já utiliza nomenclatura em inglês, porém possui diversas inconsistências com os padrões atuais de desenvolvimento e necessita de refatoração para alinhar-se ao padrão estabelecido pelo módulo Cargos.

### Status Atual: **Necessita Refatoração**

---

## 2. Estrutura Atual do Módulo

### 2.1 Controllers

| Arquivo | Descrição | Status |
|---------|-----------|--------|
| `Drivers.php` | Controller principal | Desatualizado |
| `AddDrivers.php` | Adicionar motorista | Desatualizado |
| `EditDriver.php` | Editar motorista | Desatualizado |
| `ViewDriver.php` | Visualizar motorista | Desatualizado |
| `DeleteDriver.php` | Deletar motorista | Desatualizado |

### 2.2 Models

| Arquivo | Descrição | Status |
|---------|-----------|--------|
| `AdmsListDrivers.php` | Listagem de motoristas | Desatualizado |
| `AdmsAddDrivers.php` | Adicionar motorista | Desatualizado |
| `AdmsEditDriver.php` | Editar motorista | Desatualizado |
| `AdmsViewDriver.php` | Visualizar motorista | Desatualizado |
| `AdmsDeleteDriver.php` | Deletar motorista | Desatualizado |
| `CpAdmsSearchDrivers.php` | Busca (cpadms) | Desatualizado |

### 2.3 Views

| Arquivo | Descrição | Status |
|---------|-----------|--------|
| `drivers/loadDrivers.php` | Página principal | Desatualizado |
| `drivers/listDrivers.php` | Lista AJAX | Desatualizado |
| `drivers/viewDriver.php` | Visualização completa | Desatualizado |
| `drivers/editDriver.php` | Página de edição | Desatualizado |

### 2.4 JavaScript

| Arquivo | Descrição | Status |
|---------|-----------|--------|
| `customCreate.js` (linhas 411-508) | Funções do módulo Drivers | **Problema Crítico** |

---

## 3. Problemas Identificados

### 3.1 Controllers

#### Drivers.php (Controller Principal)
```php
// PROBLEMA: Não usa match expression
if (!empty($this->TypeResult) AND ( $this->TypeResult == 1)) {
    $this->listDriversPriv();
} elseif (!empty($this->TypeResult) AND ( $this->TypeResult == 2)) {
    ...
}
```

**Problemas:**
- [ ] Não usa `match` expression (padrão moderno)
- [ ] Usa `AND` em vez de `&&`
- [ ] Nomenclatura de variáveis mista (TypeResult, PageId)
- [ ] Falta PHPDoc adequado
- [ ] Falta type hints nos retornos de métodos
- [ ] Não usa `use` statements (FQN inline)

#### AddDrivers.php
```php
// PROBLEMA: Retorna JSON mas não usa NotificationService
if ($addDrivers->getResult()) {
    $result = ['erro' => true, 'msg' => $_SESSION['msg']];
}
```

**Problemas:**
- [ ] Usa `$_SESSION['msg']` em vez de `NotificationService`
- [ ] Campo `erro` deveria ser `error` (padrão)
- [ ] Falta logging com `LoggerService`
- [ ] Nomenclatura inconsistente (`AddDrivers` vs `AddDriver`)

#### EditDriver.php
```php
// PROBLEMA: Full page reload ao invés de AJAX
$_SESSION['msg'] = "...";
$UrlDestino = URLADM . 'drivers/list';
header("Location: $UrlDestino");
```

**Problemas:**
- [ ] Usa redirecionamento em vez de resposta JSON
- [ ] Não é modal-based (full page reload)
- [ ] Mistura GET e POST no mesmo método
- [ ] Falta `NotificationService` e `LoggerService`

#### DeleteDriver.php
```php
// PROBLEMA: Redireciona ao invés de retornar JSON
$UrlDestino = URLADM . 'drivers/list';
header("Location: $UrlDestino");
```

**Problemas:**
- [ ] Não retorna JSON (redireciona)
- [ ] Falta modal de confirmação no frontend
- [ ] Não verifica se motorista está em uso antes de deletar
- [ ] Falta `NotificationService` e `LoggerService`

### 3.2 Models

#### AdmsAddDrivers.php
```php
// PROBLEMA: Usa $_SESSION para mensagens
$_SESSION['msg'] = "<div class='alert alert-danger'>...";
```

**Problemas:**
- [ ] HTML de notificação no Model (deveria ser no Controller/Service)
- [ ] Retorna `true` para erro e `false` para sucesso (invertido!)
- [ ] Falta type hints em propriedades
- [ ] Nível de acesso hardcoded (`private int $Nivel = 21`)

#### AdmsListDrivers.php
**Problemas:**
- [ ] Falta método `search()` integrado
- [ ] `listAdd()` deveria estar no próprio Model de listagem
- [ ] Query exposta sem método dedicado

#### AdmsDeleteDriver.php
**Problemas:**
- [ ] Não verifica se motorista está vinculado a entregas
- [ ] Mensagens de erro genéricas

### 3.3 Views

#### loadDrivers.php
**Problemas:**
- [ ] Modal de adicionar inline (deveria ser partial)
- [ ] Modal de sucesso/erro separados (desnecessários)
- [ ] `<span>` para passar dados (deveria usar `data-*` em div config)
- [ ] Formulário de busca não usa estrutura padrão
- [ ] Falta modal de edição (usa página separada)
- [ ] Falta modal de exclusão com confirmação

#### listDrivers.php
**Problemas:**
- [ ] Link de edição abre página nova (não modal)
- [ ] Delete não tem confirmação modal
- [ ] Falta classes `.btn-view-driver`, `.btn-edit-driver`, `.btn-delete-driver`

### 3.4 JavaScript (customCreate.js)

**PROBLEMA CRÍTICO:** Funções do módulo Drivers estão no arquivo genérico `customCreate.js`

```javascript
// Linhas 411-508 de customCreate.js
function listDrivers(pageDrive, varcomp = null) { ... }
$("#searchDriver").keyup(function () { ... });
$(document).on('click', '.view_data_driver', function () { ... });
$("#insert_form_driver").on("submit", function (event) { ... });
```

**Problemas:**
- [ ] Funções em arquivo genérico (deveria ter `drivers.js`)
- [ ] Usa jQuery em vez de vanilla JS + async/await
- [ ] `location.reload()` após cadastro (não mantém estado)
- [ ] Não mantém filtros/página após operações CRUD
- [ ] Paginação não funciona com busca ativa
- [ ] Sem event delegation adequada

---

## 4. Comparativo com Padrão (Módulo Cargos)

### 4.1 Controller Principal

| Aspecto | Drivers (Atual) | Cargos (Padrão) |
|---------|-----------------|-----------------|
| Match expression | Não | Sim |
| Type hints | Parcial | Completo |
| PHPDoc | Incompleto | Completo |
| Use statements | FQN inline | Imports no topo |
| Método loadInitialPage | Não | Sim |
| Método searchItems | Não | Sim |

### 4.2 Controllers CRUD

| Aspecto | Drivers (Atual) | Cargos (Padrão) |
|---------|-----------------|-----------------|
| NotificationService | Não | Sim |
| LoggerService | Não | Sim |
| Resposta JSON | Parcial | Sim |
| Modal-based | Não | Sim |
| Métodos separados edit/update | Não | Sim |

### 4.3 Views

| Aspecto | Drivers (Atual) | Cargos (Padrão) |
|---------|-----------------|-----------------|
| Modals em partials | Não | Sim |
| Config div com data-* | Parcial | Sim |
| Cards de estatísticas | Não | Opcional |
| Botões de ação padronizados | Não | Sim |
| CSRF em formulários | Sim | Sim |

### 4.4 JavaScript

| Aspecto | Drivers (Atual) | Cargos (Padrão) |
|---------|-----------------|-----------------|
| Arquivo dedicado | Não | Sim (`cargo.js`) |
| Vanilla JS + async/await | Não (jQuery) | Sim |
| Event delegation | Parcial | Sim |
| Mantém filtros após CRUD | Não | Sim |
| Paginação com busca | Não | Sim |
| renderNotification() | Não | Sim |

---

## 5. Plano de Refatoração

### Fase 1: Preparação e Novos Arquivos
**Prioridade: Alta**

1. **Criar Controller `Driver.php`** (singular, padrão)
   - Implementar `match` expression
   - Adicionar métodos: `loadInitialPage()`, `listAllItems()`, `searchItems()`
   - Type hints completos
   - PHPDoc adequado

2. **Criar Controller `AddDriver.php`** (singular)
   - Resposta JSON
   - NotificationService
   - LoggerService

3. **Refatorar `EditDriver.php`**
   - Separar `edit()` (GET→HTML) e `update()` (POST→JSON)
   - NotificationService e LoggerService

4. **Refatorar `ViewDriver.php`**
   - Retornar HTML partial para modal

5. **Refatorar `DeleteDriver.php`**
   - Resposta JSON
   - Verificação de uso antes de deletar
   - NotificationService e LoggerService

### Fase 2: Models
**Prioridade: Alta**

1. **Refatorar `AdmsListDrivers.php`**
   - Adicionar método `search(array $filters, int $page)`
   - Adicionar método `listFormData()`
   - Type hints completos

2. **Criar `AdmsDriver.php`** (CRUD unificado) ou refatorar existentes
   - Remover HTML de notificações
   - Type hints
   - Correção de retorno (true=sucesso, false=erro)

3. **Remover** `CpAdmsSearchDrivers.php`
   - Funcionalidade integrada ao `AdmsListDrivers`

### Fase 3: Views
**Prioridade: Alta**

1. **Refatorar `loadDrivers.php` → `loadDriver.php`**
   - Estrutura de filtros padrão
   - Div de config com data-*
   - Remover modais inline

2. **Refatorar `listDrivers.php` → `listDriver.php`**
   - Classes de botões padronizadas
   - Data attributes para CRUD

3. **Criar partials/**
   - `_add_driver_modal.php`
   - `_edit_driver_modal.php`
   - `_edit_driver_form.php`
   - `_view_driver_modal.php`
   - `_view_driver_details.php`
   - `_delete_driver_modal.php`

4. **Remover** `viewDriver.php` e `editDriver.php` (full page)

### Fase 4: JavaScript
**Prioridade: Alta**

1. **Criar `assets/js/driver.js`**
   - Vanilla JS + async/await
   - Event delegation
   - Funções: `listDrivers()`, `performSearch()`, `refreshList()`
   - CRUD via modais
   - `renderNotification()`
   - Manutenção de estado (filtros + página)

2. **Remover código de `customCreate.js`**
   - Linhas 411-508

### Fase 5: Limpeza
**Prioridade: Média**

1. Renomear diretório `drivers/` → `driver/`
2. Remover arquivos obsoletos
3. Atualizar rotas no banco de dados (adms_paginas)
4. Testar fluxo completo

---

## 6. Estrutura Final Proposta

```
app/adms/
├── Controllers/
│   ├── Driver.php           # Controller principal (NOVO)
│   ├── AddDriver.php        # Adicionar (REFATORADO)
│   ├── EditDriver.php       # Editar (REFATORADO)
│   ├── ViewDriver.php       # Visualizar (REFATORADO)
│   └── DeleteDriver.php     # Deletar (REFATORADO)
│
├── Models/
│   ├── AdmsListDrivers.php  # Listagem + busca (REFATORADO)
│   ├── AdmsAddDriver.php    # Adicionar (RENOMEADO)
│   ├── AdmsEditDriver.php   # Editar (REFATORADO)
│   ├── AdmsViewDriver.php   # Visualizar (REFATORADO)
│   └── AdmsDeleteDriver.php # Deletar (REFATORADO)
│
└── Views/
    └── driver/              # Diretório singular
        ├── loadDriver.php   # Página principal
        ├── listDriver.php   # Lista AJAX
        └── partials/
            ├── _add_driver_modal.php
            ├── _add_driver_form.php
            ├── _edit_driver_modal.php
            ├── _edit_driver_form.php
            ├── _view_driver_modal.php
            ├── _view_driver_details.php
            └── _delete_driver_modal.php

assets/js/
└── driver.js                # JavaScript dedicado (NOVO)
```

---

## 7. Arquivos a Remover

| Arquivo | Motivo |
|---------|--------|
| `Controllers/Drivers.php` | Substituído por `Driver.php` |
| `Controllers/AddDrivers.php` | Renomeado para `AddDriver.php` |
| `Models/AdmsAddDrivers.php` | Renomeado para `AdmsAddDriver.php` |
| `cpadms/Models/CpAdmsSearchDrivers.php` | Funcionalidade integrada |
| `Views/drivers/` (diretório) | Renomeado para `driver/` |
| `Views/drivers/viewDriver.php` | Substituído por modal |
| `Views/drivers/editDriver.php` | Substituído por modal |
| `customCreate.js` (linhas 411-508) | Movido para `driver.js` |

---

## 8. Estimativa de Esforço

| Fase | Complexidade | Arquivos |
|------|--------------|----------|
| Fase 1: Controllers | Média | 5 |
| Fase 2: Models | Média | 5 |
| Fase 3: Views | Alta | 9 |
| Fase 4: JavaScript | Média | 1 |
| Fase 5: Limpeza | Baixa | - |

---

## 9. Riscos e Mitigações

| Risco | Mitigação |
|-------|-----------|
| Quebra de funcionalidade existente | Testes manuais após cada fase |
| Rotas antigas não funcionando | Atualizar adms_paginas e adms_menus |
| JavaScript conflitante | Testar em navegadores diferentes |
| Dependências não mapeadas | Buscar por "driver" em todo o projeto |

---

## 10. Dependências Externas

O módulo Drivers possui relacionamento com:
- `adms_usuarios` (usuário vinculado ao motorista)
- `adms_sits` (situação/status do motorista)
- `adms_cors` (cores para status)
- `adms_delivery_routings` (rotas de entrega - verificar antes de deletar)

---

## 11. Checklist de Validação Pós-Refatoração

- [ ] Listar motoristas funciona corretamente
- [ ] Busca por nome/apelido funciona
- [ ] Paginação mantém filtros
- [ ] Adicionar motorista via modal
- [ ] Editar motorista via modal
- [ ] Visualizar motorista via modal
- [ ] Deletar motorista com confirmação
- [ ] Notificações aparecem corretamente
- [ ] Logs registrados no sistema
- [ ] Responsivo em mobile
- [ ] Sem erros no console do navegador

---

## 12. Conclusão

O módulo Drivers requer uma refatoração significativa para alinhar-se aos padrões atuais do projeto Mercury. A principal mudança é a transição de um fluxo baseado em páginas completas (full page reload) para um fluxo modal-based com AJAX, seguindo o padrão estabelecido pelo módulo Cargos.

A refatoração proposta melhorará:
- **Experiência do usuário:** Operações mais rápidas sem reload
- **Manutenibilidade:** Código organizado e padronizado
- **Rastreabilidade:** Logging de todas as operações
- **Segurança:** Validações adequadas e NotificationService
- **Consistência:** Mesmo padrão de outros módulos refatorados

---

**Documento gerado em:** 04/02/2026
**Próxima revisão:** Após conclusão da Fase 1
