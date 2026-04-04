# Proposta Técnica: Módulo de Gestão de Férias (Vacation Management)

**Status:** Rascunho para Avaliação  
**Versão:** 1.0  
**Autor:** Gemini CLI / Engenharia de Software  
**Projeto:** Mercury  

---

## 1. Objetivo
Implementar um controle centralizado de períodos aquisitivos e gozo de férias dos colaboradores, garantindo conformidade com a CLT e facilitando a programação antecipada pelas lojas e departamentos.

## 2. Arquitetura de Dados (Banco de Dados)

Sugerimos a criação de 3 tabelas principais para manter o histórico e a integridade:

### A. `adms_vacation_periods` (Períodos Aquisitivos)
Gerencia o direito às férias baseado na admissão.
*   `id`: INT (PK)
*   `adms_user_id`: INT (FK) - Relacionamento com `adms_usuarios`.
*   `date_start_acq`: DATE - Início do período (ex: 01/01/2025).
*   `date_end_acq`: DATE - Fim do período (ex: 31/12/2025).
*   `date_limit_concessive`: DATE - Data limite para o fim do gozo (vencimento das férias).
*   `days_entitled`: INT - Total de dias de direito (padrão 30, reduzido por faltas).
*   `days_taken`: INT - Dias já usufruídos.
*   `days_balance`: INT - Saldo disponível.
*   `adms_sit_vacation_period_id`: INT (FK) - Status (1: Aberto, 2: Quitado, 3: Vencido).

### B. `adms_vacations` (Solicitações/Programação)
Registra o agendamento das férias.
*   `id`: INT (PK)
*   `adms_vacation_period_id`: INT (FK).
*   `date_start`: DATE - Data de início do gozo.
*   `date_end`: DATE - Data de término.
*   `days_quantity`: INT - Quantidade de dias (ex: 15).
*   `sell_allowance`: TINYINT (0 ou 1) - Abono pecuniário ("venda" de 10 dias).
*   `installment`: INT (1, 2 ou 3) - Identificador da parcela.
*   `adms_sit_vacation_id`: INT (FK) - Status (Pendente, Aprovada, Gozando, Finalizada).

### C. `adms_vacation_logs` (Histórico)
*   Registra quem aprovou, quando e observações de cancelamento.

---

## 3. Camada de Backend (Padrão PSR-4 / MVC)

### Controllers (`app/adms/Controllers/`)
*   `Vacations.php`: Listagem e dashboard (Resource Controller).
*   `AddVacation.php`: Processamento de nova solicitação.
*   `EditVacation.php`: Ajustes em programações pendentes.
*   `ApproveVacation.php`: Ação exclusiva para gestores/RH.

### Models (`app/adms/Models/`)
*   `AdmsListVacations.php`: Query complexa unindo Usuário, Loja e Período.
*   `AdmsAddVacation.php`: Persistência dos dados.
*   `AdmsVacationValidator.php`: **Coração do módulo**. Métodos de validação:
    *   `valPeriodBalance()`: Verifica se há saldo no período aquisitivo.
    *   `valCLTRules()`: Valida se uma parcela tem >= 14 dias e as demais >= 5 dias.
    *   `valOverlap()`: Impede que o mesmo usuário tenha férias sobrepostas.
    *   `valBlackoutDates()`: Impede início em quintas, sextas ou vésperas de feriado (Regra CLT).

### Services (`app/adms/Services/`)
*   `VacationCalculationService.php`: Calcula datas de término e saldos automaticamente.
*   `VacationNotificationService.php`: Dispara alertas via WebSocket para o gestor quando uma nova solicitação é criada.

---

## 4. Camada de Frontend (UI/UX)

### Views (`app/adms/Views/vacation/`)
*   **Dashboard**: Card com "Dias restantes para vencer" e "Status do Período Atual".
*   **Formulário**: Campo de data com restrições dinâmicas (bloquear datas inválidas no seletor).
*   **Relatório**: Visão em linha do tempo (Gantt) para o gestor ver todos os funcionários da loja simultaneamente.

### JavaScript (`assets/js/vacations.js`)
*   Cálculo em tempo real: ao digitar a data de início e os dias, mostra automaticamente a data de retorno.
*   Integração com `NotificationService` para exibir erros de validação sem recarregar a página.

---

## 5. Regras de Negócio Propostas

1.  **Bloqueio de Antecedência:** Solicitações devem ser feitas com no mínimo 30 ou 45 dias de antecedência.
2.  **Validação de Período Concessivo:** Alerta crítico se as férias não forem marcadas até 2 meses antes do vencimento do segundo período (férias dobradas).
3.  **Fluxo de Aprovação:**
    *   Usuário solicita ➔ Gestor Imediato aprova ➔ RH valida/Finaliza.
4.  **Integração com Folha:** Gerar arquivo de exportação (CSV/Excel) para o sistema de folha de pagamento.

---

## 6. Segurança e Auditoria
*   **CSRF Protection:** Obrigatório em todos os formulários.
*   **LoggerService:** Registrar: "Usuário X alterou início das férias do colaborador Y de 10/05 para 15/05".
*   **Access Level:** Apenas nível RH (2) ou Admin (1) podem criar períodos aquisitivos manualmente.
