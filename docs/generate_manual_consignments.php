<?php
/**
 * Gerador de Manual do Usuário - Módulo de Consignações
 *
 * Uso: php docs/generate_manual_consignments.php
 * Saída: docs/MANUAL_CONSIGNACOES.pdf
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');
$options->set('isFontSubsettingEnabled', true);

$dompdf = new Dompdf($options);

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    @page {
        margin: 2cm 2cm 2.5cm 2cm;
    }
    body {
        font-family: Helvetica, Arial, sans-serif;
        font-size: 11pt;
        color: #333;
        line-height: 1.6;
    }
    h1 {
        color: #007bff;
        font-size: 22pt;
        border-bottom: 3px solid #007bff;
        padding-bottom: 8px;
        margin-top: 0;
    }
    h2 {
        color: #0056b3;
        font-size: 16pt;
        border-bottom: 2px solid #dee2e6;
        padding-bottom: 6px;
        margin-top: 30px;
        page-break-after: avoid;
    }
    h3 {
        color: #495057;
        font-size: 13pt;
        margin-top: 20px;
        page-break-after: avoid;
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
        font-size: 14pt;
        color: #495057;
        margin-top: 60px;
    }
    .toc {
        page-break-after: always;
    }
    .toc h2 {
        border-bottom: 3px solid #007bff;
    }
    .toc ul {
        list-style: none;
        padding-left: 0;
    }
    .toc li {
        padding: 4px 0;
        border-bottom: 1px dotted #ccc;
    }
    .toc li.sub {
        padding-left: 20px;
        font-size: 10pt;
    }
    .step-box {
        background: #f8f9fa;
        border-left: 4px solid #007bff;
        padding: 10px 14px;
        margin: 10px 0;
        page-break-inside: avoid;
    }
    .step-num {
        display: inline-block;
        background: #007bff;
        color: #fff;
        width: 24px;
        height: 24px;
        text-align: center;
        border-radius: 50%;
        font-weight: bold;
        font-size: 10pt;
        line-height: 24px;
        margin-right: 8px;
    }
    .alert-info {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 10px 14px;
        border-radius: 4px;
        margin: 10px 0;
        page-break-inside: avoid;
    }
    .alert-warning {
        background: #fff3cd;
        border: 1px solid #ffc107;
        color: #856404;
        padding: 10px 14px;
        border-radius: 4px;
        margin: 10px 0;
        page-break-inside: avoid;
    }
    .alert-danger {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
        padding: 10px 14px;
        border-radius: 4px;
        margin: 10px 0;
        page-break-inside: avoid;
    }
    .alert-success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
        padding: 10px 14px;
        border-radius: 4px;
        margin: 10px 0;
        page-break-inside: avoid;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 10px 0;
        page-break-inside: avoid;
    }
    table th {
        background: #343a40;
        color: #fff;
        padding: 8px 10px;
        text-align: left;
        font-size: 10pt;
    }
    table td {
        border: 1px solid #dee2e6;
        padding: 6px 10px;
        font-size: 10pt;
    }
    table tr:nth-child(even) {
        background: #f8f9fa;
    }
    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 9pt;
        font-weight: bold;
        color: #fff;
    }
    .badge-warning { background: #ffc107; color: #333; }
    .badge-success { background: #28a745; }
    .badge-danger { background: #dc3545; }
    .badge-info { background: #17a2b8; }
    .badge-secondary { background: #6c757d; }
    .badge-primary { background: #007bff; }
    .field-table td:first-child {
        font-weight: bold;
        width: 30%;
    }
    .footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 8pt;
        color: #999;
        border-top: 1px solid #ddd;
        padding-top: 4px;
    }
    .section-break {
        page-break-before: always;
    }
    .icon {
        color: #007bff;
        font-weight: bold;
    }
    ul, ol {
        margin: 8px 0;
        padding-left: 24px;
    }
    li {
        margin-bottom: 4px;
    }
    .caption {
        text-align: center;
        font-size: 9pt;
        color: #6c757d;
        font-style: italic;
        margin: 5px 0 15px 0;
    }
    .perm-table td:nth-child(2),
    .perm-table td:nth-child(3) {
        text-align: center;
    }
</style>
</head>
<body>

<!-- CAPA -->
<div class="cover">
    <h1>Manual do Usuario</h1>
    <div class="subtitle">Modulo de Consignacoes</div>
    <div class="company">Mercury - Grupo Meia Sola</div>
    <div class="version">Versao 1.0 - Marco 2026</div>
</div>

<div style="page-break-after: always;"></div>

<!-- SUMARIO -->
<div class="toc">
    <h2>Sumario</h2>
    <ul>
        <li><strong>1. Introducao</strong></li>
        <li class="sub">1.1 O que e o Modulo de Consignacoes?</li>
        <li class="sub">1.2 Niveis de Acesso e Permissoes</li>
        <li><strong>2. Acessando o Modulo</strong></li>
        <li class="sub">2.1 Navegacao pelo Menu</li>
        <li class="sub">2.2 Visao Geral da Tela Principal</li>
        <li><strong>3. Painel de Estatisticas</strong></li>
        <li><strong>4. Cadastrar Nova Consignacao</strong></li>
        <li class="sub">4.1 Passo a Passo do Cadastro</li>
        <li class="sub">4.2 Adicionando Produtos</li>
        <li class="sub">4.3 Validacoes e Regras</li>
        <li><strong>5. Visualizar Consignacao</strong></li>
        <li class="sub">5.1 Detalhes da Consignacao</li>
        <li class="sub">5.2 Indicadores de Prazo</li>
        <li class="sub">5.3 Imprimir Consignacao</li>
        <li><strong>6. Editar Consignacao</strong></li>
        <li class="sub">6.1 Campos Editaveis</li>
        <li class="sub">6.2 Gerenciando Produtos</li>
        <li class="sub">6.3 Alterando Situacao</li>
        <li><strong>7. Excluir Consignacao</strong></li>
        <li><strong>8. Busca e Filtros</strong></li>
        <li><strong>9. Exportar para CSV</strong></li>
        <li><strong>10. Dashboard e Graficos</strong></li>
        <li><strong>11. Situacoes e Fluxo de Status</strong></li>
        <li><strong>12. Perguntas Frequentes</strong></li>
    </ul>
</div>

<!-- 1. INTRODUCAO -->
<h2>1. Introducao</h2>

<h3>1.1 O que e o Modulo de Consignacoes?</h3>
<p>
    O modulo de Consignacoes permite gerenciar o envio de produtos em consignacao para clientes.
    Com ele, voce pode registrar quais produtos foram enviados, acompanhar os prazos de retorno,
    controlar os valores financeiros envolvidos e gerenciar todo o ciclo de vida da consignacao
    ate sua finalizacao ou cancelamento.
</p>

<p><strong>Principais funcionalidades:</strong></p>
<ul>
    <li>Cadastro de consignacoes com dados do cliente, loja e consultor(a)</li>
    <li>Gerenciamento de produtos por consignacao (referencia, tamanho, valor, situacao)</li>
    <li>Controle de prazos com indicadores visuais (no prazo, atrasado)</li>
    <li>Dashboard com estatisticas e graficos</li>
    <li>Busca avancada com filtros (loja, data, texto)</li>
    <li>Exportacao de dados para CSV</li>
    <li>Impressao de comprovante de consignacao</li>
    <li>Notificacoes em tempo real via WebSocket</li>
</ul>

<h3>1.2 Niveis de Acesso e Permissoes</h3>
<p>O sistema possui dois perfis principais de acesso:</p>

<table class="perm-table">
    <tr>
        <th>Funcionalidade</th>
        <th>Administrador (Nivel 1-3)</th>
        <th>Usuario de Loja (Nivel 4+)</th>
    </tr>
    <tr>
        <td>Visualizar consignacoes</td>
        <td>Todas as lojas</td>
        <td>Somente sua loja</td>
    </tr>
    <tr>
        <td>Cadastrar consignacao</td>
        <td>Qualquer loja</td>
        <td>Somente sua loja</td>
    </tr>
    <tr>
        <td>Editar consignacao pendente</td>
        <td>Sim</td>
        <td>Sim</td>
    </tr>
    <tr>
        <td>Editar consignacao finalizada/cancelada</td>
        <td>Sim</td>
        <td>Nao (cadeado)</td>
    </tr>
    <tr>
        <td>Excluir consignacao pendente</td>
        <td>Sim</td>
        <td>Sim</td>
    </tr>
    <tr>
        <td>Excluir consignacao finalizada/cancelada</td>
        <td>Sim</td>
        <td>Nao (cadeado)</td>
    </tr>
    <tr>
        <td>Alterar situacao do produto</td>
        <td>Sim</td>
        <td>Nao (mantido automaticamente)</td>
    </tr>
    <tr>
        <td>Exportar CSV</td>
        <td>Todas as lojas</td>
        <td>Somente sua loja</td>
    </tr>
    <tr>
        <td>Selecionar loja no cadastro</td>
        <td>Dropdown com todas</td>
        <td>Fixo na sua loja</td>
    </tr>
</table>

<div class="alert-info">
    <strong>Nota:</strong> Os botoes de acao (editar, excluir) aparecem desabilitados com um icone
    de cadeado quando o usuario nao tem permissao para aquela operacao.
</div>

<!-- 2. ACESSANDO O MODULO -->
<div class="section-break"></div>
<h2>2. Acessando o Modulo</h2>

<h3>2.1 Navegacao pelo Menu</h3>

<div class="step-box">
    <span class="step-num">1</span>
    Faca login no sistema Mercury com suas credenciais.
</div>

<div class="step-box">
    <span class="step-num">2</span>
    No menu lateral esquerdo, localize a secao de <strong>Consignacoes</strong>.
</div>

<div class="step-box">
    <span class="step-num">3</span>
    Clique em <strong>"Consignacoes"</strong> para acessar a tela principal do modulo.
</div>

<div class="alert-info">
    <strong>Dica:</strong> A opcao de menu so aparecera se o seu usuario tiver permissao de acesso ao modulo.
    Caso nao visualize, entre em contato com o administrador do sistema.
</div>

<h3>2.2 Visao Geral da Tela Principal</h3>
<p>Ao acessar o modulo, a tela principal e dividida em cinco areas:</p>

<ol>
    <li><strong>Cabecalho:</strong> Titulo "Consignacoes" com os botoes "Exportar" e "Nova Consignacao"</li>
    <li><strong>Cards de Estatisticas:</strong> Resumo com total, pendentes, concluidas, produtos e valor pendente</li>
    <li><strong>Filtros de Busca:</strong> Campos para pesquisa por texto, loja e periodo</li>
    <li><strong>Graficos (Dashboard):</strong> Graficos de acompanhamento mensal, por loja e financeiro</li>
    <li><strong>Tabela de Consignacoes:</strong> Lista paginada com todas as consignacoes e botoes de acao</li>
</ol>

<!-- 3. PAINEL DE ESTATISTICAS -->
<h2>3. Painel de Estatisticas</h2>
<p>No topo da pagina, cinco cards apresentam um resumo rapido:</p>

<table>
    <tr>
        <th>Card</th>
        <th>Cor</th>
        <th>Descricao</th>
    </tr>
    <tr>
        <td><strong>Total</strong></td>
        <td><span class="badge badge-primary">Azul</span></td>
        <td>Numero total de consignacoes registradas</td>
    </tr>
    <tr>
        <td><strong>Pendentes</strong></td>
        <td><span class="badge badge-warning">Amarelo</span></td>
        <td>Consignacoes aguardando retorno ou finalizacao</td>
    </tr>
    <tr>
        <td><strong>Concluidas</strong></td>
        <td><span class="badge badge-success">Verde</span></td>
        <td>Consignacoes finalizadas com sucesso</td>
    </tr>
    <tr>
        <td><strong>Produtos</strong></td>
        <td><span class="badge badge-info">Ciano</span></td>
        <td>Total de itens/produtos em todas as consignacoes</td>
    </tr>
    <tr>
        <td><strong>Valor Pendente</strong></td>
        <td><span class="badge badge-danger">Vermelho</span></td>
        <td>Soma financeira (R$) das consignacoes ainda pendentes</td>
    </tr>
</table>

<div class="alert-info">
    <strong>Nota:</strong> Os cards sao atualizados automaticamente quando voce aplica filtros de busca,
    mostrando as estatisticas correspondentes ao resultado filtrado.
</div>

<!-- 4. CADASTRAR -->
<div class="section-break"></div>
<h2>4. Cadastrar Nova Consignacao</h2>

<h3>4.1 Passo a Passo do Cadastro</h3>

<div class="step-box">
    <span class="step-num">1</span>
    Na tela principal, clique no botao verde <strong>"Nova Consignacao"</strong> (canto superior direito).
    No celular, clique em <strong>"Acoes" &gt; "Nova Consignacao"</strong>.
</div>

<div class="step-box">
    <span class="step-num">2</span>
    Um modal (janela) sera aberto com o formulario de cadastro dividido em duas secoes:
    <strong>Informacoes da Consignacao</strong> e <strong>Produtos</strong>.
</div>

<div class="step-box">
    <span class="step-num">3</span>
    Preencha os campos obrigatorios (marcados com <strong style="color: red;">*</strong>):
</div>

<table class="field-table">
    <tr>
        <th>Campo</th>
        <th>Descricao</th>
    </tr>
    <tr>
        <td>Loja *</td>
        <td>Selecione a loja de origem. Usuarios de loja tem este campo fixo automaticamente.</td>
    </tr>
    <tr>
        <td>Consultor(a) *</td>
        <td>Selecione o(a) consultor(a) responsavel. A lista e carregada automaticamente apos selecionar a loja.</td>
    </tr>
    <tr>
        <td>Nota Remessa *</td>
        <td>Numero da nota fiscal de remessa (ex: 12345).</td>
    </tr>
    <tr>
        <td>Data Emissao *</td>
        <td>Data de emissao da consignacao. Preenchida com a data atual por padrao.</td>
    </tr>
    <tr>
        <td>Cliente *</td>
        <td>Nome completo do cliente que recebera os produtos.</td>
    </tr>
    <tr>
        <td>CPF *</td>
        <td>CPF do cliente no formato 000.000.000-00 (11 digitos).</td>
    </tr>
    <tr>
        <td>Observacoes</td>
        <td>Campo opcional para informacoes adicionais sobre a consignacao.</td>
    </tr>
</table>

<h3>4.2 Adicionando Produtos</h3>

<div class="step-box">
    <span class="step-num">4</span>
    Na secao <strong>"Produtos"</strong>, preencha os dados do primeiro produto:
</div>

<table class="field-table">
    <tr>
        <th>Campo</th>
        <th>Descricao</th>
    </tr>
    <tr>
        <td>Referencia *</td>
        <td>Codigo de referencia do produto (ex: REF123).</td>
    </tr>
    <tr>
        <td>Tamanho *</td>
        <td>Selecione o tamanho do produto na lista.</td>
    </tr>
    <tr>
        <td>Valor *</td>
        <td>Valor unitario do produto em reais (ex: 199,90).</td>
    </tr>
</table>

<div class="step-box">
    <span class="step-num">5</span>
    Para adicionar mais produtos, clique no botao azul <strong>"Adicionar Produto"</strong>
    no canto superior direito da secao de produtos. Uma nova linha sera adicionada.
</div>

<div class="step-box">
    <span class="step-num">6</span>
    Para remover um produto, clique no botao vermelho com icone de lixeira ao lado do produto.
    O botao de remover so aparece quando ha mais de um produto.
</div>

<div class="step-box">
    <span class="step-num">7</span>
    Apos preencher todos os dados, clique em <strong>"Salvar"</strong> no rodape do modal.
</div>

<div class="alert-success">
    <strong>Sucesso!</strong> Uma mensagem de confirmacao aparecera e a lista sera atualizada
    automaticamente. Uma notificacao em tempo real sera enviada aos usuarios relevantes.
</div>

<h3>4.3 Validacoes e Regras</h3>

<div class="alert-warning">
    <strong>Regras importantes do cadastro:</strong>
    <ul style="margin-bottom: 0;">
        <li>Todos os campos marcados com <strong style="color: red;">*</strong> sao obrigatorios</li>
        <li>O CPF deve ter exatamente 11 digitos (ou 14 para CNPJ)</li>
        <li>E necessario incluir pelo menos 1 produto</li>
        <li><strong>Duplicidade:</strong> nao e permitido criar uma nova consignacao pendente para o mesmo CPF na mesma loja.
            O sistema bloqueara e exibira um aviso. Finalize ou cancele a consignacao existente antes de criar uma nova.</li>
        <li>Ao selecionar a loja, a lista de consultores e atualizada automaticamente via AJAX</li>
    </ul>
</div>

<!-- 5. VISUALIZAR -->
<div class="section-break"></div>
<h2>5. Visualizar Consignacao</h2>

<h3>5.1 Detalhes da Consignacao</h3>

<div class="step-box">
    <span class="step-num">1</span>
    Na tabela de consignacoes, localize o registro desejado.
</div>

<div class="step-box">
    <span class="step-num">2</span>
    Clique no botao azul com icone de <strong>olho</strong> na coluna "Acoes".
    No celular, clique nos <strong>tres pontos</strong> e selecione <strong>"Ver Detalhes"</strong>.
</div>

<div class="step-box">
    <span class="step-num">3</span>
    Um modal sera aberto com todas as informacoes detalhadas, organizadas em cards:
</div>

<p>O modal de visualizacao apresenta:</p>

<ul>
    <li><strong>Cabecalho:</strong> ID, nome do cliente e badge colorido com a situacao</li>
    <li><strong>Cards de Resumo:</strong> Quantidade de produtos, valor total (R$), dias corridos e indicador de prazo</li>
    <li><strong>Informacoes Gerais:</strong> Cliente, CPF, loja e consultor(a)</li>
    <li><strong>Datas e Notas:</strong> Nota de envio, data de envio, nota de retorno, data de retorno, prazo permitido, tempo de consignacao e status do prazo</li>
    <li><strong>Observacoes:</strong> Exibido apenas se preenchido</li>
    <li><strong>Tabela de Produtos:</strong> Foto do produto (quando disponivel), referencia, tamanho, valor e situacao individual</li>
</ul>

<h3>5.2 Indicadores de Prazo</h3>
<p>Na tabela principal e no modal de visualizacao, os indicadores de prazo sao exibidos com cores:</p>

<table>
    <tr>
        <th>Indicador</th>
        <th>Cor</th>
        <th>Significado</th>
    </tr>
    <tr>
        <td>Xd (dias restantes)</td>
        <td><span class="badge badge-success">Verde</span></td>
        <td>Mais de 3 dias restantes - dentro do prazo</td>
    </tr>
    <tr>
        <td>Xd (dias restantes)</td>
        <td><span class="badge badge-warning">Amarelo</span></td>
        <td>3 dias ou menos restantes - atencao ao prazo</td>
    </tr>
    <tr>
        <td>-Xd (dias atrasados)</td>
        <td><span class="badge badge-danger">Vermelho</span></td>
        <td>Prazo vencido - consignacao em atraso</td>
    </tr>
    <tr>
        <td>Sem prazo</td>
        <td><span class="badge badge-secondary">Cinza</span></td>
        <td>Nenhum prazo de retorno definido</td>
    </tr>
</table>

<h3>5.3 Imprimir Consignacao</h3>

<div class="step-box">
    <span class="step-num">1</span>
    Abra o modal de visualizacao de uma consignacao (conforme passos acima).
</div>

<div class="step-box">
    <span class="step-num">2</span>
    No rodape do modal, clique no botao <strong>"Imprimir"</strong>.
</div>

<div class="step-box">
    <span class="step-num">3</span>
    Uma nova janela sera aberta com o comprovante de consignacao formatado para impressao, contendo:
    <ul>
        <li>Cabecalho com numero da consignacao e situacao</li>
        <li>Dados da loja, consultor(a), cliente e CPF</li>
        <li>Notas fiscais e datas</li>
        <li>Tabela completa de produtos com valores</li>
        <li>Area para assinaturas (Consultor, Cliente e Responsavel)</li>
    </ul>
</div>

<div class="step-box">
    <span class="step-num">4</span>
    Clique em <strong>"Imprimir"</strong> na pagina aberta ou use <strong>Ctrl+P</strong>.
</div>

<!-- 6. EDITAR -->
<div class="section-break"></div>
<h2>6. Editar Consignacao</h2>

<h3>6.1 Campos Editaveis</h3>

<div class="step-box">
    <span class="step-num">1</span>
    Na tabela de consignacoes, clique no botao amarelo com icone de <strong>lapis</strong> na coluna "Acoes".
    No celular, clique nos <strong>tres pontos</strong> e selecione <strong>"Editar"</strong>.
</div>

<div class="step-box">
    <span class="step-num">2</span>
    O modal de edicao sera aberto com os dados atuais preenchidos. Voce pode alterar:
</div>

<table class="field-table">
    <tr>
        <th>Campo</th>
        <th>Descricao</th>
    </tr>
    <tr>
        <td>Loja</td>
        <td>Alterar a loja de origem (apenas administradores).</td>
    </tr>
    <tr>
        <td>Consultor(a)</td>
        <td>Alterar o(a) consultor(a) responsavel.</td>
    </tr>
    <tr>
        <td>Situacao</td>
        <td>Alterar o status da consignacao (Pendente, Finalizada, Cancelada).</td>
    </tr>
    <tr>
        <td>Cliente</td>
        <td>Corrigir o nome do cliente.</td>
    </tr>
    <tr>
        <td>CPF</td>
        <td>Corrigir o CPF do cliente.</td>
    </tr>
    <tr>
        <td>Nota Remessa</td>
        <td>Corrigir o numero da nota de remessa.</td>
    </tr>
    <tr>
        <td>Data Consignacao</td>
        <td>Ajustar a data de envio.</td>
    </tr>
    <tr>
        <td>Nota Retorno</td>
        <td>Informar o numero da nota de retorno.</td>
    </tr>
    <tr>
        <td>Data Retorno</td>
        <td>Informar a data de retorno dos produtos.</td>
    </tr>
    <tr>
        <td>Observacoes</td>
        <td>Adicionar ou alterar informacoes adicionais.</td>
    </tr>
</table>

<h3>6.2 Gerenciando Produtos</h3>

<p>Na secao de produtos do formulario de edicao:</p>

<ul>
    <li><strong>Editar produto existente:</strong> Altere diretamente os campos de referencia, tamanho, valor ou situacao do produto.</li>
    <li><strong>Adicionar novo produto:</strong> Clique em <strong>"Adicionar Produto"</strong>. A nova linha aparecera abaixo dos produtos existentes.</li>
    <li><strong>Remover produto:</strong> Clique no botao vermelho (lixeira) ao lado do produto. O botao so aparece quando ha mais de um produto.</li>
</ul>

<div class="alert-info">
    <strong>Nota para usuarios de loja:</strong> A situacao individual de cada produto e mantida automaticamente.
    Apenas administradores (nivel 1-3) podem alterar a situacao dos produtos.
</div>

<h3>6.3 Alterando a Situacao</h3>

<p>Ao alterar a situacao para <strong>"Finalizada"</strong>:</p>
<ul>
    <li>A <strong>data de retorno</strong> e preenchida automaticamente com a data atual (se nao informada)</li>
    <li>O <strong>tempo de consignacao</strong> e calculado automaticamente (diferenca em dias)</li>
    <li>A consignacao ficara <strong>bloqueada</strong> para edicao por usuarios de loja</li>
</ul>

<div class="step-box">
    <span class="step-num">3</span>
    Apos realizar as alteracoes, clique em <strong>"Salvar Alteracoes"</strong> no rodape do modal.
</div>

<div class="alert-warning">
    <strong>Atencao:</strong> Consignacoes com situacao "Finalizada" ou "Cancelada" so podem ser editadas
    por administradores (nivel de acesso 1, 2 ou 3). Usuarios de loja verao um icone de
    <strong>cadeado</strong> no botao de edicao.
</div>

<!-- 7. EXCLUIR -->
<div class="section-break"></div>
<h2>7. Excluir Consignacao</h2>

<div class="step-box">
    <span class="step-num">1</span>
    Na tabela de consignacoes, clique no botao vermelho com icone de <strong>lixeira</strong> na coluna "Acoes".
    No celular, clique nos <strong>tres pontos</strong> e selecione <strong>"Excluir"</strong>.
</div>

<div class="step-box">
    <span class="step-num">2</span>
    Um modal de confirmacao sera exibido mostrando os dados da consignacao (cliente e loja).
</div>

<div class="step-box">
    <span class="step-num">3</span>
    Clique em <strong>"Confirmar Exclusao"</strong> para prosseguir ou <strong>"Cancelar"</strong> para desistir.
</div>

<div class="alert-danger">
    <strong>Atencao! Esta acao e irreversivel.</strong>
    <ul style="margin-bottom: 0;">
        <li>Ao excluir uma consignacao, <strong>todos os produtos associados</strong> tambem serao excluidos permanentemente</li>
        <li>Usuarios de loja so podem excluir consignacoes com situacao <strong>"Pendente"</strong></li>
        <li>Administradores (nivel 1-3) podem excluir consignacoes em qualquer situacao</li>
        <li>A exclusao sera registrada no log de auditoria do sistema</li>
    </ul>
</div>

<!-- 8. BUSCA E FILTROS -->
<h2>8. Busca e Filtros</h2>

<p>A secao "Filtros de Busca" permite refinar a lista de consignacoes exibida:</p>

<table class="field-table">
    <tr>
        <th>Filtro</th>
        <th>Descricao</th>
    </tr>
    <tr>
        <td>Pesquisa Geral</td>
        <td>Busca por nome do cliente, loja ou consultor(a). Aceita texto parcial.</td>
    </tr>
    <tr>
        <td>Loja</td>
        <td>Filtra consignacoes de uma loja especifica. Usuarios de loja veem apenas sua propria loja.</td>
    </tr>
    <tr>
        <td>Data Inicial</td>
        <td>Filtra consignacoes criadas a partir desta data.</td>
    </tr>
    <tr>
        <td>Data Final</td>
        <td>Filtra consignacoes criadas ate esta data.</td>
    </tr>
</table>

<div class="step-box">
    <span class="step-num">1</span>
    Preencha um ou mais campos de filtro conforme desejado.
</div>

<div class="step-box">
    <span class="step-num">2</span>
    Clique em <strong>"Buscar"</strong> para aplicar os filtros.
</div>

<div class="step-box">
    <span class="step-num">3</span>
    Para limpar todos os filtros e voltar a listagem completa, clique em <strong>"Limpar"</strong>.
</div>

<div class="alert-info">
    <strong>Dica:</strong> Os cards de estatisticas e os graficos do dashboard tambem sao atualizados
    de acordo com os filtros aplicados, permitindo uma analise segmentada dos dados.
</div>

<!-- 9. EXPORTAR CSV -->
<h2>9. Exportar para CSV</h2>

<div class="step-box">
    <span class="step-num">1</span>
    Clique no botao <strong>"Exportar"</strong> no cabecalho da pagina (icone de arquivo CSV).
    No celular, acesse via <strong>"Acoes" &gt; "Exportar CSV"</strong>.
</div>

<div class="step-box">
    <span class="step-num">2</span>
    O arquivo CSV sera gerado e o download iniciara automaticamente.
</div>

<p>O arquivo exportado contem 14 colunas:</p>

<table>
    <tr><th>#</th><th>Coluna</th></tr>
    <tr><td>1</td><td>ID</td></tr>
    <tr><td>2</td><td>Loja</td></tr>
    <tr><td>3</td><td>Consultor(a)</td></tr>
    <tr><td>4</td><td>Cliente</td></tr>
    <tr><td>5</td><td>CPF/CNPJ</td></tr>
    <tr><td>6</td><td>Qtd. Produtos</td></tr>
    <tr><td>7</td><td>Valor Total</td></tr>
    <tr><td>8</td><td>Situacao</td></tr>
    <tr><td>9</td><td>Nota Remessa</td></tr>
    <tr><td>10</td><td>Data Consignacao</td></tr>
    <tr><td>11</td><td>Nota Retorno</td></tr>
    <tr><td>12</td><td>Data Retorno</td></tr>
    <tr><td>13</td><td>Tempo (dias)</td></tr>
    <tr><td>14</td><td>Observacoes</td></tr>
</table>

<div class="alert-info">
    <strong>Nota:</strong> A exportacao respeita os filtros atualmente aplicados na busca.
    Se voce filtrou por uma loja ou periodo especifico, o CSV contera apenas os registros filtrados.
</div>

<!-- 10. DASHBOARD -->
<div class="section-break"></div>
<h2>10. Dashboard e Graficos</h2>

<p>Abaixo dos filtros de busca, o dashboard apresenta graficos interativos para analise visual:</p>

<table>
    <tr>
        <th>Grafico</th>
        <th>Tipo</th>
        <th>O que mostra</th>
    </tr>
    <tr>
        <td>Consignacoes por Mes</td>
        <td>Linha</td>
        <td>Evolucao mensal do numero de consignacoes nos ultimos 12 meses</td>
    </tr>
    <tr>
        <td>Consignacoes por Loja</td>
        <td>Barras</td>
        <td>Distribuicao de consignacoes por loja com detalhamento por situacao</td>
    </tr>
    <tr>
        <td>Resumo Financeiro</td>
        <td>Barras</td>
        <td>Valores financeiros divididos por situacao (pendente, concluido, cancelado)</td>
    </tr>
</table>

<div class="alert-info">
    <strong>Dica:</strong> Os graficos respondem aos filtros de busca. Filtre por loja ou periodo
    para obter uma visao segmentada dos indicadores.
</div>

<!-- 11. SITUACOES E FLUXO -->
<h2>11. Situacoes e Fluxo de Status</h2>

<p>Cada consignacao possui uma situacao que indica seu estado atual no ciclo de vida:</p>

<table>
    <tr>
        <th>Situacao</th>
        <th>Cor</th>
        <th>Descricao</th>
    </tr>
    <tr>
        <td><strong>Pendente</strong></td>
        <td><span class="badge badge-warning">Amarelo</span></td>
        <td>Consignacao registrada, aguardando retorno ou finalizacao. Pode ser editada e excluida por todos os usuarios.</td>
    </tr>
    <tr>
        <td><strong>Finalizada</strong></td>
        <td><span class="badge badge-success">Verde</span></td>
        <td>Consignacao concluida com sucesso. A data de retorno e preenchida automaticamente. Edicao e exclusao somente por administradores.</td>
    </tr>
    <tr>
        <td><strong>Cancelada</strong></td>
        <td><span class="badge badge-danger">Vermelho</span></td>
        <td>Consignacao cancelada. Edicao e exclusao somente por administradores.</td>
    </tr>
</table>

<h3>Fluxo de Transicao</h3>

<p>O fluxo normal de uma consignacao e:</p>

<div class="step-box">
    <strong>Pendente</strong> &rarr; (Editar e alterar situacao) &rarr; <strong>Finalizada</strong>
</div>

<div class="step-box">
    <strong>Pendente</strong> &rarr; (Editar e alterar situacao) &rarr; <strong>Cancelada</strong>
</div>

<div class="alert-warning">
    <strong>Importante:</strong> Uma vez que a consignacao e finalizada ou cancelada, apenas administradores
    (nivel 1-3) podem reverter a situacao para pendente. Usuarios de loja nao podem alterar
    consignacoes com status final.
</div>

<!-- 12. FAQ -->
<div class="section-break"></div>
<h2>12. Perguntas Frequentes</h2>

<h3>Por que nao consigo criar uma nova consignacao para este CPF?</h3>
<p>O sistema nao permite duas consignacoes <strong>pendentes</strong> para o mesmo CPF na mesma loja.
Finalize ou cancele a consignacao existente antes de criar uma nova.</p>

<h3>Por que o botao de editar esta com um cadeado?</h3>
<p>Consignacoes com situacao "Finalizada" ou "Cancelada" so podem ser editadas por administradores
(nivel de acesso 1 a 3). Se voce e um usuario de loja, solicite a um administrador para fazer a alteracao.</p>

<h3>Por que nao vejo a lista de consultores ao cadastrar?</h3>
<p>A lista de consultores e carregada automaticamente apos a selecao da loja. Selecione primeiro
a loja desejada e aguarde o carregamento da lista.</p>

<h3>O prazo esta marcado em vermelho. O que significa?</h3>
<p>O indicador vermelho significa que a consignacao ultrapassou o prazo de retorno definido.
O numero indica quantos dias de atraso. Providencie o retorno dos produtos o mais rapido possivel.</p>

<h3>Posso excluir uma consignacao finalizada?</h3>
<p>Somente administradores (nivel 1-3) podem excluir consignacoes finalizadas ou canceladas.
Usuarios de loja so podem excluir consignacoes pendentes.</p>

<h3>O que acontece com os produtos ao excluir uma consignacao?</h3>
<p>Todos os produtos associados sao excluidos automaticamente junto com a consignacao.
Esta acao e irreversivel.</p>

<h3>Como exporto apenas as consignacoes de uma loja?</h3>
<p>Aplique o filtro de loja desejado na busca e depois clique em "Exportar". O CSV contera
apenas os registros filtrados.</p>

<h3>Como imprimo o comprovante de uma consignacao?</h3>
<p>Clique no botao de visualizar (olho), e no modal que abrir, clique em "Imprimir" no rodape.
Uma nova janela sera aberta com o comprovante formatado para impressao.</p>

<h3>Recebo notificacoes sobre consignacoes?</h3>
<p>Sim. O sistema envia notificacoes em tempo real quando uma consignacao e criada, atualizada
ou excluida, para os usuarios com permissao de acesso ao modulo na loja correspondente.</p>

<br>
<div style="text-align: center; margin-top: 40px; padding: 20px; border-top: 2px solid #007bff;">
    <p style="color: #6c757d; font-size: 10pt;">
        <strong>Mercury - Grupo Meia Sola</strong><br>
        Manual do Usuario - Modulo de Consignacoes<br>
        Versao 1.0 - Marco 2026<br>
        Documento gerado automaticamente
    </p>
</div>

</body>
</html>
HTML;

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$outputPath = __DIR__ . '/MANUAL_CONSIGNACOES.pdf';
file_put_contents($outputPath, $dompdf->output());

echo "PDF gerado com sucesso: {$outputPath}\n";
echo "Tamanho: " . number_format(filesize($outputPath) / 1024, 1) . " KB\n";
