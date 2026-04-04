# Guia do Usuário — Ordens de Pagamento

**Data:** 28 de Fevereiro de 2026
**Versao:** 1.0
**Autor:** Equipe Mercury

---

## 1. Introducao

O modulo de **Ordens de Pagamento** permite gerenciar todo o ciclo de vida dos pagamentos a fornecedores do Grupo Meia Sola. Desde a solicitacao inicial ate a confirmacao do pagamento, cada ordem passa por 4 etapas organizadas em um quadro Kanban visual.

**Principais funcionalidades:**
- Cadastro e acompanhamento de ordens de pagamento
- Movimentacao entre etapas via arrastar e soltar (drag-and-drop)
- Controle de parcelas (boletos parcelados)
- Rateio de custos entre centros de custo
- 7 relatorios gerenciais e operacionais
- Exportacao para Excel
- Exclusao com motivo e rastreabilidade completa

---

## 2. Tela Principal (Kanban)

Ao acessar o modulo, voce vera um quadro Kanban com 4 colunas, cada uma representando uma etapa do fluxo de pagamento:

| Coluna | Nome | Descricao |
|--------|------|-----------|
| 1 | Solicitacoes | Ordens recem-cadastradas aguardando processamento |
| 2 | Reg. Fiscal | Ordens com nota fiscal registrada, em processamento |
| 3 | Lancado | Ordens lancadas no banco, aguardando pagamento |
| 4 | Pago | Ordens com pagamento confirmado |

### KPI Cards

No topo de cada coluna, um card exibe:
- **Quantidade** de ordens naquela etapa
- **Valor total** das ordens (em R$)

Esses valores sao atualizados automaticamente ao aplicar filtros ou mover ordens.

### Botoes de Acao nos Cards

Cada card de ordem exibe botoes de acordo com suas permissoes:
- **Visualizar** (icone de olho) — Abre os detalhes completos
- **Editar** (icone de lapis) — Abre o formulario de edicao
- **Excluir** (icone de lixeira) — Abre a confirmacao de exclusao

No celular, esses botoes ficam agrupados em um menu suspenso (tres pontos).

---

## 3. Como Criar uma Ordem de Pagamento

Passo 1. Clique no botao **Nova Ordem** no canto superior direito da tela.

Passo 2. Preencha o formulario organizado em 6 secoes:

### Secao 1: Informacoes Basicas

| Campo | Obrigatorio | Descricao |
|-------|:-----------:|-----------|
| Area | Sim | Departamento responsavel (ex: Operacoes, Marketing) |
| Centro de Custo | Sim | Centro de custo vinculado ao gasto |
| Marca | Sim | Marca relacionada ao pagamento |
| Data de Pagamento | Sim | Data prevista para o pagamento (ver regras na secao 13) |
| Aprovador | Sim | Gestor que aprovara a ordem |
| Motivo Gerencial | Nao | Classificacao gerencial para analise orcamentaria |
| Loja | Nao | Loja vinculada (se aplicavel) |

### Secao 2: Fornecedor e Valores

| Campo | Obrigatorio | Descricao |
|-------|:-----------:|-----------|
| Fornecedor | Sim | Selecione o fornecedor do pagamento |
| Valor Total | Sim | Valor total da ordem (formato: 1.234,56) |
| Nota Fiscal | Nao | Numero da nota fiscal (preenchido na transicao) |
| Num. Lancamento | Nao | Numero de lancamento fiscal (preenchido na transicao) |
| Descricao | Sim | Descricao do servico ou produto |

### Secao 3: Pagamento e Adiantamento

| Campo | Obrigatorio | Descricao |
|-------|:-----------:|-----------|
| Forma de Pagamento | Sim | Boleto, PIX, Transferencia, Cartao, Dinheiro, Deposito ou QR Code |
| Adiantamento | Sim | Se houve adiantamento ao fornecedor (Sim/Nao) |
| Valor do Adiantamento | Condicional | Obrigatorio se Adiantamento = Sim |
| Adiantamento Pago? | Sim | Status do adiantamento (Sim/Nao) |
| Comprovante? | Sim | Se existe comprovante de pagamento (Sim/Nao) |

### Secao 4: Dados Bancarios

| Campo | Obrigatorio | Descricao |
|-------|:-----------:|-----------|
| Banco | Nao | Banco do favorecido |
| Agencia | Nao | Numero da agencia |
| Tipo de Conta | Nao | Corrente ou Poupanca |
| Conta | Nao | Numero da conta |
| Titular | Nao | Nome do titular da conta |
| CPF/CNPJ | Nao | Documento do titular |
| Tipo de Chave PIX | Nao | CPF, CNPJ, Email, Telefone ou Aleatoria |
| Chave PIX | Nao | Valor da chave PIX |

Nota: Os dados bancarios sao preenchidos conforme a forma de pagamento. Na transicao de "Reg. Fiscal" para "Lancado", os campos bancarios relevantes se tornam obrigatorios.

### Secao 5: Rateio de Custos (Opcional)

Se o custo precisa ser dividido entre centros de custo, ative o rateio. Veja a secao 9 para mais detalhes.

### Secao 6: Observacoes e Documentos

| Campo | Obrigatorio | Descricao |
|-------|:-----------:|-----------|
| Observacoes | Nao | Informacoes adicionais em texto livre |
| Documentos | Sim | Anexe pelo menos um arquivo (nota fiscal, contrato, etc.) |

Formatos aceitos: PNG, JPG, PDF, DOC, DOCX, XLS, XLSX, RAR.

Passo 3. Clique em **Salvar** para criar a ordem.

A ordem sera criada na coluna "Solicitacoes" (Backlog). O gestor aprovador recebera uma notificacao automatica e um email informando sobre a nova ordem.

---

## 4. Como Mover uma Ordem (Transicoes)

Existem duas formas de mover uma ordem entre colunas:

**Opcao A — Arrastar e soltar:** Clique e arraste o card da ordem de uma coluna para outra.

**Opcao B — Botoes de transicao:** Dentro do card, use os botoes de avancar ou retornar.

Ao mover, um modal sera exibido solicitando informacoes adicionais:

### Transicao: Solicitacoes para Reg. Fiscal (1 para 2)

| Campo | Obrigatorio | Descricao |
|-------|:-----------:|-----------|
| Numero da Nota Fiscal | Sim | NF relacionada ao pagamento |
| Numero de Lancamento | Sim | Numero do lancamento fiscal |

### Transicao: Reg. Fiscal para Lancado (2 para 3)

| Campo | Obrigatorio | Descricao |
|-------|:-----------:|-----------|
| Numero de Lancamento | Sim | Numero do lancamento fiscal |

Campos adicionais variam conforme a forma de pagamento:

- **PIX:** Tipo de Chave PIX e Chave PIX
- **Transferencia/Deposito/Outros:** Banco, Agencia e Conta
- **Boleto:** Nenhum campo adicional

### Transicao: Lancado para Pago (3 para 4)

| Campo | Obrigatorio | Descricao |
|-------|:-----------:|-----------|
| Data do Pagamento Efetivo | Sim | Data em que o pagamento foi realizado |

### Retornos (Mover para Etapa Anterior)

Ao retornar uma ordem para uma etapa anterior:

| Campo | Obrigatorio | Descricao |
|-------|:-----------:|-----------|
| Motivo do Retorno | Sim | Justificativa para devolver a ordem |

Nota: Todo retorno requer justificativa obrigatoria e fica registrado no historico da ordem.

---

## 5. Como Editar uma Ordem

Passo 1. No card da ordem, clique no botao **Editar** (icone de lapis).

Passo 2. O formulario de edicao sera carregado em um modal com os dados atuais da ordem.

Passo 3. Modifique os campos desejados.

Passo 4. Clique em **Salvar** para confirmar as alteracoes.

Nota: Se voce nao alterar a data de pagamento, a validacao de prazo minimo nao sera reaplicada. Isso permite editar outros campos sem precisar ajustar uma data ja aprovada.

### Gerenciar Arquivos na Edicao

- **Adicionar arquivo:** Use o campo de upload para anexar novos documentos.
- **Remover arquivo:** Clique no botao de exclusao (icone X) ao lado do arquivo que deseja remover.

---

## 6. Como Visualizar uma Ordem

Passo 1. No card da ordem, clique no botao **Visualizar** (icone de olho).

Passo 2. O modal de detalhes exibe as seguintes informacoes organizadas em abas:

### Aba: Dados da Ordem
Exibe todos os campos preenchidos: area, fornecedor, valores, dados bancarios, datas, observacoes e arquivos anexados.

### Aba: Parcelas
Lista todas as parcelas da ordem com:
- Numero da parcela
- Valor
- Data de vencimento
- Status (Pendente / Paga)
- Data de pagamento efetivo (se paga)

Permite marcar ou desmarcar parcelas como pagas (ver secao 8).

### Aba: Rateio de Custos
Mostra a distribuicao de custos entre centros de custo, com percentuais e valores alocados.

### Aba: Historico
Timeline completa de mudancas de status, incluindo:
- Data e hora de cada transicao
- Usuario responsavel
- Status anterior e novo
- Motivo (quando aplicavel)

Tambem exibe os ultimos 20 registros de auditoria (audit trail) com acoes realizadas na ordem.

---

## 7. Como Excluir uma Ordem

O sistema possui 3 niveis de exclusao, dependendo do seu perfil e do status da ordem:

### Nivel 1 — Exclusao Simples
**Quando:** Voce e o criador da ordem, a ordem esta em "Solicitacoes" e nunca foi editada.
**Como:** Clique em Excluir e confirme. Nao e necessario informar motivo.

### Nivel 2 — Exclusao com Justificativa
**Quando:** Voce tem perfil financeiro (nivel de acesso ate 5) e a ordem esta em "Solicitacoes" ou "Reg. Fiscal".
**Como:** Clique em Excluir, preencha o campo de motivo obrigatorio e confirme.

### Nivel 3 — Exclusao Administrativa
**Quando:** Voce e Super Admin e a ordem esta em "Lancado" ou "Pago".
**Como:** Clique em Excluir, preencha o motivo obrigatorio, marque as duas caixas de confirmacao e confirme.

Nota: A exclusao e do tipo "soft-delete" — os dados nao sao apagados do banco, apenas marcados como excluidos. O Super Admin pode restaurar ordens excluidas.

---

## 8. Parcelas e Pagamentos

Quando a forma de pagamento envolve parcelas (boleto parcelado), o sistema permite gerenciar cada parcela individualmente.

### Criar Parcelas

Passo 1. No formulario de criacao ou edicao, selecione a forma de pagamento que suporta parcelas.

Passo 2. No campo "Quantidade de Parcelas", informe o numero desejado (1 a 12).

Passo 3. Para cada parcela, preencha:
- **Valor da parcela** (formato: 1.234,56)
- **Data de vencimento** (deve respeitar as regras de data de pagamento)

Nota: A data da primeira parcela deve ser igual ou posterior a data de pagamento principal da ordem.

### Marcar Parcela como Paga

Passo 1. Abra a visualizacao da ordem (botao Visualizar).

Passo 2. Na aba "Parcelas", localize a parcela desejada.

Passo 3. Informe a data de pagamento efetivo.

Passo 4. Clique no botao **Marcar como Paga**.

O card da parcela mudara para verde, indicando que foi paga.

### Desmarcar Parcela

Caso necessario, clique no botao **Desmarcar Pagamento** para reverter o status da parcela para "Pendente".

---

## 9. Rateio de Custos

O rateio permite dividir o custo de uma ordem entre multiplos centros de custo.

### Ativar o Rateio

Passo 1. No formulario de criacao ou edicao, localize a secao "Rateio de Custos".

Passo 2. Ative o botao de alternancia (toggle).

Passo 3. A tabela de alocacao sera exibida.

### Adicionar Linha de Rateio

Passo 1. Clique no botao **Adicionar Linha**.

Passo 2. Selecione o centro de custo.

Passo 3. Informe o percentual de rateio (ex: 60%).

Passo 4. O valor sera calculado automaticamente com base no percentual e no valor total da ordem.

### Dividir Igualmente

Clique no botao **Dividir Igualmente** para distribuir o rateio de forma proporcional entre todas as linhas.

Exemplo: Com 3 centros de custo, cada um recebera 33,33% (o ultimo recebe o centavo restante para garantir 100%).

### Validacao do Rateio

Para que o rateio seja aceito:
- Cada linha deve ter um centro de custo selecionado
- Cada percentual deve ser maior que 0%
- A soma dos percentuais deve ser exatamente 100%
- A soma dos valores deve ser igual ao valor total da ordem

A tabela exibe os totais em tempo real:
- **Verde:** Totais corretos (100% e valor total batendo)
- **Vermelho:** Totais incorretos (ajuste necessario)

### Atualizacao Automatica

Se voce alterar o valor total da ordem apos configurar o rateio, os valores alocados serao recalculados automaticamente mantendo os percentuais.

---

## 10. Relatorios

O modulo oferece 7 relatorios divididos em duas categorias:

### Relatorios Operacionais

| Relatorio | Descricao | Filtros Especificos |
|-----------|-----------|-------------------|
| Parcelas Vencidas | Lista parcelas com data de vencimento no passado e ainda nao pagas | Intervalo de datas |
| Parcelas a Vencer | Lista parcelas que vencem nos proximos N dias | Dias (padrao: 30) |
| Fluxo de Pagamentos | Totais mensais agrupados por status | Ano |

### Relatorios Gerenciais

| Relatorio | Descricao | Filtros Especificos |
|-----------|-----------|-------------------|
| Tempo de Resolucao (SLA) | Tempo medio entre cada transicao de status | Intervalo de datas |
| Gastos por Area/CC | Valores agrupados por area e centro de custo | Intervalo de datas |
| Top Fornecedores | Ranking dos maiores fornecedores por valor | Top N (5 a 100) |
| Por Forma de Pagamento | Distribuicao de valores por forma de pagamento | Intervalo de datas |

### Filtros Comuns a Todos os Relatorios

| Filtro | Descricao |
|--------|-----------|
| Centro de Custo | Filtra por centro de custo especifico |
| Motivo Gerencial | Filtra por classificacao gerencial |
| Busca | Texto livre (fornecedor, area, descricao, ID) |

### Como Gerar um Relatorio

Passo 1. Clique no botao **Relatorios** no cabecalho da tela.

Passo 2. Selecione o tipo de relatorio desejado.

Passo 3. O modal do relatorio sera aberto. Aplique os filtros desejados.

Passo 4. O relatorio sera gerado automaticamente e exibido em formato de tabela.

### Resumo do Relatorio

Todo relatorio exibe um resumo com:
- **Quantidade** de registros encontrados
- **Valor total** dos registros

---

## 11. Exportar para Excel

### Exportar Relatorio

Passo 1. Gere um relatorio conforme descrito na secao 10.

Passo 2. Clique no botao **Exportar Excel** no modal do relatorio.

Passo 3. O arquivo XLSX sera baixado automaticamente com os dados do relatorio e os filtros aplicados.

### Imprimir Relatorio

Passo 1. Gere um relatorio conforme descrito na secao 10.

Passo 2. Clique no botao **Imprimir** no modal do relatorio.

Passo 3. Uma janela de impressao sera aberta com o relatorio formatado em A4 paisagem, incluindo cabecalho da empresa, tabela de dados e rodape.

### Baixar Arquivos da Ordem

Passo 1. Abra a visualizacao da ordem.

Passo 2. Na secao de documentos, clique no botao de download.

Se a ordem possui um unico arquivo, o download sera direto. Se possui multiplos arquivos, eles serao empacotados em um arquivo ZIP.

---

## 12. Busca e Filtros

### Campos de Filtro Disponiveis

| Filtro | Descricao |
|--------|-----------|
| Busca geral | Pesquisa por valor, fornecedor, CNPJ, ID da ordem, area ou centro de custo |
| Data inicial / Data final | Intervalo de data de pagamento prevista |
| Motivo Gerencial | Filtra por classificacao gerencial |
| Centro de Custo | Filtra por centro de custo |
| Data Pago De / Data Pago Ate | Intervalo de data de pagamento efetivo |

### Como Pesquisar

Passo 1. Preencha um ou mais campos de filtro na barra acima do Kanban.

Passo 2. A busca e acionada automaticamente apos 400 milissegundos de pausa na digitacao.

Passo 3. O Kanban sera atualizado mostrando apenas as ordens que correspondem aos filtros.

Passo 4. Os KPI cards tambem serao recalculados com base nos resultados filtrados.

### Limpar Filtros

Clique no botao **Limpar** para remover todos os filtros e voltar a visualizacao completa.

---

## 13. Regras de Data de Pagamento

O sistema aplica regras automaticas para garantir que as ordens sejam cadastradas com antecedencia suficiente para processamento:

### Regra 1 — Prazo Minimo de 7 Dias

A data de pagamento deve ser no minimo 7 dias a partir da data atual.

Exemplo: Se hoje e 28/02/2026 (sexta-feira), a data mais proxima permitida e 07/03/2026 (sabado), que na pratica sera a segunda-feira 09/03/2026.

### Regra 2 — Corte Semanal (Quarta-feira 12h)

Para pagamentos na semana seguinte, o cadastro deve ser feito ate quarta-feira ao meio-dia:

- **Antes de quarta 12h:** Pode agendar para a proxima segunda-feira (respeitando a regra de 7 dias).
- **Apos quarta 12h:** O pagamento so pode ser agendado para a segunda-feira da semana seguinte (ou mais adiante).

### Validacao na Edicao

Ao editar uma ordem, se a data de pagamento nao for alterada, a validacao de prazo minimo nao e reaplicada. Isso permite editar outros campos sem precisar ajustar datas ja aceitas.

---

## 14. Perguntas Frequentes

**1. Posso mover uma ordem de "Pago" de volta para etapas anteriores?**
Sim, mas apenas o Super Admin pode retornar uma ordem de "Pago" para "Lancado". Um motivo obrigatorio deve ser informado, e a acao fica registrada no historico.

**2. O que acontece quando excluo uma ordem?**
A exclusao e do tipo "soft-delete" — os dados sao preservados no banco de dados, mas a ordem deixa de aparecer nas listagens. O Super Admin pode restaurar ordens excluidas.

**3. Posso editar uma ordem que esta na coluna "Pago"?**
Sim, desde que voce tenha permissao de edicao. Todos os campos podem ser alterados.

**4. Como sei se uma parcela esta vencida?**
Na listagem Kanban, parcelas vencidas sao destacadas em vermelho no card da ordem. Voce tambem pode usar o relatorio "Parcelas Vencidas" para uma lista completa.

**5. O rateio de custos e obrigatorio?**
Nao. O rateio e uma funcionalidade opcional. Se nao ativado, o custo total sera atribuido ao centro de custo informado no campo principal.

**6. Quantas parcelas posso cadastrar?**
O sistema aceita de 1 a 12 parcelas por ordem.

**7. Quem recebe notificacao quando uma ordem e criada?**
O gestor aprovador recebe um email e uma notificacao em tempo real (via WebSocket). Usuarios com perfil financeiro da mesma loja tambem sao notificados.

**8. Posso exportar os dados do Kanban inteiro para Excel?**
Sim. Use o botao de exportacao disponivel na tela principal para gerar uma planilha com todas as ordens visiveis. Para dados analiticos, utilize os relatorios (secao 10).

**9. O que significa o icone de estrela/destaque no card?**
Indica que a ordem possui adiantamento. Outros icones indicam: comprovante anexado, parcelas cadastradas, ou ordem excluida (opacidade reduzida).

**10. Como restaurar uma ordem excluida?**
Apenas o Super Admin pode restaurar ordens. Ative a opcao "Mostrar Excluidos" no filtro e use o botao de restauracao no card da ordem.

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
**Modulo:** Ordens de Pagamento v2.0
**Ultima Atualizacao:** 28/02/2026
