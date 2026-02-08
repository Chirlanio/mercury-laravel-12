# Checklist de Modernização de Módulos - Projeto Mercury

**Data**: 2026-02-07
**Total de Módulos**: 110
**Módulos Modernos**: ~85 (77%)
**Módulos Parciais**: ~7 (6%)
**Módulos Legados**: ~18 (17%)

---

## 📊 Legenda

### Prioridade
- 🔴 **ALTA** - Módulos críticos, uso frequente, alto impacto no negócio
- 🟡 **MÉDIA** - Módulos importantes, uso moderado
- 🟢 **BAIXA** - Módulos de suporte, uso esporádico

### Complexidade
- 🟦 **SIMPLES** - CRUD básico (~20h)
- 🟨 **MÉDIO** - CRUD + lógica de negócio (~41h)
- 🟥 **COMPLEXO** - Processos, integrações, cálculos (~86h)

### Status
- ✅ **MODERNO** - Usa NotificationService, LoggerService, padrões atuais
- ⚠️ **PARCIAL** - Parcialmente modernizado
- ❌ **LEGADO** - Código antigo, precisa modernização

---

## 🎯 FASE 1: MÓDULOS CRÍTICOS (3 meses - Q1 2025)

### Prioridade ALTA - Impacto Imediato

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Responsável | Data Início | Data Fim | Status Execução |
|---|--------|-----------|--------------|--------|---------|-------------|-------------|----------|-----------------|
| 1 | **Sales** (Vendas) | 🔴 ALTA | 🟥 COMPLEXO | ✅ MODERNO | 0h | ✅ Claude | 20/01/2026 | 21/01/2026 | [x] DONE |
| 2 | **Products** (Produtos) | 🔴 ALTA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 3 | **Employees** (Funcionários) | 🔴 ALTA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 4 | **Adjustments** (Ajustes) | 🔴 ALTA | 🟥 COMPLEXO | ❌ LEGADO | 86h | - | - | - | [ ] TODO |
| 5 | **Stores** (Lojas) | 🔴 ALTA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 6 | **InventoryBalance** (Balanço) | 🔴 ALTA | 🟥 COMPLEXO | ❌ LEGADO | 86h | - | - | - | [ ] TODO |
| 7 | **OrderControl** (Controle de Pedidos) | 🔴 ALTA | 🟥 COMPLEXO | ❌ LEGADO | 86h | - | - | - | [ ] TODO |
| 8 | **Coupons** (Cupons) | 🔴 ALTA | 🟨 MÉDIO | ✅ MODERNO | 0h | ✅ Concluído | - | - | [x] DONE |
| 9 | **Suppliers** (Fornecedores) | 🔴 ALTA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 10 | **CheckLists** | 🔴 ALTA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |

**Total Fase 1**: 549h (~3 meses com 2 devs)

---

## 📈 FASE 2: MÓDULOS SECUNDÁRIOS (6 meses - Q2-Q3 2025)

### Prioridade MÉDIA - Uso Frequente

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Responsável | Data Início | Data Fim | Status Execução |
|---|--------|-----------|--------------|--------|---------|-------------|-------------|----------|-----------------|
| 11 | **Transfers** (Transferências) | 🟡 MÉDIA | 🟥 COMPLEXO | ⚠️ PARCIAL | 43h | - | - | - | [ ] TODO |
| 12 | **Returns** (Devoluções) | 🟡 MÉDIA | 🟥 COMPLEXO | ⚠️ PARCIAL | 43h | - | - | - | [ ] TODO |
| 13 | **Delivery** (Entregas) | 🟡 MÉDIA | 🟥 COMPLEXO | ⚠️ PARCIAL | 43h | - | - | - | [ ] TODO |
| 14 | **Users** (Usuários) | 🟡 MÉDIA | 🟨 MÉDIO | ⚠️ PARCIAL | 21h | - | - | - | [ ] TODO |
| 15 | **EcommerceOrders** (Pedidos E-commerce) | 🟡 MÉDIA | 🟥 COMPLEXO | ⚠️ PARCIAL | 43h | - | - | - | [ ] TODO |
| 16 | **Relocation** (Remanejamentos) | 🟡 MÉDIA | 🟥 COMPLEXO | ⚠️ PARCIAL | 43h | - | - | - | [ ] TODO |
| 17 | **PersonnelMovements** (Movimentações) | 🟡 MÉDIA | 🟥 COMPLEXO | ❌ LEGADO | 86h | - | - | - | [ ] TODO |
| 18 | **TravelExpenses** (Despesas de Viagem) | 🟡 MÉDIA | 🟥 COMPLEXO | ❌ LEGADO | 86h | - | - | - | [ ] TODO |
| 19 | **MedicalCertificate** (Atestados) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 20 | **AbsenceControl** (Controle de Faltas) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 21 | **OvertimeControl** (Controle de Horas) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 22 | **VacancyOpening** (Vagas) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 23 | **JobCandidates** (Candidatos) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 24 | **Referral** (Encaminhamentos) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 25 | **Policies** (Políticas) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 26 | **ProcessLibrary** (Biblioteca de Processos) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 27 | **MaterialRequest** (Requisição de Material) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 28 | **MaterialMarketing** (Material de Marketing) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 29 | **SupplyCheckList** (Checklist de Suprimentos) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 30 | **InternalTransferSystem** (Sistema de Transferência Interna) | 🟡 MÉDIA | 🟥 COMPLEXO | ❌ LEGADO | 86h | - | - | - | [ ] TODO |
| 31 | **Consignments** (Consignações) | 🟡 MÉDIA | 🟥 COMPLEXO | ❌ LEGADO | 86h | - | - | - | [ ] TODO |
| 32 | **OrderPayments** (Pagamentos de Pedidos) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 33 | **FixedAssets** (Ativos Fixos) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 34 | **CountFixedAssets** (Contagem de Ativos) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 35 | **Contracts** (Contratos) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 36 | **Drivers** (Motoristas) | 🟡 MÉDIA | 🟦 SIMPLES | ❌ LEGADO | 20h | - | - | - | [ ] TODO |
| 37 | **DeliveryRoutes** (Rotas de Entrega) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 38 | **StoreGoals** (Metas de Loja) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 39 | **Accountability** (Prestação de Contas) | 🟡 MÉDIA | 🟨 MÉDIO | ❌ LEGADO | 41h | - | - | - | [ ] TODO |
| 40 | **CostCenters** (Centros de Custo) | 🟡 MÉDIA | 🟦 SIMPLES | ❌ LEGADO | 20h | - | - | - | [ ] TODO |

**Total Fase 2**: 1.445h (~6 meses com 2 devs)

---

## 🔧 FASE 3: MÓDULOS DE SUPORTE (9 meses - Q4 2025 + Q1-Q2 2026)

### Prioridade BAIXA - Manutenção e Suporte

#### Cadastros Básicos (SIMPLES - 20h cada)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 41 | **Brands** (Marcas) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 42 | **Categories** (Categorias) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 43 | **Colors** (Cores) | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AbstractConfigController) |
| 44 | **Banks** (Bancos) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 45 | **Neighborhoods** (Bairros) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 46 | **Flags** (Bandeiras) | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AbstractConfigController) |
| 47 | **Positions** (Cargos) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 48 | **Cfop** | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AbstractConfigController) |
| 49 | **Cycles** (Ciclos) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 50 | **DefectLocations** (Locais de Defeito) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 51 | **Defects** (Defeitos) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 52 | **ReversalReason** (Motivos de Estorno) | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AJAX/modal pattern) |
| 53 | **Routes** (Rotas) | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AbstractConfigController) |
| 54 | **TypePayments** (Tipos de Pagamento) | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AbstractConfigController) |
| 55 | **ProductTypes** (Tipos de Produto) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |

#### Status e Situações (SIMPLES - 20h cada)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 56 | **StatusGeneral** (Status Geral) | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AbstractConfigController) |
| 57 | **StatusAdjustment** (Status Ajuste) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 58 | **StatusBalance** (Status Balanço) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 59 | **StatusDelivery** (Status Entrega) | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AbstractConfigController) |
| 60 | **StatusPayment** (Status Pagamento) | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AbstractConfigController) |
| 61 | **StatusTransfer** (Status Transferência) | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AbstractConfigController) |
| 62 | **StatusReturn** (Status Troca) | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AbstractConfigController) |
| 63 | **StatusUser** (Status Usuário) | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AbstractConfigController) |
| 64 | **StatusOrderPayment** (Status Pagamento Pedido) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 65 | **CouponStatus** (Status Cupom) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |

#### Configurações e Administração (SIMPLES/MÉDIO)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 66 | **Menu** | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 67 | **Pages** (Páginas) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 68 | **AccessLevels** (Níveis de Acesso) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 69 | **PermissionGroups** (Grupos de Permissão) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 70 | **Permissions** (Permissões) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 71 | **EmailConfig** (Configuração de Email) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 72 | **Dashboard** | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 73 | **ActivityLog** (Log de Atividades) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 74 | **UsersOnline** (Usuários Online) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 75 | **UserStatistics** (Estatísticas de Usuário) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |

#### Módulos de Conteúdo e Arquivos (SIMPLES/MÉDIO)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 76 | **Files** (Arquivos) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 77 | **Videos** | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 78 | **Faq** | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 79 | **PolicieBlog** (Blog de Políticas) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |

#### Módulos Especializados (MÉDIO/COMPLEXO)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 80 | **Estorno** (Estornos) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 81 | **BalanceProducts** (Produtos do Balanço) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 82 | **OrderService** (Ordem de Serviço) | 🟢 BAIXA | 🟥 COMPLEXO | ❌ LEGADO | 86h | [ ] TODO |
| 83 | **Details** (Detalhes) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 84 | **ResponsibleAudit** (Responsável Auditoria) | 🟢 BAIXA | 🟦 SIMPLES | ✅ MODERNO | 0h | [x] DONE (AbstractConfigController) |
| 85 | **Areas** (Áreas) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |

#### Integrações e Sincronização (COMPLEXO)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 86 | **SynchronizeSales** (Sincronizar Vendas) | 🟢 BAIXA | 🟥 COMPLEXO | ✅ MODERNO | 0h | [x] DONE (parte do Sales) |
| 87 | **GenteGestao** (Integração RH) | 🟢 BAIXA | 🟥 COMPLEXO | ❌ LEGADO | 86h | [ ] TODO |
| 88 | **PrateleiraInfinita** (Integração E-commerce) | 🟢 BAIXA | 🟥 COMPLEXO | ❌ LEGADO | 86h | [ ] TODO |

#### Módulos de Treinamento (MÉDIO)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 89 | **TrainingUsers** (Usuários Treinamento) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 90 | **TrainingProfile** (Perfil Treinamento) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 91 | **TrainingLogin** (Login Treinamento) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 92 | **TrainingHome** (Home Treinamento) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |

#### Módulos Auxiliares e Utilitários (SIMPLES)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 93 | **ChangePassword** (Alterar Senha) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 94 | **ForgotPassword** (Esqueceu Senha) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 95 | **UpdatePassword** (Atualizar Senha) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 96 | **Login** | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 97 | **Home** | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 98 | **ViewProfile** (Ver Perfil) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 99 | **EditProfile** (Editar Perfil) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |

#### Módulos Legados para Revisão (SIMPLES/MÉDIO)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 100 | **FindProduct** (Buscar Produto) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 101 | **PriceProduct** (Preço Produto) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 102 | **AccountingAccount** (Conta Contábil) | 🟢 BAIXA | 🟨 MÉDIO | ❌ LEGADO | 41h | [ ] TODO |
| 103 | **CreateRoute** (Criar Rota) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 104 | **PrintDelivery** (Imprimir Entrega) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 105 | **ExportReturns** (Exportar Trocas) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |

#### Funcionalidades de Ordem e Organização (SIMPLES)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 106 | **ChangeOrderMenu** (Alterar Ordem Menu) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 107 | **ChangeOrderMenuItem** (Alterar Ordem Item Menu) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 108 | **ChangeOrderGroupPg** (Alterar Ordem Grupo Pg) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 109 | **ChangeOrderAccessLevel** (Alterar Ordem Nível Acesso) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 110 | **ChangeOrderPaymentType** (Alterar Ordem Tipo Pg) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |

#### Funcionalidades de Depuração e Debug (SIMPLES)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 111 | **DebugMenu** | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 112 | **DebugMenuDetailed** | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 113 | **DebugViewCoupon** | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 114 | **ClearMenuCache** | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 115 | **ForceRebuildMenu** | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |

#### Bibliotecas e Helpers de Sistema (SIMPLES)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 116 | **LibDropdown** | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 117 | **LibMenu** | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 118 | **LibPermissions** | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 119 | **LibResp** | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |

#### Autorização e Confirmação (SIMPLES)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 120 | **Authorize** (Autorizar) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 121 | **AuthorizeResp** (Autorização Responsável) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 122 | **Confirm** (Confirmar) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 123 | **ConfirmTransfer** (Confirmar Transferência) | 🟢 BAIXA | 🟦 SIMPLES | ⚠️ PARCIAL | 10h | [ ] TODO |

#### Sincronização e Permissões (SIMPLES)

| # | Módulo | Prioridade | Complexidade | Status | Esforço | Status Execução |
|---|--------|-----------|--------------|--------|---------|-----------------|
| 124 | **SyncPageAccessLevel** (Sincronizar Página Nível Acesso) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |
| 125 | **SendFiles** (Enviar Arquivos) | 🟢 BAIXA | 🟦 SIMPLES | ❌ LEGADO | 20h | [ ] TODO |

**Total Fase 3**: 2.126h (~9 meses com 1-2 devs em paralelo)

---

## 📊 Resumo Geral

> **Nota (2026-02-07):** Este checklist foi originalmente criado para planejamento de modernização.
> Os contadores abaixo refletem apenas os módulos listados neste documento (125 entradas de planejamento).
> O projeto real possui **110 módulos** (por contagem de diretórios de Views), dos quais **~85 (77%) são modernos**.
> A diferença se deve a módulos auxiliares listados aqui que não são módulos independentes no código.

### Por Fase

| Fase | Módulos | Esforço Total | Prazo (2 devs) | Custo Estimado |
|------|---------|---------------|----------------|----------------|
| **Fase 1** (Críticos) | 10 | 549h | 3 meses | R$ 123.900 |
| **Fase 2** (Secundários) | 30 | 1.445h | 6 meses | R$ 133.000 |
| **Fase 3** (Suporte) | 85 | 2.126h | 9 meses | R$ 175.700 |
| **TOTAL** | **125** | **4.120h** | **18 meses** | **R$ 432.600** |

### Por Prioridade

| Prioridade | Quantidade | Percentual | Esforço Total |
|-----------|-----------|------------|---------------|
| 🔴 **ALTA** | 10 | 8% | 549h |
| 🟡 **MÉDIA** | 30 | 24% | 1.445h |
| 🟢 **BAIXA** | 85 | 68% | 2.126h |
| **TOTAL** | **125** | **100%** | **4.120h** |

### Por Complexidade

| Complexidade | Quantidade | Percentual | Média de Horas |
|-------------|-----------|------------|----------------|
| 🟦 **SIMPLES** | 65 | 52% | 20h |
| 🟨 **MÉDIO** | 45 | 36% | 41h |
| 🟥 **COMPLEXO** | 15 | 12% | 86h |
| **TOTAL** | **125** | **100%** | - |

### Por Status Atual (neste checklist)

| Status | Quantidade | Percentual |
|--------|-----------|------------|
| ✅ **MODERNO** | 17 | 13.6% |
| ⚠️ **PARCIAL** | 6 | 4.8% |
| ❌ **LEGADO** | 102 | 81.6% |
| **TOTAL** | **125** | **100%** |

### Status Real do Projeto (por contagem de módulos)

| Status | Quantidade | Percentual |
|--------|-----------|------------|
| ✅ **MODERNO** | ~85 | 77% |
| ⚠️ **PARCIAL** | ~7 | 6% |
| ❌ **LEGADO** | ~18 | 17% |
| **TOTAL** | **110** | **100%** |

---

## 🎯 Quick Wins Recomendados (Primeiros 3 módulos)

Começar por módulos simples para validar padrões e treinar a equipe:

### Semana 1-2
1. ✅ **Brands** (Marcas) - 20h
   - [ ] Controller de Listagem
   - [ ] Controller de Adição
   - [ ] Controller de Edição
   - [ ] Controller de Exclusão
   - [ ] Controller de Visualização
   - [ ] Models
   - [ ] Views + Modais
   - [ ] JavaScript
   - [ ] Testes

### Semana 3-4
2. ✅ **Banks** (Bancos) - 20h
   - [ ] Implementação completa seguindo padrões

### Semana 5-6
3. ✅ **Colors** (Cores) - ~~20h~~ 0h
   - [x] Migrado para AbstractConfigController (Fev/2026)

**Total**: 60h (1.5 meses com 1 dev) | R$ 5.520

**Benefícios**:
- ✅ Validar padrões documentados
- ✅ Treinar equipe em módulos simples
- ✅ Criar templates reais para referência
- ✅ Identificar gaps na documentação
- ✅ Ajustar ferramentas (linter, generators)

---

## 📈 Métricas de Progresso

### Indicadores Principais

```
Progresso Geral (checklist): [█░░░░░░░░░] 13.6% (17/125 módulos listados)
Progresso Real (projeto):    [███████░░░] 77% (~85/110 módulos)

Por Fase:
├─ Fase 1 (Críticos):     [██░░░░░░░░] 20% (2/10 módulos) - Coupons ✅, Sales ✅
├─ Fase 2 (Secundários):  [░░░░░░░░░░]  0% (0/30 módulos)
└─ Fase 3 (Suporte):      [██░░░░░░░░] 18% (15/85 módulos) - SynchronizeSales ✅, 13 config modules ✅, ReversalReason ✅

Módulos Config Migrados (Fev/2026 - AbstractConfigController):
├─ Cor, Bandeira, Situacao, Cfop, TipoPagamento, TipoPg
├─ SituacaoPg, Rota, SituacaoTransf, SituacaoTroca
├─ SituacaoUser, SituacaoDelivery, ResponsavelAuditoria
└─ ReversalReason (MotivoEstorno) - AJAX/modal pattern
```

### Meta 2025

| Trimestre | Meta de Módulos | Meta de Progresso |
|-----------|----------------|-------------------|
| Q1 2025 | 10 módulos | 8% |
| Q2 2025 | +15 módulos | 20% |
| Q3 2025 | +20 módulos | 36% |
| Q4 2025 | +30 módulos | 60% |

---

## 🔄 Processo de Modernização

### Checklist por Módulo

Para cada módulo, seguir os passos:

#### 1️⃣ Planejamento (2h)
- [ ] Analisar módulo atual
- [ ] Identificar dependências
- [ ] Mapear regras de negócio
- [ ] Definir escopo de alterações
- [ ] Criar branch: `feature/modernize-[module-name]`

#### 2️⃣ Desenvolvimento Backend (60-70% do tempo)
- [ ] **Controller de Listagem**
  - [ ] Implementar `match()` para roteamento
  - [ ] Usar `FormSelectRepository` para selects
  - [ ] Implementar paginação AJAX
  - [ ] Adicionar estatísticas
- [ ] **Controller de Adição**
  - [ ] Injetar `NotificationService`
  - [ ] Adicionar `LoggerService::info()`
  - [ ] Implementar validações
  - [ ] Resposta JSON padronizada
- [ ] **Controller de Edição**
  - [ ] Carregar dados via AJAX
  - [ ] Logging de alterações
  - [ ] Auditoria (user_updated_id, updated_at)
- [ ] **Controller de Exclusão**
  - [ ] Confirmação obrigatória
  - [ ] Logging de exclusão
  - [ ] Verificar dependências
- [ ] **Controller de Visualização**
  - [ ] Modal AJAX
  - [ ] Dados completos com JOINs
- [ ] **Models**
  - [ ] Usar UUID (Ramsey\Uuid)
  - [ ] Helpers de banco (AdmsRead, AdmsCreate, etc)
  - [ ] Validações com AdmsCampoVazio
  - [ ] Auditoria completa

#### 3️⃣ Desenvolvimento Frontend (20-30% do tempo)
- [ ] **View Principal**
  - [ ] Header com ícone e título
  - [ ] Card de estatísticas
  - [ ] Formulário de busca
  - [ ] Área de conteúdo AJAX
- [ ] **Modais**
  - [ ] Modal de cadastro (Bootstrap 4.6)
  - [ ] Modal de edição
  - [ ] Modal de visualização
- [ ] **JavaScript**
  - [ ] Usar async/await
  - [ ] Fetch API (não jQuery.ajax)
  - [ ] Debounce em inputs de busca
  - [ ] Loading states
  - [ ] Tratamento de erros

#### 4️⃣ Testes (10% do tempo)
- [ ] Testes unitários (PHPUnit)
- [ ] Testes de integração
- [ ] QA manual
- [ ] Testes de performance

#### 5️⃣ Code Review e Deploy
- [ ] Code review com checklist de padrões
- [ ] Ajustes conforme feedback
- [ ] Merge para develop
- [ ] Deploy em staging
- [ ] Validação em produção
- [ ] Merge para main

---

## 🛠️ Ferramentas de Apoio

### 1. Gerador de Código CLI

```bash
# Gerar módulo completo
php artisan make:module Product --type=simple

# Gerar apenas controller
php artisan make:controller Products --list

# Gerar apenas model
php artisan make:model AdmsAddProduct
```

### 2. Checklist Automatizado

```bash
# Verificar se módulo segue padrões
php artisan check:standards app/adms/Controllers/Products.php

# Gerar relatório de conformidade
php artisan report:modernization
```

### 3. Scripts de Validação

```bash
# Verificar uso de NotificationService
grep -r "NotificationService" app/adms/Controllers/

# Verificar uso de LoggerService
grep -r "LoggerService" app/adms/Controllers/

# Listar módulos legados
php artisan list:legacy-modules
```

---

## 📞 Contatos e Suporte

**Product Owner**: [Nome]
**Tech Lead**: [Nome]
**Squad Modernização**: [Nomes]

**Documentação**:
- CLAUDE.md - Arquitetura e padrões
- PADRONIZACAO.md - Templates e código
- ESFORCO_ATUALIZACAO.md - Análise de esforço

**Repositório**: https://github.com/Chirlanio/mercury

---

**Última Atualização**: 2026-02-07
**Versão**: 1.2
**Próxima Revisão**: Q2 2026 (final de junho)

---

## 📝 Histórico de Atualizações

| Data | Versão | Alterações |
|------|--------|------------|
| 2026-02-07 | 1.2 | 13 módulos config migrados para AbstractConfigController (Cor, Bandeira, Situacao, Cfop, TipoPagamento, TipoPg, SituacaoPg, Rota, SituacaoTransf, SituacaoTroca, SituacaoUser, SituacaoDelivery, ResponsavelAuditoria). ReversalReason (MotivoEstorno) migrado para padrão AJAX/modal. Contadores e métricas atualizados para refletir 77% de modernização real. |
| 2026-01-21 | 1.1 | Sales marcado como MODERNO (refatoração completa com AJAX, testes, NotificationService, LoggerService) |
| 2025-01-12 | 1.0 | Versão inicial do checklist |
