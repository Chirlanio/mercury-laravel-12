<?php
/**
 * Gera PDF do Manual de Produtos Avariados
 *
 * Uso: php docs/generate_manual_pdf.php
 * Saida: docs/MANUAL_PRODUTOS_AVARIADOS.pdf
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Ler o markdown
$markdown = file_get_contents(__DIR__ . '/MANUAL_PRODUTOS_AVARIADOS.md');

// Converter markdown basico para HTML
$html = convertMarkdownToHtml($markdown);

// Gerar PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('dpi', 96);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$output = __DIR__ . '/MANUAL_PRODUTOS_AVARIADOS.pdf';
file_put_contents($output, $dompdf->output());
echo "PDF gerado: {$output}\n";
echo "Tamanho: " . round(filesize($output) / 1024) . " KB\n";

/**
 * Converte markdown basico para HTML com estilos embutidos
 */
function convertMarkdownToHtml(string $md): string
{
    $lines = explode("\n", $md);
    $html = '';
    $inCodeBlock = false;
    $inTable = false;
    $inList = false;
    $tableRows = [];

    foreach ($lines as $line) {
        // Code blocks
        if (preg_match('/^```/', $line)) {
            if ($inCodeBlock) {
                $html .= '</pre>';
                $inCodeBlock = false;
            } else {
                $html .= '<pre style="background:#f4f4f4;padding:10px;border-radius:4px;font-size:10px;line-height:1.4;overflow-x:auto;border:1px solid #ddd;">';
                $inCodeBlock = true;
            }
            continue;
        }

        if ($inCodeBlock) {
            $html .= htmlspecialchars($line) . "\n";
            continue;
        }

        // Close table if line doesn't start with |
        if ($inTable && !preg_match('/^\|/', trim($line)) && trim($line) !== '') {
            $html .= renderTable($tableRows);
            $tableRows = [];
            $inTable = false;
        }

        // Tables
        if (preg_match('/^\|/', trim($line))) {
            // Skip separator lines
            if (preg_match('/^\|[\s\-\|:]+\|$/', trim($line))) {
                continue;
            }
            $inTable = true;
            $cells = array_map('trim', explode('|', trim($line, '| ')));
            $tableRows[] = $cells;
            continue;
        }

        // Close list
        if ($inList && !preg_match('/^[\-\d]/', trim($line)) && trim($line) !== '') {
            $html .= '</ul>';
            $inList = false;
        }

        $trimmed = trim($line);

        // Empty line
        if ($trimmed === '') {
            if ($inTable) {
                $html .= renderTable($tableRows);
                $tableRows = [];
                $inTable = false;
            }
            continue;
        }

        // Horizontal rule
        if ($trimmed === '---') {
            $html .= '<hr style="border:none;border-top:1px solid #ccc;margin:15px 0;">';
            continue;
        }

        // Headers
        if (preg_match('/^(#{1,6})\s+(.+)/', $trimmed, $m)) {
            $level = strlen($m[1]);
            $text = formatInline($m[2]);
            $sizes = [1 => '22px', 2 => '18px', 3 => '15px', 4 => '13px', 5 => '12px', 6 => '11px'];
            $colors = [1 => '#1a5276', 2 => '#2c3e50', 3 => '#34495e'];
            $size = $sizes[$level] ?? '12px';
            $color = $colors[$level] ?? '#333';
            $margin = $level <= 2 ? 'margin-top:25px;' : 'margin-top:15px;';
            $border = $level <= 2 ? 'border-bottom:2px solid #3498db;padding-bottom:5px;' : '';
            $html .= "<h{$level} style=\"font-size:{$size};color:{$color};{$margin}margin-bottom:8px;{$border}\">{$text}</h{$level}>";
            continue;
        }

        // Blockquote
        if (preg_match('/^>\s*(.+)/', $trimmed, $m)) {
            $text = formatInline($m[1]);
            $html .= '<div style="background:#e8f4fd;border-left:4px solid #3498db;padding:8px 12px;margin:8px 0;font-size:11px;color:#2c3e50;">' . $text . '</div>';
            continue;
        }

        // Unordered list
        if (preg_match('/^[\-\*]\s+(.+)/', $trimmed, $m)) {
            if (!$inList) {
                $html .= '<ul style="margin:5px 0;padding-left:20px;">';
                $inList = true;
            }
            $html .= '<li style="font-size:11px;margin:3px 0;">' . formatInline($m[1]) . '</li>';
            continue;
        }

        // Ordered list
        if (preg_match('/^\d+\.\s+(.+)/', $trimmed, $m)) {
            if (!$inList) {
                $html .= '<ul style="margin:5px 0;padding-left:20px;">';
                $inList = true;
            }
            $html .= '<li style="font-size:11px;margin:3px 0;">' . formatInline($m[1]) . '</li>';
            continue;
        }

        // Regular paragraph
        $html .= '<p style="font-size:11px;line-height:1.6;margin:5px 0;color:#333;">' . formatInline($trimmed) . '</p>';
    }

    // Close pending elements
    if ($inTable) $html .= renderTable($tableRows);
    if ($inList) $html .= '</ul>';
    if ($inCodeBlock) $html .= '</pre>';

    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; margin: 30px 40px; color: #333; }
        @page { margin: 60px 50px 50px 50px; }
        @page :first { margin-top: 30px; }
    </style></head><body>' . $html . '</body></html>';
}

function renderTable(array $rows): string
{
    if (empty($rows)) return '';

    $html = '<table style="width:100%;border-collapse:collapse;margin:10px 0;font-size:10px;">';

    foreach ($rows as $i => $cells) {
        $tag = ($i === 0) ? 'th' : 'td';
        $bg = ($i === 0) ? 'background:#2c3e50;color:white;' : (($i % 2 === 0) ? 'background:#f8f9fa;' : '');
        $html .= '<tr>';
        foreach ($cells as $cell) {
            $align = ($i > 0 && preg_match('/^\[/', trim($cell))) ? 'text-align:center;' : '';
            $html .= "<{$tag} style=\"border:1px solid #ddd;padding:6px 8px;{$bg}{$align}\">" . formatInline(trim($cell)) . "</{$tag}>";
        }
        $html .= '</tr>';
    }

    $html .= '</table>';
    return $html;
}

function formatInline(string $text): string
{
    // Bold
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    // Italic
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    // Inline code
    $text = preg_replace('/`(.+?)`/', '<code style="background:#f4f4f4;padding:1px 4px;border-radius:3px;font-size:10px;">$1</code>', $text);
    return $text;
}
