<?php

namespace App\Services;

use App\Models\TrainingContent;
use App\Models\TrainingContentProgress;
use App\Models\TrainingCourse;
use App\Models\TrainingCourseEnrollment;
use App\Models\User;

class TrainingProgressService
{
    public function __construct(
        private TrainingCourseCompletionService $completionService,
    ) {}

    // Auto-completion thresholds by content type
    private const COMPLETION_THRESHOLDS = [
        TrainingContent::TYPE_VIDEO => 90,
        TrainingContent::TYPE_AUDIO => 90,
        TrainingContent::TYPE_DOCUMENT => 50,
        TrainingContent::TYPE_TEXT => 90,
        TrainingContent::TYPE_LINK => 50,
    ];

    /**
     * Update progress for a content item.
     *
     * @return array{success: bool, completed: bool, course_completed: bool}
     */
    public function updateProgress(
        int $contentId,
        ?int $courseId,
        User $user,
        float $progressPercent,
        ?int $positionSeconds = null,
        int $timeSpentSeconds = 0,
    ): array {
        $progress = TrainingContentProgress::firstOrCreate(
            ['user_id' => $user->id, 'content_id' => $contentId, 'course_id' => $courseId],
            ['status' => TrainingContentProgress::STATUS_NOT_STARTED]
        );

        // Skip if already completed
        if ($progress->is_completed) {
            return ['success' => true, 'completed' => true, 'course_completed' => false];
        }

        // Update fields
        if (! $progress->started_at) {
            $progress->started_at = now();
        }

        $progress->status = TrainingContentProgress::STATUS_IN_PROGRESS;
        $progress->progress_percent = max($progress->progress_percent, $progressPercent);
        $progress->total_time_spent_seconds += $timeSpentSeconds;
        $progress->last_accessed_at = now();
        $progress->views_count++;

        if ($positionSeconds !== null) {
            $progress->last_position_seconds = $positionSeconds;
        }

        // Check auto-completion
        $content = TrainingContent::find($contentId);
        $completed = false;
        $courseCompleted = false;

        if ($content && $this->shouldMarkComplete($content, $progressPercent)) {
            $progress->status = TrainingContentProgress::STATUS_COMPLETED;
            $progress->completed_at = now();
            $progress->progress_percent = 100;
            $completed = true;
        }

        $progress->save();

        // Update enrollment status if in a course
        if ($courseId) {
            $this->updateEnrollmentStatus($courseId, $user);

            if ($completed) {
                $courseCompleted = $this->recalculateCourseProgress($courseId, $user);
            }
        }

        return ['success' => true, 'completed' => $completed, 'course_completed' => $courseCompleted];
    }

    /**
     * Manually mark a content as complete.
     *
     * @return array{success: bool, completed: bool, course_completed: bool}
     */
    public function markComplete(int $contentId, ?int $courseId, User $user): array
    {
        $progress = TrainingContentProgress::firstOrCreate(
            ['user_id' => $user->id, 'content_id' => $contentId, 'course_id' => $courseId],
            ['status' => TrainingContentProgress::STATUS_NOT_STARTED]
        );

        if ($progress->is_completed) {
            return ['success' => true, 'completed' => true, 'course_completed' => false];
        }

        $progress->update([
            'status' => TrainingContentProgress::STATUS_COMPLETED,
            'progress_percent' => 100,
            'completed_at' => now(),
            'started_at' => $progress->started_at ?? now(),
            'last_accessed_at' => now(),
        ]);

        $courseCompleted = false;
        if ($courseId) {
            $this->updateEnrollmentStatus($courseId, $user);
            $courseCompleted = $this->recalculateCourseProgress($courseId, $user);
        }

        return ['success' => true, 'completed' => true, 'course_completed' => $courseCompleted];
    }

    /**
     * Recalculate course completion percentage.
     * Returns true if course just became 100% completed.
     */
    public function recalculateCourseProgress(int $courseId, User $user): bool
    {
        $course = TrainingCourse::find($courseId);
        if (! $course) {
            return false;
        }

        $requiredContents = $course->courseContents()->where('is_required', true)->get();
        if ($requiredContents->isEmpty()) {
            return false;
        }

        $completedCount = 0;
        foreach ($requiredContents as $cc) {
            $progress = TrainingContentProgress::where('user_id', $user->id)
                ->where('content_id', $cc->content_id)
                ->where('course_id', $courseId)
                ->where('status', TrainingContentProgress::STATUS_COMPLETED)
                ->first();

            if ($progress) {
                $completedCount++;
            }
        }

        $percent = round(($completedCount / $requiredContents->count()) * 100, 2);

        $enrollment = TrainingCourseEnrollment::where('course_id', $courseId)
            ->where('user_id', $user->id)
            ->first();

        if (! $enrollment) {
            return false;
        }

        $wasCompleted = $enrollment->status === TrainingCourseEnrollment::STATUS_COMPLETED;
        $enrollment->completion_percent = $percent;

        if ($percent >= 100 && ! $wasCompleted) {
            $enrollment->status = TrainingCourseEnrollment::STATUS_COMPLETED;
            $enrollment->completed_at = now();
            $enrollment->save();

            // Trigger completion processing
            $this->completionService->processCompletion($course, $user);

            return true;
        }

        $enrollment->save();

        return false;
    }

    /**
     * Check if content should be auto-completed based on progress.
     */
    private function shouldMarkComplete(TrainingContent $content, float $progressPercent): bool
    {
        $threshold = self::COMPLETION_THRESHOLDS[$content->content_type] ?? 90;

        return $progressPercent >= $threshold;
    }

    /**
     * Update enrollment status from enrolled to in_progress.
     */
    private function updateEnrollmentStatus(int $courseId, User $user): void
    {
        TrainingCourseEnrollment::where('course_id', $courseId)
            ->where('user_id', $user->id)
            ->where('status', TrainingCourseEnrollment::STATUS_ENROLLED)
            ->update(['status' => TrainingCourseEnrollment::STATUS_IN_PROGRESS]);
    }
}
