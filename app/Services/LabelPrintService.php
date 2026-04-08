<?php

namespace App\Services;

use App\Models\ProductVariant;
use Barryvdh\DomPDF\Facade\Pdf;
use Picqer\Barcode\BarcodeGeneratorPNG;

class LabelPrintService
{
    public function generatePdf(array $variantIds, array $preset): \Barryvdh\DomPDF\PDF
    {
        $variants = ProductVariant::with(['product', 'size'])
            ->whereIn('id', $variantIds)
            ->get();

        $generator = new BarcodeGeneratorPNG();

        $labels = $variants->map(function ($variant) use ($generator) {
            $barcodeNumber = $variant->barcode ?: $variant->aux_reference ?: $variant->product->reference;
            $barcodeImage = null;

            if ($barcodeNumber) {
                try {
                    $type = $this->detectBarcodeType($barcodeNumber);
                    $png = $generator->getBarcode($barcodeNumber, $type, 2, 40);
                    $barcodeImage = 'data:image/png;base64,' . base64_encode($png);
                } catch (\Exception $e) {
                    // Fallback: try CODE-128
                    try {
                        $png = $generator->getBarcode($barcodeNumber, $generator::TYPE_CODE_128, 2, 40);
                        $barcodeImage = 'data:image/png;base64,' . base64_encode($png);
                    } catch (\Exception) {
                        $barcodeImage = null;
                    }
                }
            }

            return [
                'reference' => $variant->product->reference,
                'description' => $variant->product->description,
                'size_name' => $variant->size?->name ?? $variant->size_cigam_code ?? '-',
                'barcode_number' => $barcodeNumber,
                'barcode_image' => $barcodeImage,
                'sale_price' => $variant->product->sale_price
                    ? 'R$ ' . number_format((float) $variant->product->sale_price, 2, ',', '.')
                    : null,
            ];
        })->all();

        $width = (float) $preset['width'];
        $height = (float) $preset['height'];
        $columns = (int) $preset['columns'];
        $gap = (float) $preset['gap'];
        $format = $preset['format'];

        // Calculate paper size in points (1mm = 2.83465pt)
        $mmToPt = 2.83465;

        if ($format === 'A4') {
            $paperWidth = 210;
            $paperHeight = 297;
            $labelsPerRow = $columns;
            $labelsPerCol = max(1, floor(($paperHeight - 10) / ($height + $gap)));
            $labelsPerPage = $labelsPerRow * $labelsPerCol;
        } else {
            // Custom/roll: paper sized to fit labels
            $paperWidth = ($columns * $width) + (($columns - 1) * $gap) + 4;
            $paperHeight = $height + 4;
            $labelsPerPage = $columns;
        }

        $pdf = Pdf::loadView('pdf.product-labels', [
            'labels' => $labels,
            'preset' => $preset,
            'paperWidth' => $paperWidth,
            'paperHeight' => $paperHeight,
            'labelsPerPage' => $labelsPerPage,
        ]);

        $pdf->setPaper([0, 0, $paperWidth * $mmToPt, $paperHeight * $mmToPt]);

        return $pdf;
    }

    protected function detectBarcodeType(string $code): string
    {
        $generator = new BarcodeGeneratorPNG();
        $cleaned = preg_replace('/\D/', '', $code);

        if (strlen($cleaned) === 13) {
            return $generator::TYPE_EAN_13;
        }

        if (strlen($cleaned) === 12) {
            return $generator::TYPE_UPC_A;
        }

        return $generator::TYPE_CODE_128;
    }
}
