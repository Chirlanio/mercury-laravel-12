# WebSocket Notifications Tracker

**Versao:** 3.0
**Criado em:** 26 de Fevereiro de 2026
**Objetivo:** Rastrear a implementacao de notificacoes WebSocket em tempo real nos modulos do Mercury.

---

## Infraestrutura

| Componente | Arquivo | Status |
|------------|---------|--------|
| SystemNotificationService | `app/adms/Services/SystemNotificationService.php` | Ativo |
| NotificationRecipientService | `app/adms/Services/NotificationRecipientService.php` | **Novo v2.0** |
| WebSocketNotifier | `app/adms/Services/WebSocketNotifier.php` | Ativo |
| WebSocket Server (Ratchet) | `bin/websocket-server.php` | Ativo |
| Tabela `adms_notifications` | Database | Ativo |
| Tabela `adms_notification_recipients` | Database | **Novo v2.0** |

### API do SystemNotificationService

```php
// Notificar um usuario
SystemNotificationService::notify(int $userId, string $type, string $category, string $title, string $message, array $options);

// Notificar multiplos usuarios
SystemNotificationService::notifyUsers(array $userIds, string $type, string $category, string $title, string $message, array $options);
```

**Tipos:** `approval`, `status_change`, `system_alert`, `workflow`
**Categorias:** `transfer`, `holiday_payment`, `helpdesk`, `broadcast`, `general`, `sales`, `delivery`, `personnel`, `order_control`, `adjustments`, `coupons`, `consignments`, `store_goals`, `product_promotions`, `ecommerce`, `reversals`, `returns`, `relocation`, `internal_transfer`, `material_request`, `order_payments`, `travel_expenses`, `overtime`, `absence`, `medical_certificate`, `vacancy`, `experience_tracker`, `training`, `work_schedule`, `service_order`, `checklist`, `fixed_assets`

---

## Modulos Implementados

### Transferencias (Transfer)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `EditTransfer.php` | Status alterado | `status_change` | `transfer` | Criador da transferencia | Existente |
| `EditTransfer.php` | Status alterado | `status_change` | `transfer` | Super Admin (nivel 1) + Gerentes loja destino (niveis 2-5) | **Novo** |
| `AddTransfer.php` | Nova transferencia | `workflow` | `transfer` | Super Admin (nivel 1) + Gerentes loja destino (niveis 2-5) | **Novo** |

### Holiday Payment

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `EditHolidayPayment.php` | Aprovacao/Rejeicao | `approval` | `holiday_payment` | Criador da solicitacao | Existente |
| `AddHolidayPayment.php` | Nova solicitacao | `approval` | `holiday_payment` | Admins (niveis 1-2) | **Novo** |
| `ApproveHolidayPayment.php` | Aprovacao/Rejeicao | `approval` | `holiday_payment` | Criador da solicitacao | **Novo** |

### Chat

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddChatBroadcast.php` | Novo broadcast | `workflow` | `broadcast` | Todos os usuarios | Existente |

### Helpdesk

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `HelpdeskChatNotifier` | Nova mensagem | `workflow` | `helpdesk` | Participantes do ticket | Existente |

### Stock Movements

| Service | Evento | Tipo | Categoria | Destinatarios | Status |
|---------|--------|------|-----------|---------------|--------|
| `StockMovementAlertService` | Alerta estoque | `system_alert` | `general` | Admins (niveis 1-2) | Existente (corrigido) |
| `StockMovementSyncService` | Sync completo | `system_alert` | `general` | Gerentes (niveis 1-5) | Existente (corrigido) |
| `StockMovementSyncService` | Falha sync | `system_alert` | `general` | Admins (niveis 1-2) | Existente (corrigido) |

### Vendas (Sales)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddSales.php` | Nova venda | `workflow` | `sales` | Super Admin (nivel 1) + Gerentes da loja (niveis 2-5) | **Novo** |

### Movimentacao de Pessoal (Personnel)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddPersonnelMoviments.php` | Nova movimentacao | `workflow` | `personnel` | RH/Admins (niveis 1-3) | **Novo** |
| `EditPersonnelMoviments.php` | Movimentacao editada | `status_change` | `personnel` | RH/Admins (niveis 1-3) | **Novo** |

### Entregas (Delivery)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddDelivery.php` | Nova entrega | `workflow` | `delivery` | Super Admin (nivel 1) + Gerentes da loja (niveis 2-5) | **Novo** |
| `EditDelivery.php` | Entrega atualizada | `status_change` | `delivery` | Super Admin (nivel 1) + Gerentes da loja (niveis 2-5) | **Novo** |

### Ordens de Compra (Order Control)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddOrderControl.php` | Nova ordem | `workflow` | `order_control` | Super Admin (nivel 1) + Gerentes da loja (niveis 2-5) | **Novo** |
| `EditOrderControl.php` | Ordem atualizada | `status_change` | `order_control` | Super Admin (nivel 1) + Gerentes da loja (niveis 2-5) | **Novo** |

### Ajustes de Estoque (Adjustments)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddAdjustment.php` | Novo ajuste | `workflow` | `adjustments` | Conforme regras `adms_notification_recipients` | **v2.0** |
| `EditAdjustment.php` | Ajuste atualizado | `status_change` | `adjustments` | Conforme regras `adms_notification_recipients` | **v2.0** |

### Cupons (Coupons)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddCoupon.php` | Novo cupom | `workflow` | `coupons` | Conforme regras `adms_notification_recipients` | **v3.0** |
| `EditCoupon.php` | Cupom atualizado | `status_change` | `coupons` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Consignacoes (Consignments)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddConsignment.php` | Nova consignacao | `workflow` | `consignments` | Super Admin + Gerentes da loja (store-scoped) | **v3.0** |
| `EditConsignment.php` | Consignacao atualizada | `status_change` | `consignments` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Metas de Loja (Store Goals)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddStoreGoals.php` | Nova meta | `workflow` | `store_goals` | Super Admin + Gerentes da loja (store-scoped) | **v3.0** |
| `EditStoreGoal.php` | Meta atualizada | `status_change` | `store_goals` | Super Admin + Gerentes da loja (store-scoped) | **v3.0** |

### Promocoes de Produto (Product Promotions)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddProductPromotion.php` | Nova promocao | `workflow` | `product_promotions` | Conforme regras `adms_notification_recipients` | **v3.0** |
| `EditProductPromotion.php` | Promocao atualizada | `status_change` | `product_promotions` | Conforme regras `adms_notification_recipients` | **v3.0** |

### E-commerce (Ecommerce Orders)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddEcommerceOrder.php` | Novo pedido | `workflow` | `ecommerce` | Super Admin + Gerentes da loja (store-scoped) | **v3.0** |
| `EditEcommerceOrder.php` | Pedido atualizado | `status_change` | `ecommerce` | Super Admin + Gerentes da loja (store-scoped) | **v3.0** |

### Estornos (Reversals)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddReversal.php` | Novo estorno | `workflow` | `reversals` | Conforme regras `adms_notification_recipients` | **v3.0** |
| `EditReversal.php` | Estorno atualizado | `status_change` | `reversals` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Trocas/Devolucoes (Returns)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddReturns.php` | Nova troca/devolucao | `workflow` | `returns` | Conforme regras `adms_notification_recipients` | **v3.0** |
| `EditReturn.php` | Troca/devolucao atualizada | `status_change` | `returns` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Remanejamento (Relocation)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddRelocation.php` | Novo remanejamento | `workflow` | `relocation` | Super Admin + Gerentes loja destino (store-scoped) | **v3.0** |
| `EditRelocation.php` | Remanejamento atualizado | `status_change` | `relocation` | Super Admin + Gerentes loja destino (store-scoped) | **v3.0** |

### Transferencia Interna (Internal Transfer)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddInternalTransferSystem.php` | Nova transferencia interna | `workflow` | `internal_transfer` | Super Admin + Gerentes loja destino (store-scoped) | **v3.0** |
| `EditInternalTransferSystem.php` | Transferencia interna atualizada | `status_change` | `internal_transfer` | Super Admin + Gerentes loja destino (store-scoped) | **v3.0** |

### Requisicao de Material (Material Request)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddMaterialRequest.php` | Nova requisicao | `workflow` | `material_request` | Super Admin + Gerentes da loja (store-scoped) | **v3.0** |
| `EditMaterialRequest.php` | Requisicao atualizada | `status_change` | `material_request` | Super Admin + Gerentes da loja (store-scoped) | **v3.0** |

### Ordens de Pagamento (Order Payments)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddOrderPayments.php` | Nova ordem pagamento | `workflow` | `order_payments` | Conforme regras `adms_notification_recipients` | **v3.0** |
| `EditOrderPayments.php` | Ordem pagamento atualizada | `status_change` | `order_payments` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Verba de Viagem (Travel Expenses)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddTravelExpenses.php` | Nova verba viagem | `workflow` | `travel_expenses` | Super Admin + Gerentes da loja (store-scoped) | **v3.0** |

### Controle de Jornada (Overtime Control)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddOvertimeControl.php` | Novo registro jornada | `workflow` | `overtime` | Conforme regras `adms_notification_recipients` | **v3.0** |
| `EditOvertimeControl.php` | Registro jornada atualizado | `status_change` | `overtime` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Controle de Faltas (Absence Control)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddAbsenceControl.php` | Nova falta registrada | `workflow` | `absence` | Conforme regras `adms_notification_recipients` | **v3.0** |
| `EditAbsenceControl.php` | Registro falta atualizado | `status_change` | `absence` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Atestados Medicos (Medical Certificates)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddMedicalCertificate.php` | Novo atestado | `workflow` | `medical_certificate` | Conforme regras `adms_notification_recipients` | **v3.0** |
| `EditMedicalCertificate.php` | Atestado atualizado | `status_change` | `medical_certificate` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Abertura de Vagas (Vacancy Openings)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddVacancyOpening.php` | Nova vaga | `workflow` | `vacancy` | Conforme regras `adms_notification_recipients` | **v3.0** |
| `EditVacancyOpening.php` | Vaga atualizada | `status_change` | `vacancy` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Avaliacao de Experiencia (Experience Tracker)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `FillExperienceEvaluation.php` | Avaliacao preenchida | `workflow` | `experience_tracker` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Treinamentos (Training)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddTraining.php` | Novo treinamento | `workflow` | `training` | Conforme regras `adms_notification_recipients` | **v3.0** |
| `EditTraining.php` | Treinamento atualizado | `status_change` | `training` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Escalas de Trabalho (Work Schedule)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddWorkSchedule.php` | Nova escala | `workflow` | `work_schedule` | Conforme regras `adms_notification_recipients` | **v3.0** |
| `EditWorkSchedule.php` | Escala atualizada | `status_change` | `work_schedule` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Ordens de Servico (Service Orders)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddServiceOrder.php` | Nova ordem servico | `workflow` | `service_order` | Super Admin + Gerentes da loja (store-scoped) | **v3.0** |
| `EditServiceOrder.php` | Ordem servico atualizada | `status_change` | `service_order` | Super Admin + Gerentes da loja (store-scoped) | **v3.0** |

### Checklists

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddChecklist.php` | Novo checklist | `workflow` | `checklist` | Super Admin + Gerentes da loja (store-scoped) | **v3.0** |
| `EditChecklist.php` | Checklist atualizado | `status_change` | `checklist` | Conforme regras `adms_notification_recipients` | **v3.0** |

### Ativos Imobilizados (Fixed Assets)

| Controller | Evento | Tipo | Categoria | Destinatarios | Status |
|------------|--------|------|-----------|---------------|--------|
| `AddFixedAssets.php` | Novo ativo imobilizado | `workflow` | `fixed_assets` | Conforme regras `adms_notification_recipients` | **v3.0** |
| `EditFixedAsset.php` | Ativo imobilizado atualizado | `status_change` | `fixed_assets` | Conforme regras `adms_notification_recipients` | **v3.0** |

---

## Modulos NAO Implementados (Prioridade Baixa/Media)

| Modulo | Motivo |
|--------|--------|
| Employees (CRUD) | Baixa frequencia de alteracoes |
| Budgets (Orcamentos) | Modulo legado, ainda nao refatorado |
| Products/Sync Cigam | Processo batch, nao interativo |
| Config Modules (13) | Apenas lookup tables, baixa relevancia |
| EditTravelExpense | Legado com redirect, sem AJAX |
| Legacy duplicates | CadastrarTroca, EditarTroca, EditarRemanejo, CadastrarAjuste, EditarAjuste |

---

## Padrao de Implementacao (v2.0 - Config-based)

Desde v2.0, os destinatarios sao resolvidos pela tabela `adms_notification_recipients`
via `NotificationRecipientService`, eliminando queries hardcoded nos controllers.

### Tabela de Configuracao

```sql
-- adms_notification_recipients
-- Uma linha por destinatario, permite ativar/desativar individualmente
CREATE TABLE adms_notification_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_category VARCHAR(50) NOT NULL,     -- 'sales', 'transfer', etc.
    recipient_type ENUM('user','access_level','area') NOT NULL,
    recipient_value INT NOT NULL,                   -- user_id, nivel_acesso_id, ou area_id
    store_scope ENUM('all','same_store','specific') NOT NULL DEFAULT 'all',
    store_id INT DEFAULT NULL,                      -- usado quando store_scope='specific'
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    ...
);
```

**Migration:** `database/migrations/2026_02_26_create_notification_recipients.sql`

### Padrao nos Controllers (v2.0)

```php
use App\adms\Services\NotificationRecipientService;

private function notifyTargetUsers(): void
{
    try {
        $currentUserId = $_SESSION['usuario_id'] ?? 0;
        $storeId = $this->dataForm['store_field'] ?? null;

        $userIds = NotificationRecipientService::resolveRecipients(
            'categoria',                              // notification_category
            !empty($storeId) ? $storeId : null,       // store context
            [$currentUserId]                           // exclude self
        );
        if (empty($userIds)) return;

        SystemNotificationService::notifyUsers(
            $userIds,
            'tipo', 'categoria', 'Titulo', 'Mensagem',
            ['icon' => 'fa-icon', 'color' => 'cor', 'action_url' => URLADM . 'modulo/list']
        );
    } catch (\Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }
}
```

### Categorias Configuradas

| Categoria | Icone | Cor | action_url | store_scope |
|-----------|-------|-----|------------|-------------|
| `sales` | `fa-cash-register` | `success` | `sales/list` | Loja da venda |
| `transfer` | `fa-exchange-alt` | `info` | `transfers/list` | Loja destino |
| `holiday_payment` | — | — | — | Sem filtro |
| `personnel` | — | — | — | Sem filtro |
| `delivery` | — | — | — | Loja da entrega |
| `order_control` | — | — | — | Loja da ordem |
| `adjustments` | `fa-sliders-h` | `primary` | `adjustments/list` | Loja do ajuste |
| `coupons` | `fa-ticket-alt` | `success` | `coupons/list` | Global |
| `consignments` | `fa-handshake` | `success` | `consignments/list` | Loja da consignacao |
| `store_goals` | `fa-bullseye` | `success` | `store-goals/list` | Loja da meta |
| `product_promotions` | `fa-tags` | `success` | `product-promotions/list` | Global |
| `ecommerce` | `fa-shopping-cart` | `success` | `ecommerce-orders/list` | Loja do pedido |
| `reversals` | `fa-undo-alt` | `primary` | `reversals/list` | Global |
| `returns` | `fa-exchange-alt` | `primary` | `returns/list` | Global |
| `relocation` | `fa-truck-loading` | `primary` | `relocation/list` | Loja destino |
| `internal_transfer` | `fa-people-arrows` | `primary` | `internal-transfer-system/list` | Loja destino |
| `material_request` | `fa-clipboard-list` | `primary` | `material-request/list` | Loja do solicitante |
| `order_payments` | `fa-file-invoice-dollar` | `warning` | `order-payments/list` | Global |
| `travel_expenses` | `fa-plane-departure` | `warning` | `travel-expenses/list` | Loja do solicitante |
| `overtime` | `fa-clock` | `info` | `overtime-control/list` | Global |
| `absence` | `fa-user-slash` | `info` | `absence-control/list` | Global |
| `medical_certificate` | `fa-file-medical` | `info` | `medical-certificates/list` | Global |
| `vacancy` | `fa-user-plus` | `info` | `vacancy-openings/list` | Global |
| `experience_tracker` | `fa-user-check` | `info` | `experience-tracker/list` | Global |
| `training` | `fa-chalkboard-teacher` | `info` | `training/list` | Global |
| `work_schedule` | `fa-calendar-alt` | `info` | `work-schedule/list` | Global |
| `service_order` | `fa-tools` | `secondary` | `service-orders/list` | Loja da OS |
| `checklist` | `fa-tasks` | `secondary` | `checklists/list` | Loja do checklist |
| `fixed_assets` | `fa-building` | `dark` | `fixed-assets/list` | Global |

### Como Adicionar/Alterar Destinatarios

```sql
-- Adicionar area de logistica como destinatario de delivery
INSERT INTO adms_notification_recipients
  (notification_category, recipient_type, recipient_value, store_scope, description)
VALUES
  ('delivery', 'area', 5, 'all', 'Equipe logistica recebe de todas as lojas');

-- Desativar um destinatario sem deletar
UPDATE adms_notification_recipients SET is_active = 0 WHERE id = 15;

-- Adicionar usuario especifico
INSERT INTO adms_notification_recipients
  (notification_category, recipient_type, recipient_value, store_scope, description)
VALUES
  ('transfer', 'user', 42, 'all', 'Joao da logistica (usuario fixo)');
```

### Principios

1. **Fire-and-forget:** Notificacoes nunca bloqueiam a operacao principal
2. **Self-notification prevention:** Usuario atual sempre excluido via `$excludeUserIds`
3. **Try/catch:** Erros logados mas nao propagados
4. **Config-driven:** Destinatarios definidos em `adms_notification_recipients`, nao em codigo
5. **Super Admin global:** Nivel 1 com `store_scope='all'` recebe de todas as lojas
6. **Extensivel:** Suporta `access_level`, `area`, e `user` como tipos de destinatario

### Referencia de Tabela/Colunas (IMPORTANTE)

| Correto | Errado (nao usar) |
|---------|-------------------|
| `adms_usuarios` (plural) | ~~`adms_usuario`~~ (singular) |
| `loja_id` | ~~`usuario_loja`~~ |
| `adms_sits_usuario_id = 1` | ~~`situacao = 1`~~ |
| `adms_niveis_acesso_id` | (correto em ambos) |

> **Nota:** `$_SESSION['usuario_loja']` armazena o valor de `adms_usuarios.loja_id`.
> A coluna de sessao tem nome diferente da coluna no banco.

---

## Historico de Alteracoes

| Data | Descricao |
|------|-----------|
| 2026-02-26 | Implementacao inicial: 11 controllers, 6 modulos de prioridade alta |
| 2026-02-26 | Fix: tabela `adms_usuario` → `adms_usuarios`, colunas `usuario_loja` → `loja_id`, `situacao` → `adms_sits_usuario_id` |
| 2026-02-26 | Fix: corrigido mesmo bug pre-existente em StockMovementSyncService e StockMovementAlertService |
| 2026-02-26 | Fix: Super Admin (nivel 1) agora recebe notificacoes de todas as lojas, nao apenas da loja atribuida |
| 2026-02-26 | v2.0: Tabela `adms_notification_recipients` + `NotificationRecipientService` para destinatarios configuraveis |
| 2026-02-26 | v2.0: 10 controllers migrados de queries hardcoded para `NotificationRecipientService::resolveRecipients()` |
| 2026-02-26 | v3.0: 42 controllers implementados com notificacoes WebSocket (22 modulos adicionais: Comercial, Estoque, Financeiro, RH, Suporte, Patrimonio) |
