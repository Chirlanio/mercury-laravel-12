# Formato do XLSX do Plano de Contas (CIGAM)

**Fonte:** export do módulo contábil do CIGAM (Grupo Meia Sola).
**Arquivo de referência:** `docs/Plano de Contas.xlsx` (86 KB, 2026-04-20, 1.131 linhas incluindo cabeçalho).

Este documento congela o contrato entre o ERP e o importador
`App\Services\DRE\ChartOfAccountsImporter`. Se o ERP mudar o layout
(colunas, posição, nomes), revisar aqui antes de ajustar o importador.

---

## 1. Estrutura geral

- **1 aba por arquivo** (nome variável — ex: `CGtmp976`). Importador lê a primeira aba, não importa o nome.
- **Linha 1 = cabeçalho** com 21 colunas.
- **Linha 2 = registro-mestre do plano** — tem apenas `Codigo Reduzido` preenchido; `V_Grupo`, `Classific conta` e `Nome conta` são nulos. É o "cabeçalho lógico" do plano no ERP (equivale à FK que as outras linhas carregam em `V_Codigo plan.con`). **Ignorar no importador.**
- **Linha 3 em diante = contas efetivas** (sintéticas e analíticas) e centros de custo.

## 2. Colunas relevantes para a aplicação

As 21 colunas do CIGAM são padronizadas; a maioria não é usada pela DRE. Mapeamento do que importa:

| Coluna XLSX (letra) | Header | Destino | Tipo | Notas |
|---|---|---|---|---|
| A | `Codigo Reduzido` | `reduced_code` | string | Chave de upsert. Curto (4 dígitos típico). **Estável** entre reimportações. |
| C | `Tipo` | `type` | `S` \| `A` | S = Sintética (totalizadora, não recebe lançamento). A = Analítica (folha, recebe lançamento). Mapeia para enum `AccountType`. |
| D | `V_Grupo` | `account_group` (1..5) **ou** roteia para CC (8) | int 1..5 ou 8 | **1** Ativo, **2** Passivo, **3** Receitas, **4** Custos/Despesas, **5** Resultado, **8** Centros de Custo. Linha com `V_Grupo=8` NÃO é conta — é CC e vai para `cost_centers`. |
| E | `Classific conta` | `code` | string | Formato `X.X.X.XX.XXXXX` para folha analítica. Quantidade de pontos = `classification_level`. Vazio na linha-mestre. |
| F | `Nome conta` | `name` | string | Descrição em PT maiúsculas ("CAIXA TESOURARIA", "SALARIOS E ORDENADOS"). |
| I | `VL_Ativa` | `is_active` | `True`/`False` (string) | Conta ativa no ERP. Importador converte para boolean. |
| J | `Natureza Saldo` | `balance_nature` | `D` \| `C` \| `A` | D = Devedora, C = Credora, A = Ambas. No export analisado está tudo `A` (reservado pelo ERP), guardamos mesmo assim pra quando mudar. |
| N | `VL_Conta Resultado` | `is_result_account` | `True`/`False` (string) | Flag do ERP. Mesmo quando `False`, se `account_group ∈ {3,4,5}` derivamos `true`. |

Colunas **ignoradas** (mantemos documentadas para não esquecer o que existe):

- **B** `V_Codigo plan.con`: FK para o registro-mestre (linha 2). Todos apontam para o mesmo id; sem valor para a DRE.
- **G** `Codigo Alternativo`: segundo código interno do ERP (opcional).
- **H** `Livre 14`: campo livre pouco populado.
- **K** `Unidade Resultado`: flag interno CIGAM.
- **L** `Saldo demons acu`: flag de saldo acumulado (só análise fiscal).
- **M** `Origem conta`: `I` = interna, outro = externa. Pouco relevante.
- **O..U** (`V_Tipo LALUR`, `V_Código Fixo LALUR`, `VL_Parte B LALUR`, `V_Funcao conta`, `V_Funcionamento conta`, `V_Naturez Subconta`, `DescNatureza`): campos fiscais LALUR do ERP. Fora do escopo DRE gerencial.

## 3. Hierarquia do `code`

O `code` é o identificador hierárquico do ERP. Cada "nível" é um segmento separado por ponto:

```
1                   ← nível 0 (grupo macro ATIVO, sintético)
1.1                 ← nível 1 (ATIVO CIRCULANTE, sintético)
1.1.1               ← nível 2 (DISPONIVEL, sintético)
1.1.1.01            ← nível 3 (grupo analítico, sintético)
1.1.1.01.00016      ← nível 4 (CAIXA TESOURARIA, analítica — folha)
```

- `classification_level` = número de pontos em `code`.
- `parent_id` é derivado: **pai é o registro cujo `code` é o prefixo até o penúltimo ponto.** Ex: pai de `1.1.1.01.00016` é `1.1.1.01`. Pai de `1.1.1` é `1.1`. A raiz (`1`) não tem pai.
- A resolução do `parent_id` acontece em **segunda passada** no importador — depois que todos os upserts ocorrem — para não depender da ordem de linhas no arquivo.

### Centros de custo (V_Grupo = 8)

Mesmo formato de `code` hierárquico, com seus próprios níveis:

```
8                   ← raiz do bloco (sintético)
8.1                 ← CENTROS DE CUSTO (sintético)
8.1.01              ← MARKETING (sintético, depto)
8.1.01.01           ← Marketing - Schutz Riomar Recife (analítico)
8.1.01.02           ← Marketing - Arezzo Kennedy (analítico)
...
```

Importador roteia **todas** as linhas `V_Grupo=8` para `cost_centers` (não importa o nível). `parent_id` também é resolvido por prefixo dentro de `cost_centers`.

## 4. Contagens do arquivo de referência (`Plano de Contas.xlsx`)

```
Total de linhas (sem cabeçalho):  1.130
  Linha-mestre (ignorada):             1

Por V_Grupo (após ignorar mestre):
  1 — Ativo:                       219
  2 — Passivo:                     361
  3 — Receitas:                     50
  4 — Custos/Despesas:             204
  5 — Resultado:                     6
  8 — Centros de Custo:            289
  total:                         1.129

Por Tipo:
  S — Sintética:                   139
  A — Analítica:                   990
```

Então o importador plantado contra este arquivo deve produzir:
- `chart_of_accounts`: 840 registros (grupos 1..5).
- `cost_centers`: 289 registros (grupo 8) novos, além dos CCs que já existirem no banco.

## 5. Idempotência e desativação por sumiço

A chave de upsert é `reduced_code` (estável no ERP). Rodar o importador duas vezes contra o mesmo arquivo não cria duplicatas.

Se uma linha some do arquivo em relação à última importação:
- Contas/CCs com `external_source = <source atual>` e `reduced_code` ausente no arquivo novo são marcadas `is_active = false` (não deletadas).
- Contas/CCs sem `external_source` (ex: criadas manualmente em dev) **não** são tocadas — respeitamos dados manuais.

## 6. Limitações conhecidas (2026-04-22)

- Encoding do ERP é UTF-8 (confirmado nos nomes: "FORNECEDORES", "SALÁRIOS E ORDENADOS"). Se o export futuro vier em Windows-1252, o importador precisa converter.
- `Natureza Saldo` está totalmente `A` (ambas) no export analisado. O ERP suporta D/C — se começarem a popular, o campo já estará lá.
- Não há coluna `parent_code` explícita — derivamos do prefixo. Se o ERP mudar convenção de separador, quebra.
- Não há flag `V_Data Criação` útil — não dá para auditar criação por data de uma conta específica.
