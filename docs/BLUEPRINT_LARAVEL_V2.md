# Mercury v2 - Blueprint Completo para Migração Laravel

**Versão:** 1.0
**Data:** 05 de Abril de 2026
**Objetivo:** Documento de referência completo para reescrita do Mercury em Laravel

---

## Sumário

1. [Visão Geral do Projeto](#1-visão-geral-do-projeto)
2. [Inventário Quantitativo](#2-inventário-quantitativo)
3. [Arquitetura Atual (v1)](#3-arquitetura-atual-v1)
4. [Mapa de Módulos](#4-mapa-de-módulos)
5. [Banco de Dados - Tabelas e Relacionamentos](#5-banco-de-dados)
6. [Services e Regras de Negócio](#6-services-e-regras-de-negócio)
7. [State Machines](#7-state-machines)
8. [API REST](#8-api-rest)
9. [WebSocket e Real-time](#9-websocket-e-real-time)
10. [Frontend (Views + JS)](#10-frontend)
11. [Testes](#11-testes)
12. [Integrações Externas](#12-integrações-externas)
13. [Sistema de Permissões](#13-sistema-de-permissões)
14. [Variáveis de Ambiente](#14-variáveis-de-ambiente)
15. [Estratégia de Migração Laravel](#15-estratégia-de-migração-laravel)

---

## 1. Visão Geral do Projeto

**Mercury** é um portal ERP administrativo desenvolvido para o Grupo Meia Sola (rede de lojas de calçados). Cobre operações de RH, estoque, vendas, financeiro, logística, treinamento, helpdesk, chat e integrações com ERP Cigam (PostgreSQL) e WhatsApp.

### Stack Atual
| Camada | Tecnologia |
|--------|-----------|
| Backend | PHP 8.0+ (MVC custom) |
| Database Principal | MySQL 8 (PDO) |
| Database ERP | PostgreSQL (Cigam) |
| Frontend | Bootstrap 5.3 + Vanilla JS (ES6+) |
| Real-time | Ratchet 0.4 WebSocket + ReactPHP |
| Auth API | JWT (firebase/php-jwt) |
| Email | PHPMailer |
| PDF | DomPDF 3.0 |
| Excel | PhpSpreadsheet 5.3 |
| Testes | PHPUnit 12.4 |

---

## 2. Inventário Quantitativo

| Artefato | Quantidade |
|----------|-----------|
| Controllers | 742 |
| Models | 617 |
| Helpers (DB) | 42 |
| Search Models | 74 |
| Services | 74 |
| Views | 906 |
| Módulos de Views | 148 (130 adms + 18 cpadms) |
| JavaScript Files | 153 custom + 8 libs |
| CSS Files | 14 |
| Migrations | 229 (98 SQL + 131 PHP) |
| Test Files | 364 |
| Tabelas DB | 130+ |
| Endpoints API | 40+ |
| Linhas JS | ~118.809 |
| Testes Passando | 3.899 |

---

## 3. Arquitetura Atual (v1)

### 3.1 Roteamento
- **DB-driven**: Tabela `adms_paginas` armazena rotas (controller + método)
- **ConfigController.php**: Parseia URL → resolve controller/método → verifica permissões
- **Middlewares sequenciais**: CSRF → Session Validation → Force Password Change → Page Tracking
- Páginas públicas marcadas com `lib_pub=1` (bypass CSRF)

### 3.2 Padrão MVC
```
URL: /mercury/sales/list
     ↓
ConfigController → resolve "Sales" controller, "list" método
     ↓
Sales::list() → AdmsListSales (model) → AdmsRead (helper) → MySQL
     ↓
ConfigView::renderizar() → Views/sales/loadSales.php (container)
     ↓
JS (sales.js) → fetch('/mercury/sales/list/1') → Views/sales/listSales.php (AJAX)
```

### 3.3 Padrões de Controllers

| Padrão | Qtd | Descrição |
|--------|-----|-----------|
| AbstractConfigController | ~75 | Módulos config/lookup com CRUD herdado |
| CRUD Standalone | ~200 | Entity + Add + Edit + Delete + View controllers separados |
| AbstractChatController | ~10 | Base para módulos de chat |
| Legacy (Português) | ~80 | Cadastrar*, Editar*, Apagar*, Ver*, Listar* |
| API REST | 11 | BaseApiController com JWT |

### 3.4 Padrão de Models

| Tipo | Prefixo | Função |
|------|---------|--------|
| CRUD | Adms{Entity} | Create/Update/Delete principal |
| Listagem | AdmsList{Entities} | SELECT com paginação e filtros |
| Estatísticas | AdmsStatistics{Entities} | Contagens e agregações |
| Visualização | AdmsView{Entity} | SELECT detalhado com JOINs |
| Search | CpAdmsSearch{Entity} | Busca textual para listagens |
| Export | CpAdmsExport{Entity} | Exportação CSV/Excel |

### 3.5 Database Helpers
```php
AdmsRead::fullRead($sql, $params)    // SELECT customizado
AdmsRead::exeRead($table, $where)    // SELECT simples
AdmsCreate::exeCreate($table, $data) // INSERT
AdmsUpdate::exeUpdate($table, $data, $where, $params) // UPDATE
AdmsDelete::exeDelete($table, $where, $params) // DELETE
AdmsPaginacao // Paginação com LIMIT/OFFSET
```
**Formato de params**: `"key1=value1&key2=value2"` (parse_str)

### 3.6 Traits
| Trait | Função |
|-------|--------|
| MoneyConverterTrait | Converte "1.234,56" → 1234.56 |
| JsonResponseTrait | Resposta JSON padronizada |
| StorePermissionTrait | Filtro por loja via PermissionService |
| FinancialPermissionTrait | Filtro financeiro (dual-store) |

---

## 4. Mapa de Módulos

### 4.1 Módulos por Domínio

#### RH e Pessoal (10 módulos)
| Módulo | Controllers | Models | Views | JS | Tabelas Principais |
|--------|------------|--------|-------|----|--------------------|
| **Employees** | 5 (CRUD + Report) | 5+ | 12 | employees.js (861 LOC) | adms_employees, adms_employment_contracts |
| **AbsenceControl** | 5 (CRUD) | 4+ | 7 | absence-control.js (736 LOC) | adms_absence_control |
| **OvertimeControl** | 5 (CRUD) | 4+ | files | overtime-control.js | adms_overtime_control |
| **MedicalCertificate** | 5 (CRUD) | 4+ | files | medical-certificate.js | adms_medical_certificates |
| **PersonnelMoviments** | 5 (CRUD) | 4+ | 8 | personnel-moviments.js (708 LOC) | adms_personnel_moviments, adms_dismissal_follow_up |
| **PersonnelRequests** | 5 (CRUD) | 4+ | 7 | personnel-requests.js (833 LOC) | adms_personnel_requests, adms_personnel_request_messages |
| **VacancyOpening** | 5 (CRUD) | 4+ | files | vacancy-opening.js (1281 LOC) | adms_vacancy_opening |
| **WorkSchedule** | 5 (CRUD) | 4+ | files | work-schedule.js (1069 LOC) | adms_work_schedules, adms_work_schedule_days, adms_employee_work_schedules |
| **ExperienceTracker** | 3+ | 3+ | files | experience-tracker.js | adms_experience_evaluations |
| **Managers** | 5 (CRUD) | 4+ | files | managers.js | adms_managers |

#### Férias e Feriados (3 módulos)
| Módulo | Controllers | Models | JS | Tabelas Principais |
|--------|------------|--------|----|--------------------|
| **Holidays** | 1 (AbstractConfig) | herdado | holidays.js | adms_holidays |
| **VacationPeriods** | 4 (CRUD) | 4+ | vacation-periods.js (730 LOC) | adms_vacation_periods |
| **Vacations** | 6 (CRUD + Approve) | 5+ | vacations.js (1439 LOC) | adms_vacations, adms_vacation_logs |

#### Vendas e Financeiro (8 módulos)
| Módulo | Controllers | Models | JS | Tabelas Principais |
|--------|------------|--------|----|--------------------|
| **Sales** | 8 | 6+ | sales.js (1151 LOC) | adms_movements, adms_sales_summary |
| **OrderPayments** | 8 (Kanban) | 6+ | order-payments.js (4867 LOC) | adms_order_payments, adms_installments |
| **OrderControl** | 10+ | 8+ | order-control.js (1247 LOC) | adms_purchase_order_controls |
| **Reversals** | 5 (CRUD) | 4+ | reversals.js | adms_estornos |
| **Returns** | 5 (CRUD + Export) | 4+ | returns.js | adms_returns |
| **Coupons** | 5 (CRUD) | 4+ | coupons.js (1180 LOC) | adms_coupons |
| **StoreGoals** | 5 (CRUD) | 4+ | store-goals.js | adms_store_goals |
| **TravelExpenses** | 5 (CRUD) | 4+ | travel-expenses.js | adms_travel_expenses |

#### Estoque e Inventário (7 módulos)
| Módulo | Controllers | Models | JS | Tabelas Principais |
|--------|------------|--------|----|--------------------|
| **StockMovements** | 2 | 3+ | stock-movements.js (758 LOC) | adms_movements, adms_stock_movement_alerts |
| **StockAudit** | 8+ (multi-phase) | 8+ | 8 JS files | adms_stock_audits, adms_stock_audit_items, +10 tabelas |
| **Adjustments** | 5 (CRUD) | 4+ | adjustments.js | adms_adjustments, adms_adjustment_items |
| **Transfers** | 6 (CRUD + Confirm) | 5+ | transfers.js | adms_transfers |
| **Consignments** | 6 (CRUD + Print) | 5+ | consignments.js (1152 LOC) | adms_consignments |
| **FixedAssets** | 6 (CRUD + Count) | 5+ | files | adms_fixed_assets |
| **DamagedProducts** | 5 (CRUD + Match) | 5+ | damaged-products.js | adms_damaged_products, adms_damaged_product_matches |

#### Produtos e Catálogo (6 módulos)
| Módulo | Controllers | Models | JS | Tabelas Principais |
|--------|------------|--------|----|--------------------|
| **Products** | 3+ | 5+ | products.js (1063 LOC) | adms_products |
| **ProductPromotions** | 5 (CRUD + Import) | 4+ | product-promotions.js | adms_product_promotions |
| **ProdCategories** | AbstractConfig | herdado | - | adms_prod_categories |
| **ProdCollections** | AbstractConfig | herdado | - | adms_prod_collections |
| **ProdColors** | AbstractConfig | herdado | - | adms_prod_colors |
| **ProdBrands/Materials/Sizes** | AbstractConfig | herdado | - | adms_prod_brands/materials/sizes |

#### Logística e Entregas (5 módulos)
| Módulo | Controllers | Models | JS | Tabelas Principais |
|--------|------------|--------|----|--------------------|
| **Delivery** | 5 (CRUD + Print) | 4+ | delivery.js (861 LOC) | tb_delivery |
| **DeliveryRouting** | 3 | 3+ | delivery-routing.js | routing tables |
| **Driver** | 5 (CRUD) | 4+ | driver.js | adms_drivers |
| **Relocation** | 5 (CRUD) | 4+ | relocation.js | adms_relocations |
| **MaterialRequest** | 5 (CRUD) | 4+ | material-request.js | adms_marketing_material_requests |

#### Comunicação (5 módulos)
| Módulo | Controllers | Models | JS | Tabelas Principais |
|--------|------------|--------|----|--------------------|
| **Chat** | 11 (AbstractChat) | 5+ | chat.js + mercury-ws.js | conversations, messages |
| **ChatGroup** | 4 | 3+ | (shared chat.js) | conversation_participants |
| **ChatBroadcast** | 2 | 2+ | (shared chat.js) | broadcast tables |
| **Helpdesk** | 6+ | 5+ | helpdesk.js (9 files) | hd_tickets, hd_interactions, hd_attachments |
| **SystemNotifications** | 2 | 2+ | navbar-notifications.js | adms_notifications |

#### Treinamento (5 módulos)
| Módulo | Controllers | Models | JS | Tabelas Principais |
|--------|------------|--------|----|--------------------|
| **Training** | 5 (CRUD) | 5+ | training.js (1166 LOC) | adms_trainings |
| **TrainingSubject** | AbstractConfig | herdado | training-subject.js | adms_training_subjects |
| **Facilitator** | 5 (CRUD) | 4+ | facilitator.js | adms_facilitators |
| **CertificateTemplate** | AbstractConfig | herdado | certificate-template.js | adms_certificate_templates |
| **PublicTraining** | 3 | 3+ | public-training.js | (shared training tables) |

#### Administração e Config (12 módulos)
| Módulo | Controllers | Models | JS | Tabelas Principais |
|--------|------------|--------|----|--------------------|
| **User** | 5 (CRUD) | 5+ | users.js | adms_usuarios |
| **AccessLevel** | 7 (CRUD + Reorder) | 5+ | access-levels.js | adms_niveis_acesso |
| **Permissions** | 2 | 2+ | permissions.js | adms_nivacs_pgs |
| **Store** | 5 (CRUD) | 4+ | store.js | tb_lojas |
| **Page/PageGroups** | 5+5 | 4+4 | pages.js, page-groups.js | adms_paginas, adms_grps_pgs |
| **Menu** | 6 | 3+ | menu.js | adms_menus |
| **Cargo** | AbstractConfig | herdado | cargo.js | tb_cargos |
| **Bank** | AbstractConfig | herdado | bank.js | adms_banks |
| **CostCenters** | AbstractConfig | herdado | cost-centers.js | adms_cost_centers |
| **Areas** | AbstractConfig | herdado | - | adms_areas |
| **Supplier** | 5 (CRUD) | 4+ | supplier.js (768 LOC) | adms_suppliers |
| **Brand** | 5 (CRUD) | 4+ | brand.js | adms_marcas |

#### Módulos Especializados (6 módulos)
| Módulo | Controllers | JS | Tabelas |
|--------|------------|----|---------| 
| **TurnList (LDV)** | 4+ | turn-list.js (1961 LOC) | ldv_waiting_queue, ldv_attendances, ldv_breaks |
| **ServiceOrder** | 6+ | service-orders.js | adms_qualidade_ordem_servico |
| **Checklist** | 5+ | checklist.js | adms_checklists |
| **Ecommerce** | 5 | ecommerce.js | adms_ecommerce_orders |
| **ProcessLibrary** | 5 | process-library.js | adms_process_librarys |
| **Policies** | 5 | - | adms_policies |

#### Dashboard e Relatórios (5 módulos)
| Módulo | Controllers | JS |
|--------|------------|-----|
| **Dashboard** | 1 | dashboard.js |
| **DashboardRH** | 1 | dashboard-rh.js |
| **DashboardServiceOrders** | 1 | dashboard-service-orders.js |
| **ActivityLog** | 2 | activity-log.js |
| **Report** controllers | 5+ (vários) | (inline) |

### 4.2 Módulos Legacy (Português) - Candidatos a Migração Prioritária
~80 controllers com nomenclatura portuguesa que devem ser modernizados:
- `Cadastrar*`, `Editar*`, `Apagar*`, `Ver*`, `Listar*`
- Módulos: Balanço, Rota, Troca, Vídeo, Ciclo, UsuarioTreinamento, Categoria, etc.

---

## 5. Banco de Dados

### 5.1 Tabelas por Prefixo

| Prefixo | Qtd | Domínio |
|---------|-----|---------|
| `adms_` | ~100 | Sistema principal (admin, RH, financeiro, estoque) |
| `tb_` | ~6 | Legacy (lojas, cargos, redes, status, tamanhos) |
| `ldv_` | ~6 | Lista da Vez (fila de atendimento) |
| `hd_` | ~5 | Helpdesk (tickets, interações) |
| `api_` | ~2 | API (tokens, rate limits) |
| sem prefixo | ~5 | Chat (conversations, messages) |

### 5.2 Tabelas Principais por Domínio

#### Core/Autenticação
```
adms_usuarios          - Usuários do sistema
adms_niveis_acesso     - Níveis de acesso (1-23)
adms_paginas           - Páginas/rotas do sistema
adms_nivacs_pgs        - Permissões página×nível
adms_menus             - Menu de navegação
adms_activity_logs     - Log de auditoria
adms_record_locks      - Lock pessimista de registros
adms_notifications     - Notificações
adms_notification_recipients - Regras de destinatários
api_tokens             - JWT refresh tokens
api_rate_limits        - Rate limiting
```

#### Lojas e Estrutura
```
tb_lojas               - Lojas (id string: "Z424", "A001")
tb_redes               - Redes de lojas
tb_cargos              - Cargos/posições
adms_areas             - Áreas/departamentos
adms_banks             - Bancos
adms_cost_centers      - Centros de custo
adms_suppliers         - Fornecedores
adms_marcas            - Marcas
```

#### Funcionários e RH
```
adms_employees                    - Funcionários
adms_status_employee              - Status (Pendente/Ativo/Inativo/Férias/Afastado)
adms_employee_status_history      - Histórico de status
adms_employment_contracts         - Contratos
adms_managers                     - Gerentes
adms_drivers                      - Motoristas
adms_absence_control              - Controle de faltas
adms_overtime_control             - Horas extras
adms_medical_certificates         - Atestados médicos
adms_personnel_moviments          - Movimentações de pessoal
adms_vacancy_opening              - Vagas abertas
adms_job_applicants               - Candidatos
adms_work_schedules               - Escalas de trabalho
adms_work_schedule_days           - Dias por escala
adms_employee_work_schedules      - Atribuição funcionário×escala
adms_employee_schedule_day_overrides - Exceções por dia
```

#### Férias
```
adms_holidays                     - Feriados
adms_vacation_periods             - Períodos aquisitivos
adms_status_vacation_periods      - Status dos períodos (6)
adms_vacations                    - Solicitações de férias
adms_status_vacations             - Status das férias (9)
adms_vacation_logs                - Log de ações
adms_vacation_alert_log           - Log de alertas
```

#### Vendas e Movimentações
```
adms_movements                    - Movimentos unificados (ERP sync)
adms_sales_summary                - Resumo de vendas (materializado)
adms_movement_types               - Tipos de movimento
adms_sync_log                     - Log de sincronização
adms_stock_movement_alerts        - Alertas de movimentação
```

#### Financeiro
```
adms_order_payments               - Ordens de pagamento
adms_installments                 - Parcelas
adms_sits_order_payments          - Status (BACKLOG/DOING/WAITING/DONE)
adms_estornos                     - Estornos
adms_motivo_estorno               - Motivos de estorno
adms_type_key_pixs                - Tipos de chave PIX
adms_travel_expenses              - Despesas de viagem
adms_store_goals                  - Metas de loja
adms_coupons                      - Cupons
```

#### Pedidos
```
adms_purchase_order_controls      - Pedidos de compra
adms_purchase_order_control_items - Itens do pedido
adms_order_control                - Controle de pedidos
```

#### Estoque e Auditoria
```
adms_stock_audits                 - Auditorias de estoque
adms_stock_audit_items            - Itens auditados
adms_stock_audit_areas            - Áreas auditadas
adms_stock_audit_signatures       - Assinaturas digitais
adms_stock_audit_store_justifications - Justificativas da loja
adms_stock_audit_justification_images - Fotos de justificativa
adms_stock_audit_import_logs      - Log de importação
adms_stock_audit_accuracy_history - Histórico de acurácia
adms_stock_audit_schedule         - Agendamento
adms_stock_audit_statuses         - Status (6): Rascunho→Autorização→Contagem→Conciliação→Finalizada→Cancelada
adms_stock_audit_cycles           - Ciclos (Mensal/Bimestral/etc.)
adms_audit_vendors                - Empresas auditoras
adms_audit_vendor_collaborators   - Colaboradores da auditora
adms_audit_teams                  - Equipes de auditoria
```

#### Transferências e Consignações
```
adms_transfers                    - Transferências
adms_status_transfers             - Status (Pendente/Em Rota/Entregue/Confirmado/Cancelado)
adms_transfer_types               - Tipos (Transferência/Remanejo/Devolução/Troca/Match)
adms_consignments                 - Consignações
adms_consignment_alerts           - Alertas de consignação
adms_adjustments                  - Ajustes de estoque
adms_adjustment_items             - Itens de ajuste
adms_adjustment_status_history    - Histórico de status
adms_nf_preparations              - Preparação de NF
adms_nf_preparation_items         - Itens da NF
adms_relocations                  - Remanejos
adms_relocation_items             - Itens do remanejo
adms_returns                      - Devoluções
```

#### Produtos Avariados
```
adms_damaged_products             - Produtos danificados
adms_damaged_product_photos       - Fotos
adms_damaged_product_matches      - Matches (pares)
adms_damage_types                 - Tipos de dano
adms_status_damaged_products      - Status (5)
adms_network_brand_rules          - Regras marca×rede
```

#### Produtos e Catálogo
```
adms_products                     - Produtos (sync Cigam)
adms_prod_categories              - Categorias
adms_prod_collections             - Coleções
adms_prod_subcollections          - Subcoleções
adms_prod_colors                  - Cores
adms_prod_brands                  - Marcas
adms_prod_materials               - Materiais
adms_prod_sizes                   - Tamanhos
adms_prod_article_complements     - Complementos
adms_prod_sync_logs               - Log de sincronização
adms_prod_import_logs             - Log de importação
adms_product_promotions           - Promoções
adms_promotion_items              - Itens promocionais
adms_promotion_history            - Histórico de promoções
```

#### Entregas e Logística
```
tb_delivery                       - Entregas
adms_deliveries                   - Entregas (v2)
adms_fixed_assets                 - Ativos fixos
adms_marketing_material_requests  - Requisições de material
```

#### Chat e Comunicação
```
conversations                     - Conversas
conversation_participants         - Participantes
messages                          - Mensagens
adms_dp_chat_sessions            - Sessões chat DP
```

#### Helpdesk
```
hd_tickets                        - Tickets
hd_departments                    - Departamentos
hd_categories                     - Categorias
hd_interactions                   - Interações
hd_attachments                    - Anexos
hd_permissions                    - Permissões
```

#### Treinamento
```
adms_trainings                    - Treinamentos
adms_training_subjects            - Assuntos
adms_training_statuses            - Status (5)
adms_facilitators                 - Facilitadores
adms_certificate_templates        - Templates de certificado
adms_training_participants        - Participantes
adms_training_evaluations         - Avaliações
```

#### Requisições de Pessoal (WhatsApp DP)
```
adms_personnel_requests           - Requisições
adms_status_personnel_requests    - Status (6)
adms_personnel_request_messages   - Mensagens
adms_personnel_request_ratings    - Avaliações
adms_personnel_request_sla_config - Config SLA
adms_personnel_request_sla_alerts - Alertas SLA
adms_personnel_request_templates  - Templates de resposta
```

#### Lista da Vez (LDV)
```
ldv_waiting_queue                 - Fila de consultores
ldv_attendances                   - Atendimentos
ldv_attendance_history            - Histórico diário
ldv_breaks                        - Pausas
ldv_break_types                   - Tipos de pausa
ldv_attendance_status             - Status de atendimento
```

#### Ordem de Serviço e Checklist
```
adms_qualidade_ordem_servico      - Ordens de serviço
adms_detalhes_ordem_servico       - Detalhes
adms_defeitos_ordem_servico       - Defeitos
adms_def_local_ordem_servico      - Localização de defeitos
adms_checklists                   - Checklists
adms_checklist_answers            - Respostas
adms_service_check_lists          - Checklists de serviço
```

#### Monitoramento
```
adms_page_visits                  - Visitas a páginas
adms_users_online_heartbeat       - Heartbeat de presença
adms_device_info                  - Info de dispositivo
adms_idle_status                  - Status idle
adms_monitoring_alerts            - Alertas de monitoramento
```

### 5.3 Enums / Constants

| Enum | Valores |
|------|---------|
| OrderPaymentStatus | BACKLOG(1), DOING(2), WAITING(3), DONE(4) |
| EmployeeStatus | PENDING(1), ACTIVE(2), INACTIVE(3), VACATION(4), LEAVE(5) |
| AdjustmentStatus | PENDENTE(1), AJUSTADO(2), SEM_AJUSTE(3), CANCELADO(4), EM_ANALISE(5), TRANSFERENCIA_SALDO(6), AGUARDANDO_RESPOSTA(7) |
| RelocationStatus | PENDING(1), IN_PROGRESS(2), COMPLETED(3), CANCELED(4), PARTIAL(5) |
| ConsignmentStatus | (definido no módulo) |
| StockAuditStatus | DRAFT(1), AWAITING_AUTH(2), COUNTING(3), RECONCILIATION(4), FINISHED(5), CANCELLED(6) |
| TransferStatus | PENDENTE(1), EM_ROTA(2), ENTREGUE(3), CONFIRMADO(4), CANCELADO(5) |
| VacationPeriodStatus | EM_AQUISIÇÃO(1), DISPONÍVEL(2), PARCIALMENTE_GOZADO(3), QUITADO(4), VENCIDO(5), PERDIDO(6) |
| VacationStatus | RASCUNHO(1), PENDENTE_GESTOR(2), APROVADA_GESTOR(3), APROVADA_RH(4), EM_GOZO(5), FINALIZADA(6), CANCELADA(7), REJEITADA_GESTOR(8), REJEITADA_RH(9) |

---

## 6. Services e Regras de Negócio

### 6.1 Catálogo de Services (74 total)

#### Core / Framework
| Service | Arquivo | Função |
|---------|---------|--------|
| SessionContext | SessionContext.php | Facade para $_SESSION (getUserId, getAccessLevel, getUserStore, etc.) |
| PermissionService | PermissionService.php | Checks de permissão (isSuperAdmin, isAdmin, isStoreLevel, etc.) |
| AuthenticationService | AuthenticationService.php | Login/logout/verificação |
| CsrfService | CsrfService.php | Tokens CSRF (32 bytes, 60min TTL, session-bound) |
| PasswordService | PasswordService.php | Validação de senha (12+ chars, complexidade), hash, temporária |
| LoggerService | LoggerService.php | Auditoria (5 níveis, auto-redact de dados sensíveis) |
| RecordLockService | RecordLockService.php | Lock pessimista (5min TTL, heartbeat, WebSocket broadcast) |

#### Notificações e Email
| Service | Função |
|---------|--------|
| NotificationService | PHPMailer SMTP, rate limit 30/15min, flash messages |
| SystemNotificationService | Notificações sistema via WebSocket |
| NotificationRecipientService | Regras de destinatários configuráveis |
| DismissalNotificationService | Notificações de demissão |
| HelpdeskEmailService | Emails de tickets |
| HelpdeskChatNotifier | Notificação real-time de tickets |
| ChecklistEmailService | Emails de checklist |
| StoreGoalEmailService | Emails de metas |
| TrainingEmailService | Emails de treinamento |

#### Chat e WebSocket
| Service | Função |
|---------|--------|
| ChatService | Mensagens diretas, conversas |
| GroupChatService | Grupos, membros, typing |
| BroadcastService | Broadcast para múltiplos usuários |
| WebSocketService | Ratchet MessageComponent (conexões, broadcasts) |
| WebSocketTokenService | JWT curto (5min TTL) para auth WS |
| WebSocketNotifier | Fire-and-forget IPC (curl → 8081) |

#### State Machines e Transições
| Service | Função |
|---------|--------|
| OrderPaymentTransitionService | Backlog→Doing→Waiting→Done com campos condicionais |
| OrderControlStatusTransitionService | Pending→Invoiced→Delivered com permissões por nível |
| AuditStateMachineService | Draft→Auth→Counting→Reconciliation→Finished |
| VacationStatusTransitionService | Rascunho→Pendente→Aprovada→Em Gozo→Finalizada |
| AdjustmentTransitionService | Transições de ajuste de estoque |
| ReversalTransitionService | Transições de estorno |
| PersonnelMovimentTransitionService | Transições de movimentação de pessoal |
| VacancyTransitionService | Transições de vaga |

#### Validação de Negócio
| Service | Função |
|---------|--------|
| VacationValidatorService | 11 regras CLT (mínimo dias, parcelas, blackout, Art. 130/135/143/145) |
| VacationCalculationService | Saldo disponível, sell allowance |
| VacationPeriodGeneratorService | Auto-geração períodos aquisitivos (aniversário + CLT) |
| OrderControlValidationService | Validação de pedidos de compra |
| OrderPaymentAllocationService | Rateio por centro de custo (soma = 100%) |
| OrderPaymentDeleteService | Soft/hard delete com checks de dependência |
| AdjustmentNfService | Vinculação ajuste↔NF |
| AdjustmentDeleteService | Exclusão com constraints |

#### RH e Lifecycle
| Service | Função |
|---------|--------|
| EmployeeLifecycleService | Orquestração criação/ativação/inativação |
| EmployeeInactivationService | Inativação + notificações |
| EmployeeDeleteService | Soft delete com preservação |
| EmployeeContractService | Contratos de trabalho |
| VacancyRecruitmentService | Workflow de recrutamento |
| PersonnelRequestService | Integração WhatsApp DP |

#### Estoque e Sync
| Service | Função |
|---------|--------|
| StockAuditReportService | Relatórios de auditoria (DomPDF, chunks 200 rows) |
| StockAuditRandomSelectionService | Seleção aleatória para amostragem |
| StockAuditCigamService | Sync com ERP Cigam |
| StockMovementAlertService | Alertas de threshold |
| StockMovementSyncService | Sync de movimentações |
| UnifiedMovementSyncService | Pipeline unificado Sales+StockMovements |

#### Dados e Arquivos
| Service | Função |
|---------|--------|
| FormSelectRepository | Dropdowns de formulários (cache) |
| SelectCacheService | Cache de selects |
| FileUploadService | Upload com validação |
| ExportService | CSV/Excel export |
| ImportService | CSV/Excel import |
| TextExtractionService | Extração de texto (PDF/Word) |
| ProductLookupService | Busca rápida por EAN/SKU |

#### Utilitários
| Service | Função |
|---------|--------|
| Ean13Generator | Geração de códigos de barras EAN-13 |
| StatisticsService | Agregação de estatísticas |
| TrainingQRCodeService | QR codes para treinamento |
| StoreGoalsRedistributionService | Redistribuição de metas |
| BudgetService | Gestão de orçamentos |
| TravelExpenseService | Despesas de viagem |
| GoogleOAuthService | OAuth 2.0 Google |
| EvolutionBotHandlerService | Handler webhook WhatsApp |
| ChecklistServiceBusiness | Lógica de checklists |

---

## 7. State Machines

### 7.1 Order Payment (Kanban)
```
Backlog(1) ──→ Doing(2) ──→ Waiting(3) ──→ Done(4)
               ↕                             ↕
           Backlog(1) ←──────── Waiting(3) ←─┘

Transição 1→2: Requer number_nf, launch_number
Transição 2→3: Requer launch_number + campos por tipo pagamento
  - Padrão: bank_id, agency, checking_account
  - PIX(1): adms_type_key_pix_id, key_pix
  - Boleto(5): nenhum campo extra
Transição 3→4: Requer date_paid
Transições revertas: 2→1, 4→3
```

### 7.2 Order Control (Pedidos de Compra)
```
Pending(1) ──→ Invoiced(2) ──→ Delivered(5)
    ├──→ Partial(3) ──→ Invoiced(2)
    └──→ Cancelled(4) ↔ Pending(1) [reopen]

Permissões:
  1→2,3: Level ≤ 5 (Gerente+)
  1→4, 2→4, 3→4: Level ≤ 2 (Admin+)
  4→1 (reopen): Level ≤ 2
```

### 7.3 Stock Audit (Multi-fase)
```
Draft(1) → AwaitingAuth(2) → Counting(3) → Reconciliation(4) → Finished(5)
  ↓            ↓                ↓              ↓
  └────────────┴────────────────┴──────────────→ Cancelled(6)

Permissões:
  →AwaitingAuth: Levels 1,2,3
  →Counting: Levels 1,2
  →Reconciliation: Levels 1,2,3
  →Finished: Levels 1,2,3
  →Cancelled: Levels 1,2
```

### 7.4 Vacations (CLT)
```
Rascunho(1) → Pendente Gestor(2) → Aprovada Gestor(3) → Aprovada RH(4) → Em Gozo(5) → Finalizada(6)
                ↓                     ↓
           Rejeitada Gestor(8)   Rejeitada RH(9)
     Qualquer status ativável → Cancelada(7)

Permissões:
  Submit: qualquer usuário
  Approve/reject gestor: level ≤ 5
  Approve/reject RH: level ≤ 2
  Start/finish gozo: level ≤ 2
  Cancel: depende do status atual

Side effects:
  2→: notifica gerentes
  3→: notifica RH + solicitante
  4→: notifica solicitante + gerentes
  5→: atualiza days_taken
  7 (de gozo)→: reverte days_taken
```

### 7.5 Adjustment (Ajuste de Estoque)
```
Pendente(1) → Ajustado(2)
            → Sem Ajuste(3)
            → Cancelado(4)
            → Em Análise(5) → Ajustado(2) | Transferência Saldo(6) | Aguardando Resposta(7)
```

---

## 8. API REST

### 8.1 Framework
- **Router**: ApiRouter.php com regex pattern matching
- **Base**: BaseApiController.php
- **Auth**: JWT Bearer (access 1h + refresh 7d)
- **Rate Limit**: 60 req/60s por IP+endpoint (DB-based)
- **CORS**: Configurável via API_CORS_ORIGINS
- **Response**: `{success, data, error, [meta]}` padronizado
- **Paginação**: `?page=1&per_page=20` (max 100)

### 8.2 Endpoints

| Controller | Método | Rota | Auth |
|-----------|--------|------|------|
| **AuthController** | POST | /v1/auth/login | - |
| | POST | /v1/auth/refresh | - |
| **SalesController** | GET | /v1/sales | JWT |
| | GET | /v1/sales/{id} | JWT |
| | GET | /v1/sales/statistics | JWT |
| | GET | /v1/sales/by-consultant | JWT |
| **EmployeesController** | GET | /v1/employees | JWT |
| | GET | /v1/employees/{id} | JWT |
| | GET | /v1/employees/statistics | JWT |
| **OrderPaymentsController** | GET | /v1/order-payments | JWT |
| | GET | /v1/order-payments/{id} | JWT |
| | POST | /v1/order-payments | JWT |
| | PUT | /v1/order-payments/{id} | JWT |
| **TransfersController** | GET | /v1/transfers | JWT |
| | GET | /v1/transfers/{id} | JWT |
| | POST | /v1/transfers | JWT |
| | PUT | /v1/transfers/{id}/status | JWT |
| **TicketsController** | CRUD | /v1/tickets/* | JWT |
| **AdjustmentsController** | CRUD | /v1/adjustments/* | JWT |
| **InteractionsController** | GET/POST | /v1/interactions/* | JWT |
| **PersonnelRequestsController** | CRUD | /v1/personnel-requests/* | API Key/JWT |
| **DpChatController** | CRUD | /v1/dp-chat/* | API Key |
| **EvolutionBotController** | POST | /v1/evolution-bot/webhook | - |

---

## 9. WebSocket e Real-time

### 9.1 Arquitetura Dual-Port
```
Browser ──WSS──→ Port 8080 (Ratchet) ←──HTTP──→ Port 8081 (ReactPHP internal)
                                                      ↑
                                              PHP Controllers (curl)
```

### 9.2 Eventos WebSocket
| Evento | Direção | Descrição |
|--------|---------|-----------|
| typing.start/stop | Client→Server→Clients | Indicador de digitação |
| monitoring.subscribe | Client→Server | Admin se inscreve em monitoramento |
| user.idle/active | Client→Server→Monitors | Status de atividade |
| notification.new | Server→Client | Nova notificação (chat, sistema) |
| message.new | Server→Client | Nova mensagem de chat |
| record.locked/unlocked | Server→Client | Lock de registro |

### 9.3 Auth WebSocket
- JWT curto (5min TTL) gerado por WebSocketTokenService
- Passado como query param na conexão WS
- Payload: `{user_id, user_name, iat, exp, iss: "mercury-ws"}`

### 9.4 IPC (Internal Communication)
- WebSocketNotifier faz POST curl para localhost:8081
- Header `X-Internal-Key` para autenticação
- Timeout: 2s connect + 1s response
- Fire-and-forget (nunca bloqueia operação principal)

---

## 10. Frontend

### 10.1 Stack
- Bootstrap 5.3 (CSS + JS)
- jQuery 3.5.1 (slim)
- Vanilla JS ES6+ (async/await, fetch)
- Font Awesome 6.6.0
- CKEditor (rich text)
- SortableJS (drag-drop)
- Chart.js (gráficos)
- SignaturePad.js (assinaturas)
- Mask.js (CPF, telefone, moeda)

### 10.2 Padrão SPA-style (AJAX)
```
1. loadPage.php renderiza container + scripts
2. JS carrega listagem via fetch (AJAX)
3. Paginação/busca atualiza conteúdo via AJAX
4. CRUD via modais (Bootstrap 5)
5. Notificações via flash messages ou WebSocket
```

### 10.3 Convenções JS
- Container: `#content_{module_name}`
- Prefixo de funções por módulo (e.g., `sa*` para stock-audit, `ac*` para absence-control)
- Event delegation no container principal
- CSRF token em todas as requests POST
- Header `X-Requested-With: XMLHttpRequest` para detectar AJAX
- Debounce 500ms em buscas

### 10.4 Bibliotecas Externas
| Lib | Uso |
|-----|-----|
| Bootstrap 5.3 | Grid, componentes, modais |
| jQuery 3.5.1 | Seletores, compatibilidade |
| Font Awesome 6.6 | Ícones |
| CKEditor | Editor rich text (treinamento, certificados) |
| SortableJS | Drag-drop (delivery routing, kanban) |
| Chart.js | Gráficos (dashboards, auditoria) |
| SignaturePad.js | Assinaturas digitais (auditoria) |
| ViaCEP API | Busca de endereço por CEP |
| Mask.js | Máscaras de input |

---

## 11. Testes

### 11.1 Resumo
- **Framework**: PHPUnit 12.4
- **Total**: 3.899 testes passando, 364 arquivos de teste, 77 módulos
- **Bootstrap**: `tests/bootstrap.php` + `SessionContext::setTestData()` (sem session_start)

### 11.2 Cobertura por Área
| Área | Arquivos de Teste |
|------|------------------|
| Personnel/HR | 63 |
| Sales/Orders | 86 |
| Inventory/Stock | 67 |
| Support/Communication | 36 |
| Administration | 28 |
| Unit/Integration | 23 |
| Delivery/Logistics | 18 |
| Financial | 22 |
| Training | 17 |
| Auth/Security | 4 |

### 11.3 Módulos SEM testes (~58% sem cobertura dedicada)
- Vários módulos legacy
- Ecommerce (apenas 1 test)
- Muitos AbstractConfigController modules
- Budget, Policies, ProcessLibrary

---

## 12. Integrações Externas

| Integração | Tecnologia | Uso |
|-----------|-----------|-----|
| **ERP Cigam** | PostgreSQL (AdmsConnCigam/AdmsReadCigam) | Sync produtos, preços, movimentações |
| **WhatsApp** | Evolution Bot API (webhook) | Requisições de pessoal, comunicação DP |
| **Google OAuth** | OAuth 2.0 (GoogleOAuthService) | Login Google para treinamentos |
| **ViaCEP** | REST API | Busca de endereço por CEP |
| **Email SMTP** | PHPMailer | Notificações, relatórios, certificados |

---

## 13. Sistema de Permissões

### 13.1 Hierarquia de Níveis
```
Level 1:  SuperAdmin    (Acesso total, todas as lojas)
Level 2:  Admin
Level 3:  Support
Level 7:  DP (RH)
Level 9:  Financial
Level 10: Financial Restricted
Level 14: Operations
Level 18: Store          (Restrito à própria loja)
Level 22: Driver
Level 23: Candidate
```

### 13.2 Regras de Filtro
- **Store Filter**: Levels < 18 veem todas as lojas; >= 18 veem apenas sua loja
- **Financial Filter**: Levels <= 9 veem dados financeiros de todas as lojas; > 9 restritos
- **Super Admin**: Recebe notificações de TODAS as lojas (OR clause no SQL)

### 13.3 Modelo de Dados
```
adms_usuarios.adms_niveis_acesso_id → adms_niveis_acesso.id
adms_nivacs_pgs: adms_niveis_acesso_id × adms_pagina_id = permissao (1 ou 2)
adms_paginas: menu_controller, menu_metodo → resolve Controller::method
```

---

## 14. Variáveis de Ambiente

```env
# App
APP_ENV=development|production
APP_URL=http://localhost/mercury/
APP_CONTROLLER=Home
APP_METHOD=index
APP_LIMIT=20

# Permissões (constantes)
PERM_SUPER_ADMIN=1, PERM_ADMIN=2, PERM_SUPPORT=3, PERM_DP=7
PERM_FINANCIAL=9, PERM_FINANCIAL_ONE=10, PERM_OPERATION=14
PERM_STORE=18, PERM_DRIVER=22, PERM_CANDIDATE=23

# Database MySQL
DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT=3306

# Database PostgreSQL (Cigam)
CIGAM_HOST, CIGAM_USER, CIGAM_PASS, CIGAM_NAME, CIGAM_PORT

# Email
MAIL_HOST, MAIL_PORT=587, MAIL_USER, MAIL_PASS, MAIL_FROM, MAIL_FROM_NAME

# WebSocket
WEBSOCKET_ENABLED=true|false
WEBSOCKET_HOST=0.0.0.0, WEBSOCKET_PORT=8080
WEBSOCKET_INTERNAL_PORT=8081, WEBSOCKET_INTERNAL_KEY=secret
WEBSOCKET_PUBLIC_URL=ws://hostname:8080

# JWT (API)
JWT_SECRET, JWT_ACCESS_TTL=3600, JWT_REFRESH_TTL=604800
JWT_ISSUER=mercury-api, JWT_ALGORITHM=HS256

# API
API_RATE_LIMIT=60, API_RATE_WINDOW=60, API_CORS_ORIGINS=*

# Security
HASH_KEY=random-string, METHOD_ENCRYPTION=aes-256-cbc
```

---

## 15. Estratégia de Migração Laravel

### 15.1 Mapeamento de Conceitos

| Mercury v1 | Laravel v2 |
|-----------|-----------|
| ConfigController (routing) | routes/web.php + routes/api.php |
| adms_paginas (DB routes) | Route::resource() + middleware |
| ConfigView::renderizar() | Blade templates |
| AdmsRead/Create/Update/Delete | Eloquent ORM |
| AdmsPaginacao | Eloquent ->paginate() |
| CsrfService | @csrf (built-in) |
| SessionContext | Auth::user() + request()->user() |
| PermissionService | Policies + Gates |
| adms_nivacs_pgs | Spatie Permission ou custom |
| AbstractConfigController | Resource Controllers |
| Models/helper/traits/ | Eloquent Traits/Scopes |
| LoggerService | Activity Log (spatie) ou custom |
| Services/ | App\Services\ (mesmo padrão) |
| State Machines | spatie/laravel-model-states ou custom |
| WebSocket (Ratchet) | Laravel Reverb ou Pusher |
| REST API (custom) | Laravel API Resources + Sanctum |
| PHPMailer | Laravel Mail + Notifications |
| DomPDF | barryvdh/laravel-dompdf |
| PhpSpreadsheet | Maatwebsite/Laravel-Excel |
| Views SPA-style | Livewire ou Inertia.js + Vue/React |
| JS fetch (vanilla) | Livewire wire:click ou Axios |
| CKEditor | Trix (com Livewire) ou CKEditor |
| migrations (custom SQL) | Laravel Migrations (Artisan) |
| tests/bootstrap.php | PHPUnit + RefreshDatabase |
| .env (custom EnvLoader) | Laravel .env (built-in) |

### 15.2 Estrutura Laravel Proposta

```
mercury-v2/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── HR/                    # RH e Pessoal
│   │   │   │   ├── EmployeeController.php
│   │   │   │   ├── AbsenceController.php
│   │   │   │   ├── VacationController.php
│   │   │   │   ├── PersonnelMovementController.php
│   │   │   │   ├── WorkScheduleController.php
│   │   │   │   └── ...
│   │   │   ├── Financial/             # Financeiro
│   │   │   │   ├── OrderPaymentController.php
│   │   │   │   ├── SaleController.php
│   │   │   │   ├── ReversalController.php
│   │   │   │   ├── CouponController.php
│   │   │   │   └── ...
│   │   │   ├── Inventory/             # Estoque
│   │   │   │   ├── StockAuditController.php
│   │   │   │   ├── TransferController.php
│   │   │   │   ├── AdjustmentController.php
│   │   │   │   ├── ConsignmentController.php
│   │   │   │   └── ...
│   │   │   ├── Product/               # Produtos
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── PromotionController.php
│   │   │   │   └── ...
│   │   │   ├── Logistics/             # Logística
│   │   │   │   ├── DeliveryController.php
│   │   │   │   ├── DriverController.php
│   │   │   │   ├── RelocationController.php
│   │   │   │   └── ...
│   │   │   ├── Communication/         # Comunicação
│   │   │   │   ├── ChatController.php
│   │   │   │   ├── HelpdeskController.php
│   │   │   │   ├── NotificationController.php
│   │   │   │   └── ...
│   │   │   ├── Training/              # Treinamento
│   │   │   │   ├── TrainingController.php
│   │   │   │   ├── CertificateController.php
│   │   │   │   └── ...
│   │   │   ├── Admin/                 # Administração
│   │   │   │   ├── UserController.php
│   │   │   │   ├── AccessLevelController.php
│   │   │   │   ├── StoreController.php
│   │   │   │   └── ...
│   │   │   ├── Config/                # Configurações (lookup tables)
│   │   │   │   ├── BankController.php
│   │   │   │   ├── AreaController.php
│   │   │   │   ├── CostCenterController.php
│   │   │   │   └── ... (todas AbstractConfigController → Resource)
│   │   │   ├── Report/                # Relatórios
│   │   │   │   ├── SalesReportController.php
│   │   │   │   ├── EmployeeReportController.php
│   │   │   │   └── ...
│   │   │   ├── Dashboard/             # Dashboards
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── HRDashboardController.php
│   │   │   │   └── ...
│   │   │   └── Api/V1/               # API REST
│   │   │       ├── AuthController.php
│   │   │       ├── SaleController.php
│   │   │       └── ...
│   │   │
│   │   ├── Middleware/
│   │   │   ├── ForcePasswordChange.php
│   │   │   ├── TrackPageVisit.php
│   │   │   ├── CheckRecordLock.php
│   │   │   └── ...
│   │   │
│   │   └── Requests/                  # Form Requests (validação)
│   │       ├── HR/
│   │       ├── Financial/
│   │       └── ...
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Employee.php
│   │   ├── Store.php
│   │   ├── AccessLevel.php
│   │   ├── OrderPayment.php
│   │   ├── StockAudit.php
│   │   ├── Vacation.php
│   │   ├── Transfer.php
│   │   ├── Training.php
│   │   ├── Ticket.php               # Helpdesk
│   │   ├── Conversation.php         # Chat
│   │   └── ... (1 model por tabela principal)
│   │
│   ├── Services/                     # Migração direta dos Services v1
│   │   ├── OrderPaymentTransitionService.php
│   │   ├── AuditStateMachineService.php
│   │   ├── VacationValidatorService.php
│   │   ├── VacationStatusTransitionService.php
│   │   ├── StockAuditCigamService.php
│   │   ├── UnifiedMovementSyncService.php
│   │   ├── WebSocketNotifier.php
│   │   └── ...
│   │
│   ├── States/                       # State Machines
│   │   ├── OrderPaymentState.php
│   │   ├── StockAuditState.php
│   │   ├── VacationState.php
│   │   ├── AdjustmentState.php
│   │   └── ...
│   │
│   ├── Policies/                     # Authorization
│   │   ├── EmployeePolicy.php
│   │   ├── OrderPaymentPolicy.php
│   │   ├── StockAuditPolicy.php
│   │   └── ...
│   │
│   ├── Notifications/                # Laravel Notifications
│   │   ├── VacationApproved.php
│   │   ├── TicketCreated.php
│   │   ├── TransferStatusChanged.php
│   │   └── ...
│   │
│   ├── Events/                       # Events + Listeners
│   │   ├── OrderPaymentTransitioned.php
│   │   ├── StockAuditFinalized.php
│   │   └── ...
│   │
│   ├── Jobs/                         # Background Jobs
│   │   ├── SyncProductsFromCigam.php
│   │   ├── SyncMovementsFromCigam.php
│   │   ├── GenerateAuditReport.php
│   │   ├── ImportProductPrices.php
│   │   └── ...
│   │
│   └── Exports/ + Imports/           # Excel
│       ├── EmployeeExport.php
│       ├── OrderControlImport.php
│       └── ...
│
├── database/
│   ├── migrations/                   # 130+ migrations (1 por tabela)
│   ├── seeders/                      # Status, tipos, lookups
│   └── factories/                    # Model factories para testes
│
├── resources/
│   ├── views/                        # Blade ou Livewire
│   │   ├── hr/
│   │   ├── financial/
│   │   ├── inventory/
│   │   ├── logistics/
│   │   ├── communication/
│   │   ├── training/
│   │   ├── admin/
│   │   ├── dashboard/
│   │   ├── components/              # Componentes reutilizáveis
│   │   │   ├── data-table.blade.php
│   │   │   ├── stats-card.blade.php
│   │   │   ├── modal-crud.blade.php
│   │   │   └── ...
│   │   └── layouts/
│   │
│   └── js/ (se Inertia)
│       └── Pages/
│
├── routes/
│   ├── web.php
│   ├── api.php
│   └── channels.php                 # WebSocket channels
│
└── tests/
    ├── Feature/
    ├── Unit/
    └── ...
```

### 15.3 Prioridade de Migração (Fases)

#### Fase 1 - Foundation (Semanas 1-4)
- [ ] Setup Laravel + database schema completo
- [ ] Migrar sistema de autenticação (User, AccessLevel, Permissions)
- [ ] Implementar middleware stack (CSRF, ForcePassword, PageTracking)
- [ ] Migrar SessionContext → Auth::user() + helpers
- [ ] Migrar PermissionService → Policies + Gates
- [ ] Setup Reverb (WebSocket) para substituir Ratchet
- [ ] Dashboard básico (Home)

#### Fase 2 - Core Modules (Semanas 5-10)
- [ ] Employees (CRUD + lifecycle)
- [ ] Stores (CRUD)
- [ ] Sales + sync Cigam
- [ ] StockMovements + sync
- [ ] Todos os AbstractConfigController modules (~75 → Resource controllers)

#### Fase 3 - Financial (Semanas 11-14)
- [ ] OrderPayments (Kanban + state machine)
- [ ] OrderControl (state machine + items)
- [ ] Reversals + Returns
- [ ] TravelExpenses
- [ ] CostCenters + Budgets

#### Fase 4 - HR Advanced (Semanas 15-18)
- [ ] VacationPeriods + Vacations (CLT rules)
- [ ] PersonnelMoviments + VacancyOpening
- [ ] WorkSchedule
- [ ] AbsenceControl + OvertimeControl + MedicalCertificate

#### Fase 5 - Inventory Advanced (Semanas 19-22)
- [ ] StockAudit (6 fases completas)
- [ ] Transfers + Consignments
- [ ] Adjustments + NF Preparation
- [ ] DamagedProducts (matching)
- [ ] Products + sync Cigam

#### Fase 6 - Communication (Semanas 23-26)
- [ ] Chat (WebSocket via Reverb)
- [ ] Helpdesk (tickets + SLA)
- [ ] Notifications (Laravel Notifications)
- [ ] PersonnelRequests (WhatsApp integration)

#### Fase 7 - Specialized (Semanas 27-30)
- [ ] Training + Certificates
- [ ] ExperienceTracker
- [ ] Delivery + Routing
- [ ] TurnList (LDV)
- [ ] ServiceOrder + Checklist

#### Fase 8 - Reports & Polish (Semanas 31-34)
- [ ] Dashboards (todos)
- [ ] Reports (todos)
- [ ] Exports/Imports
- [ ] API REST (migrar para Laravel API Resources + Sanctum)
- [ ] Migrar módulos legacy (portugueses)

### 15.4 Decisões Arquiteturais Recomendadas

| Decisão | Recomendação | Justificativa |
|---------|-------------|---------------|
| Frontend | **Livewire 3** | Mantém PHP-centric, substitui JS vanilla, SPA-like sem build step |
| WebSocket | **Laravel Reverb** | Substitui Ratchet, integrado com Events/Broadcasting |
| Auth API | **Laravel Sanctum** | SPA + API tokens, substitui JWT custom |
| Permissões | **Spatie Permission** | DB-driven como v1, mas com caching |
| State Machines | **Spatie Model States** | Substitui services de transição custom |
| Activity Log | **Spatie Activity Log** | Substitui LoggerService |
| Excel | **Maatwebsite/Laravel-Excel** | Substitui PhpSpreadsheet direto |
| PDF | **barryvdh/laravel-dompdf** | Mantém DomPDF com wrapper Laravel |
| File Storage | **Laravel Storage** (S3/local) | Substitui move_uploaded_file direto |
| Queue | **Laravel Queue** (Redis) | Background jobs para syncs, imports, emails |
| Cache | **Laravel Cache** (Redis) | Substitui SelectCacheService |
| Notifications | **Laravel Notifications** | Email + DB + Broadcast (WebSocket) unificado |
| Testing | **Pest PHP** ou PHPUnit | Factories + RefreshDatabase |
| DB Cigam | **Multiple DB connections** | config/database.php com conexão pgsql separada |
| Search | **Laravel Scout** | Substituir CpAdmsSearch* models |

### 15.5 Tabelas que Podem Ser Eliminadas/Simplificadas

| Tabela v1 | Ação v2 | Motivo |
|-----------|---------|--------|
| adms_paginas | Eliminar | Rotas no routes/*.php |
| adms_nivacs_pgs | Migrar → spatie permissions | Mesmo conceito, melhor implementação |
| adms_menus | Simplificar | Gerado a partir das rotas |
| api_rate_limits | Eliminar | Middleware de rate limiting do Laravel |
| api_tokens | Eliminar | Sanctum gerencia tokens |
| adms_record_locks | Manter | Lógica de negócio necessária |
| adms_page_visits | Manter | Analytics |
| adms_users_online_heartbeat | Manter | Presença via Reverb |

---

## Apêndice A - Arquivos de Referência

### Documentação Existente (docs/)
- `ANALISE_COMPLETA_PROJETO_2026_MAR.md` - Análise completa mais recente
- `PADRONIZACAO.md` - Templates de código (seção 20: AbstractConfigController)
- `GUIA_IMPLEMENTACAO_MODULOS.md` - Guia passo-a-passo
- `SESSION_SERVICE_LAYER.md` - Migração $_SESSION → SessionContext
- `ANALISE_MODULO_ORDERPAYMENTS.md` - Referência mais completa (Kanban)
- `ANALISE_MODULO_SALES.md` - Referência CRUD complexo
- `PLANO_ACAO_AUDITORIA_ESTOQUE.md` - Auditoria multi-fase
- `PLANO_ACAO_GESTAO_FERIAS.md` - Férias CLT

### Módulos de Referência para Migração
1. **OrderPayments** - Workflow mais complexo (Kanban, state machine, rateio, API, relatórios)
2. **StockAudit** - Multi-fase, assinaturas, heatmap, dashboard
3. **Vacations** - Regras CLT, approval flow
4. **Sales** - CRUD + sync Cigam + estatísticas
5. **Chat** - WebSocket real-time

---

*Documento gerado em 05/04/2026 - Mercury Project Blueprint v1.0*
*Para a equipe de desenvolvimento do Grupo Meia Sola*
