<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('certificate_templates')->insert([
            'name' => 'Template Padrão',
            'html_template' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>@page{size:A4 landscape;margin:0}*{margin:0;padding:0;box-sizing:border-box}body{width:297mm;height:210mm;font-family:"Times New Roman",Georgia,serif;text-align:center;background:#fff;position:relative;overflow:hidden;color:#1b2a4a}.corner{position:absolute;width:60mm;height:60mm;overflow:hidden}.corner-tl{top:0;left:0}.corner-tr{top:0;right:0}.corner-bl{bottom:0;left:0}.corner-br{bottom:0;right:0}.corner-tl .s1{position:absolute;top:0;left:0;width:0;height:0;border-top:28mm solid #1b2a4a;border-right:28mm solid transparent}.corner-tl .s2{position:absolute;top:0;left:0;width:0;height:0;border-top:18mm solid #c9a84c;border-right:18mm solid transparent}.corner-tl .s3{position:absolute;top:0;left:0;width:0;height:0;border-top:12mm solid #1b2a4a;border-right:12mm solid transparent}.corner-tr .s1{position:absolute;top:0;right:0;width:0;height:0;border-top:28mm solid #1b2a4a;border-left:28mm solid transparent}.corner-tr .s2{position:absolute;top:0;right:0;width:0;height:0;border-top:18mm solid #c9a84c;border-left:18mm solid transparent}.corner-tr .s3{position:absolute;top:0;right:0;width:0;height:0;border-top:12mm solid #1b2a4a;border-left:12mm solid transparent}.corner-bl .s1{position:absolute;bottom:0;left:0;width:0;height:0;border-bottom:28mm solid #1b2a4a;border-right:28mm solid transparent}.corner-bl .s2{position:absolute;bottom:0;left:0;width:0;height:0;border-bottom:18mm solid #c9a84c;border-right:18mm solid transparent}.corner-bl .s3{position:absolute;bottom:0;left:0;width:0;height:0;border-bottom:12mm solid #1b2a4a;border-right:12mm solid transparent}.corner-br .s1{position:absolute;bottom:0;right:0;width:0;height:0;border-bottom:28mm solid #1b2a4a;border-left:28mm solid transparent}.corner-br .s2{position:absolute;bottom:0;right:0;width:0;height:0;border-bottom:18mm solid #c9a84c;border-left:18mm solid transparent}.corner-br .s3{position:absolute;bottom:0;right:0;width:0;height:0;border-bottom:12mm solid #1b2a4a;border-left:12mm solid transparent}.frame{position:absolute;top:8mm;left:8mm;right:8mm;bottom:8mm;border:1.5px solid #1b2a4a}.center-table{position:absolute;top:8mm;left:8mm;width:281mm;height:194mm}.center-table td{vertical-align:middle;text-align:center;padding:0 35mm}.title{font-size:42px;font-weight:bold;letter-spacing:4px;color:#1b2a4a;padding-bottom:2mm}.title-line{display:inline-block;width:60mm;border-top:2px solid #c9a84c;margin-bottom:4mm}.subtitle{font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#1b2a4a;padding-bottom:5mm}.participant{font-size:32px;font-style:italic;color:#1b2a4a;padding-bottom:5mm}.description{font-size:11px;text-transform:uppercase;color:#1b2a4a;line-height:2;letter-spacing:.5px}.signature-block{position:absolute;bottom:25mm;left:0;width:100%;text-align:center}.sig-line{display:inline-block;width:70mm;border-top:1px solid #1b2a4a;margin-bottom:2mm}.sig-name{font-size:11px;color:#1b2a4a}.sig-role{font-size:9px;color:#666;font-style:italic}.code{font-size:8px;color:#bbb;position:absolute;bottom:12mm;left:0;width:100%;text-align:center}</style></head><body><div class="corner corner-tl"><div class="s1"></div><div class="s2"></div><div class="s3"></div></div><div class="corner corner-tr"><div class="s1"></div><div class="s2"></div><div class="s3"></div></div><div class="corner corner-bl"><div class="s1"></div><div class="s2"></div><div class="s3"></div></div><div class="corner corner-br"><div class="s1"></div><div class="s2"></div><div class="s3"></div></div><div class="frame"></div><table class="center-table"><tr><td><div class="title">CERTIFICADO</div><div class="title-line"></div><br><div class="subtitle">Este certificado comprova que</div><div class="participant">{{participant_name}}</div><p class="description">concluiu com &ecirc;xito o curso <strong>{{training_title}}</strong> ministrado por {{facilitator_name}}<br>em {{training_date}}, com carga hor&aacute;ria de {{duration}} horas.<br>Assunto: {{subject}}.</p></td></tr></table><div class="signature-block"><div class="sig-line"></div><br><div class="sig-name">{{facilitator_name}}</div><div class="sig-role">Facilitador(a) Respons&aacute;vel</div></div><p class="code">C&oacute;digo de verifica&ccedil;&atilde;o: {{certificate_code}}</p></body></html>',
            'is_default' => true,
            'is_active' => true,
            'created_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('certificate_templates')->where('name', 'Template Padrão')->delete();
    }
};
