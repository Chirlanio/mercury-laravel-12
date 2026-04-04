# Estratégia de Testes — Projeto Mercury

**Versão:** 1.0
**Última Atualização:** 22 de Março de 2026

---

## Framework

O projeto utiliza **PHPUnit 12.4** como framework de testes.

- **Cobertura atual:** 5.144 testes, 7.539 asserções
- **Comando principal:** `php vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/`
- **Executar módulo específico:** `php vendor/bin/phpunit tests/NomeDoModulo/`

---

## Estrutura de Diretórios

```
tests/
├── bootstrap.php                  # Setup: constantes, fixtures, autoload
├── Sales/
│   ├── AdmsSalesTest.php
│   ├── AdmsListSalesTest.php
│   └── SalesControllerTest.php
├── Vacations/
│   ├── VacationPeriodGeneratorServiceTest.php
│   └── AdmsVacationPeriodTest.php
├── StockAudit/
│   ├── AdmsStockAuditTest.php
│   └── ...
└── ...
```

Cada módulo possui seu próprio diretório dentro de `tests/`. Os arquivos seguem a convenção:

- **Models:** `{NomeDoModel}Test.php` (ex: `AdmsSalesTest.php`)
- **Controllers:** `{NomeDoController}ControllerTest.php` (ex: `SalesControllerTest.php`)
- **Services:** `{NomeDoService}Test.php` (ex: `VacationPeriodGeneratorServiceTest.php`)

---

## Bootstrap (`tests/bootstrap.php`)

O arquivo de bootstrap é responsável por:

1. Carregar o autoloader do Composer (`vendor/autoload.php`)
2. Definir constantes globais necessárias (caminhos, configurações)
3. Criar fixtures de teste (dados simulados)
4. Configurar o ambiente para execução sem dependência de sessão PHP

---

## Padrões de Teste

### 1. Testes Estruturais (Reflection-based)

Utilizam `ReflectionClass` e `ReflectionMethod` para validar a estrutura interna das classes sem executar lógica de negócio ou acessar banco de dados.

```php
public function testClassHasExpectedMethods(): void
{
    $reflection = new ReflectionClass(AdmsSales::class);
    $this->assertTrue($reflection->hasMethod('create'));
    $this->assertTrue($reflection->hasMethod('update'));
}

public function testMethodHasCorrectReturnType(): void
{
    $method = new ReflectionMethod(AdmsSales::class, 'getResult');
    $this->assertSame('bool', $method->getReturnType()->getName());
}
```

### 2. Testes de Integração (DB-dependent)

Testes que dependem de conexão com banco de dados. Estes testes verificam fluxos completos, mas podem falhar em ambientes sem banco configurado.

### 3. Mock de Sessão com SessionContext

Para simular dados de sessão sem iniciar `session_start()`, utilize `SessionContext::setTestData()`:

```php
use App\adms\Models\helper\SessionContext;

protected function setUp(): void
{
    SessionContext::setTestData([
        'usuario_id' => 1,
        'usuario_nome' => 'Teste',
        'usuario_loja' => 'Z424',
        'adms_niveis_acesso_id' => 1,
    ]);
}
```

---

## Diretrizes para Novos Testes

### Mínimo obrigatório por módulo

Cada novo módulo deve conter **no mínimo 5 testes**:

1. **Instanciação** — Verificar que a classe pode ser instanciada
2. **Estrutura** — Validar métodos e propriedades esperados
3. **Caso de sucesso** — Fluxo principal com dados válidos
4. **Caso de erro (entrada inválida)** — Dados ausentes ou malformados
5. **Caso de borda** — Limites, valores nulos, IDs inexistentes

### Boas práticas

- Usar `setUp()` para configurar dependências comuns (SessionContext, fixtures)
- Nomear métodos de teste de forma descritiva: `testCreateWithValidDataReturnsTrue`
- Não depender de ordem de execução entre testes
- Isolar testes — cada teste deve funcionar independentemente
- Utilizar `ReflectionClass` para testar métodos privados quando necessário
- Preferir asserções específicas (`assertSame`, `assertInstanceOf`) sobre genéricas (`assertTrue`)

### Exemplo completo

```php
class AdmsExemploTest extends TestCase
{
    protected function setUp(): void
    {
        SessionContext::setTestData([
            'usuario_id' => 1,
            'adms_niveis_acesso_id' => 1,
        ]);
    }

    public function testInstantiation(): void
    {
        $model = new AdmsExemplo();
        $this->assertInstanceOf(AdmsExemplo::class, $model);
    }

    public function testGetResultReturnsBool(): void
    {
        $reflection = new ReflectionMethod(AdmsExemplo::class, 'getResult');
        $this->assertSame('bool', $reflection->getReturnType()->getName());
    }

    public function testCreateWithEmptyDataReturnsFalse(): void
    {
        $model = new AdmsExemplo();
        $result = $model->create([]);
        $this->assertFalse($result);
    }
}
```

---

## Execução

```bash
# Todos os testes
php vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/

# Módulo específico
php vendor/bin/phpunit tests/Sales/

# Teste individual
php vendor/bin/phpunit tests/Sales/AdmsSalesTest.php

# Com output detalhado
php vendor/bin/phpunit --bootstrap tests/bootstrap.php --testdox tests/
```

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
