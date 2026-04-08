<?php

namespace Database\Seeders;

use App\Models\MovementType;
use Illuminate\Database\Seeder;

class MovementTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [1, 'Compra'],
            [2, 'Venda'],
            [3, 'Conserto'],
            [4, 'Consignada'],
            [5, 'Transferência'],
            [6, 'Devolução'],
            [7, 'Bonificação/Doação'],
            [8, 'Brinde'],
            [9, 'Ajuste/Acerto'],
            [10, 'Consumo'],
            [11, 'Inventário'],
            [12, 'Empréstimo'],
            [13, 'Produção'],
            [14, 'Quebra'],
            [15, 'Serviço'],
            [16, 'Cancelamento'],
            [17, 'Ordem de Compra'],
            [18, 'Reserva'],
            [19, 'Outra Saída/Serviço'],
            [20, 'Remessa'],
            [21, 'Retorno'],
            [22, 'Outras Entradas/Serviços'],
            [23, 'Estorno'],
            [25, 'Demonstração'],
            [26, 'Lançamento'],
        ];

        foreach ($types as [$code, $description]) {
            MovementType::updateOrCreate(
                ['code' => $code],
                ['description' => $description]
            );
        }
    }
}
