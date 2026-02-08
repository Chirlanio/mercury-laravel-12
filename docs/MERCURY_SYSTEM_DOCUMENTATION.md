# Mercury System Documentation

> **⚠️ Atualização 2026:** Uma análise completa e atualizada da arquitetura, segurança e testes do sistema foi realizada em **Janeiro-Fevereiro de 2026**. Em Fevereiro, 13 módulos de configuração foram migrados para o padrão `AbstractConfigController` (ver [PADRONIZACAO.md](PADRONIZACAO.md) seção 20). Consulte o [Relatório de Análise Geral 2026](analysis/RELATORIO_ANALISE_GERAL_2026.md) e o [Relatório de Análise de Módulos](analysis/RELATORIO_ANALISE_MODULOS_2026.md) para detalhes técnicos aprofundados.

## Overview
This document serves as the central hub for the Mercury System documentation.


Este documento é a fonte central de verdade para todos os padrões de desenvolvimento, arquitetura e guias de implementação do sistema Mercury. O objetivo é garantir que o desenvolvimento de novos módulos e a manutenção dos existentes sigam princípios consistentes de qualidade, segurança e manutenibilidade.

---

## 2. Princípios Fundamentais de Desenvolvimento

Todo o código produzido para o sistema Mercury deve aderir estritamente aos seguintes princípios:

### 2.1. Padronização de Serviços Essenciais

Para garantir consistência e centralizar funcionalidades críticas, o uso dos seguintes serviços é **obrigatório** em todos os módulos:

- **`App\adms\Services\NotificationService`**: Deve ser utilizado para todas as formas de notificação ao usuário, incluindo mensagens de feedback na interface (sucesso, erro, aviso) e envio de e-mails. **NÃO** é permitido o uso direto de `$_SESSION['msg']` ou outras implementações de mensagens flash.

- **`App\adms\Services\LoggerService`**: Deve ser utilizado para registrar todas as atividades relevantes do sistema, especialmente alterações de estado (criação, edição, exclusão), ações críticas (login, logout, exportação) e erros inesperados. Logs são essenciais para auditoria, depuração e monitoramento de segurança.

- **`App\adms\Services\FormSelectRepository`**: Deve ser utilizado para popular todos os campos de seleção (`<select>`) em formulários. Centraliza a busca por listas de dados (ex: situações, cores, departamentos), evitando a duplicação de queries nos Models.

### 2.2. Consistência Visual e de Código

- **Consistência Visual:** Todos os módulos devem ter a mesma aparência e comportamento, utilizando os componentes e a estrutura definidos neste guia para reduzir a carga cognitiva do usuário.
- **Manutenibilidade:** Um padrão de código único facilita a manutenção, a aplicação de novas funcionalidades e a correção de bugs.

### 2.3. Foco em Segurança

- **Prevenção de XSS:** Toda e qualquer saída de dados no HTML deve ser escapada com `htmlspecialchars()`.
- **Prevenção de CSRF:** Todos os formulários que executam ações de escrita (POST, PUT, DELETE) devem ser protegidos com tokens anti-CSRF.
- **Validação de Dados:** Todos os dados de entrada (backend e frontend) devem ser rigorosamente validados.

---

## 3. Guia de Padrões de UI/UX (Frontend)

Esta seção define a estrutura visual e os componentes que devem ser utilizados em todas as telas de gerenciamento do sistema.

### 3.1. Estrutura Padrão da Página

Toda página de listagem e gerenciamento deve seguir rigorosamente a estrutura HTML e de classes abaixo:

```html
<!-- Container Principal -->
<div class="content p-3">
    <div class="list-group-item">
        <!-- 1. Cabeçalho da Página -->
        <div class="d-flex align-items-center bg-light pr-2 pl-2 mb-4 border rounded shadow-sm">
            <!-- Título e Botões de Ação -->
        </div>

        <!-- 2. Cards de Estatísticas (Opcional) -->
        <div class="row mb-4 d-print-none" id="statistics_cards">
            <!-- Cards dinâmicos -->
        </div>

        <!-- 3. Formulário de Busca -->
        <div class="card mb-4 d-print-none">
            <!-- Filtros -->
        </div>

        <!-- 4. Mensagens e Alertas (Gerenciado pelo NotificationService) -->
        <div id="messages">
            <!-- O NotificationService irá popular esta área -->
        </div>

        <!-- 5. Conteúdo Principal (Tabela) -->
        <div class="table-responsive" id="content_module">
            <!-- Tabela de dados -->
        </div>
    </div>
</div>
```

### 3.2. Biblioteca de Componentes

- **Cabeçalho da Página:** Deve conter um título responsivo com ícone e a barra de ferramentas de ações (`.btn-toolbar`).
- **Botões de Ação:** Devem usar as classes de cor padrão e ser agrupados em um `.btn-group`. Para telas menores, um dropdown deve ser usado para agrupar ações secundárias.
- **Cards de Estatísticas:** Devem possuir borda colorida e valores que são atualizados dinamicamente com base nos filtros de busca aplicados.
- **Tabelas de Dados:** Devem usar o cabeçalho `thead-dark` e os botões de ação devem ser `<button>` com classes `btn-outline-*`.

### 3.3. Paleta de Cores e Classes CSS

| Uso | Classe | Cor |
|---|---|---|
| **Ação Primária** | `btn-primary` | Azul |
| **Criação** | `btn-success` | Verde |
| **Informação** | `btn-info` | Ciano |
| **Edição** | `btn-warning` | Amarelo |
| **Exclusão/Perigo**| `btn-danger` | Vermelho |
| **Ação Secundária**| `btn-secondary`| Cinza |

---

## 4. Padrões de Backend e Lógica de Negócio

### 4.1. Serviço de Notificações (`NotificationService`)

O `NotificationService` é a única forma autorizada de exibir mensagens ao usuário e enviar e-mails.

**Uso para Mensagens Flash:**

```php
use App\adms\Services\NotificationService;

// Em um Controller, após uma operação
$notification = new NotificationService();

if ($operacao_sucesso) {
    $notification->success('Registro salvo com sucesso!');
} else {
    $notification->error('Falha ao salvar o registro.');
}
```

**Uso para E-mails:**

```php
$notification->sendEmail(
    to: 'usuario@example.com',
    subject: 'Assunto do E-mail',
    body: '<h1>Corpo do E-mail</h1>'
);
```

### 4.3. Serviço de Exportação (`ExportService`)

O `ExportService` centraliza a lógica de exportação de dados para formatos como Excel, CSV e PDF.

**Exemplo de Uso (Exportar para Excel):**

```php
use App\adms\Services\ExportService;
use App\adms\Models\AdmsExportReturns; // Exemplo de Model que busca os dados

class ExportReturns {

    public function export() {
        // 1. Buscar os dados a serem exportados
        $exportModel = new AdmsExportReturns();
        $exportModel->list($_SESSION['filters'] ?? []);
        $dataToExport = $exportModel->getResult();

        // 2. Definir os cabeçalhos da planilha
        $headers = ['#ID', 'Protocolo', 'Cliente', 'Tipo', 'Status', 'Data Cadastro'];

        // 3. Mapear os dados do resultado para o formato esperado (array de arrays)
        $data = [];
        if (!empty($dataToExport)) {
            foreach ($dataToExport as $row) {
                $data[] = [
                    $row['id'],
                    $row['protocol'] ?? '',
                    $row['client_name'] ?? '',
                    $row['type'] ?? '',
                    $row['status'] ?? '',
                    !empty($row['created_at']) ? date("d/m/Y H:i", strtotime($row['created_at'])) : ''
                ];
            }
        }

        // 4. Chamar o serviço de exportação
        $exportService = new ExportService();
        $filename = 'relatorio_trocas_' . date('Y-m-d') . '.xls';
        $exportService->exportToBasicExcel($headers, $data, $filename, 'Relatório de Trocas e Devoluções');
    }
}
```

O `LoggerService` é mandatório para registrar eventos importantes, permitindo rastreabilidade e auditoria.

**Níveis de Log:**
- `info`: Para eventos normais e informativos (ex: login, logout, criação de registro).
- `warning`: Para eventos inesperados, mas que não são erros críticos (ex: tentativa de login falha, acesso a recurso não permitido).
- `error`: Para erros de execução que impedem o funcionamento de uma funcionalidade.

**Exemplo de Uso:**

```php
use App\adms\Services\LoggerService;

// Log de uma ação bem-sucedida
LoggerService::info(
    'USER_CREATED',
    "Usuário '{$admin_name}' criou o novo usuário '{$new_user_name}'.",
    ['admin_id' => $admin_id, 'new_user_id' => $new_user_id]
);

// Log de uma tentativa de ação falha
LoggerService::warning(
    'DELETE_FAILED',
    "Usuário '{$user_name}' tentou apagar um registro protegido.",
    ['user_id' => $user_id, 'record_id' => $record_id]
);
```

---

## 5. Guias de Manutenção e Refatoração

### 5.1. Checklist de Implementação para Novos Módulos

- [ ] A estrutura da view principal segue o padrão definido na seção 3.1.
- [ ] Todas as interações de escrita (CUD) são feitas via AJAX e retornam JSON.
- [ ] Todas as mensagens de feedback para o usuário são tratadas pelo `NotificationService`.
- [ ] Todas as ações relevantes (login, logout, CUD, etc.) são registradas com o `LoggerService`.
- [ ] O código backend (Controllers, Models) utiliza tipagem estrita do PHP.
- [ ] Não há lógica de negócio ou queries SQL diretamente nas Views.
- [ ] Todos os formulários possuem proteção contra CSRF.
- [ ] Todas as saídas de dados no HTML são protegidas com `htmlspecialchars()`.

### 5.2. Checklist de Refatoração: Services e Helpers

Este checklist guia a migração de código antigo para a nova arquitetura baseada em serviços.

- [ ] **AuthenticationService**: Mover toda a lógica de `AdmsLogin`, `AdmsAlterarSenha` para este serviço.
- [ ] **PermissionService**: Centralizar as verificações de permissão de `AdmsLibPermi` e `AdmsPaginas`.
- [ ] **ExportService**: Migrar toda a lógica de geração de planilhas (Excel/CSV) e PDFs (`dompdf`).
- [ ] **FormatHelper**: Substituir chamadas diretas a `number_format`, formatação de datas, etc., por métodos do helper.
- [ ] **UiHelper**: Substituir a geração manual de HTML (ex: mensagens de alerta) por métodos do helper.
- [ ] **FormSelectRepository**: Mover toda a lógica de busca de dados para campos `<select>` (ex: `listarCadastrar()` em Models como `AdmsCadastrarNivAc`) para este serviço.