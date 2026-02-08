# Análise Técnica - Módulo de Ajuste de Estoque

**Data:** 06 de Novembro de 2025
**Autor:** Gemini
**Versão:** 2.0 (Análise Orientada a SOLID)

## 1. Visão Geral

Esta análise foca nas funcionalidades de **cadastro** e **atualização** do módulo de Ajuste de Estoque. A implementação atual, distribuída entre `AddAdjustment.php`, `EditAdjustment.php` e `Adjustments.php`, apresenta desvios significativos dos padrões de arquitetura e dos princípios de design **SOLID**.

O módulo, embora funcional, sofre de fragmentação e acoplamento elevado, resultando em um código difícil de manter, testar e estender. Esta análise foi reestruturada para detalhar as violações de cada princípio SOLID e propor soluções alinhadas às melhores práticas do projeto Mercury.

---

## 2. Análise Arquitetural sob a Ótica SOLID

### S - Princípio da Responsabilidade Única (SRP) - VIOLADO

Uma classe deve ter apenas um motivo para mudar.

*   **Violação:** O `Adjustments.php` acumula múltiplas responsabilidades:
    1.  **Roteamento de Ações:** O método `list()` age como um mini-roteador, decidindo se deve listar, buscar ou carregar a página inicial com base em parâmetros `GET`. A responsabilidade de roteamento pertence à camada de roteamento da aplicação, não a um método de controller.
    2.  **Fornecimento de Dados para Outros Módulos:** O método `getEmployees()` serve como um endpoint AJAX para buscar funcionários. A lógica de negócio para buscar funcionários não tem relação com ajustes de estoque e não deveria residir neste controller.
*   **Impacto:** A classe `Adjustments` se torna um "canivete suíço", difícil de entender e manter. Uma alteração na busca de funcionários exige a modificação de um controller de ajustes, o que é contraintuitivo.

### O - Princípio Aberto/Fechado (OCP) - VIOLADO

As entidades de software devem ser abertas para extensão, mas fechadas para modificação.

*   **Violação:** A lógica de criação e atualização está diretamente acoplada nos Models `AdmsAddAdjustments` e `AdmsEditAdjustment`. Se um novo requisito surgisse (ex: "notificar o setor de auditoria via e-mail apenas para ajustes de saída acima de R$ 1.000"), seria necessário **modificar** essas classes existentes.
*   **Impacto:** O sistema é frágil. Cada novo requisito de negócio força alterações em código já existente e funcional, aumentando o risco de introduzir bugs.
*   **Solução Proposta:** Uma arquitetura baseada em Serviços permitiria estender o comportamento. Poderíamos usar o padrão *Strategy* ou *Decorator* no `AdjustmentService` para adicionar novas etapas ao processo de ajuste (como a notificação condicional) sem alterar a lógica principal de salvamento.

### L - Princípio da Substituição de Liskov (LSP) - POTENCIALMENTE VIOLADO

Uma classe derivada deve ser substituível por sua classe base sem quebrar a aplicação.

*   **Violação:** A existência de dois Models distintos para listagem e busca (`AdmsListAdjustments` e `CpAdmsSearchAdjustments`) sugere uma violação. Embora não haja uma herança direta visível, eles realizam operações conceitualmente similares (retornar uma lista de ajustes), mas com implementações separadas. Se tivéssemos uma interface `AdjustmentProviderInterface`, ambos deveriam implementá-la e ser intercambiáveis.
*   **Impacto:** Duplicação de código e dificuldade em tratar diferentes tipos de busca de forma polimórfica.
*   **Solução Proposta:** A criação de um `AdjustmentRepository` único com um método `findAll(array $filters)` unificaria essa lógica, aderindo melhor ao LSP, pois qualquer chamada a este método se comportaria de forma consistente, independentemente dos filtros aplicados.

### I - Princípio da Segregação de Interfaces (ISP) - VIOLADO

Clientes não devem ser forçados a depender de interfaces que não utilizam.

*   **Violação:** Como os controllers instanciam classes concretas (`new AdmsEditAdjustment()`), eles se acoplam a **todos** os métodos públicos dessas classes, mesmo que utilizem apenas um ou dois. Por exemplo, `Adjustments::getEmployees()` instancia `AdmsEditAdjustment` inteiro, quando na verdade só precisa de um método que retorne funcionários por loja.
*   **Impacto:** Acoplamento desnecessário. Aumenta o impacto de qualquer alteração feita nos Models, mesmo em métodos não relacionados ao que o controller utiliza.
*   **Solução Proposta:** Definir interfaces específicas, como `EmployeeFinderInterface` com um método `findByStore(int $storeId)`. O controller dependeria apenas dessa pequena interface, e não de uma classe monolítica.

### D - Princípio da Inversão de Dependência (DIP) - VIOLADO

Módulos de alto nível não devem depender de módulos de baixo nível. Ambos devem depender de abstrações.

*   **Violação:** Esta é a violação mais clara. Os controllers (módulos de alto nível) dependem diretamente de implementações concretas dos Models (módulos de baixo nível) ao instanciá-los com `new`.
*   **Impacto:** O código é impossível de testar unitariamente, pois não podemos substituir os Models reais por *mocks*. O acoplamento é máximo, tornando a refatoração e a manutenção extremamente difíceis.
*   **Solução Proposta:** Implementar **Injeção de Dependência (DI)**. O controller deve receber suas dependências (idealmente, interfaces como `AdjustmentServiceInterface`) em seu construtor.

---

## 3. Recomendações para Refatoração (Guiadas pelo SOLID)

1.  **Unificar em um `AdjustmentsController` (SRP):** Consolidar todas as ações (CRUD) em um único controller "magro", cuja única responsabilidade é receber requisições HTTP e delegar para a camada de serviço.

2.  **Criar `AdjustmentService` (SRP, OCP):** Mover toda a orquestração da lógica de negócio (validação, uso de logs, notificações) para esta classe. Isso isola a lógica de negócio e a torna extensível.

3.  **Criar Repositórios com Interfaces (LSP, ISP, DIP):**
    *   **`AdjustmentRepositoryInterface`** e sua implementação `AdjustmentRepository`: Centralizar todo o acesso ao banco de dados para ajustes, unificando listagem e busca.
    *   **`EmployeeRepositoryInterface`** e sua implementação `EmployeeRepository`: Isolar a lógica de busca de funcionários.

4.  **Implementar Injeção de Dependência (DIP):** O `AdjustmentsController` deve receber as interfaces dos serviços e repositórios em seu construtor. Um contêiner de DI poderia gerenciar a criação dessas dependências.

5.  **Uso Obrigatório dos Serviços Padrão (SRP):** Garantir que o `AdjustmentService` utilize o `NotificationService` e o `LoggerService`, centralizando essas responsabilidades transversais.

## 4. Conclusão

O módulo de Ajuste de Estoque, em seu estado atual, representa um débito técnico significativo devido à violação sistemática dos princípios SOLID. A refatoração proposta, focada na criação de camadas de Serviço e Repositório e no uso de Injeção de Dependência, é crucial para transformar o módulo em um componente robusto, manutenível, testável e alinhado com as práticas de engenharia de software modernas adotadas pelo projeto Mercury.