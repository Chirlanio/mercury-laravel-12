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

        // Template Formal
        DB::table('certificate_templates')->insert([
            'name' => 'Formal',
            'html_template' => $this->formalTemplate(),
            'is_default' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Template Minimalista
        DB::table('certificate_templates')->insert([
            'name' => 'Minimalista',
            'html_template' => $this->minimalistTemplate(),
            'is_default' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('certificate_templates')) {
            return;
        }

        DB::table('certificate_templates')->whereIn('name', ['Formal', 'Minimalista'])->delete();
    }

    private function formalTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { width: 297mm; height: 210mm; font-family: Georgia, serif; background: #fff; position: relative; overflow: hidden; color: #2c1810; }
        .border-outer { position: absolute; top: 8mm; left: 8mm; right: 8mm; bottom: 8mm; border: 3px solid #8b4513; }
        .border-inner { position: absolute; top: 12mm; left: 12mm; right: 12mm; bottom: 12mm; border: 1px solid #8b4513; }
        .content { text-align: center; padding: 38mm 50mm 0 50mm; }
        .org { font-size: 12px; letter-spacing: 5px; text-transform: uppercase; color: #8b4513; padding-bottom: 4mm; }
        .title { font-size: 46px; font-weight: bold; color: #2c1810; letter-spacing: 3px; padding-bottom: 3mm; }
        .line { display: inline-block; width: 80mm; border-top: 2px solid #8b4513; margin-bottom: 5mm; }
        .certify { font-size: 15px; color: #5a3d2b; padding-bottom: 4mm; }
        .participant { font-size: 36px; font-style: italic; color: #2c1810; padding-bottom: 2mm; }
        .underline { display: inline-block; width: 130mm; border-bottom: 1px solid #8b4513; margin-bottom: 5mm; }
        .description { font-size: 14px; color: #5a3d2b; line-height: 2; }
        .signatures { position: absolute; bottom: 22mm; left: 0; width: 297mm; }
        .signatures td { width: 50%; text-align: center; padding: 0 40mm; }
        .sig-line { border-top: 1px solid #8b4513; margin-bottom: 2mm; }
        .sig-name { font-size: 12px; color: #2c1810; }
        .sig-role { font-size: 10px; color: #8b7355; font-style: italic; }
        .code { font-size: 9px; color: #c4a882; position: absolute; bottom: 14mm; left: 0; width: 100%; text-align: center; }
    </style>
</head>
<body>
    <div class="border-outer"></div>
    <div class="border-inner"></div>
    <div class="content">
        <div class="org">Grupo Meia Sola</div>
        <div class="title">CERTIFICADO</div>
        <div class="line"></div><br>
        <div class="certify">Certificamos que</div>
        <div class="participant">{{participant_name}}</div>
        <div class="underline"></div><br>
        <p class="description">
            concluiu com &ecirc;xito o curso <strong>{{training_title}}</strong>,<br>
            ministrado por {{facilitator_name}}, em {{training_date}},<br>
            com carga hor&aacute;ria de {{duration}} horas. Assunto: {{subject}}.
        </p>
    </div>
    <table class="signatures">
        <tr>
            <td><div class="sig-line"></div><div class="sig-name">Coordena&ccedil;&atilde;o de RH</div><div class="sig-role">Diretor(a)</div></td>
            <td><div class="sig-line"></div><div class="sig-name">{{facilitator_name}}</div><div class="sig-role">Facilitador(a)</div></td>
        </tr>
    </table>
    <div class="code">C&oacute;digo: {{certificate_code}}</div>
</body>
</html>
HTML;
    }

    private function minimalistTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { width: 297mm; height: 210mm; font-family: Arial, Helvetica, sans-serif; background: #fff; position: relative; overflow: hidden; color: #333; }
        .accent { position: absolute; top: 0; left: 0; width: 8mm; height: 210mm; background: #2563eb; }
        .content { text-align: left; padding: 40mm 40mm 0 25mm; margin-left: 8mm; }
        .title { font-size: 36px; font-weight: 300; color: #2563eb; letter-spacing: 2px; padding-bottom: 6mm; }
        .line { width: 40mm; border-top: 3px solid #2563eb; margin-bottom: 8mm; }
        .certify { font-size: 13px; color: #999; text-transform: uppercase; letter-spacing: 2px; padding-bottom: 4mm; }
        .participant { font-size: 32px; font-weight: 700; color: #111; padding-bottom: 6mm; }
        .description { font-size: 14px; color: #555; line-height: 1.8; }
        .description strong { color: #2563eb; }
        .meta { position: absolute; bottom: 35mm; left: 25mm; margin-left: 8mm; }
        .meta-item { font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 1px; padding-bottom: 1mm; }
        .meta-value { font-size: 14px; color: #333; padding-bottom: 4mm; }
        .sig-block { position: absolute; bottom: 22mm; right: 40mm; text-align: right; }
        .sig-line { display: inline-block; width: 60mm; border-top: 1px solid #ccc; margin-bottom: 2mm; }
        .sig-name { font-size: 12px; color: #333; }
        .sig-role { font-size: 10px; color: #999; }
        .code { font-size: 8px; color: #ccc; position: absolute; bottom: 10mm; right: 40mm; text-align: right; }
    </style>
</head>
<body>
    <div class="accent"></div>
    <div class="content">
        <div class="title">CERTIFICADO</div>
        <div class="line"></div>
        <div class="certify">Este certificado atesta que</div>
        <div class="participant">{{participant_name}}</div>
        <p class="description">
            concluiu o curso <strong>{{training_title}}</strong>,<br>
            com carga hor&aacute;ria de {{duration}} horas.
        </p>
    </div>
    <div class="meta">
        <div class="meta-item">Data</div>
        <div class="meta-value">{{training_date}}</div>
        <div class="meta-item">Assunto</div>
        <div class="meta-value">{{subject}}</div>
    </div>
    <div class="sig-block">
        <div class="sig-line"></div><br>
        <div class="sig-name">{{facilitator_name}}</div>
        <div class="sig-role">Facilitador(a)</div>
    </div>
    <div class="code">{{certificate_code}}</div>
</body>
</html>
HTML;
    }
};
