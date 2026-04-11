<?php

namespace App\Services;

use App\Models\DeliveryRoute;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class DeliveryManifestService
{
    public function generate(DeliveryRoute $route)
    {
        $route->load(['driver', 'items.delivery.store', 'createdBy']);

        $html = $this->buildHtml($route);
        $pdf = PDF::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("manifesto_rota_{$route->route_number}.pdf");
    }

    private function buildHtml(DeliveryRoute $route): string
    {
        $items = $route->items->map(fn ($item, $i) => "
            <tr>
                <td style='padding:6px;border:1px solid #ddd;text-align:center;'>{$item->sequence_order}</td>
                <td style='padding:6px;border:1px solid #ddd;'>{$item->client_name}</td>
                <td style='padding:6px;border:1px solid #ddd;font-size:11px;'>{$item->address}</td>
                <td style='padding:6px;border:1px solid #ddd;text-align:center;'>{$item->delivery->contact_phone}</td>
                <td style='padding:6px;border:1px solid #ddd;'></td>
                <td style='padding:6px;border:1px solid #ddd;'></td>
            </tr>
        ")->implode('');

        $date = $route->date_route->format('d/m/Y');
        $driver = e($route->driver->name);
        $routeNumber = e($route->route_number);
        $totalItems = $route->items->count();

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 13px; color: #333; margin: 15mm; }
                h1 { font-size: 20px; margin-bottom: 5mm; color: #1a1a1a; }
                .meta { font-size: 12px; color: #666; margin-bottom: 8mm; }
                .meta strong { color: #333; }
                table { width: 100%; border-collapse: collapse; margin-top: 5mm; }
                th { background: #f3f4f6; padding: 8px; border: 1px solid #ddd; font-size: 11px; text-transform: uppercase; color: #555; }
                .footer { margin-top: 15mm; font-size: 11px; color: #999; text-align: center; }
            </style>
        </head>
        <body>
            <h1>Manifesto de Rota</h1>
            <div class="meta">
                <strong>Rota:</strong> {$routeNumber} &nbsp;&nbsp;
                <strong>Motorista:</strong> {$driver} &nbsp;&nbsp;
                <strong>Data:</strong> {$date} &nbsp;&nbsp;
                <strong>Entregas:</strong> {$totalItems}
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>Cliente</th>
                        <th>Endereço</th>
                        <th style="width:90px;">Telefone</th>
                        <th style="width:90px;">Recebido por</th>
                        <th style="width:80px;">Assinatura</th>
                    </tr>
                </thead>
                <tbody>{$items}</tbody>
            </table>
            <div class="footer">Mercury - Grupo Meia Sola</div>
        </body>
        </html>
        HTML;
    }
}
