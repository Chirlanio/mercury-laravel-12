<?php
/**
 * Gerador do Manual do Usuário - Módulo de Remanejos
 *
 * Executar via CLI: php docs/generate_manual_relocation.php
 * Gera: docs/MANUAL_USUARIO_REMANEJOS.pdf
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    @page {
        margin: 25mm 20mm 25mm 20mm;
    }
    body {
        font-family: Helvetica, Arial, sans-serif;
        font-size: 11pt;
        line-height: 1.6;
        color: #333;
    }
    h1 {
        font-size: 22pt;
        color: #007bff;
        border-bottom: 3px solid #007bff;
        padding-bottom: 8px;
        margin-top: 0;
    }
    h2 {
        font-size: 16pt;
        color: #0056b3;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 5px;
        margin-top: 25px;
        page-break-after: avoid;
    }
    h3 {
        font-size: 13pt;
        color: #495057;
        margin-top: 18px;
        page-break-after: avoid;
    }
    h4 {
        font-size: 11pt;
        color: #495057;
        margin-top: 12px;
    }
    p, li {
        text-align: justify;
    }
    .cover {
        text-align: center;
        padding-top: 180px;
    }
    .cover h1 {
        font-size: 28pt;
        border: none;
        color: #007bff;
    }
    .cover .subtitle {
        font-size: 16pt;
        color: #6c757d;
        margin-top: 10px;
    }
    .cover .version {
        font-size: 12pt;
        color: #999;
        margin-top: 40px;
    }
    .cover .company {
        font-size: 11pt;
        color: #666;
        margin-top: 5px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 10px 0 15px 0;
        font-size: 10pt;
    }
    th {
        background-color: #007bff;
        color: white;
        padding: 8px 10px;
        text-align: left;
        font-weight: bold;
    }
    td {
        padding: 6px 10px;
        border-bottom: 1px solid #dee2e6;
    }
    tr:nth-child(even) td {
        background-color: #f8f9fa;
    }
    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 9pt;
        font-weight: bold;
        color: white;
    }
    .badge-primary { background: #007bff; }
    .badge-success { background: #28a745; }
    .badge-warning { background: #ffc107; color: #333; }
    .badge-danger { background: #dc3545; }
    .badge-secondary { background: #6c757d; }
    .badge-info { background: #17a2b8; }
    .alert {
        padding: 10px 15px;
        border-radius: 5px;
        margin: 10px 0;
        font-size: 10pt;
    }
    .alert-info {
        background: #d1ecf1;
        border-left: 4px solid #17a2b8;
        color: #0c5460;
    }
    .alert-warning {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        color: #856404;
    }
    .alert-danger {
        background: #f8d7da;
        border-left: 4px solid #dc3545;
        color: #721c24;
    }
    .step {
        background: #f8f9fa;
        border-left: 4px solid #007bff;
        padding: 8px 15px;
        margin: 8px 0;
    }
    .step-number {
        display: inline-block;
        width: 24px;
        height: 24px;
        background: #007bff;
        color: white;
        border-radius: 50%;
        text-align: center;
        line-height: 24px;
        font-size: 10pt;
        font-weight: bold;
        margin-right: 8px;
    }
    code {
        background: #f1f1f1;
        padding: 1px 5px;
        border-radius: 3px;
        font-family: monospace;
        font-size: 10pt;
    }
    .csv-example {
        background: #2d2d2d;
        color: #f8f8f2;
        padding: 10px 15px;
        border-radius: 5px;
        font-family: monospace;
        font-size: 9pt;
        margin: 10px 0;
        line-height: 1.8;
    }
    .footer {
        text-align: center;
        font-size: 8pt;
        color: #999;
        margin-top: 30px;
        border-top: 1px solid #dee2e6;
        padding-top: 8px;
    }
    .toc {
        margin: 20px 0;
    }
    .toc a {
        text-decoration: none;
        color: #333;
    }
    .toc li {
        margin: 5px 0;
        text-align: left;
    }
    .page-break {
        page-break-before: always;
    }
    ul, ol {
        padding-left: 20px;
    }
    li {
        margin-bottom: 4px;
    }
</style>
</head>
<body>

<!-- CAPA -->
<div class="cover">
    <h1>Manual do Usuário</h1>
    <div class="subtitle">Módulo de Remanejos de Produtos</div>
    <p style="margin-top: 20px; color: #666;">Sistema Mercury - Plataforma de Gestão</p>
    <div class="version">Versão 1.0 — Março de 2026</div>
    <div class="company">Grupo Meia Sola</div>
</div>

<!-- SUMÁRIO -->
<div class="page-break"></div>
<h1>Sumário</h1>
<div class="toc">
<ol>
    <li>Acessando o Módulo</li>
    <li>Tela Principal — Listagem de Remanejos</li>
    <li>Cadastrar Novo Remanejo</li>
    <li>Visualizar Detalhes do Remanejo</li>
    <li>Atualizar Remanejo (Registrar Envio)</li>
    <li>Excluir Remanejo</li>
    <li>Finalizar Remanejo</li>
    <li>Status do Remanejo</li>
    <li>Formato do Arquivo CSV</li>
    <li>Perguntas Frequentes</li>
</ol>
</div>

<!-- 1. ACESSANDO O MÓDULO -->
<div class="page-break"></div>
<h2>1. Acessando o Módulo</h2>

<p>Para acessar o módulo de Remanejos de Produtos:</p>

<div class="step">
    <span class="step-number">1</span>
    Faça login no sistema Mercury com seu usuário e senha.
</div>
<div class="step">
    <span class="step-number">2</span>
    No menu lateral esquerdo, clique na seção <strong>Produto</strong>.
</div>
<div class="step">
    <span class="step-number">3</span>
    Clique em <strong>Remanejos</strong> para abrir a listagem.
</div>

<div class="alert alert-info">
    <strong>Dica:</strong> Você também pode acessar diretamente pela URL: <code>/relocation/list</code>
</div>

<!-- 2. TELA PRINCIPAL -->
<h2>2. Tela Principal — Listagem de Remanejos</h2>

<p>A tela principal exibe todos os remanejos cadastrados e é composta por:</p>

<h3>2.1 Cards de Estatísticas</h3>
<p>No topo da página, quatro cards apresentam um resumo geral:</p>
<table>
    <tr><th>Card</th><th>Descrição</th></tr>
    <tr><td><strong>Total</strong></td><td>Quantidade total de remanejos cadastrados</td></tr>
    <tr><td><strong>Pendentes</strong></td><td>Remanejos aguardando envio</td></tr>
    <tr><td><strong>Itens Enviados</strong></td><td>Total de peças já enviadas</td></tr>
    <tr><td><strong>Conclusão</strong></td><td>Percentual de itens enviados em relação ao solicitado</td></tr>
</table>

<div class="alert alert-info">
    <strong>Filtros dinâmicos:</strong> Os valores dos cards atualizam automaticamente conforme os filtros de busca aplicados.
</div>

<h3>2.2 Filtros de Busca</h3>
<p>Abaixo dos cards, o painel de filtros permite localizar remanejos rapidamente:</p>
<ul>
    <li><strong>Buscar:</strong> Pesquisa por ID ou descrição do remanejo</li>
    <li><strong>Origem:</strong> Filtra pela loja de origem</li>
    <li><strong>Destino:</strong> Filtra pela loja de destino</li>
    <li><strong>Status:</strong> Filtra pelo status atual (Pendente, Concluído, etc.)</li>
</ul>
<p>Os resultados são atualizados automaticamente ao digitar ou selecionar um filtro. Use o botão <strong>Limpar</strong> para remover todos os filtros.</p>

<h3>2.3 Tabela de Remanejos</h3>
<p>A tabela exibe as seguintes informações de cada remanejo:</p>
<table>
    <tr><th>Coluna</th><th>Descrição</th></tr>
    <tr><td>#ID</td><td>Identificador único do remanejo</td></tr>
    <tr><td>Descrição</td><td>Nome/descrição do remanejo</td></tr>
    <tr><td>Origem</td><td>Loja de origem dos produtos</td></tr>
    <tr><td>Destino</td><td>Loja de destino dos produtos</td></tr>
    <tr><td>Prioridade</td><td>Baixa, Normal ou Alta</td></tr>
    <tr><td>Prazo</td><td>Prazo em dias para conclusão</td></tr>
    <tr><td>Status</td><td>Situação atual do remanejo</td></tr>
    <tr><td>Ações</td><td>Botões de Visualizar, Editar e Excluir</td></tr>
</table>

<h3>2.4 Botões de Ação</h3>
<p>Cada linha da tabela possui botões de ação (conforme suas permissões):</p>
<ul>
    <li><strong>Visualizar</strong> (ícone de olho): Abre os detalhes do remanejo</li>
    <li><strong>Editar</strong> (ícone de lápis): Abre a página de atualização/envio</li>
    <li><strong>Excluir</strong> (ícone de lixeira): Exclui o remanejo (apenas status Pendente)</li>
</ul>

<!-- 3. CADASTRAR -->
<div class="page-break"></div>
<h2>3. Cadastrar Novo Remanejo</h2>

<p>Para criar um novo remanejo de produtos:</p>

<div class="step">
    <span class="step-number">1</span>
    Na tela principal, clique no botão verde <strong>Novo Remanejo</strong> (canto superior direito).
</div>
<div class="step">
    <span class="step-number">2</span>
    Uma janela modal será aberta com duas abas: <strong>Loja Única</strong> e <strong>Múltiplas Lojas</strong>.
</div>

<h3>3.1 Modo Loja Única</h3>
<p>Use este modo quando todos os produtos vão da <strong>mesma origem para o mesmo destino</strong>.</p>

<div class="step">
    <span class="step-number">3</span>
    Preencha os campos obrigatórios:
</div>
<table>
    <tr><th>Campo</th><th>Obrigatório</th><th>Descrição</th></tr>
    <tr><td>Nome do Remanejo</td><td>Sim</td><td>Descrição identificadora (ex: "Coleção Verão 2025")</td></tr>
    <tr><td>Loja Origem</td><td>Sim</td><td>Loja que enviará os produtos</td></tr>
    <tr><td>Loja Destino</td><td>Sim</td><td>Loja que receberá os produtos</td></tr>
    <tr><td>Prioridade</td><td>Sim</td><td>Baixa, Normal ou Alta</td></tr>
    <tr><td>Prazo (Dias)</td><td>Sim</td><td>De 1 a 9 dias para conclusão</td></tr>
    <tr><td>Arquivo CSV</td><td>Sim</td><td>Planilha com os produtos a serem remanejados</td></tr>
</table>

<div class="step">
    <span class="step-number">4</span>
    Faça o upload do arquivo CSV com os produtos (veja a seção 9 para o formato).
</div>
<div class="step">
    <span class="step-number">5</span>
    Clique em <strong>Salvar</strong>.
</div>

<div class="alert alert-warning">
    <strong>Importante:</strong> No modo Loja Única, todas as linhas do CSV devem ter a mesma loja de origem e destino informadas no formulário.
</div>

<h3>3.2 Modo Múltiplas Lojas</h3>
<p>Use este modo quando os produtos vão para <strong>destinos diferentes</strong> na mesma operação.</p>

<div class="step">
    <span class="step-number">3</span>
    Selecione a aba <strong>Múltiplas Lojas</strong>.
</div>
<div class="step">
    <span class="step-number">4</span>
    Preencha: Nome do Remanejo, Prioridade e Prazo.
</div>
<div class="step">
    <span class="step-number">5</span>
    Faça o upload do arquivo CSV (cada linha pode ter origem e destino diferentes).
</div>
<div class="step">
    <span class="step-number">6</span>
    Clique em <strong>Salvar</strong>.
</div>

<div class="alert alert-info">
    <strong>Como funciona:</strong> O sistema agrupa automaticamente as linhas do CSV por par origem→destino e cria um remanejo separado para cada par. Exemplo: se o CSV tem itens de Z424→A001 e Z424→A002, serão criados 2 remanejos.
</div>

<!-- 4. VISUALIZAR -->
<div class="page-break"></div>
<h2>4. Visualizar Detalhes do Remanejo</h2>

<div class="step">
    <span class="step-number">1</span>
    Na listagem, clique no botão <strong>Visualizar</strong> (ícone de olho) na linha desejada.
</div>
<div class="step">
    <span class="step-number">2</span>
    Uma janela modal abrirá com todas as informações do remanejo.
</div>

<h3>4.1 Informações Exibidas</h3>

<p><strong>Estatísticas do Remanejo:</strong></p>
<table>
    <tr><th>Informação</th><th>Descrição</th></tr>
    <tr><td>Total de Requisições</td><td>Quantidade de itens na solicitação</td></tr>
    <tr><td>Total de Produtos</td><td>Quantidade de referências distintas</td></tr>
    <tr><td>Total Solicitado</td><td>Soma de todas as quantidades solicitadas</td></tr>
    <tr><td>Total Enviado</td><td>Soma de todas as quantidades enviadas</td></tr>
    <tr><td>Total Pendente</td><td>Diferença entre solicitado e enviado</td></tr>
    <tr><td>Percentual de Adesão</td><td>Percentual de conclusão do envio</td></tr>
    <tr><td>Total de Notas Fiscais</td><td>Quantidade de notas fiscais registradas</td></tr>
    <tr><td>Prazo</td><td>Prazo em dias definido na criação</td></tr>
    <tr><td>Realizado</td><td><span class="badge badge-success">No Prazo</span> ou <span class="badge badge-danger">Fora do Prazo</span></td></tr>
</table>

<p><strong>Tabela de Produtos:</strong> Lista todos os produtos com foto, referência, tamanhos, quantidades solicitadas/enviadas, nota fiscal, observações e status individual.</p>

<h3>4.2 Ações Disponíveis na Visualização</h3>
<table>
    <tr><th>Botão</th><th>Ação</th></tr>
    <tr><td><span class="badge badge-success">Finalizar</span></td><td>Marca o remanejo como Concluído</td></tr>
    <tr><td><span class="badge badge-warning">Editar</span></td><td>Abre a página de atualização (registrar envio)</td></tr>
    <tr><td><span class="badge badge-danger">Excluir</span></td><td>Exclui o remanejo (apenas se Pendente)</td></tr>
    <tr><td>Imprimir</td><td>Abre o relatório para impressão</td></tr>
    <tr><td>Excel</td><td>Exporta os dados para planilha Excel</td></tr>
</table>

<!-- 5. ATUALIZAR -->
<div class="page-break"></div>
<h2>5. Atualizar Remanejo (Registrar Envio)</h2>

<p>A atualização é o processo de registrar quais produtos foram efetivamente enviados.</p>

<div class="step">
    <span class="step-number">1</span>
    Na listagem ou na visualização, clique no botão <strong>Editar</strong> (ícone de lápis).
</div>
<div class="step">
    <span class="step-number">2</span>
    A página de edição será carregada com todos os itens do remanejo.
</div>

<h3>5.1 Campo "Envio Total"</h3>
<p>O campo <strong>Envio Total</strong> define o modo de envio:</p>

<h4>Opção "Sim" — Envio Total</h4>
<p>Selecione quando <strong>todos os itens</strong> serão enviados de uma vez:</p>
<ul>
    <li>Todos os itens são marcados automaticamente</li>
    <li>O campo <strong>Nota Fiscal</strong> (topo) é habilitado e <strong>obrigatório</strong></li>
    <li>Os campos <strong>Enviado</strong> de cada linha ficam habilitados para informar a quantidade</li>
    <li>Os campos <strong>Nota Fiscal</strong> individuais (por linha) ficam <strong>desabilitados</strong> (a NF geral cobre todos)</li>
    <li>Os campos <strong>Observações</strong> ficam habilitados</li>
</ul>

<h4>Opção "Não" — Envio Parcial</h4>
<p>Selecione quando apenas <strong>alguns itens</strong> serão enviados:</p>
<ul>
    <li>Todos os itens iniciam <strong>desmarcados</strong> e com campos desabilitados</li>
    <li>O campo <strong>Nota Fiscal</strong> (topo) fica desabilitado</li>
    <li>Para habilitar uma linha, marque o <strong>checkbox</strong> do item desejado</li>
    <li>Ao marcar, os campos <strong>Enviado</strong>, <strong>Nota Fiscal</strong> e <strong>Observações</strong> daquela linha são habilitados</li>
</ul>

<div class="step">
    <span class="step-number">3</span>
    Preencha a quantidade enviada no campo <strong>Enviado</strong> de cada item.
</div>

<div class="alert alert-warning">
    <strong>Observação obrigatória:</strong> Se a quantidade enviada for <strong>diferente</strong> da quantidade solicitada, o campo <strong>Observações</strong> torna-se obrigatório. Informe o motivo da divergência (ex: "Quantidade insuficiente em estoque").
</div>

<div class="step">
    <span class="step-number">4</span>
    Clique em <strong>Salvar</strong> para registrar o envio.
</div>

<p>Após salvar, o sistema atualiza automaticamente o status do remanejo conforme as quantidades informadas:</p>
<ul>
    <li>Se todos os itens foram enviados integralmente → Status <span class="badge badge-success">Concluído</span></li>
    <li>Se apenas parte dos itens foi enviada → Status <span class="badge badge-info">Em Andamento</span></li>
    <li>Se nenhum item foi enviado → Status permanece <span class="badge badge-warning">Pendente</span></li>
</ul>

<!-- 6. EXCLUIR -->
<div class="page-break"></div>
<h2>6. Excluir Remanejo</h2>

<div class="alert alert-danger">
    <strong>Atenção:</strong> Apenas remanejos com status <strong>Pendente</strong> podem ser excluídos. Essa ação é irreversível.
</div>

<div class="step">
    <span class="step-number">1</span>
    Na listagem, clique no botão <strong>Excluir</strong> (ícone de lixeira) na linha desejada.
</div>
<div class="step">
    <span class="step-number">2</span>
    Uma janela de confirmação será exibida com os dados do remanejo (ID, Descrição, Origem, Destino, Status).
</div>
<div class="step">
    <span class="step-number">3</span>
    Confira os dados exibidos e clique em <strong>Excluir</strong> para confirmar, ou em <strong>Cancelar</strong> para voltar.
</div>
<div class="step">
    <span class="step-number">4</span>
    Após a exclusão, uma notificação de sucesso será exibida e o remanejo será removido da listagem.
</div>

<!-- 7. FINALIZAR -->
<h2>7. Finalizar Remanejo</h2>

<p>Finalizar marca o remanejo como <strong>Concluído</strong>, independente das quantidades enviadas.</p>

<div class="step">
    <span class="step-number">1</span>
    Na listagem, clique em <strong>Visualizar</strong> (ícone de olho) para abrir os detalhes.
</div>
<div class="step">
    <span class="step-number">2</span>
    Clique no botão verde <strong>Finalizar</strong>.
</div>
<div class="step">
    <span class="step-number">3</span>
    Confirme a ação na janela de confirmação.
</div>
<div class="step">
    <span class="step-number">4</span>
    O status será alterado para <span class="badge badge-success">Concluído</span>.
</div>

<!-- 8. STATUS -->
<h2>8. Status do Remanejo</h2>

<table>
    <tr><th>Status</th><th>Cor</th><th>Significado</th></tr>
    <tr><td>Pendente</td><td><span class="badge badge-warning">Amarelo</span></td><td>Aguardando envio — nenhum item foi enviado ainda</td></tr>
    <tr><td>Em Andamento</td><td><span class="badge badge-info">Azul</span></td><td>Envio parcial — alguns itens foram enviados</td></tr>
    <tr><td>Concluído</td><td><span class="badge badge-success">Verde</span></td><td>Todos os itens foram enviados ou o remanejo foi finalizado</td></tr>
    <tr><td>Cancelado</td><td><span class="badge badge-danger">Vermelho</span></td><td>Remanejo cancelado administrativamente</td></tr>
    <tr><td>Parcial</td><td><span class="badge badge-secondary">Cinza</span></td><td>Item individual com envio parcial (quantidade enviada menor que solicitada)</td></tr>
</table>

<!-- 9. CSV -->
<div class="page-break"></div>
<h2>9. Formato do Arquivo CSV</h2>

<p>O arquivo CSV deve seguir o formato abaixo para ser aceito pelo sistema:</p>

<h3>9.1 Regras Gerais</h3>
<ul>
    <li><strong>Separador:</strong> ponto e vírgula ( <code>;</code> ) — não use vírgula</li>
    <li><strong>Codificação:</strong> UTF-8</li>
    <li><strong>Primeira linha:</strong> Cabeçalho (ignorado automaticamente pelo sistema)</li>
    <li><strong>Extensão:</strong> .csv ou .txt</li>
    <li><strong>Colunas:</strong> exatamente 5, na ordem abaixo</li>
</ul>

<h3>9.2 Estrutura das Colunas</h3>
<table>
    <tr><th>Coluna</th><th>Descrição</th><th>Exemplo</th></tr>
    <tr><td>Origem</td><td>Código da loja de origem</td><td>Z424</td></tr>
    <tr><td>Destino</td><td>Código da loja de destino</td><td>A001</td></tr>
    <tr><td>Referência</td><td>Código do produto</td><td>SKU001</td></tr>
    <tr><td>Tamanho</td><td>Tamanho do produto</td><td>P, M, G, GG</td></tr>
    <tr><td>Quantidade</td><td>Quantidade a ser remanejada</td><td>10</td></tr>
</table>

<h3>9.3 Exemplo — Loja Única</h3>
<div class="csv-example">
Origem;Destino;Referência;Tamanho;Quantidade<br>
Z424;A001;BLUSA001;P;20<br>
Z424;A001;BLUSA001;M;30<br>
Z424;A001;CALCA002;G;15
</div>

<h3>9.4 Exemplo — Múltiplas Lojas</h3>
<div class="csv-example">
Origem;Destino;Referência;Tamanho;Quantidade<br>
Z424;A001;BLUSA001;P;20<br>
Z424;A002;BLUSA001;M;15<br>
A001;Z999;CALCA002;G;10
</div>
<p>Neste exemplo, o sistema criará <strong>3 remanejos separados</strong> (um para cada par origem→destino).</p>

<div class="alert alert-info">
    <strong>Dica:</strong> Utilize os modelos de CSV disponíveis na tela de cadastro. Clique no link "Baixar modelo" para obter o arquivo de exemplo.
</div>

<!-- 10. FAQ -->
<div class="page-break"></div>
<h2>10. Perguntas Frequentes</h2>

<h3>Por que não consigo excluir um remanejo?</h3>
<p>Apenas remanejos com status <strong>Pendente</strong> podem ser excluídos. Remanejos Em Andamento ou Concluídos não podem ser removidos. Verifique também se você possui permissão de exclusão.</p>

<h3>Por que os campos de edição estão desabilitados?</h3>
<p>Se você está no modo <strong>"Não"</strong> (Envio Parcial), os campos só são habilitados ao marcar o checkbox da linha desejada. Marque o checkbox para habilitar os campos daquela linha.</p>

<h3>Por que o campo Observações ficou obrigatório?</h3>
<p>Quando a quantidade enviada é <strong>diferente</strong> da quantidade solicitada, o sistema exige uma justificativa no campo Observações. Informe o motivo da divergência.</p>

<h3>Posso editar um remanejo já finalizado?</h3>
<p>Não. Remanejos com status <strong>Concluído</strong> ou <strong>Cancelado</strong> não podem ser editados. Caso necessário, entre em contato com o administrador do sistema.</p>

<h3>Meu arquivo CSV foi rejeitado. O que verificar?</h3>
<ul>
    <li>O separador deve ser <strong>ponto e vírgula</strong> ( ; ) e não vírgula</li>
    <li>O arquivo deve ter exatamente <strong>5 colunas</strong></li>
    <li>As quantidades devem ser <strong>números inteiros positivos</strong></li>
    <li>Os códigos de loja devem existir no sistema</li>
    <li>No modo Loja Única, todas as linhas devem ter a mesma origem e destino</li>
</ul>

<h3>Como imprimir o relatório de um remanejo?</h3>
<p>Abra a visualização do remanejo (ícone de olho), clique no botão <strong>Imprimir</strong>. Uma nova janela será aberta com o relatório formatado. Use <strong>Ctrl+P</strong> para imprimir ou salvar como PDF.</p>

<h3>Como exportar para Excel?</h3>
<p>Na visualização do remanejo, clique no botão <strong>Excel</strong>. O download do arquivo .xlsx será iniciado automaticamente.</p>

<!-- RODAPÉ -->
<div class="footer" style="margin-top: 60px;">
    <p>Manual do Usuário — Módulo de Remanejos de Produtos</p>
    <p>Sistema Mercury — Grupo Meia Sola — Versão 1.0 — Março 2026</p>
    <p>Documento gerado automaticamente. Sujeito a atualizações.</p>
</div>

</body>
</html>
HTML;

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$outputPath = __DIR__ . '/MANUAL_USUARIO_REMANEJOS_v2.pdf';
file_put_contents($outputPath, $dompdf->output());

echo "PDF gerado com sucesso: {$outputPath}\n";
echo "Tamanho: " . round(filesize($outputPath) / 1024, 1) . " KB\n";
