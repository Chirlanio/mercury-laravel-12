<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_templates')) {
            return;
        }

        DB::table('certificate_templates')
            ->where('name', 'Template Padrão')
            ->update([
                'html_template' => $this->getTemplate(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Reversível mas não restaura o template anterior
    }

    private function getTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            width: 297mm; height: 210mm;
            font-family: Georgia, "Times New Roman", serif;
            background: #fff;
            position: relative;
            overflow: hidden;
            color: #1e293b;
        }

        /* === Borda dupla dourada === */
        .border-double {
            position: absolute;
            top: 6mm; left: 6mm; right: 6mm; bottom: 6mm;
            border: 4mm double #e8d5a3;
        }
        /* Borda interna fina */
        .border-inner {
            position: absolute;
            top: 14mm; left: 14mm; right: 14mm; bottom: 14mm;
            border: 0.5mm solid #dbc48e;
        }

        /* === Cantos em L === */
        .corner-tl { position: absolute; top: 8mm; left: 8mm; width: 18mm; height: 18mm; border-top: 1mm solid #c9943a; border-left: 1mm solid #c9943a; }
        .corner-tr { position: absolute; top: 8mm; right: 8mm; width: 18mm; height: 18mm; border-top: 1mm solid #c9943a; border-right: 1mm solid #c9943a; }
        .corner-bl { position: absolute; bottom: 8mm; left: 8mm; width: 18mm; height: 18mm; border-bottom: 1mm solid #c9943a; border-left: 1mm solid #c9943a; }
        .corner-br { position: absolute; bottom: 8mm; right: 8mm; width: 18mm; height: 18mm; border-bottom: 1mm solid #c9943a; border-right: 1mm solid #c9943a; }

        /* === Conte&uacute;do centralizado (padding calculado) === */
        /*
         * P&aacute;gina: 210mm | Topo: 18mm | Assinaturas: 40mm rodap&eacute;
         * &Aacute;rea &uacute;til: 210 - 18 - 40 = 152mm
         * Conte&uacute;do: ~80mm | Padding: (152 - 80) / 2 + 18 = ~54mm
         */
        .content {
            text-align: center;
            padding: 38mm 50mm 0 50mm;
        }

        /* === Medalha === */
        .medal {
            color: #b45309;
            font-size: 40px;
            padding-bottom: 3mm;
        }

        /* === Tipografia === */
        .title {
            font-size: 42px;
            color: #1e293b;
            letter-spacing: 2px;
            padding-bottom: 4mm;
        }
        .separator {
            display: inline-block;
            width: 40mm;
            border-top: 0.5mm solid #d4a94b;
            margin-bottom: 5mm;
        }
        .certify {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
            color: #475569;
            padding-bottom: 4mm;
        }
        .participant {
            font-size: 40px;
            color: #0f172a;
            padding-bottom: 2mm;
        }
        .participant-line {
            display: inline-block;
            width: 120mm;
            border-bottom: 0.3mm solid #d4a94b;
            margin-bottom: 5mm;
        }
        .course-label {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
            color: #475569;
            padding-bottom: 3mm;
        }
        .course-name {
            font-size: 28px;
            color: #b45309;
            padding-bottom: 5mm;
        }
        .details {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            color: #475569;
            line-height: 1.8;
            padding-bottom: 3mm;
        }
        .date {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            color: #475569;
        }

        /* === Assinaturas no rodap&eacute; === */
        .signatures {
            position: absolute;
            bottom: 18mm;
            left: 0;
            width: 297mm;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            padding: 0 40mm;
        }
        .sig-line {
            border-top: 0.3mm solid #94a3b8;
            margin-bottom: 2mm;
        }
        .sig-label {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #475569;
        }

        /* === C&oacute;digo === */
        .code {
            position: absolute;
            bottom: 10mm;
            left: 0;
            width: 297mm;
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            color: #cbd5e1;
        }
    </style>
</head>
<body>
    <!-- Bordas decorativas -->
    <div class="border-double"></div>
    <div class="border-inner"></div>
    <div class="corner-tl"></div>
    <div class="corner-tr"></div>
    <div class="corner-bl"></div>
    <div class="corner-br"></div>

    <!-- Conte&uacute;do -->
    <div class="content">
        <div class="medal">&#9733;</div>
        <div class="title">Certificado de Conclus&atilde;o</div>
        <div class="separator"></div><br>
        <div class="certify">Certificamos que</div>
        <div class="participant">{{participant_name}}</div>
        <div class="participant-line"></div><br>
        <div class="course-label">concluiu com &ecirc;xito o curso de</div>
        <div class="course-name">{{training_title}}</div>
        <div class="details">
            com carga hor&aacute;ria de {{duration}} horas
        </div>
        <div class="date">{{training_date}}</div>
    </div>

    <!-- Assinaturas -->
    <table class="signatures">
        <tr>
            <td>
                <div class="sig-line"></div>
                <div class="sig-label">Diretor(a)</div>
            </td>
            <td>
                <div class="sig-line"></div>
                <div class="sig-label">{{facilitator_name}}</div>
            </td>
        </tr>
    </table>

    <div class="code">C&oacute;digo: {{certificate_code}}</div>
</body>
</html>
HTML;
    }
};
