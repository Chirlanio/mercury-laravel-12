# Guia de Modernização e Padrões de Arquitetura Mercury

**Data:** 02 de Dezembro de 2025
**Autor:** Gemini
**Versão:** 1.0

---

## 1. Visão Geral

Este documento estabelece as diretrizes e padrões para a modernização contínua do sistema Mercury. A análise de diversos módulos revelou um conjunto de desafios arquiteturais que precisam ser endereçados para garantir a manutenibilidade, escalabilidade e qualidade do código.

O objetivo é evoluir de uma arquitetura híbrida e inconsistente para um padrão coeso, previsível e alinhado com as melhores práticas de desenvolvimento de software.

---

## 2. Diagnóstico da Arquitetura Atual

A arquitetura do Mercury encontra-se em um estado de transição. Embora práticas modernas como AJAX, serviços e uma camada de Views bem estruturada já existam, elas coexistem com padrões legados que geram os seguintes problemas:

-   **Fragmentação de Controllers:** A prática de criar um arquivo de controller para cada ação de CRUD (`Add*.php`, `Edit*.php`, `Delete*.php`) é o principal débito técnico. Isso viola o Princípio da Responsabilidade Única (SRP) e pulveriza a lógica de negócio, tornando o roteamento complexo e a manutenção difícil.
-   **Inconsistência na Experiência do Usuário (UX):** A mistura de operações baseadas em AJAX com ações que causam recarregamento total da página (como em algumas funcionalidades de exclusão) cria uma experiência de usuário desconexa.
-   **Acoplamento Elevado:** A ausência de **Injeção de Dependência (DI)** e a instanciação direta de dependências (`new Class()`) em controllers e models tornam o código rígido, difícil de testar e de reutilizar.
-   **Lógica de Negócio no Local Errado:** Regras de negócio e acesso a dados frequentemente residem nos controllers, quando deveriam estar em camadas de serviço e repositório, respectivamente.

---

## 3. As Regras de Ouro da Modernização

Para resolver esses problemas, todos os novos módulos e a refatoração de módulos existentes **devem** seguir as regras abaixo.

### Regra 1: Unificar Controllers por Recurso (Resource-Based Controllers)

-   **NÃO FAÇA:** Criar múltiplos arquivos para um mesmo recurso.
    -   *Exemplo ruim:* `AddEmployee.php`, `EditEmployee.php`, `DeleteEmployee.php`.
-   **FAÇA:** Criar um único controller que gerencia o ciclo de vida completo de um recurso.
    -   *Exemplo bom:* `EmployeesController.php` com os métodos `create()`, `store()`, `edit()`, `update()`, `destroy()`, `show()`.

    ```php
    class EmployeesController {
        public function index() { /* Listar todos os funcionários */ }
        public function create() { /* Exibir formulário de criação */ }
        public function store() { /* Salvar novo funcionário */ }
        public function show($id) { /* Exibir um funcionário */ }
        public function edit($id) { /* Exibir formulário de edição */ }
        public function update($id) { /* Atualizar um funcionário */ }
        public function destroy($id) { /* Excluir um funcionário */ }
    }
    ```

### Regra 2: Adoção Total de AJAX para CRUD

-   **NÃO FAÇA:** Usar `POST` tradicional com recarregamento de página para ações de formulário.
-   **FAÇA:** Todas as operações de CRUD (criar, editar, excluir) devem ser realizadas via requisições **AJAX**. As respostas do servidor devem ser em formato **JSON**, contendo o status da operação e, se necessário, o HTML de notificações ou dados para atualização dinâmica da interface.

    ```javascript
    // Exemplo de requisição AJAX para exclusão
    fetch('/employees/' + employeeId, {
        method: 'DELETE',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Exibe notificação e remove a linha da tabela
            showNotification(data.message, 'success');
            document.getElementById('employee-row-' + employeeId).remove();
        } else {
            showNotification(data.message, 'error');
        }
    });
    ```

### Regra 3: Implementar Camadas de Serviço e Repositório

-   **NÃO FAÇA:** Colocar lógica de negócio ou consultas ao banco de dados diretamente nos controllers.
-   **FAÇA:** Isolar as responsabilidades em camadas claras:
    -   **Controller:** Responsável por receber a requisição HTTP, validar a entrada básica e orquestrar a chamada para a camada de serviço. **NUNCA** deve conter lógica de negócio complexa ou queries SQL.
    -   **Service Layer (`EmployeeService.php`):** Contém a lógica de negócio pura (validações, cálculos, orquestração de múltiplas ações). **NÃO** deve acessar o banco de dados diretamente, mas sim através de um repositório.
    -   **Repository Layer (`EmployeeRepository.php`):** É a única camada que pode interagir com o banco de dados. Centraliza todas as consultas SQL ou de ORM para um determinado recurso.

    ```php
    // No Controller
    public function store(Request $request) {
        $data = $request->getValidatedData();
        $employee = $this->employeeService->createEmployee($data);
        return $this->jsonResponse(['success' => true, 'employee' => $employee]);
    }

    // No EmployeeService
    public function createEmployee(array $data) {
        // Lógica de negócio aqui (ex: validar se CPF já existe)
        if ($this->employeeRepository->findByCpf($data['cpf'])) {
            throw new BusinessException('CPF já cadastrado.');
        }
        // Mais lógica...
        return $this->employeeRepository->create($data);
    }

    // No EmployeeRepository
    public function create(array $data) {
        // Lógica de acesso ao banco de dados aqui
        $stmt = $this->pdo->prepare("INSERT INTO ...");
        $stmt->execute($data);
        return $this->find($this->pdo->lastInsertId());
    }
    ```

### Regra 4: Utilizar Injeção de Dependência (DI)

-   **NÃO FAÇA:** Instanciar dependências manualmente (`$service = new MyService();`).
-   **FAÇA:** Injetar dependências através do construtor. Isso desacopla o código e facilita os testes. O ideal é usar um contêiner de DI para automatizar este processo.

    ```php
    class EmployeesController {
        private $employeeService;

        // Dependência é injetada, não criada
        public function __construct(EmployeeService $employeeService) {
            $this->employeeService = $employeeService;
        }

        // ... métodos do controller
    }
    ```

### Regra 5: Padronizar o Uso de Serviços Essenciais

-   **`NotificationService`:** **SEMPRE** utilize este serviço para enviar feedback (sucesso, erro, aviso) ao usuário. Evite o uso direto de `$_SESSION['msg']`.
-   **`LoggerService`:** **SEMPRE** utilize este serviço para registrar eventos importantes, erros e exceções. Logs estruturados são fundamentais para depuração e monitoramento.

---

## 4. Plano de Ação para Refatoração

A modernização do sistema será um processo gradual. A seguinte abordagem deve ser adotada ao refatorar um módulo legado:

1.  **Análise do Módulo Legado:**
    *   Identifique todos os controllers de ação (`Add*`, `Edit*`, `Delete*`, etc.) associados ao recurso.
    *   Mapeie as rotas e os endpoints AJAX existentes.
    *   Analise os Models para entender a lógica de negócio e o acesso a dados.

2.  **Criação da Nova Estrutura:**
    *   Crie um novo **Controller unificado** para o recurso (ex: `MeuRecursoController`).
    *   Crie as camadas de **Serviço** (`MeuRecursoService`) e **Repositório** (`MeuRecursoRepository`).

3.  **Migração da Lógica:**
    *   Mova a lógica de acesso ao banco de dados dos Models antigos para o `MeuRecursoRepository`.
    *   Mova a lógica de negócio dos controllers e models antigos para o `MeuRecursoService`.
    *   Implemente os métodos de CRUD (`store`, `update`, `destroy`, etc.) no `MeuRecursoController`, orquestrando as chamadas para o `MeuRecursoService`.

4.  **Atualização do Frontend:**
    *   Refatore o código JavaScript para fazer requisições AJAX para os novos endpoints do controller unificado.
    *   Garanta que a UI seja atualizada dinamicamente com base nas respostas JSON do servidor.

5.  **Limpeza e Finalização:**
    *   Após a migração e testes completos, **remova os arquivos de controller e model legados**.
    *   Atualize a documentação específica do módulo (`ANALISE_MODULO_*.md`) para refletir a nova arquitetura, detalhando as rotas, a estrutura de serviço/repositório e o fluxo de dados.

Seguir este guia de forma consistente resultará em um sistema Mercury mais robusto, moderno e prazeroso de se trabalhar.
