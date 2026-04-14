<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enriches the existing "DP" (Departamento Pessoal) helpdesk department with
 * the configuration needed to turn it into the PersonnelRequests channel:
 *
 *  - Enables AI classification with a DP-specific prompt
 *  - Enables auto-assign (round-robin) and requires_identification (CPF)
 *  - Adds missing DP categories not in the original bootstrap seeder
 *  - Creates intake templates for the most common DP requests
 *  - Seeds starter KB articles so deflection via WhatsApp works on day one
 *
 * The original DP seed lives in 2026_04_14_100007_seed_hd_departments_and_categories.php.
 * This migration runs after all hd_* column additions (auto_assign, requires_identification,
 * ai_classification_*), hd_intake_templates and hd_articles tables exist.
 *
 * Idempotent: uses updateOrInsert / existence checks so re-running is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_departments')) {
            return;
        }

        $now = now();

        // ------------------------------------------------------------------
        // 1. Locate or create the DP department
        // ------------------------------------------------------------------
        $dp = DB::table('hd_departments')->where('name', 'DP')->first();

        if (! $dp) {
            // Fallback: original seed hasn't run. Create DP minimally so this
            // migration is self-sufficient on a fresh tenant.
            $dpId = DB::table('hd_departments')->insertGetId([
                'name' => 'DP',
                'description' => 'Departamento Pessoal',
                'icon' => 'UserGroupIcon',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $dp = DB::table('hd_departments')->where('id', $dpId)->first();
        }

        // ------------------------------------------------------------------
        // 2. Update DP with AI + intake settings. We only set columns that
        //    exist, since earlier migrations add them conditionally.
        // ------------------------------------------------------------------
        $update = ['updated_at' => $now];

        if (Schema::hasColumn('hd_departments', 'auto_assign')) {
            $update['auto_assign'] = true;
        }
        if (Schema::hasColumn('hd_departments', 'requires_identification')) {
            $update['requires_identification'] = true;
        }
        if (Schema::hasColumn('hd_departments', 'ai_classification_enabled')) {
            $update['ai_classification_enabled'] = true;
        }
        if (Schema::hasColumn('hd_departments', 'ai_classification_prompt')) {
            $update['ai_classification_prompt'] = <<<'PROMPT'
Você está classificando uma mensagem recebida pelo Departamento Pessoal via WhatsApp.
Classifique entre uma destas categorias (retorne exatamente o nome):
- Férias: pedidos de férias, saldo, programação, venda de abono
- Folha de Pagamento: holerite, contracheque, dúvidas sobre desconto, adiantamento
- Vale Transporte: saldo, recarga, cadastro
- Vale Alimentação: saldo, cartão, bloqueio
- Atestados: envio de atestado médico, afastamento
- Admissão / Demissão: rescisão, documentação, desligamento, nova contratação
- Declarações: declaração de vínculo, IR, comprovante de renda
- Benefícios: plano de saúde, convênio odontológico, auxílios
- 13º Salário: dúvidas sobre parcelas, antecipação
- Alteração Cadastral: endereço, PIX, dados bancários, estado civil
- Outros - DP: qualquer coisa que não se encaixe acima

Se a mensagem for urgente (atestado com dias de afastamento, rescisão iminente) suba a prioridade.
PROMPT;
        }

        DB::table('hd_departments')->where('id', $dp->id)->update($update);

        // ------------------------------------------------------------------
        // 3. Add missing DP categories. The bootstrap seeder created 7 base
        //    categories; we complement with 4 more that map to common DP
        //    requests in Brazilian CLT workflows.
        // ------------------------------------------------------------------
        $extraCategories = [
            ['name' => 'Declarações', 'default_priority' => 2],
            ['name' => 'Benefícios', 'default_priority' => 2],
            ['name' => '13º Salário', 'default_priority' => 2],
            ['name' => 'Alteração Cadastral', 'default_priority' => 1],
        ];

        foreach ($extraCategories as $cat) {
            $exists = DB::table('hd_categories')
                ->where('department_id', $dp->id)
                ->where('name', $cat['name'])
                ->exists();

            if (! $exists) {
                DB::table('hd_categories')->insert([
                    'department_id' => $dp->id,
                    'name' => $cat['name'],
                    'default_priority' => $cat['default_priority'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Refresh category list after insertion
        $categories = DB::table('hd_categories')
            ->where('department_id', $dp->id)
            ->get()
            ->keyBy('name');

        // ------------------------------------------------------------------
        // 4. Intake templates. Reuses the generic hd_intake_templates table
        //    (not WhatsApp-specific — also shows up in web ticket creation).
        //    Only inserts if no template exists yet for the (department, category) pair.
        // ------------------------------------------------------------------
        if (Schema::hasTable('hd_intake_templates')) {
            $templates = [
                'Férias' => [
                    'name' => 'Solicitação de Férias',
                    'fields' => [
                        ['name' => 'periodo_desejado', 'label' => 'Qual período de férias deseja solicitar?', 'type' => 'text', 'required' => true],
                        ['name' => 'dias', 'label' => 'Quantos dias?', 'type' => 'select', 'required' => true, 'options' => ['10', '15', '20', '30']],
                        ['name' => 'vende_abono', 'label' => 'Deseja vender abono pecuniário (até 1/3)?', 'type' => 'boolean'],
                        ['name' => 'adianta_13', 'label' => 'Deseja adiantar o 13º salário?', 'type' => 'boolean'],
                    ],
                ],
                'Folha de Pagamento' => [
                    'name' => 'Dúvida sobre Folha / Holerite',
                    'fields' => [
                        ['name' => 'mes_referencia', 'label' => 'Qual o mês/ano de referência?', 'type' => 'text', 'required' => true],
                        ['name' => 'tipo_duvida', 'label' => 'Sobre o que é a dúvida?', 'type' => 'select', 'required' => true, 'options' => ['Desconto não reconhecido', 'Valor divergente', 'Não recebi o holerite', 'Bônus / Comissão', 'Outro']],
                        ['name' => 'descricao', 'label' => 'Descreva com detalhes', 'type' => 'textarea', 'required' => true],
                    ],
                ],
                'Atestados' => [
                    'name' => 'Envio de Atestado Médico',
                    'fields' => [
                        ['name' => 'data_inicio', 'label' => 'Data de início do afastamento', 'type' => 'date', 'required' => true],
                        ['name' => 'dias_afastamento', 'label' => 'Quantos dias de afastamento?', 'type' => 'text', 'required' => true],
                        ['name' => 'cid', 'label' => 'CID (se informado no atestado)', 'type' => 'text'],
                        ['name' => 'arquivo_atestado', 'label' => 'Anexe a foto/PDF do atestado', 'type' => 'file', 'required' => true],
                    ],
                ],
                'Declarações' => [
                    'name' => 'Solicitação de Declaração',
                    'fields' => [
                        ['name' => 'tipo', 'label' => 'Qual declaração precisa?', 'type' => 'select', 'required' => true, 'options' => ['Vínculo Empregatício', 'IR / Rendimentos', 'Comprovante de Renda', 'Outra']],
                        ['name' => 'finalidade', 'label' => 'Para qual finalidade?', 'type' => 'text'],
                        ['name' => 'observacoes', 'label' => 'Observações', 'type' => 'textarea'],
                    ],
                ],
                'Alteração Cadastral' => [
                    'name' => 'Atualização de Dados Cadastrais',
                    'fields' => [
                        ['name' => 'campo', 'label' => 'Qual dado deseja atualizar?', 'type' => 'select', 'required' => true, 'options' => ['Endereço', 'Telefone', 'E-mail', 'Estado Civil', 'PIX / Dados Bancários', 'Outro']],
                        ['name' => 'novo_valor', 'label' => 'Novo valor', 'type' => 'text', 'required' => true],
                        ['name' => 'comprovante', 'label' => 'Comprovante (se aplicável)', 'type' => 'file'],
                    ],
                ],
            ];

            foreach ($templates as $categoryName => $tmpl) {
                $category = $categories->get($categoryName);
                if (! $category) {
                    continue;
                }

                $exists = DB::table('hd_intake_templates')
                    ->where('department_id', $dp->id)
                    ->where('category_id', $category->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('hd_intake_templates')->insert([
                    'department_id' => $dp->id,
                    'category_id' => $category->id,
                    'name' => $tmpl['name'],
                    'fields' => json_encode($tmpl['fields']),
                    'active' => true,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // ------------------------------------------------------------------
        // 5. KB articles — starter content so WhatsApp deflection has something
        //    to suggest via findKbSuggestion() (MySQL MATCH AGAINST).
        // ------------------------------------------------------------------
        if (Schema::hasTable('hd_articles')) {
            $articles = [
                [
                    'slug' => 'como-solicitar-ferias',
                    'title' => 'Como consultar e solicitar férias',
                    'summary' => 'Passo a passo para consultar saldo, planejar e solicitar seu período de férias.',
                    'category' => 'Férias',
                    'content_md' => <<<'MD'
# Como consultar e solicitar férias

## Consultando seu saldo
Você pode consultar seu saldo de férias em duas formas:

1. **Pelo sistema Mercury:** acesse *RH > Meus Dados > Férias* para ver períodos aquisitivos e dias disponíveis.
2. **Pelo WhatsApp do DP:** envie "saldo de férias" e um atendente retorna com a situação atual.

## Solicitando
Para abrir uma solicitação de férias:

1. Converse com seu gestor direto com pelo menos **30 dias de antecedência**.
2. Envie a solicitação para o DP via WhatsApp informando: **período desejado**, **quantidade de dias** e se deseja **vender abono** (até 1/3) ou **adiantar 13º**.
3. O DP confirma a programação e envia o aviso oficial.

## Regras importantes (CLT)
- Período mínimo de 5 dias por parcela quando divididas.
- Não é permitido iniciar férias em véspera de feriado ou sábado/domingo.
- O aviso deve ser emitido com 30 dias de antecedência.

Se sua dúvida não foi respondida, **abra um ticket** ou continue a conversa com um atendente.
MD,
                ],
                [
                    'slug' => 'como-baixar-holerite',
                    'title' => 'Onde baixar meu holerite e contracheque',
                    'summary' => 'Como acessar seu holerite mensal e o que fazer em caso de divergências.',
                    'category' => 'Folha de Pagamento',
                    'content_md' => <<<'MD'
# Onde baixar meu holerite

## Acesso mensal
O holerite fica disponível todo mês no sistema a partir do **quinto dia útil**.

1. Acesse *RH > Meus Dados > Holerites* no Mercury.
2. Selecione o mês/ano desejado.
3. Clique em "Baixar PDF".

## Dúvidas comuns
- **Desconto não reconhecido:** registre um ticket no DP com o mês de referência e o nome do desconto. Resposta em até 2 dias úteis.
- **Valor divergente:** envie o holerite + os cálculos esperados. O DP revisa com a folha.
- **Não recebi:** verifique se o quinto dia útil já passou. Se sim, avise o DP.

## 13º salário
A primeira parcela cai em novembro e a segunda em dezembro, salvo adiantamento solicitado via férias.
MD,
                ],
                [
                    'slug' => 'declaracao-de-vinculo',
                    'title' => 'Como pedir declaração de vínculo empregatício',
                    'summary' => 'Procedimento para solicitar declaração de vínculo, IR e comprovante de renda.',
                    'category' => 'Declarações',
                    'content_md' => <<<'MD'
# Solicitando declarações

O DP emite os seguintes documentos:

- **Declaração de Vínculo Empregatício**
- **Declaração de Rendimentos (IR)**
- **Comprovante de Renda**
- **Declaração de Horário de Trabalho**

## Como pedir
Envie mensagem para o DP informando:
1. **Tipo de declaração**
2. **Finalidade** (banco, cartório, consulado, etc.)
3. **Prazo** em que precisa

O documento é emitido em até **3 dias úteis** e enviado por e-mail / WhatsApp com assinatura digital.

## Retirada presencial
Se precisar com assinatura física, retire na sede do DP durante horário comercial.
MD,
                ],
                [
                    'slug' => 'envio-de-atestado-medico',
                    'title' => 'Procedimento para envio de atestado médico',
                    'summary' => 'Como enviar seu atestado, prazos e o que fazer em caso de afastamento longo.',
                    'category' => 'Atestados',
                    'content_md' => <<<'MD'
# Envio de Atestado Médico

## Prazo para envio
Você deve enviar o atestado para o DP em **até 48 horas** após a consulta. Atrasos podem resultar em falta não abonada.

## Como enviar
1. Tire uma foto nítida (ou PDF) do atestado original, sem cortar bordas.
2. Envie para o WhatsApp do DP junto com: **data de início**, **dias de afastamento** e **CID** (se informado).
3. Aguarde a confirmação de recebimento.

## Afastamento superior a 15 dias
A partir do 16º dia, o afastamento é encaminhado ao INSS. O DP orienta sobre a perícia e documentos necessários.

## Importante
- O atestado original deve ser entregue em mãos ao gestor ou DP.
- Atestados com rasuras ou sem CRM do médico não são aceitos.
MD,
                ],
                [
                    'slug' => 'beneficios-corporativos',
                    'title' => 'Benefícios corporativos: como usar e consultar saldo',
                    'summary' => 'Vale Transporte, Vale Alimentação, plano de saúde e odontológico — como funciona e quem contatar.',
                    'category' => 'Benefícios',
                    'content_md' => <<<'MD'
# Benefícios corporativos

## Vale Transporte
- Desconto em folha: 6% do salário-base, limitado ao valor do VT.
- Recarga mensal automática até o 5º dia útil.
- Para alteração de trajeto, envie o novo endereço ao DP.

## Vale Alimentação
- Crédito todo dia 5 no cartão Flash/Alelo (varia por loja).
- Saldo pode ser consultado no app da operadora.
- Perda ou roubo do cartão: avise o DP imediatamente para bloqueio.

## Plano de Saúde
- Inclusão de dependentes: envie documentação ao DP.
- Carteirinha digital disponível no app da operadora.
- Reembolsos: solicite diretamente à operadora, não ao DP.

## Plano Odontológico
- Opcional — adesão em até 30 dias após admissão ou em janeiro de cada ano.

## Dúvidas
Para dúvidas específicas abra um ticket na categoria **Benefícios** do DP.
MD,
                ],
            ];

            // Resolve an author for seeded articles (prefer first admin, else any user)
            $authorId = DB::table('users')->where('role', 'super_admin')->value('id')
                ?? DB::table('users')->where('role', 'admin')->value('id')
                ?? DB::table('users')->value('id');

            foreach ($articles as $art) {
                $category = $categories->get($art['category']);

                $exists = DB::table('hd_articles')->where('slug', $art['slug'])->exists();
                if ($exists) {
                    continue;
                }

                // Simple markdown → HTML for the initial seed. The model's
                // boot hook would rerender on save; for seed we store both
                // with a minimal pass-through so the public view works.
                $html = '<p>'.nl2br(e($art['content_md'])).'</p>';

                DB::table('hd_articles')->insert([
                    'slug' => $art['slug'],
                    'title' => $art['title'],
                    'summary' => $art['summary'],
                    'content_md' => $art['content_md'],
                    'content_html' => $html,
                    'department_id' => $dp->id,
                    'category_id' => $category?->id,
                    'is_published' => true,
                    'published_at' => $now,
                    'view_count' => 0,
                    'helpful_count' => 0,
                    'not_helpful_count' => 0,
                    'author_id' => $authorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hd_departments')) {
            return;
        }

        $dp = DB::table('hd_departments')->where('name', 'DP')->first();
        if (! $dp) {
            return;
        }

        // Revert DP settings to defaults. We don't drop the department itself
        // since it predates this migration.
        $update = ['updated_at' => now()];
        foreach (['auto_assign', 'requires_identification', 'ai_classification_enabled'] as $col) {
            if (Schema::hasColumn('hd_departments', $col)) {
                $update[$col] = false;
            }
        }
        if (Schema::hasColumn('hd_departments', 'ai_classification_prompt')) {
            $update['ai_classification_prompt'] = null;
        }
        DB::table('hd_departments')->where('id', $dp->id)->update($update);

        // Remove only the categories added by this migration
        $addedCategoryNames = ['Declarações', 'Benefícios', '13º Salário', 'Alteração Cadastral'];

        if (Schema::hasTable('hd_intake_templates')) {
            $categoryIds = DB::table('hd_categories')
                ->where('department_id', $dp->id)
                ->whereIn('name', $addedCategoryNames)
                ->pluck('id');

            DB::table('hd_intake_templates')
                ->where('department_id', $dp->id)
                ->whereIn('category_id', $categoryIds)
                ->delete();

            // Also remove intake templates for base categories we created here.
            $baseTemplateNames = [
                'Solicitação de Férias',
                'Dúvida sobre Folha / Holerite',
                'Envio de Atestado Médico',
                'Solicitação de Declaração',
                'Atualização de Dados Cadastrais',
            ];
            DB::table('hd_intake_templates')
                ->where('department_id', $dp->id)
                ->whereIn('name', $baseTemplateNames)
                ->delete();
        }

        DB::table('hd_categories')
            ->where('department_id', $dp->id)
            ->whereIn('name', $addedCategoryNames)
            ->delete();

        if (Schema::hasTable('hd_articles')) {
            $seededSlugs = [
                'como-solicitar-ferias',
                'como-baixar-holerite',
                'declaracao-de-vinculo',
                'envio-de-atestado-medico',
                'beneficios-corporativos',
            ];
            DB::table('hd_articles')->whereIn('slug', $seededSlugs)->delete();
        }
    }
};
