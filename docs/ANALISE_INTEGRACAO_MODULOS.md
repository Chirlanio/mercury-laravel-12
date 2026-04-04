# Análise de Integração entre Módulos - Mercury

**Data:** 03 de Abril de 2026
**Ultima Atualizacao:** 03 de Abril de 2026
**Versão:** 1.1

---

## Sumário Executivo

Esta análise mapeia todos os módulos do Mercury e identifica **oportunidades de integração** onde uma ação em um módulo deveria gerar efeitos em outros módulos. Foram identificadas **42 integrações potenciais** organizadas em 3 categorias: RH/Pessoas, Operações/Estoque e Atendimento/Pós-venda.

### Panorama Atual (Atualizado Abril/2026)
- **Integrações já implementadas:** 13 (8 originais + 5 concluídas em Abril/2026)
- **Integrações parcialmente implementadas:** 2
- **Integrações ausentes (oportunidades):** 27
- **Prioridade Alta:** 10
- **Prioridade Média:** 17
- **Prioridade Baixa:** 13
- **Testes:** 150 testes (414 assertions) cobrindo services e integrações

---

## 1. Mapa de Módulos

### 1.1 Cluster RH/Pessoas
| Módulo | Ações Principais | Status Transitions |
|--------|-----------------|-------------------|
| **Employees** | CRUD, soft-delete 3 níveis | Pendente → Ativo → Inativo/Férias/Afastado |
| **PersonnelMoviments** | CRUD desligamento | Pendente → Em Andamento → Concluído |
| **VacancyOpening** | CRUD vagas | Aberta → Processando → Em Admissão → Finalizada/Cancelada |
| **Vacations** | CRUD férias, aprovação | Rascunho → Pendente → Aprovada → Em Gozo → Finalizada |
| **VacationPeriods** | CRUD períodos aquisitivos | Geração automática por admissão |
| **ExperienceTracker** | Avaliações 45/90 dias (cron) | Criada → Preenchida → Expirada |
| **WorkSchedule** | CRUD escalas, overrides | Atribuição por funcionário |
| **MedicalCertificate** | CRUD atestados | Upload com período de vigência |
| **AbsenceControl** | CRUD faltas | Registro por data |
| **OvertimeControl** | CRUD horas extras | Registro por período |
| **Training** | CRUD treinamentos, presença QR | Agendado → Em Andamento → Concluído |

### 1.2 Cluster Operações/Estoque
| Módulo | Ações Principais | Status Transitions |
|--------|-----------------|-------------------|
| **Sales** | CRUD vendas, sync Cigam | Registro histórico (sem status) |
| **StoreGoals** | CRUD metas, import Excel | Metas mensais por loja |
| **StockMovements** | Sync Cigam, alertas | Registro imutável por tipo |
| **StockAudit** | Contagem, conciliação, justificativas | Criada → Contagem → Conciliação → Finalizada |
| **Transfers** | CRUD transferências estoque | Pendente → Em Trânsito → Recebida/Cancelada |
| **Adjustments** | CRUD ajustes estoque | Pendente → Aprovado → Concluído/Rejeitado |
| **OrderControl** | CRUD pedidos compra | Pendente → Faturado → Entregue/Cancelado |
| **Consignments** | CRUD consignações | Criada → Ativa → Liquidada/Cancelada |
| **ProductPromotions** | CRUD promoções | Agendada → Ativa → Expirada/Cancelada |
| **Returns** | CRUD devoluções | Registro com motivo |
| **Reversals** | CRUD estornos | Aguardando → Autorizado → Estornado/Cancelado |

### 1.3 Cluster Atendimento/Logística
| Módulo | Ações Principais | Status Transitions |
|--------|-----------------|-------------------|
| **Delivery** | CRUD entregas, roteirização | Criada → Roteada → Entregue/Cancelada |
| **Helpdesk** | CRUD tickets, interações | Aberto → Em Andamento → Resolvido → Fechado |
| **ServiceOrder** | CRUD OS, kanban | Aguardando → Em Andamento → Concluída → Entregue |
| **TravelExpenses** | CRUD despesas viagem | Solicitada → Aprovada → Reembolsada/Rejeitada |
| **Relocation** | CRUD realocações | Planejada → Em Andamento → Concluída |
| **InternalTransfer** | CRUD transferências internas | Pendente → Enviada → Recebida |
| **MaterialRequest** | CRUD requisições material | Solicitada → Aprovada → Atendida/Negada |

---

## 2. Integrações Existentes (Já Implementadas)

### INT-01: PersonnelMoviments → Employees (Inativação)
- **Trigger:** Criação de movimentação de desligamento
- **Efeito:** `EmployeeInactivationService::deactivate()` → Status do funcionário muda para Inativo (3)
- **Service:** `EmployeeInactivationService.php`
- **Status:** ✅ Implementado

### INT-02: PersonnelMoviments → Notificações Área (Desligamento)
- **Trigger:** Criação de movimentação de desligamento
- **Efeito:** Email para 6 áreas gestoras (DP, Marketing, Operações, TI, RH, E-commerce)
- **Service:** `DismissalNotificationService.php`
- **Status:** ✅ Implementado

### INT-03: Employees → WorkSchedule (Atribuição)
- **Trigger:** Criação de novo funcionário
- **Efeito:** `EmployeeContractService::assignWorkSchedule()` → Vincula escala ao funcionário
- **Service:** `EmployeeContractService.php`
- **Status:** ✅ Implementado

### INT-04: MedicalCertificate → StoreGoals (Redistribuição)
- **Trigger:** Cadastro de atestado médico ≥ 10 dias
- **Efeito:** `StoreGoalsRedistributionService` → Redistribui metas aos demais consultores
- **Service:** `StoreGoalsRedistributionService.php`
- **Status:** ✅ Implementado

### INT-05: Vacations → Employees (Aprovação Fluxo)
- **Trigger:** Transição de status de férias (aprovação gestor/RH)
- **Efeito:** Notificações automáticas para gestores, RH e solicitante
- **Service:** `VacationStatusTransitionService.php`
- **Status:** ✅ Implementado

### INT-06: Sales → StoreGoals (Alerta Metas)
- **Trigger:** Venda registrada/sincronizada
- **Efeito:** `StockMovementAlertService::checkGoalReached()` → Alerta quando meta diária atingida
- **Service:** `StockMovementAlertService.php`
- **Status:** ✅ Implementado

### INT-07: StockMovements → Alertas (Anomalias)
- **Trigger:** Sincronização de movimentações
- **Efeito:** Detecção de anomalias (variação > 30% da média móvel de 7 dias)
- **Service:** `StockMovementAlertService.php`
- **Status:** ✅ Implementado

### INT-08: Employees → StoreGoals (Redistribuição por Exclusão)
- **Trigger:** Exclusão de funcionário
- **Efeito:** `StoreGoalsRedistributionService` → Redistribui metas entre funcionários restantes
- **Status:** ✅ Implementado

---

## 3. Integrações Parcialmente Implementadas

### INT-09: Vacations → Employees (Status Férias)
- **Trigger:** Férias aprovadas e iniciadas (status = Em Gozo)
- **Efeito Esperado:** Employee status → 4 (Férias); ao finalizar → 2 (Ativo)
- **Status:** ⚠️ Parcial — `EmployeeStatus` tem o status 4 (Férias) mapeado e a transição é válida (2→4, 4→2), mas **não há código que execute automaticamente** a mudança ao iniciar/finalizar férias
- **Prioridade:** 🔴 Alta

### INT-10: PersonnelMoviments ↔ VacancyOpening (Vínculo Desligamento-Vaga)
- **Trigger:** Desligamento com flag "abrir vaga de substituição"
- **Efeito:** Criação automática de vaga tipo Substituição vinculada ao movimento
- **Status:** ✅ **Implementado (Abril/2026)** — `VacancyIntegrationService::createFromMoviment()`, campos `origin_moviment_id` e `generated_vacancy_id` migrados, checkbox no form, links cruzados nas views
- **Service:** `VacancyIntegrationService.php`
- **Referência:** `docs/ANALISE_INTEGRACAO_RH_MODULES.md`

### INT-11: VacancyOpening → Employees (Pré-preenchimento na Admissão)
- **Trigger:** Vaga em status Em Admissão (3) ou Finalizada (4)
- **Efeito:** Botão "Pré-cadastrar Colaborador" com dados da vaga pré-preenchidos (loja, cargo, escala, data admissão)
- **Status:** ✅ **Implementado (Abril/2026)** — `VacancyIntegrationService::prepareEmployeeData()`, endpoint `prepare-employee-data`, JS auto-fill no modal, `linkVacancyToEmployee()` pós-criação
- **Service:** `VacancyIntegrationService.php`

### INT-12: ExperienceTracker → Employees (Efetivação)
- **Trigger:** Avaliação de 90 dias concluída com aprovação
- **Efeito Esperado:** Status do funcionário muda de Pendente (1) para Ativo (2)
- **Status:** ⚠️ Parcial — Cron `AdmsCheckExperienceEvaluations` cria avaliações automáticas, mas **não executa transição de status ao concluir**. `EmployeeLifecycleService` já suporta a transição Pendente → Ativo.
- **Prioridade:** 🟡 Média

### INT-13: PersonnelMoviments → Employees (Reativação ao Cancelar)
- **Trigger:** Soft-delete de movimentação de desligamento
- **Efeito:** `EmployeeInactivationService::reactivate()` → Funcionário volta a Ativo
- **Status:** ✅ **Implementado (Abril/2026)** — `AdmsDeletePersonnelMoviments` (soft-delete) chama `EmployeeInactivationService::reactivate()` automaticamente ao excluir movimento
- **Service:** `EmployeeInactivationService.php`

---

## 4. Integrações Ausentes — Cluster RH/Pessoas

### 🔴 Prioridade Alta

#### INT-14: Vacations → WorkSchedule (Exclusão de Escala)
- **Trigger:** Férias aprovadas (status ≥ 4 Aprovada RH)
- **Efeito:** Excluir funcionário da escala de trabalho durante período de férias
- **Impacto:** Escala pode mostrar funcionários ausentes como disponíveis
- **Implementação Sugerida:** `WorkScheduleService::createVacationOverride(employeeId, startDate, endDate)`

#### INT-15: MedicalCertificate → Employees (Afastamento)
- **Trigger:** Atestado médico ≥ 15 dias cadastrado
- **Efeito:** Employee status → 5 (Afastado); ao expirar atestado → 2 (Ativo)
- **Impacto:** Funcionários com afastamento longo continuam com status "Ativo" no sistema
- **Implementação Sugerida:** Usar `EmployeeLifecycleService::executeTransition()` no `AdmsAddMedicalCertificate`

#### INT-16: MedicalCertificate → WorkSchedule (Exclusão de Escala)
- **Trigger:** Cadastro de atestado médico
- **Efeito:** Criar override na escala para o período do atestado
- **Impacto:** Escala mostra funcionário como disponível durante afastamento médico
- **Implementação Sugerida:** Reutilizar o mesmo mecanismo de INT-14

#### INT-17: PersonnelMoviments → Vacations (Cancelamento Automático)
- **Trigger:** Desligamento criado para funcionário
- **Efeito:** Cancelar todas as solicitações de férias pendentes (status 1-4)
- **Impacto:** Férias aprovadas para funcionário desligado continuam ativas no sistema
- **Implementação Sugerida:** `VacationStatusTransitionService::cancelByEmployee(employeeId, reason)`

### 🟡 Prioridade Média

#### INT-18: PersonnelMoviments → Training (Desinscrição)
- **Trigger:** Desligamento criado
- **Efeito:** Remover funcionário de treinamentos agendados/em andamento
- **Impacto:** Vagas de treinamento ocupadas por funcionários desligados
- **Implementação Sugerida:** `AdmsTrainingParticipant::removeByEmployee(employeeId)`

#### INT-19: PersonnelMoviments → OvertimeControl (Fechamento)
- **Trigger:** Desligamento criado
- **Efeito:** Finalizar/fechar registros de hora extra em aberto
- **Impacto:** Registros de HE ficam pendentes para funcionários desligados

#### INT-20: PersonnelMoviments → AbsenceControl (Arquivamento)
- **Trigger:** Desligamento criado
- **Efeito:** Marcar registros de falta como "arquivados" após data de desligamento
- **Impacto:** Registros de falta continuam ativos para funcionários desligados

#### INT-21: MedicalCertificate → AbsenceControl (Justificativa)
- **Trigger:** Cadastro de atestado médico
- **Efeito:** Vincular atestado às faltas do período, marcando-as como "justificadas"
- **Impacto:** Faltas e atestados são registros independentes sem vinculação
- **Implementação Sugerida:** Criar campo `medical_certificate_id` na tabela de faltas

#### INT-22: OvertimeControl → WorkSchedule (Validação)
- **Trigger:** Cadastro de hora extra
- **Efeito:** Validar que hora extra não excede limite da escala; calcular automaticamente com base no horário da escala
- **Impacto:** Horas extras registradas sem referência à escala do funcionário

#### INT-23: AbsenceControl → OvertimeControl (Validação Cruzada)
- **Trigger:** Cadastro de falta ou hora extra
- **Efeito:** Impedir registro de HE em dias com falta; impedir falta em dias com HE
- **Impacto:** Possível ter falta e hora extra no mesmo dia para o mesmo funcionário

#### INT-24: Training → ExperienceTracker (Conclusão)
- **Trigger:** Funcionário conclui treinamento obrigatório
- **Efeito:** Registrar como critério positivo na avaliação de experiência
- **Impacto:** Treinamentos e avaliações de experiência são desconectados

#### INT-25: VacancyOpening → Training (Matrícula Automática)
- **Trigger:** Vaga finalizada e novo funcionário admitido
- **Efeito:** Inscrever novo funcionário em treinamentos obrigatórios do cargo
- **Impacto:** Admissão por vaga não gera inscrição automática em treinamento

#### INT-26: Vacations → StoreGoals (Exclusão de Metas)
- **Trigger:** Férias aprovadas e iniciadas
- **Efeito:** Excluir funcionário do cálculo de metas durante período de férias
- **Impacto:** Meta distribuída para funcionário ausente (similar ao atestado ≥10 dias)

### ⚪ Prioridade Baixa

#### INT-27: AbsenceControl → VacationPeriods (Impacto CLT)
- **Trigger:** Acúmulo de faltas injustificadas no período aquisitivo
- **Efeito:** Recalcular dias de direito conforme Art. 130 CLT (6-14 faltas = 24d, 15-23 = 18d, etc.)
- **Impacto:** `VacationPeriodGeneratorService` já calcula, mas não recalcula dinamicamente com novas faltas

#### INT-28: ExperienceTracker → PersonnelMoviments (Alerta)
- **Trigger:** Avaliação de experiência negativa (90 dias)
- **Efeito:** Gerar alerta/sugestão para gestor sobre possível desligamento
- **Impacto:** Avaliação negativa não gera ação proativa

#### INT-29: MedicalCertificate → Vacations (Bloqueio)
- **Trigger:** Funcionário com atestado vigente
- **Efeito:** Impedir solicitação de férias durante período de afastamento médico
- **Impacto:** É possível solicitar férias enquanto está afastado por atestado

---

## 5. Integrações Ausentes — Cluster Operações/Estoque

### 🔴 Prioridade Alta

#### INT-30: OrderControl → Delivery (Geração de Entrega)
- **Trigger:** Pedido muda para status "Faturado" (2) ou "Entregue" (5)
- **Efeito:** Criar registro de entrega automaticamente quando pedido faturado; marcar entrega como concluída quando pedido entregue
- **Impacto:** Pedidos faturados sem entrega correspondente gerada
- **Implementação Sugerida:** `ProcurementWorkflowService::onOrderInvoiced(orderId)`

#### INT-31: StockAudit → Adjustments (Geração de Ajustes)
- **Trigger:** Auditoria finalizada com divergências significativas
- **Efeito:** Gerar automaticamente registros de ajuste para divergências aceitas
- **Impacto:** Divergências detectadas na auditoria requerem criação manual de ajustes
- **Implementação Sugerida:** `AuditRemediationService::generateAdjustments(auditId)`

#### INT-32: Returns → Sales (Estorno de Venda)
- **Trigger:** Devolução registrada e vinculada a venda
- **Efeito:** Atualizar registros de venda; recalcular metas do consultor; recalcular comissões
- **Impacto:** Devoluções não impactam automaticamente os registros de vendas/metas

### 🟡 Prioridade Média

#### INT-33: Reversals → OrderControl (Atualização de Pagamento)
- **Trigger:** Estorno autorizado e processado
- **Efeito:** Atualizar status de pagamento do pedido correspondente
- **Impacto:** Estornos financeiros não refletem no módulo de pedidos

#### INT-34: ProductPromotions → StockMovements (Alertas de Estoque)
- **Trigger:** Promoção ativada para produto
- **Efeito:** Gerar alerta se estoque do produto está abaixo do mínimo para período promocional
- **Impacto:** Promoções ativadas sem verificação de disponibilidade de estoque

#### INT-35: Transfers → StockMovements (Registro de Movimentação)
- **Trigger:** Transferência recebida (status 3)
- **Efeito:** Registrar movimentação de entrada no estoque da loja destino
- **Impacto:** Transferências concluídas sem registro formal de movimentação

#### INT-36: Consignments → Returns (Devolução de Consignação)
- **Trigger:** Consignação encerrada com itens não vendidos
- **Efeito:** Gerar registro de devolução ao fornecedor
- **Impacto:** Consignações encerradas sem registro formal de devolução

#### INT-37: OrderControl → Transfers (Geração de Transferência)
- **Trigger:** Pedido recebido em CD com destino a loja específica
- **Efeito:** Criar automaticamente transferência para loja de destino
- **Impacto:** Pedidos recebidos requerem criação manual de transferência

### ⚪ Prioridade Baixa

#### INT-38: Sales → ProductPromotions (Efetividade)
- **Trigger:** Venda de produto em promoção
- **Efeito:** Registrar métrica de efetividade da promoção
- **Impacto:** Sem dados de conversão de promoções

#### INT-39: StockAudit → ProductPromotions (Validação)
- **Trigger:** Início de auditoria
- **Efeito:** Alertar sobre produtos em promoção com estoque auditado divergente
- **Impacto:** Promoções de produtos com estoque incorreto

---

## 6. Integrações Ausentes — Cluster Atendimento/Logística

### 🟡 Prioridade Média

#### INT-40: Helpdesk → ServiceOrder (Escalação)
- **Trigger:** Ticket de helpdesk sobre defeito de produto
- **Efeito:** Criar OS vinculada ao ticket automaticamente
- **Impacto:** Tickets de defeito requerem criação manual de OS
- **Implementação Sugerida:** Campo `helpdesk_ticket_id` na tabela de OS

#### INT-41: ServiceOrder → Returns (Devolução por Defeito)
- **Trigger:** OS concluída com parecer "produto irreparável"
- **Efeito:** Gerar registro de devolução/troca automaticamente
- **Impacto:** Produtos irreparáveis requerem criação manual de devolução

#### INT-42: Delivery → ServiceOrder (Problema na Entrega)
- **Trigger:** Entrega com produto danificado registrada
- **Efeito:** Gerar OS para o produto danificado
- **Impacto:** Danos na entrega requerem abertura manual de OS

---

## 7. Matriz de Dependências entre Módulos

```
                    Emp  PM  Vac  Vac  Exp  WS  Med  Abs  OT  Trn  Sal  SG  SM  SA  Trf  Adj  OC  Del  Ret  Rev  HD  SO
                         Mov Open Per  Trk      Cert Ctrl Ctrl
Employees           ---  ←   ←    .    ←    ←   ←    .    .    .    .    ←   .    .    .    .    .    .    .    .    .    .
PersonnelMoviments  →    ---  →   .    .    .    .    →    →    →    .    .   .    .    .    .    .    .    .    .    .    .
VacancyOpening      →    ←   ---  .    .    .    .    .    .    →    .    .   .    .    .    .    .    .    .    .    .    .
VacationPeriods     .    .    .   ---   .    .    .    ←    .    .    .    .   .    .    .    .    .    .    .    .    .    .
ExperienceTracker   →    →    .    .   ---   .    .    .    .    ←    .    .   .    .    .    .    .    .    .    .    .    .
WorkSchedule        ←    .    .    ←    .   ---   ←    .    ←    .    .    .   .    .    .    .    .    .    .    .    .    .
MedicalCertificate  →    .    .    .    .    →   ---   →    .    .    .    →   .    .    .    .    .    .    .    .    .    .
AbsenceControl      .    .    .    →    .    .    ←   ---   ↔    .    .    .   .    .    .    .    .    .    .    .    .    .
OvertimeControl     .    .    .    .    .    ←    .    ↔   ---   .    .    .   .    .    .    .    .    .    .    .    .    .
Training            .    ←    ←    .    →    .    .    .    .   ---   .    .   .    .    .    .    .    .    .    .    .    .
Sales               .    .    .    .    .    .    .    .    .    .   ---   →   ←    .    .    .    .    .    ←    .    .    .
StoreGoals          ←    .    .    .    .    .    ←    .    .    .    ←   ---  .    .    .    .    .    .    .    .    .    .
StockMovements      .    .    .    .    .    .    .    .    .    .    →    .  ---   .    ←    ←    .    .    .    .    .    .
StockAudit          .    .    .    .    .    .    .    .    .    .    .    .   .   ---   .    →    .    .    .    .    .    .
Transfers           .    .    .    .    .    .    .    .    .    .    .    .   →    .   ---   .    ←    .    .    .    .    .
Adjustments         .    .    .    .    .    .    .    .    .    .    .    .   →    ←    .   ---   .    .    .    .    .    .
OrderControl        .    .    .    .    .    .    .    .    .    .    .    .   .    .    →    .   ---   →    .    ←    .    .
Delivery            .    .    .    .    .    .    .    .    .    .    .    .   .    .    .    .    ←   ---   .    .    .    →
Returns             .    .    .    .    .    .    .    .    .    .    →    .   .    .    .    .    .    .   ---   .    .    ←
Reversals           .    .    .    .    .    .    .    .    .    .    .    .   .    .    .    .    →    .    .   ---   .    .
Helpdesk            .    .    .    .    .    .    .    .    .    .    .    .   .    .    .    .    .    .    .    .   ---   →
ServiceOrder        .    .    .    .    .    .    .    .    .    .    .    .   .    .    .    .    .    ←    →    .    ←   ---

Legenda: → dispara ação no módulo destino | ← recebe ação do módulo origem | ↔ bidirecional | . sem relação
```

---

## 8. Fluxos de Integração Prioritários

### Fluxo A: Ciclo de Vida do Funcionário (End-to-End)

```
ADMISSÃO                          OPERAÇÃO                           DESLIGAMENTO
─────────                         ────────                           ────────────
                                                                     
VacancyOpening                    WorkSchedule ←─── Escala           PersonnelMoviments
  (Finalizada)                    MedicalCert  ←─── Atestado           (Criado)
      │                           Vacations    ←─── Férias               │
      ▼                           AbsenceCtrl  ←─── Faltas               ├→ Employee.status = Inativo ✅
  Employee                        OvertimeCtrl ←─── HE                   ├→ VacancyOpening.create() ✅
  (Pre-cadastro)                  Training     ←─── Treinamento          ├→ Vacations.cancel() ❌
      │                               │                                  ├→ Training.remove() ❌
      ├→ WorkSchedule ✅               ▼                                  ├→ WorkSchedule.archive() ❌
      ├→ VacancyOpening.link ✅   Employee.status                        ├→ StoreGoals.redistribute() ✅
      ├→ ExperienceTracker ❌     atualizado conforme:                   └→ OvertimeCtrl.close() ❌
      └→ Training.enroll ❌       - Férias → status 4 ⚠️
                                  - Atestado → status 5 ❌
                                  - Retorno → status 2 ⚠️
                                  
✅ = Implementado | ⚠️ = Parcial | ❌ = Ausente
```

### Fluxo B: Cadeia de Suprimentos (Pedido → Entrega)

```
OrderControl          Transfers           StockMovements        Delivery
 (Faturado)          (Criada)             (Registrada)          (Roteada)
     │                   │                     │                    │
     ├→ Delivery ❌      ├→ StockMov ❌        │                    ├→ ServiceOrder ❌
     ├→ Transfer ❌      └→ Destino ✅         │                    └→ Returns ❌
     └→ Payment ✅                              │
                                                ▼
                                           StoreGoals
                                           (Alertas) ✅
```

### Fluxo C: Resolução de Problemas (Pós-venda)

```
Helpdesk              ServiceOrder         Returns              Reversals
 (Aberto)             (Criada)             (Registrada)         (Processado)
     │                     │                    │                    │
     ├→ ServiceOrder ❌    ├→ Returns ❌        ├→ Sales ❌          ├→ OrderControl ❌
     └→ Notificações ✅   └→ Notificações ✅   ├→ StoreGoals ❌    └→ Financeiro ✅
                                                └→ StockMov ❌
```

---

## 9. Plano de Implementação Sugerido

### Fase 1 — Integrações Críticas RH (Estimativa: 40h → 24h restantes)
| # | Integração | Esforço | Status |
|---|-----------|---------|--------|
| INT-09 | Vacations → Employee status (férias/retorno) | 8h | Pendente |
| INT-14 | Vacations → WorkSchedule (exclusão escala) | 6h | Pendente |
| INT-15 | MedicalCertificate → Employee status (afastamento) | 6h | Pendente |
| INT-16 | MedicalCertificate → WorkSchedule (exclusão escala) | 4h | Pendente |
| INT-17 | PersonnelMoviments → Vacations (cancelamento) | 8h | Pendente |
| INT-10 | PersonnelMoviments ↔ VacancyOpening (vínculo + migração DB) | ~~8h~~ | ✅ Concluído |

**Service Sugerido:** `EmployeeStatusSyncService` — centraliza todas as transições de status do funcionário disparadas por outros módulos.

### Fase 2 — Integrações Operações (Estimativa: 32h)
| # | Integração | Esforço |
|---|-----------|---------|
| INT-30 | OrderControl → Delivery (geração automática) | 10h |
| INT-31 | StockAudit → Adjustments (geração de ajustes) | 10h |
| INT-32 | Returns → Sales (estorno e recálculo) | 12h |

**Service Sugerido:** `InventoryReconciliationService` — unifica ajustes, devoluções e auditorias.

### Fase 3 — Integrações Pós-venda (Estimativa: 20h)
| # | Integração | Esforço |
|---|-----------|---------|
| INT-40 | Helpdesk → ServiceOrder (escalação) | 8h |
| INT-41 | ServiceOrder → Returns (devolução por defeito) | 6h |
| INT-42 | Delivery → ServiceOrder (problema na entrega) | 6h |

**Service Sugerido:** `CustomerIssueLifecycleService` — orquestra fluxo Ticket → OS → Devolução.

### Fase 4 — Validações Cruzadas (Estimativa: 24h)
| # | Integração | Esforço |
|---|-----------|---------|
| INT-21 | MedicalCertificate ↔ AbsenceControl (justificativa) | 6h |
| INT-22 | OvertimeControl → WorkSchedule (validação) | 6h |
| INT-23 | AbsenceControl ↔ OvertimeControl (validação cruzada) | 4h |
| INT-29 | MedicalCertificate → Vacations (bloqueio) | 4h |
| INT-26 | Vacations → StoreGoals (exclusão de metas) | 4h |

### Fase 5 — Integrações de Enriquecimento (Estimativa: 28h → 20h restantes)
| # | Integração | Esforço | Status |
|---|-----------|---------|--------|
| INT-11 | VacancyOpening → Employees (pré-preenchimento) | ~~8h~~ | ✅ Concluído |
| INT-12 | ExperienceTracker → Employees (efetivação) | 6h | Pendente |
| INT-18 | PersonnelMoviments → Training (desinscrição) | 4h | Pendente |
| INT-25 | VacancyOpening → Training (matrícula automática) | 6h | Pendente |
| INT-33 | Reversals → OrderControl (atualização pagamento) | 4h | Pendente |

---

## 10. Arquitetura de Integração Recomendada

### Padrão: Service Layer com Event Dispatch

```php
// Exemplo: PersonnelMoviments dispara cancelamento de férias
class AdmsAddPersonnelMoviments
{
    private function executeWithTransaction(array $data): bool
    {
        // ... lógica existente ...
        
        // Integrações cascata
        EmployeeInactivationService::deactivate($employeeId, $userId, $reason);     // ✅ Existente
        VacationCancellationService::cancelByEmployee($employeeId, $reason);         // ❌ Novo
        TrainingParticipantService::removeByEmployee($employeeId);                   // ❌ Novo
        WorkScheduleService::archiveByEmployee($employeeId, $dismissalDate);         // ❌ Novo
        
        return true;
    }
}
```

### Princípios
1. **Cada integração é um Service** — nunca acoplar Models diretamente
2. **Fire-and-forget para notificações** — try/catch, não bloquear operação principal
3. **Transação atômica para mudanças de dados** — integração dentro do mesmo transaction quando possível
4. **Idempotência** — chamadas repetidas não devem causar efeitos duplicados
5. **LoggerService em todas as integrações** — rastreabilidade completa

---

## 11. Tabelas de Vinculação Necessárias (Migrações)

```sql
-- Fase 1: Vínculo PersonnelMoviments ↔ VacancyOpening ✅ EXECUTADO (Abril/2026)
-- Migration: database/migrations/2026_04_rh_modules_integration.sql
-- Inclui também: 3 tabelas status_history, campos soft-delete, view vw_rh_lifecycle
ALTER TABLE adms_vacancy_opening ADD COLUMN origin_moviment_id INT NULL;    -- ✅
ALTER TABLE adms_vacancy_opening ADD COLUMN hired_employee_id INT NULL;     -- ✅
ALTER TABLE adms_employees ADD COLUMN origin_vacancy_id INT NULL;           -- ✅
ALTER TABLE adms_personnel_moviments ADD COLUMN open_vacancy TINYINT(1) DEFAULT 0;     -- ✅
ALTER TABLE adms_personnel_moviments ADD COLUMN generated_vacancy_id INT NULL;         -- ✅

-- Fase 4: Vínculo MedicalCertificate ↔ AbsenceControl
ALTER TABLE adms_absence_control ADD COLUMN medical_certificate_id INT NULL;

-- Fase 3: Vínculo Helpdesk ↔ ServiceOrder
ALTER TABLE adms_service_orders ADD COLUMN helpdesk_ticket_id INT NULL;

-- Fase 3: Vínculo ServiceOrder ↔ Returns
ALTER TABLE adms_returns ADD COLUMN service_order_id INT NULL;

-- Fase 3: Vínculo Delivery ↔ ServiceOrder
ALTER TABLE adms_service_orders ADD COLUMN delivery_id INT NULL;
```

---

## 12. Métricas de Sucesso

| Métrica | Antes (Mar/2026) | Atual (Abril/2026) | Meta Fase 1 | Meta Final |
|---------|------------------|-------------------|-------------|------------|
| Integrações implementadas | 8 | **13** (+5) | 19 (+6) | 34 (+21) |
| Services dedicados | 0 (módulos RH) | **11** | 14 | 20+ |
| Testes (integrações RH) | 0 | **150** (414 assert.) | 200 | 300+ |
| Ações manuais eliminadas | - | ~8/semana | ~15/semana | ~45/semana |
| Inconsistências de status | Não medido | Reduzido (soft-delete) | -60% | -95% |
| Tempo médio ciclo admissão | Manual | Parcial (pré-cadastro) | -30% | -50% |

---

## Conclusão

O Mercury possui uma base sólida de services (55 arquivos) e um padrão de state machine bem definido (usado em Employees, PersonnelMoviments, VacancyOpening, Vacations, OrderControl, Reversals). As integrações existentes demonstram que o padrão funciona (ex: desligamento → inativação → redistribuição de metas).

As maiores lacunas estão no **Cluster RH**, onde o ciclo de vida do funcionário deveria ser o eixo central conectando férias, atestados, escala, faltas e horas extras. No **Cluster Operações**, a cadeia pedido → transferência → entrega carece de orquestração. No **Cluster Atendimento**, o fluxo ticket → OS → devolução é completamente manual.

A implementação em 5 fases permite entregas incrementais com valor imediato, priorizando integrações que eliminam inconsistências de dados (status de funcionário desatualizado) e trabalho manual repetitivo (cancelamento de férias em desligamento).

---

**Documento gerado em:** 03/04/2026
**Atualizado em:** 03/04/2026 (v1.1 — INT-10, INT-11, INT-13 concluídos, métricas atualizadas)
**Próxima revisão:** Após conclusão da Fase 1 (INT-09, INT-14 a INT-17)
