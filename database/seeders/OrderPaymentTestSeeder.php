<?php

namespace Database\Seeders;

use App\Models\CostCenter;
use App\Models\ManagementReason;
use App\Models\OrderPayment;
use App\Models\OrderPaymentInstallment;
use App\Models\OrderPaymentStatusHistory;
use Illuminate\Database\Seeder;

class OrderPaymentTestSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 1;

        // Criar dados de referencia faltantes
        if (CostCenter::count() === 0) {
            $costCenters = [
                ['code' => 'CC001', 'name' => 'Operacional Lojas', 'area_id' => 4, 'is_active' => true],
                ['code' => 'CC002', 'name' => 'Marketing e Publicidade', 'area_id' => 5, 'is_active' => true],
                ['code' => 'CC003', 'name' => 'Administrativo', 'area_id' => 1, 'is_active' => true],
                ['code' => 'CC004', 'name' => 'Logistica e Transporte', 'area_id' => 1, 'is_active' => true],
                ['code' => 'CC005', 'name' => 'TI e Sistemas', 'area_id' => 1, 'is_active' => true],
                ['code' => 'CC006', 'name' => 'E-commerce', 'area_id' => 3, 'is_active' => true],
            ];
            foreach ($costCenters as $cc) {
                CostCenter::create($cc);
            }
        }

        if (ManagementReason::count() === 0) {
            $reasons = [
                ['code' => 'MG01', 'name' => 'Reposicao de Estoque', 'is_active' => true],
                ['code' => 'MG02', 'name' => 'Manutencao Predial', 'is_active' => true],
                ['code' => 'MG03', 'name' => 'Material de Escritorio', 'is_active' => true],
                ['code' => 'MG04', 'name' => 'Servico Terceirizado', 'is_active' => true],
                ['code' => 'MG05', 'name' => 'Campanha Promocional', 'is_active' => true],
            ];
            foreach ($reasons as $r) {
                ManagementReason::create($r);
            }
        }

        $orders = [
            // BACKLOG (5)
            ['supplier_id' => 1, 'store_id' => 1, 'area_id' => 4, 'description' => 'Material de limpeza e higiene - Janeiro', 'total_value' => 1250.00, 'date_payment' => '2026-04-15', 'payment_type' => 'Boleto', 'status' => 'backlog', 'manager_id' => 1, 'installments' => 0],
            ['supplier_id' => 3, 'store_id' => 2, 'area_id' => 1, 'description' => 'Hospedagem equipe treinamento regional', 'total_value' => 4800.00, 'date_payment' => '2026-04-20', 'payment_type' => 'Transferencia Bancaria', 'status' => 'backlog', 'manager_id' => 3, 'installments' => 2],
            ['supplier_id' => 5, 'store_id' => 3, 'area_id' => 4, 'description' => 'Reforma balcao de atendimento', 'total_value' => 8500.00, 'date_payment' => '2026-04-25', 'payment_type' => 'PIX', 'status' => 'backlog', 'manager_id' => 4, 'installments' => 0],
            ['supplier_id' => 10, 'store_id' => 4, 'area_id' => 5, 'description' => 'Banners e material grafico campanha verao', 'total_value' => 3200.00, 'date_payment' => '2026-04-18', 'payment_type' => 'Boleto', 'status' => 'backlog', 'manager_id' => 1, 'installments' => 0],
            ['supplier_id' => 15, 'store_id' => 5, 'area_id' => 1, 'description' => 'Consultoria fiscal trimestral', 'total_value' => 6750.00, 'date_payment' => '2026-04-30', 'payment_type' => 'Transferencia Bancaria', 'status' => 'backlog', 'manager_id' => 3, 'installments' => 3],

            // DOING (4)
            ['supplier_id' => 2, 'store_id' => 1, 'area_id' => 4, 'description' => 'Uniformes novos consultoras - lote 2026', 'total_value' => 9800.00, 'date_payment' => '2026-04-10', 'payment_type' => 'Boleto', 'status' => 'doing', 'number_nf' => 'NF-2026-0412', 'launch_number' => 'LC-0098', 'manager_id' => 1, 'installments' => 3],
            ['supplier_id' => 8, 'store_id' => 6, 'area_id' => 1, 'description' => 'Manutencao ar condicionado - todas as lojas', 'total_value' => 12350.00, 'date_payment' => '2026-04-08', 'payment_type' => 'Transferencia Bancaria', 'status' => 'doing', 'number_nf' => 'NF-2026-0398', 'launch_number' => 'LC-0099', 'manager_id' => 4, 'installments' => 0],
            ['supplier_id' => 20, 'store_id' => 7, 'area_id' => 3, 'description' => 'Licenca software gestao e-commerce anual', 'total_value' => 15000.00, 'date_payment' => '2026-04-12', 'payment_type' => 'PIX', 'status' => 'doing', 'number_nf' => 'NF-2026-0415', 'launch_number' => 'LC-0100', 'manager_id' => 3, 'installments' => 0],
            ['supplier_id' => 25, 'store_id' => 8, 'area_id' => 4, 'description' => 'Embalagens personalizadas lote trimestral', 'total_value' => 5600.00, 'date_payment' => '2026-04-05', 'payment_type' => 'Boleto', 'status' => 'doing', 'number_nf' => 'NF-2026-0388', 'launch_number' => 'LC-0101', 'manager_id' => 1, 'installments' => 0],

            // WAITING (4)
            ['supplier_id' => 4, 'store_id' => 2, 'area_id' => 4, 'description' => 'Aluguel espaco evento lancamento colecao', 'total_value' => 7200.00, 'date_payment' => '2026-03-28', 'payment_type' => 'PIX', 'status' => 'waiting', 'number_nf' => 'NF-2026-0350', 'launch_number' => 'LC-0085', 'pix_key_type' => 'CNPJ', 'pix_key' => '12.345.678/0001-90', 'manager_id' => 4, 'installments' => 0],
            ['supplier_id' => 12, 'store_id' => 3, 'area_id' => 1, 'description' => 'Seguro predial renovacao anual', 'total_value' => 18500.00, 'date_payment' => '2026-03-25', 'payment_type' => 'Boleto', 'status' => 'waiting', 'number_nf' => 'NF-2026-0340', 'launch_number' => 'LC-0082', 'manager_id' => 3, 'installments' => 6],
            ['supplier_id' => 30, 'store_id' => 9, 'area_id' => 5, 'description' => 'Producao catalogo digital primavera 2026', 'total_value' => 4200.00, 'date_payment' => '2026-04-02', 'payment_type' => 'Transferencia Bancaria', 'status' => 'waiting', 'number_nf' => 'NF-2026-0375', 'launch_number' => 'LC-0090', 'bank_name' => 'Banco do Brasil', 'agency' => '1234-5', 'checking_account' => '12345-6', 'manager_id' => 1, 'installments' => 0],
            ['supplier_id' => 35, 'store_id' => 10, 'area_id' => 4, 'description' => 'Servico de dedetizacao semestral', 'total_value' => 2800.00, 'date_payment' => '2026-03-30', 'payment_type' => 'PIX', 'status' => 'waiting', 'number_nf' => 'NF-2026-0360', 'launch_number' => 'LC-0088', 'pix_key_type' => 'CPF', 'pix_key' => '123.456.789-00', 'manager_id' => 4, 'installments' => 0],

            // DONE (3)
            ['supplier_id' => 6, 'store_id' => 1, 'area_id' => 4, 'description' => 'Energia eletrica - fevereiro 2026', 'total_value' => 3450.00, 'date_payment' => '2026-03-10', 'date_paid' => '2026-03-10', 'payment_type' => 'Boleto', 'status' => 'done', 'number_nf' => 'NF-2026-0280', 'launch_number' => 'LC-0070', 'manager_id' => 1, 'installments' => 0],
            ['supplier_id' => 18, 'store_id' => 4, 'area_id' => 1, 'description' => 'Honorarios advocaticios processo trabalhista', 'total_value' => 8000.00, 'date_payment' => '2026-03-15', 'date_paid' => '2026-03-14', 'payment_type' => 'Transferencia Bancaria', 'status' => 'done', 'number_nf' => 'NF-2026-0295', 'launch_number' => 'LC-0073', 'bank_name' => 'Itau', 'agency' => '5678', 'checking_account' => '98765-0', 'manager_id' => 3, 'installments' => 0],
            ['supplier_id' => 22, 'store_id' => 5, 'area_id' => 5, 'description' => 'Impressao material PDV campanha dia das maes', 'total_value' => 5100.00, 'date_payment' => '2026-03-20', 'date_paid' => '2026-03-19', 'payment_type' => 'PIX', 'status' => 'done', 'number_nf' => 'NF-2026-0310', 'launch_number' => 'LC-0076', 'pix_key_type' => 'E-mail', 'pix_key' => 'financeiro@grafica.com.br', 'manager_id' => 1, 'installments' => 0],

            // BACKLOG vencidas (para testar indicador overdue)
            ['supplier_id' => 40, 'store_id' => 2, 'area_id' => 4, 'description' => 'Manutencao elevador - urgente', 'total_value' => 4500.00, 'date_payment' => '2026-03-01', 'payment_type' => 'Boleto', 'status' => 'backlog', 'manager_id' => 4, 'installments' => 0],
            ['supplier_id' => 45, 'store_id' => 6, 'area_id' => 1, 'description' => 'Contabilidade - fechamento marco', 'total_value' => 3800.00, 'date_payment' => '2026-03-05', 'payment_type' => 'Transferencia Bancaria', 'status' => 'backlog', 'manager_id' => 3, 'installments' => 0],
        ];

        $statusSequence = ['backlog', 'doing', 'waiting', 'done'];

        foreach ($orders as $data) {
            $installmentCount = $data['installments'];
            unset($data['installments']);

            $order = OrderPayment::create(array_merge($data, [
                'advance' => false,
                'proof' => false,
                'payment_prepared' => $data['status'] === 'done',
                'has_allocation' => false,
                'created_by_user_id' => $userId,
            ]));

            // Status history - criacao
            OrderPaymentStatusHistory::create([
                'order_payment_id' => $order->id,
                'old_status' => null,
                'new_status' => 'backlog',
                'changed_by_user_id' => $userId,
                'notes' => 'Criacao',
            ]);

            // Simular transicoes ate o status atual
            $targetIdx = array_search($data['status'], $statusSequence);
            for ($i = 1; $i <= $targetIdx; $i++) {
                OrderPaymentStatusHistory::create([
                    'order_payment_id' => $order->id,
                    'old_status' => $statusSequence[$i - 1],
                    'new_status' => $statusSequence[$i],
                    'changed_by_user_id' => $userId,
                    'notes' => 'Transicao automatica (seed)',
                ]);
            }

            // Parcelas
            if ($installmentCount > 0) {
                $parcelValue = round($data['total_value'] / $installmentCount, 2);
                for ($i = 1; $i <= $installmentCount; $i++) {
                    $parcelDate = date('Y-m-d', strtotime($data['date_payment'] . ' + ' . (($i - 1) * 30) . ' days'));
                    $isPaid = $data['status'] === 'done';
                    OrderPaymentInstallment::create([
                        'order_payment_id' => $order->id,
                        'installment_number' => $i,
                        'installment_value' => $parcelValue,
                        'date_payment' => $parcelDate,
                        'is_paid' => $isPaid,
                        'date_paid' => $isPaid ? ($data['date_paid'] ?? null) : null,
                    ]);
                }
            }
        }

        $this->command->info('Ordens criadas: ' . OrderPayment::count());
        $this->command->info('Parcelas criadas: ' . OrderPaymentInstallment::count());
        $this->command->info('Historico criado: ' . OrderPaymentStatusHistory::count());
        foreach ($statusSequence as $s) {
            $this->command->info("  {$s}: " . OrderPayment::where('status', $s)->count());
        }
    }
}
