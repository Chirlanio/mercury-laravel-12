# GUIA DE DESENVOLVIMENTO - MERCURY LARAVEL

**Data de Geração:** Janeiro 2026
**Versão do Documento:** 1.0
**Projeto:** Mercury Laravel - Sistema de Gestão Empresarial

---

## SUMÁRIO

1. [Visão Geral do Projeto](#1-visão-geral-do-projeto)
2. [Estado Atual de Desenvolvimento](#2-estado-atual-de-desenvolvimento)
3. [Comparação: Migrations vs Backup SQL](#3-comparação-migrations-vs-backup-sql)
4. [Módulos Pendentes de Implementação](#4-módulos-pendentes-de-implementação)
5. [Débitos Técnicos](#5-débitos-técnicos)
6. [Melhorias Recomendadas](#6-melhorias-recomendadas)
7. [Roadmap de Desenvolvimento](#7-roadmap-de-desenvolvimento)
8. [Padrões e Convenções](#8-padrões-e-convenções)
9. [Referência Técnica](#9-referência-técnica)

---

## 1. VISÃO GERAL DO PROJETO

### 1.1 Descrição
O **Mercury Laravel** é um sistema de gestão empresarial full-stack desenvolvido com Laravel 12 e React 18, utilizando Inertia.js para comunicação frontend-backend. O projeto é uma reconstrução/modernização de um sistema legado PHP.

### 1.2 Stack Tecnológica

| Camada | Tecnologia | Versão |
|--------|------------|--------|
| Backend | Laravel | 12.0 |
| Frontend | React | 18.2 |
| CSS | Tailwind CSS | 3.2 |
| Build Tool | Vite | 7.0 |
| Bridge | Inertia.js | 2.0 |
| Database | MySQL | - |
| Auth | Laravel Sanctum | 4.0 |

### 1.3 Arquitetura
```
┌─────────────────────────────────────────────────────────────┐
│                        FRONTEND                              │
│  React 18 + Tailwind CSS + Headless UI + Heroicons          │
└──────────────────────────┬──────────────────────────────────┘
                           │ Inertia.js
┌──────────────────────────▼──────────────────────────────────┐
│                        BACKEND                               │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │ Controllers │──│  Services   │──│      Models         │  │
│  └─────────────┘  └─────────────┘  └─────────────────────┘  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │ Middleware  │  │   Traits    │  │    Enums/Rules      │  │
│  └─────────────┘  └─────────────┘  └─────────────────────┘  │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                       DATABASE                               │
│                    MySQL (47 tabelas)                        │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. ESTADO ATUAL DE DESENVOLVIMENTO

### 2.1 Estatísticas do Projeto

| Métrica | Quantidade |
|---------|------------|
| Controllers | 21 |
| Models | 29 |
| Services | 3 |
| Migrations | 47 |
| Componentes React | 46 |
| Páginas React | 29 |
| Testes | 13 arquivos |
| Total de arquivos PHP | 106 |

### 2.2 Funcionalidades Implementadas (100%)

| Módulo | Status | Descrição |
|--------|--------|-----------|
| Autenticação | ✅ Completo | Login, Registro, Reset de Senha, Verificação de Email |
| Gerenciamento de Usuários | ✅ Completo | CRUD, Roles, Avatares, Perfil |
| Sistema de Permissões (RBAC) | ✅ Completo | 18 permissões, 4 roles hierárquicos |
| Logs de Auditoria | ✅ Completo | Rastreamento, Exportação, Análise de Padrões |
| Dashboard | ✅ Completo | Estatísticas, Gráficos, Alertas |
| Gestão de Funcionários | ✅ Completo | CRUD, Contratos, Eventos, Histórico |
| Controle de Jornada | ✅ Completo | Registro, Compensação, Exportação |
| Sistema de Menus | ✅ Completo | Dinâmico, Ordenação, Ativação |
| Gerenciamento de Páginas | ✅ Completo | CRUD, Visibilidade, Status |
| Níveis de Acesso | ✅ Completo | Permissões por Página/Menu |
| Configurações de Email | ✅ Completo | Gerenciamento de configurações |

### 2.3 Funcionalidades Parcialmente Implementadas (Placeholder)

| Módulo | Status | Rota |
|--------|--------|------|
| Produto | ⏳ Coming Soon | `/produto` |
| Planejamento | ⏳ Coming Soon | `/planejamento` |
| Financeiro | ⏳ Coming Soon | `/financeiro` |
| Ativo Fixo | ⏳ Coming Soon | `/ativo-fixo` |
| Comercial | ⏳ Coming Soon | `/comercial` |
| Delivery | ⏳ Coming Soon | `/delivery` |
| Rotas | ⏳ Coming Soon | `/rotas` |
| E-commerce | ⏳ Coming Soon | `/ecommerce` |
| Qualidade | ⏳ Coming Soon | `/qualidade` |
| Pessoas & Cultura | ⏳ Coming Soon | `/pessoas-cultura` |
| Departamento Pessoal | ⏳ Coming Soon | `/departamento-pessoal` |
| Escola Digital | ⏳ Coming Soon | `/escola-digital` |
| Movidesk | ⏳ Coming Soon | `/movidesk` |
| Biblioteca de Processos | ⏳ Coming Soon | `/biblioteca-processos` |

---

## 3. COMPARAÇÃO: MIGRATIONS VS BACKUP SQL

### 3.1 Tabelas Implementadas (Correspondência)

| Tabela Laravel | Tabela Legado (SQL) | Status |
|----------------|---------------------|--------|
| users | adms_usuarios | ✅ Migrado |
| employees | adms_employees | ✅ Migrado |
| employment_contracts | adms_employment_contracts | ✅ Migrado |
| employment_relationships | adms_employment_relationships | ✅ Migrado |
| activity_logs | adms_activity_logs | ✅ Migrado |
| menus | adms_menus | ✅ Migrado |
| pages | adms_paginas | ✅ Migrado |
| access_levels | adms_niveis_acessos | ✅ Migrado |
| managers | adms_managers | ✅ Migrado |
| sectors | adms_sectors | ✅ Migrado |
| education_levels | adms_level_educations | ✅ Migrado |
| genders | adms_sexs | ✅ Migrado |
| statuses | adms_sits | ✅ Migrado |
| employee_statuses | adms_status_employees | ✅ Migrado |
| type_moviments | adms_type_moviments | ✅ Migrado |
| stores | tb_lojas | ✅ Migrado |
| networks | tb_redes | ✅ Migrado |

### 3.2 TABELAS DO LEGADO NÃO MIGRADAS (Por Categoria)

#### 3.2.1 Módulo Financeiro (23 tabelas)
```
❌ adms_accounting_account      - Plano de contas
❌ adms_banks                   - Bancos
❌ adms_cost_centers            - Centros de custo
❌ adms_installments            - Parcelas
❌ adms_order_payments          - Pagamentos de pedidos
❌ adms_payment_methods         - Formas de pagamento
❌ adms_coupons                 - Cupons
❌ adms_daily_sales             - Vendas diárias
❌ adms_total_sales             - Total de vendas
❌ adms_estornos                - Estornos
❌ adms_aud_estornos            - Auditoria de estornos
❌ adms_motivo_estorno          - Motivos de estorno
❌ adms_tps_estornos            - Tipos de estornos
❌ adms_sits_estornos           - Status de estornos
❌ adms_adjustments             - Ajustes
❌ adms_adjustment_items        - Itens de ajuste
❌ adms_status_adjustments      - Status de ajustes
❌ adms_travel_expenses         - Despesas de viagem
❌ adms_travel_expense_reimbursements - Reembolsos
❌ adms_sit_travel_expenses     - Status despesas
❌ adms_type_expenses           - Tipos de despesas
❌ adms_type_key_pixs           - Chaves PIX
❌ adms_type_payments           - Tipos de pagamento
```

#### 3.2.2 Módulo Comercial/Vendas (18 tabelas)
```
❌ adms_consignments            - Consignações
❌ adms_consignment_products    - Produtos em consignação
❌ adms_sit_consigment_products - Status consignação
❌ adms_sit_consignments        - Status consignação
❌ adms_store_consultants_goals - Metas de consultores
❌ adms_store_goals             - Metas de lojas
❌ adms_percentage_awards       - Premiações percentuais
❌ adms_suppliers               - Fornecedores
❌ adms_brands_suppliers        - Marcas/Fornecedores
❌ adms_marcas                  - Marcas
❌ adms_categories              - Categorias
❌ adms_product_types           - Tipos de produtos
❌ adms_type_products           - Tipos de produtos (dup?)
❌ adms_purchase_order_controls - Controle de compras
❌ adms_purchase_order_control_items - Itens de compras
❌ adms_budgets_items           - Itens de orçamento
❌ adms_budgets_uploads         - Uploads de orçamento
❌ tb_cad_produtos              - Cadastro de produtos
```

#### 3.2.3 Módulo Delivery/Logística (16 tabelas)
```
❌ adms_deliveries              - Entregas
❌ adms_delivery_routing        - Roteamento de entregas
❌ adms_delivery_routing_backup - Backup de roteamento
❌ adms_delivery_statuses       - Status de entrega
❌ adms_drivers                 - Motoristas
❌ adms_routes                  - Rotas
❌ adms_routing_deliveries      - Entregas em rotas
❌ adms_sits_routes             - Status de rotas
❌ tb_delivery                  - Delivery (legado)
❌ tb_rotas                     - Rotas (legado)
❌ tb_status_delivery           - Status delivery (legado)
❌ tb_ponto_saida               - Pontos de saída
❌ aud_tb_delivery              - Auditoria delivery
❌ adms_transfers               - Transferências
❌ adms_transfer_types          - Tipos de transferência
❌ adms_status_transfers        - Status de transferências
```

#### 3.2.4 Módulo E-commerce (5 tabelas)
```
❌ adms_ecommerce_orders        - Pedidos e-commerce
❌ adms_sits_ecommerce          - Status e-commerce
❌ adms_sits_orders             - Status de pedidos
❌ adms_sits_order_items        - Status itens pedido
❌ adms_sits_order_payments     - Status pagamentos
```

#### 3.2.5 Módulo RH/Pessoas (25 tabelas)
```
❌ adms_absence_control         - Controle de ausências
❌ adms_dismissal_follow_up     - Acompanhamento demissão
❌ adms_gente_gestao            - Gestão de pessoas
❌ adms_holiday_payment_employees - Férias funcionários
❌ adms_holiday_payment_requests - Solicitações férias
❌ adms_holidays                - Feriados
❌ adms_internal_referrals      - Indicações internas
❌ adms_sit_referrals           - Status indicações
❌ adms_internal_transfer_system - Transferências internas
❌ adms_job_applicants          - Candidatos
❌ adms_candidate_files         - Arquivos candidatos
❌ adms_medical_certificates    - Atestados médicos
❌ adms_overtime_control        - Controle hora extra
❌ adms_type_overtime           - Tipos hora extra
❌ adms_personnel_moviments     - Movimentações pessoal
❌ adms_reasons_personnel_moviments - Razões movimentação
❌ adms_sits_personnel_moviments - Status movimentação
❌ adms_reasons_for_dismissals  - Razões demissão
❌ adms_recruiters              - Recrutadores
❌ adms_resignations            - Demissões
❌ adms_vacancy_opening         - Abertura de vagas
❌ adms_sits_vacancy            - Status vagas
❌ adms_users_treinamentos      - Treinamentos usuários
❌ adms_work_schedules          - Escalas de trabalho
❌ adms_social_media            - Redes sociais
```

#### 3.2.6 Módulo Qualidade/Checklists (16 tabelas)
```
❌ adms_checklists              - Checklists
❌ adms_checklist_answers       - Respostas checklist
❌ adms_checklist_answer_attachments - Anexos respostas
❌ adms_checklist_areas         - Áreas checklist
❌ adms_checklist_questions     - Perguntas checklist
❌ adms_check_lists             - Check lists
❌ adms_check_list_areas        - Áreas check list
❌ adms_check_list_questions    - Perguntas check list
❌ adms_check_list_services     - Serviços check list
❌ adms_check_list_stores       - Lojas check list
❌ adms_sits_checklists         - Status checklists
❌ adms_sit_check_lists         - Status check lists
❌ adms_sit_check_list_questions - Status perguntas
❌ adms_service_check_lists     - Check lists serviço
❌ adms_service_check_list_areas - Áreas serviço
❌ adms_service_check_list_questions - Perguntas serviço
```

#### 3.2.7 Módulo Ordem de Serviço (4 tabelas)
```
❌ adms_defeitos_ordem_servico  - Defeitos OS
❌ adms_def_local_ordem_servico - Local defeito OS
❌ adms_detalhes_ordem_servico  - Detalhes OS
❌ adms_qualidade_ordem_servico - Qualidade OS
❌ adms_tips_ordem_servico      - Tipos OS
❌ adms_sits_ordem_servico      - Status OS
```

#### 3.2.8 Módulo Ativo Fixo (4 tabelas)
```
❌ adms_fixed_assets            - Ativos fixos
❌ adms_fixed_asset_counts      - Contagem ativos
❌ adms_fixed_asset_count_names - Nomes contagem
❌ adms_sit_assets              - Status ativos
```

#### 3.2.9 Módulo Biblioteca/Processos (5 tabelas)
```
❌ adms_artigos                 - Artigos
❌ adms_cats_artigos            - Categorias artigos
❌ adms_tps_artigos             - Tipos artigos
❌ adms_process_librarys        - Biblioteca processos
❌ adms_process_library_files   - Arquivos processos
❌ adms_cats_process_librarys   - Categorias processos
❌ adms_policies                - Políticas
```

#### 3.2.10 Módulo Marketing (3 tabelas)
```
❌ adms_marketing_material_requests - Solicitações material
❌ adms_marketing_material_request_items - Itens solicitação
❌ adms_materials               - Materiais
```

#### 3.2.11 Módulo Devoluções/Relocações (8 tabelas)
```
❌ adms_returns                 - Devoluções
❌ adms_return_items            - Itens devolução
❌ adms_return_observations     - Observações devolução
❌ adms_return_reasons          - Razões devolução
❌ adms_relocations             - Relocações
❌ adms_relocation_items        - Itens relocação
❌ adms_status_relocations      - Status relocação
❌ adms_sit_relocation_items    - Status itens relocação
```

#### 3.2.12 Módulo Chat (3 tabelas)
```
❌ adms_chat_conversations      - Conversas
❌ adms_chat_messages           - Mensagens
❌ adms_chat_typing_status      - Status digitação
```

#### 3.2.13 Outros/Auxiliares (28 tabelas)
```
❌ adms_areas                   - Áreas
❌ adms_bandeiras               - Bandeiras cartão
❌ adms_boards                  - Quadros
❌ adms_cfops                   - CFOPs
❌ adms_cors                    - Cores
❌ adms_ed_videos               - Vídeos educacionais
❌ adms_home_permissions        - Permissões home
❌ adms_images                  - Imagens
❌ adms_months                  - Meses
❌ adms_motivos                 - Motivos
❌ adms_neighborhoods           - Bairros
❌ adms_request_types           - Tipos de requisição
❌ adms_resp_autorizacao        - Responsáveis autorização
❌ adms_status_requests         - Status requisições
❌ adms_status_stocks           - Status estoques
❌ adms_up_down                 - Up/Down
❌ adms_users_online            - Usuários online
❌ tb_areas                     - Áreas (legado)
❌ tb_bairros                   - Bairros (legado)
❌ tb_cargos                    - Cargos (legado)
❌ tb_dashboards                - Dashboards (legado)
❌ tb_forma_pag                 - Formas pagamento (legado)
❌ tb_funcionarios              - Funcionários (legado)
❌ tb_justificativas            - Justificativas (legado)
❌ tb_prateleira_infinita       - Prateleira infinita
❌ tb_status                    - Status (legado)
❌ tb_tam                       - Tamanhos
❌ tb_transferencias            - Transferências (legado)
```

### 3.3 Resumo da Migração

| Categoria | Total no Legado | Migrado | Pendente |
|-----------|-----------------|---------|----------|
| Core/Autenticação | 5 | 5 | 0 |
| Funcionários/RH | 30 | 5 | 25 |
| Financeiro | 23 | 0 | 23 |
| Comercial | 18 | 0 | 18 |
| Delivery | 16 | 0 | 16 |
| E-commerce | 5 | 0 | 5 |
| Qualidade | 16 | 0 | 16 |
| Ativo Fixo | 4 | 0 | 4 |
| Outros | 60+ | 5 | 55+ |
| **TOTAL** | **~180** | **~17** | **~163** |

---

## 4. MÓDULOS PENDENTES DE IMPLEMENTAÇÃO

### 4.1 Alta Prioridade (Core Business)

#### 4.1.1 Módulo Financeiro
**Complexidade:** Alta
**Tabelas necessárias:** 23
**Funcionalidades:**
- Plano de contas contábil
- Contas a pagar/receber
- Gestão de bancos e formas de pagamento
- Controle de parcelas e vencimentos
- Estornos e ajustes
- Centros de custo
- Despesas de viagem e reembolsos
- Relatórios financeiros

**Dependências:**
- Employees (já implementado)
- Stores (já implementado)

#### 4.1.2 Módulo Comercial/Vendas
**Complexidade:** Alta
**Tabelas necessárias:** 18
**Funcionalidades:**
- Cadastro de produtos
- Gestão de fornecedores e marcas
- Metas de vendas (lojas e consultores)
- Consignação de produtos
- Controle de compras
- Orçamentos
- Premiações

**Dependências:**
- Stores (já implementado)
- Employees (já implementado)

#### 4.1.3 Módulo Delivery/Logística
**Complexidade:** Média-Alta
**Tabelas necessárias:** 16
**Funcionalidades:**
- Gestão de entregas
- Roteamento de veículos
- Cadastro de motoristas
- Transferências entre lojas
- Rastreamento de status
- Relatórios de logística

**Dependências:**
- Stores (já implementado)
- Products (pendente)

### 4.2 Média Prioridade

#### 4.2.1 Módulo RH Avançado
**Complexidade:** Média
**Tabelas necessárias:** 25
**Funcionalidades:**
- Controle de ausências e atestados
- Férias e pagamentos de férias
- Horas extras
- Indicações internas
- Recrutamento e seleção
- Demissões e desligamentos
- Treinamentos
- Escalas de trabalho

**Dependências:**
- Employees (já implementado)
- WorkShifts (já implementado)

#### 4.2.2 Módulo E-commerce
**Complexidade:** Média
**Tabelas necessárias:** 5
**Funcionalidades:**
- Gestão de pedidos online
- Integração com plataformas
- Status de pedidos e pagamentos
- Sincronização de estoque

**Dependências:**
- Products (pendente)
- Financial (pendente)

#### 4.2.3 Módulo Qualidade
**Complexidade:** Média
**Tabelas necessárias:** 16
**Funcionalidades:**
- Checklists de qualidade
- Auditorias de loja
- Questionários e respostas
- Relatórios de conformidade

**Dependências:**
- Stores (já implementado)
- Employees (já implementado)

### 4.3 Baixa Prioridade

#### 4.3.1 Módulo Ativo Fixo
**Complexidade:** Baixa
**Tabelas necessárias:** 4
**Funcionalidades:**
- Cadastro de ativos
- Inventário/Contagem
- Depreciação
- Movimentação de ativos

#### 4.3.2 Módulo Chat Interno
**Complexidade:** Média
**Tabelas necessárias:** 3
**Funcionalidades:**
- Conversas entre usuários
- Notificações em tempo real
- Histórico de mensagens

**Dependências:**
- WebSockets (Laravel Echo/Pusher)

#### 4.3.3 Módulo Biblioteca de Processos
**Complexidade:** Baixa
**Tabelas necessárias:** 5
**Funcionalidades:**
- Documentação de processos
- Políticas da empresa
- Artigos e manuais
- Versionamento de documentos

---

## 5. DÉBITOS TÉCNICOS

### 5.1 Críticos (Resolver Imediatamente)

#### DT-001: Operações de Arquivo Inseguras
**Arquivos afetados:**
- `app/Http/Controllers/EmployeeController.php` (linhas 335-341, 424-430)
- `app/Http/Controllers/UserManagementController.php` (linhas 235-244, 250-255, 287-293, 334-338)
- `app/Models/User.php` (linhas 186-195)

**Problema:** Uso direto de `unlink()` e `file_exists()` sem abstração.

**Solução:**
```php
// ANTES (inseguro)
if (file_exists(storage_path('app/public/' . $user->avatar))) {
    unlink(storage_path('app/public/' . $user->avatar));
}

// DEPOIS (correto)
use Illuminate\Support\Facades\Storage;
Storage::disk('public')->delete($user->avatar);
```

#### DT-002: Logging de Dados Sensíveis
**Arquivos afetados:**
- `app/Http/Controllers/EmployeeController.php` (linhas 261-270)

**Problema:** CPF e dados pessoais sendo logados.

**Solução:** Mascarar dados antes de logar:
```php
Log::info('Update employee request', [
    'id' => $id,
    'cpf' => substr($request->cpf, 0, 3) . '***' . substr($request->cpf, -2),
]);
```

#### DT-003: Falta de Rate Limiting
**Problema:** Endpoints de login e API sem proteção contra brute force.

**Solução:**
```php
// routes/web.php
Route::middleware(['throttle:login'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// app/Providers/RouteServiceProvider.php
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});
```

### 5.2 Altos (Resolver em 2 Semanas)

#### DT-004: Cobertura de Testes Baixa
**Situação Atual:** 13 arquivos de teste para 106 classes
**Meta:** 70% de cobertura

**Prioridade de testes:**
1. EmployeeController
2. UserManagementController
3. MenuService
4. AuditLogService
5. AccessLevelController

#### DT-005: Validações Inline em Controllers
**Arquivos afetados:**
- `app/Http/Controllers/EmployeeController.php` (linhas 119-140)
- `app/Http/Controllers/UserManagementController.php` (linha 95)
- `app/Http/Controllers/AccessLevelController.php` (linhas 24-32)

**Solução:** Criar FormRequest classes:
```bash
php artisan make:request StoreEmployeeRequest
php artisan make:request UpdateEmployeeRequest
php artisan make:request StoreUserRequest
```

#### DT-006: Valores Mágicos/Hardcoded
**Problema:** Status IDs, tipos de movimentação hardcoded.

**Solução:** Criar Enums:
```php
// app/Enums/EmployeeStatus.php
enum EmployeeStatus: int
{
    case PENDING = 1;
    case ACTIVE = 2;
    case INACTIVE = 3;
    case VACATION = 4;
    case LEAVE = 5;
}
```

### 5.3 Médios (Resolver em 1 Mês)

#### DT-007: Métodos Muito Longos
**Arquivos afetados:**
- `EmployeeController::update()` - 152 linhas
- `EmployeeController::storeContract()` - 111 linhas
- `MenuService::getMenuForAccessLevel()` - 136 linhas

**Solução:** Extrair para Services/Actions:
```
EmployeeController::update() → EmployeeUpdateAction::execute()
EmployeeController::storeContract() → ContractService::create()
```

#### DT-008: N+1 Queries
**Arquivos afetados:**
- `app/Http/Controllers/EmployeeController.php` (linhas 459-477)
- `app/Http/Controllers/AccessLevelController.php` (linhas 95-96)

**Solução:** Usar eager loading:
```php
// ANTES
$employees = Employee::all();
foreach ($employees as $emp) {
    echo $emp->position->name; // N+1
}

// DEPOIS
$employees = Employee::with('position')->get();
```

#### DT-009: Código Duplicado (Upload de Imagem)
**Arquivos afetados:**
- EmployeeController (2 lugares)
- UserManagementController (2 lugares)

**Solução:** Usar ImageUploadService existente em todos os lugares.

### 5.4 Baixos (Backlog)

#### DT-010: Falta de PHPDoc
**Cobertura atual:** ~30%
**Meta:** 90%

#### DT-011: TypeScript no Frontend
**Situação:** React sem tipos
**Benefício:** Maior segurança de tipos

#### DT-012: Testes E2E
**Situação:** Inexistentes
**Ferramenta sugerida:** Laravel Dusk ou Cypress

---

## 6. MELHORIAS RECOMENDADAS

### 6.1 Arquitetura

| Melhoria | Prioridade | Impacto |
|----------|-----------|---------|
| Implementar Repository Pattern | Média | Testabilidade |
| Criar Actions para lógica complexa | Alta | Manutenibilidade |
| Implementar Cache em MenuService | Alta | Performance |
| Usar DTOs para transferência de dados | Média | Segurança de tipos |
| Implementar Event Sourcing para Auditoria | Baixa | Rastreabilidade |

### 6.2 Performance

| Melhoria | Prioridade | Impacto |
|----------|-----------|---------|
| Cache de menus por usuário | Alta | Redução de queries |
| Lazy loading de imagens | Média | UX |
| Paginação server-side em todas tabelas | Alta | Performance |
| Índices adicionais no banco | Média | Queries mais rápidas |
| Queue para operações pesadas | Média | Responsividade |

### 6.3 Segurança

| Melhoria | Prioridade | Impacto |
|----------|-----------|---------|
| Rate limiting em todos endpoints | Alta | Proteção |
| CORS configurado corretamente | Alta | Segurança |
| CSP Headers | Média | XSS Prevention |
| Auditoria de dependências | Alta | Vulnerabilidades |
| 2FA para admins | Média | Segurança de acesso |

### 6.4 DevOps

| Melhoria | Prioridade | Impacto |
|----------|-----------|---------|
| CI/CD Pipeline | Alta | Qualidade |
| Testes automatizados no deploy | Alta | Estabilidade |
| Monitoramento (Sentry/Bugsnag) | Média | Debugging |
| Backup automatizado | Alta | Disaster Recovery |
| Ambiente de staging | Média | Qualidade |

---

## 7. ROADMAP DE DESENVOLVIMENTO

### Fase 1: Estabilização (Semanas 1-2)
- [ ] Corrigir débitos técnicos críticos (DT-001 a DT-003)
- [ ] Aumentar cobertura de testes para 50%
- [ ] Implementar rate limiting
- [ ] Remover logging de dados sensíveis

### Fase 2: Módulo Financeiro (Semanas 3-6)
- [ ] Criar migrations para tabelas financeiras
- [ ] Implementar Models e relacionamentos
- [ ] Criar Controllers e rotas
- [ ] Desenvolver interface React
- [ ] Testes e documentação

### Fase 3: Módulo Comercial (Semanas 7-10)
- [ ] Cadastro de produtos
- [ ] Fornecedores e marcas
- [ ] Metas de vendas
- [ ] Relatórios comerciais

### Fase 4: Módulo Delivery (Semanas 11-13)
- [ ] Gestão de entregas
- [ ] Motoristas e rotas
- [ ] Transferências
- [ ] Rastreamento

### Fase 5: RH Avançado (Semanas 14-16)
- [ ] Férias e ausências
- [ ] Recrutamento
- [ ] Treinamentos
- [ ] Horas extras

### Fase 6: Qualidade e E-commerce (Semanas 17-20)
- [ ] Checklists de qualidade
- [ ] Integração e-commerce
- [ ] Relatórios

### Fase 7: Módulos Auxiliares (Semanas 21-24)
- [ ] Ativo fixo
- [ ] Biblioteca de processos
- [ ] Chat interno
- [ ] Polimento geral

---

## 8. PADRÕES E CONVENÇÕES

### 8.1 Nomenclatura

```
Controllers:   PascalCase + Controller   → EmployeeController
Models:        PascalCase (singular)     → Employee
Migrations:    snake_case + timestamp    → 2025_10_01_create_employees_table
Tables:        snake_case (plural)       → employees
Columns:       snake_case                → created_at
Routes:        kebab-case                → /employees/{employee}/contracts
React Pages:   PascalCase                → EmployeeIndex.jsx
React Comps:   PascalCase                → EmployeeModal.jsx
```

### 8.2 Estrutura de Diretórios

```
app/
├── Console/Commands/    # Comandos Artisan
├── Enums/               # Enumerações PHP
├── Exports/             # Classes de exportação
├── Helpers/             # Funções auxiliares
├── Http/
│   ├── Controllers/     # Controllers (por módulo)
│   ├── Middleware/      # Middlewares
│   └── Requests/        # Form Requests
├── Models/              # Eloquent Models
├── Rules/               # Regras de validação
├── Services/            # Lógica de negócio
└── Traits/              # Traits reutilizáveis

resources/js/
├── Components/          # Componentes React reutilizáveis
├── Layouts/             # Layouts de página
└── Pages/               # Páginas Inertia (por módulo)
    ├── Auth/
    ├── Employees/
    ├── Users/
    └── [Módulo]/
```

### 8.3 Padrão de Controller

```php
class ExampleController extends Controller
{
    public function __construct(
        private ExampleService $service
    ) {}

    public function index(Request $request): Response
    {
        $items = $this->service->paginate($request);

        return Inertia::render('Example/Index', [
            'items' => $items,
        ]);
    }

    public function store(StoreExampleRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route('examples.index')
            ->with('success', 'Item criado com sucesso.');
    }
}
```

### 8.4 Padrão de Service

```php
class ExampleService
{
    public function __construct(
        private AuditLogService $auditLog
    ) {}

    public function create(array $data): Example
    {
        $example = Example::create($data);

        $this->auditLog->logModelCreated($example);

        return $example;
    }
}
```

---

## 9. REFERÊNCIA TÉCNICA

### 9.1 Comandos Úteis

```bash
# Desenvolvimento
composer dev              # Inicia servidor + queue + logs + vite
php artisan serve         # Apenas servidor
npm run dev               # Apenas Vite

# Banco de Dados
php artisan migrate       # Executar migrations
php artisan migrate:fresh # Reset + migrate
php artisan db:seed       # Executar seeders

# Testes
php artisan test          # Executar todos os testes
php artisan test --filter=EmployeeTest  # Teste específico

# Cache
php artisan cache:clear   # Limpar cache
php artisan config:cache  # Cache de configuração
php artisan route:cache   # Cache de rotas

# Geração de Código
php artisan make:model Example -mfsc  # Model + Migration + Factory + Seeder + Controller
php artisan make:request StoreExampleRequest
php artisan make:test ExampleTest

# Auditoria (comandos custom)
php artisan audit:cleanup 30  # Limpar logs > 30 dias
php artisan audit:stats       # Estatísticas de auditoria
```

### 9.2 Variáveis de Ambiente Importantes

```env
APP_NAME=Mercury
APP_ENV=local|production
APP_DEBUG=true|false
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=mercury
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=public
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

### 9.3 Endpoints Principais

```
# Autenticação
POST   /login
POST   /logout
POST   /register
POST   /forgot-password
POST   /reset-password

# Usuários
GET    /users
POST   /users
GET    /users/{user}
PUT    /users/{user}
DELETE /users/{user}

# Funcionários
GET    /employees
POST   /employees
GET    /employees/{employee}
PUT    /employees/{employee}
DELETE /employees/{employee}
GET    /employees/{employee}/history
POST   /employees/{employee}/contracts
POST   /employees/{employee}/events

# Jornadas
GET    /work-shifts
POST   /work-shifts
GET    /work-shifts/export

# Menus
GET    /api/menus/sidebar
GET    /menus
POST   /menus
POST   /menus/{menu}/move-up
POST   /menus/{menu}/move-down

# Níveis de Acesso
GET    /access-levels
GET    /access-levels/{level}/permissions
POST   /access-levels/{level}/permissions

# Logs
GET    /activity-logs
POST   /activity-logs/export
DELETE /activity-logs/cleanup
```

### 9.4 Permissões Disponíveis

```php
// Gestão de Usuários
Permission::VIEW_USERS
Permission::CREATE_USERS
Permission::EDIT_USERS
Permission::DELETE_USERS
Permission::MANAGE_USER_ROLES

// Perfil
Permission::VIEW_OWN_PROFILE
Permission::EDIT_OWN_PROFILE
Permission::VIEW_ANY_PROFILE
Permission::EDIT_ANY_PROFILE

// Acesso ao Sistema
Permission::ACCESS_DASHBOARD
Permission::ACCESS_ADMIN_PANEL
Permission::ACCESS_SUPPORT_PANEL

// Configurações
Permission::MANAGE_SETTINGS
Permission::VIEW_SETTINGS
Permission::MANAGE_SYSTEM_SETTINGS

// Logs
Permission::VIEW_ACTIVITY_LOGS
Permission::EXPORT_ACTIVITY_LOGS
```

### 9.5 Roles e Hierarquia

```php
Role::SUPER_ADMIN  // Level 4 - Todas as permissões
Role::ADMIN        // Level 3 - Gerenciamento sem controle total
Role::SUPPORT      // Level 2 - Apenas visualização
Role::USER         // Level 1 - Apenas próprio perfil
```

---

## CONCLUSÃO

O projeto Mercury Laravel está em estágio avançado de desenvolvimento com uma base sólida implementada. As funcionalidades core (autenticação, RBAC, funcionários, jornadas) estão completas e funcionais.

**Próximos passos críticos:**
1. Resolver débitos técnicos críticos (segurança e arquivos)
2. Aumentar cobertura de testes
3. Implementar módulo financeiro
4. Migrar dados do sistema legado

**Estimativa para MVP completo:** 20-24 semanas de desenvolvimento
**Tabelas restantes para migrar:** ~163 de ~180 (~90%)

---

*Documento gerado automaticamente em Janeiro 2026*
*Versão: 1.0*
