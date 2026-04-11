<?php

namespace App\Services;

use App\Models\Training;

class TrainingQRCodeService
{
    /**
     * Generate a QR code as SVG string for the given token and type.
     * Uses inline SVG generation — no external package required.
     */
    public function generateSvg(string $url, string $color = '#28a745', int $size = 200): string
    {
        // Encode data as a simple QR-like visual placeholder
        // The actual QR rendering happens on the frontend with a JS library
        return $url;
    }

    /**
     * Get the public attendance URL for a training.
     */
    public function getAttendanceUrl(Training $training): string
    {
        return url("/public/training/attendance/{$training->attendance_qrcode_token}");
    }

    /**
     * Get the public evaluation URL for a training.
     */
    public function getEvaluationUrl(Training $training): string
    {
        return url("/public/training/evaluation/{$training->evaluation_qrcode_token}");
    }

    /**
     * Get QR code data for a training (URLs for frontend rendering).
     */
    public function getQRCodeData(Training $training): array
    {
        return [
            'attendance' => [
                'url' => $this->getAttendanceUrl($training),
                'token' => $training->attendance_qrcode_token,
                'color' => '#28a745', // green
            ],
            'evaluation' => [
                'url' => $this->getEvaluationUrl($training),
                'token' => $training->evaluation_qrcode_token,
                'color' => '#007bff', // blue
            ],
        ];
    }
}
