<?php

namespace Database\Seeders;

use App\Models\ChecklistArea;
use App\Models\ChecklistQuestion;
use Illuminate\Database\Seeder;

class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            [
                'name' => 'Fachada e Vitrine',
                'description' => 'Avaliação da apresentação externa da loja',
                'weight' => 1,
                'display_order' => 1,
                'questions' => [
                    'A fachada da loja está limpa e em bom estado de conservação?',
                    'A vitrine está organizada e atrativa?',
                    'A iluminação externa está funcionando corretamente?',
                    'Os adesivos e comunicação visual estão atualizados?',
                    'A calçada em frente à loja está limpa?',
                ],
            ],
            [
                'name' => 'Ambiente Interno',
                'description' => 'Avaliação do ambiente interno da loja',
                'weight' => 1,
                'display_order' => 2,
                'questions' => [
                    'O piso está limpo e sem manchas?',
                    'A iluminação interna está funcionando em todos os pontos?',
                    'A climatização está adequada?',
                    'Os espelhos estão limpos?',
                    'O provador está limpo e organizado?',
                    'O banheiro está limpo e abastecido?',
                ],
            ],
            [
                'name' => 'Exposição de Produtos',
                'description' => 'Avaliação da organização e exposição dos produtos',
                'weight' => 2,
                'display_order' => 3,
                'questions' => [
                    'Os produtos estão organizados por categoria/coleção?',
                    'Todos os produtos possuem etiqueta de preço visível?',
                    'As grades de tamanho estão completas nos expositores?',
                    'Os produtos em promoção estão devidamente sinalizados?',
                    'As mesas e prateleiras estão organizadas e completas?',
                    'Os cabides estão padronizados e em bom estado?',
                ],
            ],
            [
                'name' => 'Atendimento',
                'description' => 'Avaliação da qualidade do atendimento ao cliente',
                'weight' => 2,
                'display_order' => 4,
                'questions' => [
                    'Os colaboradores estão uniformizados e com crachá?',
                    'O cliente é abordado em até 30 segundos após entrar na loja?',
                    'Os colaboradores demonstram conhecimento dos produtos?',
                    'O atendimento segue o roteiro padrão da empresa?',
                    'Os colaboradores oferecem produtos complementares?',
                ],
            ],
            [
                'name' => 'Caixa e Operacional',
                'description' => 'Avaliação da operação de caixa e processos operacionais',
                'weight' => 1,
                'display_order' => 5,
                'questions' => [
                    'A área do caixa está organizada e limpa?',
                    'O sistema PDV está funcionando corretamente?',
                    'As sacolas e embalagens estão disponíveis e organizadas?',
                    'Os procedimentos de abertura/fechamento de caixa são seguidos?',
                    'O troco está adequado para o movimento do dia?',
                ],
            ],
            [
                'name' => 'Estoque',
                'description' => 'Avaliação da organização e gestão do estoque',
                'weight' => 1,
                'display_order' => 6,
                'questions' => [
                    'O estoque está organizado e identificado?',
                    'Os produtos estão armazenados corretamente?',
                    'O acesso ao estoque é restrito e controlado?',
                    'Não há produtos vencidos ou danificados no estoque?',
                ],
            ],
        ];

        foreach ($areas as $areaData) {
            $questions = $areaData['questions'];
            unset($areaData['questions']);

            $area = ChecklistArea::create($areaData);

            foreach ($questions as $index => $questionText) {
                ChecklistQuestion::create([
                    'checklist_area_id' => $area->id,
                    'description' => $questionText,
                    'points' => 1,
                    'weight' => 1,
                    'display_order' => $index + 1,
                    'is_active' => true,
                ]);
            }
        }
    }
}
