<?php

namespace App\Services;

use App\Models\TrainingContentProgress;
use App\Models\TrainingCourse;
use App\Models\TrainingCourseEnrollment;
use App\Models\TrainingCourseVisibility;
use App\Models\User;

class TrainingEnrollmentService
{
    /**
     * Enroll a user in a course (idempotent).
     */
    public function enroll(TrainingCourse $course, User $user): TrainingCourseEnrollment
    {
        return TrainingCourseEnrollment::firstOrCreate(
            ['course_id' => $course->id, 'user_id' => $user->id],
            [
                'status' => TrainingCourseEnrollment::STATUS_ENROLLED,
                'enrolled_at' => now(),
            ]
        );
    }

    /**
     * Check if a user has access to a course.
     *
     * - Private courses: any authenticated system user
     * - Public courses: any user (including Google OAuth)
     */
    public function hasAccess(TrainingCourse $course, User $user): bool
    {
        // Course must be published
        if ($course->status !== TrainingCourse::STATUS_PUBLISHED) {
            return false;
        }

        // Any authenticated user can access (private = system users, public = all)
        return true;
    }

    /**
     * Check if a content is unlocked for sequential courses.
     */
    public function isContentUnlocked(TrainingCourse $course, int $contentId, User $user): bool
    {
        if (! $course->requires_sequential) {
            return true;
        }

        $courseContents = $course->courseContents()->with('content')->get();

        $targetIndex = null;
        foreach ($courseContents as $index => $cc) {
            if ($cc->content_id === $contentId) {
                $targetIndex = $index;
                break;
            }
        }

        // Content not in course or is the first one
        if ($targetIndex === null || $targetIndex === 0) {
            return true;
        }

        // Check all previous required contents are completed
        for ($i = 0; $i < $targetIndex; $i++) {
            $cc = $courseContents[$i];
            if (! $cc->is_required) {
                continue;
            }

            $progress = TrainingContentProgress::where('user_id', $user->id)
                ->where('content_id', $cc->content_id)
                ->where('course_id', $course->id)
                ->first();

            if (! $progress || $progress->status !== TrainingContentProgress::STATUS_COMPLETED) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get courses for a user grouped by status.
     */
    public function getUserCourses(User $user): array
    {
        $enrollments = TrainingCourseEnrollment::forUser($user->id)
            ->with(['course.subject', 'course.facilitator'])
            ->get();

        return [
            'in_progress' => $enrollments->whereIn('status', [
                TrainingCourseEnrollment::STATUS_ENROLLED,
                TrainingCourseEnrollment::STATUS_IN_PROGRESS,
            ])->values(),
            'completed' => $enrollments->where('status', TrainingCourseEnrollment::STATUS_COMPLETED)->values(),
        ];
    }

    /**
     * Check if a visibility rule matches a user.
     */
    private function matchesVisibilityRule(TrainingCourseVisibility $rule, User $user): bool
    {
        return match ($rule->target_type) {
            TrainingCourseVisibility::TARGET_ROLE => $user->role?->value === $rule->target_id,
            TrainingCourseVisibility::TARGET_USER => (string) $user->id === $rule->target_id,
            TrainingCourseVisibility::TARGET_STORE => $this->userBelongsToStore($user, $rule->target_id),
            default => false,
        };
    }

    /**
     * Check if user belongs to a store (via employee relationship).
     */
    private function userBelongsToStore(User $user, string $storeId): bool
    {
        if (! $user->relationLoaded('employee')) {
            $user->load('employee');
        }

        return $user->employee && $user->employee->store_id === $storeId;
    }
}
