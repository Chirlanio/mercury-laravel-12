<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use App\Models\TrainingCourse;
use App\Models\TrainingCourseEnrollment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TrainingCourseCompletionService
{
    /**
     * Process course completion: generate certificate, notify.
     */
    public function processCompletion(TrainingCourse $course, User $user): bool
    {
        if (! $course->certificate_on_completion) {
            return true;
        }

        $enrollment = TrainingCourseEnrollment::where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->where('status', TrainingCourseEnrollment::STATUS_COMPLETED)
            ->first();

        if (! $enrollment) {
            return false;
        }

        $course->loadMissing(['facilitator', 'subject']);

        $template = $course->certificateTemplate
            ?? CertificateTemplate::active()->default()->first();

        if (! $template) {
            return false;
        }

        try {
            $data = [
                'participant_name' => $user->name,
                'training_title' => $course->title,
                'training_date' => $enrollment->completed_at->format('d/m/Y'),
                'duration' => $course->estimated_duration_minutes
                    ? intdiv($course->estimated_duration_minutes, 60).'h'
                    : '-',
                'subject' => $course->subject->name ?? '',
                'facilitator_name' => $course->facilitator->name ?? '',
                'certificate_code' => strtoupper(Str::random(12)),
            ];

            $html = $template->renderHtml($data);
            $pdf = PDF::loadHTML($html);
            $pdf->setPaper('A4', 'landscape');

            $filename = 'certificates/course_'.$course->id.'_user_'.$user->id.'_'.now()->format('YmdHis').'.pdf';

            Storage::disk('public')->put($filename, $pdf->output());

            $enrollment->update([
                'certificate_generated' => true,
                'certificate_path' => $filename,
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error("Course certificate generation failed: course={$course->id} user={$user->id} error={$e->getMessage()}");

            return false;
        }
    }

    /**
     * Regenerate certificate for an enrollment (deletes old file).
     */
    public function regenerateCertificate(TrainingCourseEnrollment $enrollment): bool
    {
        // Delete old file
        if ($enrollment->certificate_path) {
            Storage::disk('public')->delete($enrollment->certificate_path);
        }

        // Reset flags
        $enrollment->update([
            'certificate_generated' => false,
            'certificate_path' => null,
        ]);

        $course = $enrollment->course;
        $user = $enrollment->user;

        if (! $course || ! $user) {
            return false;
        }

        return $this->processCompletion($course, $user);
    }

    /**
     * Download a course certificate.
     */
    public function download(TrainingCourseEnrollment $enrollment)
    {
        if (! $enrollment->certificate_generated || ! $enrollment->certificate_path) {
            return null;
        }

        $path = Storage::disk('public')->path($enrollment->certificate_path);

        if (! file_exists($path)) {
            return null;
        }

        return response()->download($path, 'certificado_curso.pdf');
    }
}
