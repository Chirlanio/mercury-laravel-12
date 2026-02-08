# Modulo Checklist de Atendimento (ChecklistService)

**Versao:** 2.0
**Data:** 02/02/2026
**Responsavel:** Equipe Mercury - Grupo Meia Sola

---

## 1. Visao Geral

O modulo **Checklist de Atendimento** (ChecklistService) e responsavel por gerenciar avaliacoes de atendimento dos colaboradores nas lojas. Este modulo foi refatorado para seguir o padrao estabelecido pelo modulo Checklist (Lojas).

### 1.1 Funcionalidades

- Criar checklists de atendimento para colaboradores
- Responder perguntas de avaliacao
- Visualizar resultados e estatisticas
- Filtrar checklists por loja, status e data
- Excluir checklists pendentes
- Calculo automatico de pontuacao e percentuais

---

## 2. Estrutura de Arquivos

```
app/adms/
├── Controllers/
│   ├── ChecklistService.php         # Controller principal (listagem)
│   ├── AddChecklistService.php      # Controller de adicao
│   ├── EditChecklistService.php     # Controller de edicao/resposta
│   ├── ViewChecklistService.php     # Controller de visualizacao
│   └── DeleteChecklistService.php   # Controller de exclusao
│
├── Models/
│   ├── AdmsListChecklistService.php    # Model de listagem
│   ├── AdmsAddChecklistService.php     # Model de adicao
│   ├── AdmsEditChecklistService.php    # Model de edicao
│   ├── AdmsViewChecklistService.php    # Model de visualizacao
│   └── AdmsDeleteChecklistService.php  # Model de exclusao
│
├── Services/
│   └── ChecklistServiceBusiness.php    # Logica de negocio centralizada
│
└── Views/checklistService/
    ├── loadChecklistService.php        # Pagina principal (container)
    ├── listChecklistService.php        # Partial AJAX (tabela)
    ├── editChecklistService.php        # Pagina de edicao/resposta
    ├── viewChecklistService.php        # Pagina de visualizacao
    ├── addChecklistService.php         # Pagina de adicao (legado)
    └── partials/
        ├── _add_checklist_service_modal.php     # Modal de adicao
        └── _delete_checklist_service_modal.php  # Modal de exclusao

assets/js/
└── checklist-service.js    # JavaScript com AJAX e modais

docs/
└── migrations/
    └── checklist_service_refactor.sql  # Script de migracao SQL
```

---

## 3. Estrutura de Banco de Dados

### 3.1 Tabelas (Apos Migracao)

| Tabela | Descricao |
|--------|-----------|
| `adms_service_checklists` | Checklists principais |
| `adms_service_checklist_answers` | Respostas das perguntas |
| `adms_service_checklist_areas` | Areas de avaliacao |
| `adms_service_checklist_questions` | Perguntas por area |

### 3.2 Campos Principais

#### adms_service_checklists
```sql
- id (INT, PK)
- hash_id (VARCHAR, unique)
- adms_store_id (INT, FK)
- adms_employee_id (INT, FK)
- responsible_applicator (INT, FK)
- adms_sit_check_list_id (INT, FK)
- initial_date (DATETIME)
- final_date (DATETIME)
- created_at (DATETIME)
- updated_at (DATETIME)
```

#### adms_service_checklist_answers
```sql
- id (INT, PK)
- hash_id (VARCHAR)
- adms_service_checklist_id (INT, FK)
- adms_service_checklist_area_id (INT, FK)
- adms_service_checklist_question_id (INT, FK)
- adms_employee_id (INT, FK)
- adms_sit_check_list_id (INT, FK)
- points (DECIMAL)
- score (DECIMAL)
- justification (TEXT)
- action_plan (TEXT)
- created_at (DATETIME)
- updated_at (DATETIME)
```

---

## 4. Fluxo de Uso

### 4.1 Criar Checklist

1. Usuario clica em "Novo" na listagem
2. Modal de criacao abre
3. Usuario seleciona loja, colaborador e aplicador
4. Sistema cria o checklist com todas as perguntas pendentes
5. Usuario e redirecionado para responder as perguntas

### 4.2 Responder Perguntas

1. Sistema mostra uma pergunta por vez
2. Usuario seleciona resposta (Atendeu/Parcial/Nao Atendeu)
3. Usuario pode adicionar justificativa
4. Sistema calcula pontos e atualiza status automaticamente
5. Ao finalizar todas, status muda para "Finalizado"

### 4.3 Visualizar Resultados

1. Usuario clica no botao de visualizar
2. Sistema mostra:
   - Informacoes gerais do checklist
   - Pontuacao total e percentual
   - Grafico de atingimento por areas
   - Detalhamento de cada pergunta/resposta

---

## 5. Service de Logica de Negocio

O `ChecklistServiceBusiness.php` centraliza a logica de negocio:

```php
// Calcular estatisticas
ChecklistServiceBusiness::calculateStatistics($hashId);

// Validar acesso
ChecklistServiceBusiness::validateAccess($hashId, $userId, $userLevel, $storeId);

// Obter total de perguntas
ChecklistServiceBusiness::getTotalQuestionsCount();

// Calcular progresso
ChecklistServiceBusiness::calculateProgress($hashId);

// Verificar se pode deletar
ChecklistServiceBusiness::canDelete($hashId);

// Obter status por percentual
ChecklistServiceBusiness::getStatusByPercentage($percentage);

// Calcular pontos por resposta
ChecklistServiceBusiness::calculatePoints($responseStatusId);

// Determinar status do checklist
ChecklistServiceBusiness::determineChecklistStatus($hashId);

// Contar respostas
ChecklistServiceBusiness::getResponseCounts($hashId);

// Buscar checklist por hash
ChecklistServiceBusiness::findByHash($hashId);
```

---

## 6. Sistema de Permissoes

O modulo usa a constante `STOREPERMITION` para controlar acesso:

- **Usuarios Admin** (nivel < STOREPERMITION): Veem todos os checklists
- **Usuarios Loja** (nivel >= STOREPERMITION): Veem apenas da propria loja

As permissoes de botoes sao carregadas via `AdmsBotao`:

```php
$buttonsConfig = [
    'add_checklist' => ['menu_controller' => 'add-checklist-service', 'menu_metodo' => 'create'],
    'view_checklist' => ['menu_controller' => 'view-checklist-service', 'menu_metodo' => 'view'],
    'edit_checklist' => ['menu_controller' => 'edit-checklist-service', 'menu_metodo' => 'edit'],
    'delete_checklist' => ['menu_controller' => 'delete-checklist-service', 'menu_metodo' => 'delete'],
];
```

---

## 7. Sistema de Status

| ID | Status | Cor | Descricao |
|----|--------|-----|-----------|
| 1 | Pendente | Warning | Nenhuma pergunta respondida |
| 2 | Em Andamento | Info | Parcialmente respondido |
| 3 | Finalizado | Success | Todas perguntas respondidas |

---

## 8. Calculos de Pontuacao

### 8.1 Pontos por Resposta

| Resposta | Pontos |
|----------|--------|
| Atendeu | 1.0 |
| Atendeu Parcial | 0.5 |
| Nao Atendeu | 0.0 |

### 8.2 Classificacao por Percentual

| Percentual | Classificacao | Cor |
|------------|---------------|-----|
| >= 90% | Excelente | Success |
| >= 80% | Muito Bom | Success |
| >= 70% | Bom | Info |
| >= 60% | Satisfatorio | Warning |
| < 60% | Necessita Atenção | Danger |

---

## 9. Endpoints AJAX

### 9.1 Listagem

```
GET /admin/checklist-service/list/{page}?type=1
GET /admin/checklist-service/list/{page}?type=2&search_term=...
```

### 9.2 Visualizacao (Modal)

```
GET /admin/view-checklist-service/viewAjax/{hashId}
```

### 9.3 Exclusao (Modal)

```
POST /admin/delete-checklist-service/deleteAjax
Body: hash_id={hashId}
```

### 9.4 Criacao

```
POST /admin/add-checklist-service/create
Body: adms_store_id, adms_employee_id, responsible_applicator, AddChecklist=1
```

---

## 10. Migracao de Banco de Dados

### 10.1 Executar Migracao

O script de migracao esta em `docs/migrations/checklist_service_refactor.sql`.

**IMPORTANTE:** Fazer backup antes de executar!

```bash
# Fazer backup
mysqldump -u user -p database adms_service_check_lists > backup_service_checklists.sql

# Executar migracao
mysql -u user -p database < docs/migrations/checklist_service_refactor.sql
```

### 10.2 Verificar Integridade

```sql
SELECT 'adms_service_checklists' as tabela, COUNT(*) as total FROM adms_service_checklists;
SELECT 'adms_service_checklist_answers' as tabela, COUNT(*) as total FROM adms_service_checklist_answers;
```

---

## 11. Testes

### 11.1 Checklist de Testes Manuais

- [ ] Listagem carrega via AJAX
- [ ] Paginacao funciona corretamente
- [ ] Filtro por nome funciona (debounce)
- [ ] Filtro por status funciona
- [ ] Filtro por loja funciona (admin)
- [ ] Filtro por data funciona
- [ ] Modal de criar abre corretamente
- [ ] Selecao de loja carrega colaboradores
- [ ] Criar checklist funciona
- [ ] Responder perguntas funciona
- [ ] Progresso atualiza corretamente
- [ ] Visualizar mostra estatisticas
- [ ] Deletar funciona (apenas pendentes)
- [ ] Permissoes por nivel de usuario
- [ ] Responsividade mobile

---

## 12. Diferencas do Modulo Checklist (Lojas)

| Aspecto | Checklist (Lojas) | ChecklistService (Atendimento) |
|---------|-------------------|-------------------------------|
| Foco | Avaliacao da loja | Avaliacao do colaborador |
| Entidade avaliada | Loja | Colaborador |
| Tabelas | adms_checklists | adms_service_checklists |
| Perguntas | adms_checklist_questions | adms_service_checklist_questions |
| Aplicador | Selecionado | Obrigatorio |
| Navegacao | Todas perguntas | Uma por vez |

---

## 13. Historico de Alteracoes

| Data | Versao | Descricao |
|------|--------|-----------|
| 02/02/2026 | 2.0 | Refatoracao completa seguindo padrao Checklist |
| - | 1.0 | Versao original |

---

## 14. Contato

Para duvidas ou sugestoes sobre este modulo:

- **Documentacao:** `/docs/modules/`
- **Codigo:** `/app/adms/Controllers/ChecklistService.php`
- **Testes:** Verificar checklist de testes acima
