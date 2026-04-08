<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;

class ManualController extends Controller
{
    public function download()
    {
        $pdf = Pdf::loadView('pdf.admin-manual')
            ->setPaper('a4')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', false)
            ->setOption('dpi', 120);

        return $pdf->download('Mercury_SaaS_Manual_Administracao.pdf');
    }
}
