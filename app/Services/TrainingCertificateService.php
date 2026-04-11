<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use App\Models\Training;
use App\Models\TrainingParticipant;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TrainingCertificateService
{
    /**
     * Generate a certificate PDF for a single participant.
     */
    public function generate(Training $training, TrainingParticipant $participant): ?string
    {
        $template = $training->certificateTemplate
            ?? CertificateTemplate::active()->default()->first();

        if (! $template) {
            return null;
        }

        $training->loadMissing(['facilitator', 'subject']);

        $data = [
            'participant_name' => $participant->display_name,
            'training_title' => $training->title,
            'training_date' => $training->event_date->format('d/m/Y'),
            'duration' => $training->duration_hours,
            'subject' => $training->subject->name ?? '',
            'facilitator_name' => $training->facilitator->name ?? '',
            'certificate_code' => strtoupper(Str::random(12)),
        ];

        $html = $template->renderHtml($data);

        $pdf = PDF::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        $filename = 'certificates/training_'.
            $training->id.'_participant_'.
            $participant->id.'_'.
            now()->format('YmdHis').'.pdf';

        Storage::disk('public')->put($filename, $pdf->output());

        $participant->update([
            'certificate_generated' => true,
            'certificate_path' => $filename,
        ]);

        return $filename;
    }

    /**
     * Generate certificates for all participants of a training.
     */
    public function generateBulk(Training $training): array
    {
        $participants = $training->participants()
            ->where('certificate_generated', false)
            ->whereNotNull('attendance_time')
            ->get();

        $results = ['generated' => 0, 'errors' => 0];

        foreach ($participants as $participant) {
            try {
                $this->generate($training, $participant);
                $results['generated']++;
            } catch (\Exception $e) {
                \Log::error("Certificate generation failed for participant {$participant->id}: ".$e->getMessage());
                $results['errors']++;
            }
        }

        return $results;
    }

    /**
     * Download a participant's certificate.
     */
    public function download(TrainingParticipant $participant)
    {
        if (! $participant->certificate_generated || ! $participant->certificate_path) {
            return null;
        }

        $path = Storage::disk('public')->path($participant->certificate_path);

        if (! file_exists($path)) {
            return null;
        }

        return response()->download($path, 'certificado_'.Str::slug($participant->display_name).'.pdf');
    }
}
