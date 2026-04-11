<?php

namespace App\Services;

use App\Models\TrainingQuiz;
use App\Models\TrainingQuizAttempt;
use App\Models\TrainingQuizResponse;
use App\Models\User;

class TrainingQuizService
{
    public function __construct(
        private TrainingProgressService $progressService,
    ) {}

    /**
     * Start a new quiz attempt.
     *
     * @return array{success: bool, attempt_id?: int, quiz?: array, questions?: array, error?: string}
     */
    public function startAttempt(TrainingQuiz $quiz, User $user): array
    {
        if (! $quiz->is_active) {
            return ['success' => false, 'error' => 'Quiz nao esta ativo.'];
        }

        // Check max attempts
        if ($quiz->max_attempts) {
            $attemptCount = TrainingQuizAttempt::where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->completed()
                ->count();

            if ($attemptCount >= $quiz->max_attempts) {
                return ['success' => false, 'error' => "Limite de {$quiz->max_attempts} tentativas atingido."];
            }
        }

        // Create attempt
        $attemptNumber = TrainingQuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->count() + 1;

        $attempt = TrainingQuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
        ]);

        // Load questions without revealing correct answers
        $questions = $quiz->questions()->with('options')->get()->map(fn ($q) => [
            'id' => $q->id,
            'question_text' => $q->question_text,
            'question_type' => $q->question_type,
            'points' => $q->points,
            'options' => $q->options->map(fn ($o) => [
                'id' => $o->id,
                'option_text' => $o->option_text,
            ]),
        ]);

        return [
            'success' => true,
            'attempt_id' => $attempt->id,
            'quiz' => [
                'title' => $quiz->title,
                'time_limit_minutes' => $quiz->time_limit_minutes,
                'passing_score' => $quiz->passing_score,
                'question_count' => $questions->count(),
            ],
            'questions' => $questions->toArray(),
        ];
    }

    /**
     * Submit answers for a quiz attempt and calculate score.
     *
     * @param  array  $answers  [{question_id, selected_options: [id, ...]}]
     * @return array{success: bool, result?: array, error?: string}
     */
    public function submitAttempt(TrainingQuizAttempt $attempt, array $answers): array
    {
        if ($attempt->is_completed) {
            return ['success' => false, 'error' => 'Tentativa ja finalizada.'];
        }

        $quiz = $attempt->quiz;
        $questions = $quiz->questions()->with('options')->get()->keyBy('id');

        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($answers as $answer) {
            $questionId = $answer['question_id'];
            $selectedOptionIds = $answer['selected_options'] ?? [];

            $question = $questions->get($questionId);
            if (! $question) {
                continue;
            }

            $correctIds = $question->correct_option_ids;
            sort($selectedOptionIds);
            sort($correctIds);

            $isCorrect = $selectedOptionIds === $correctIds;
            $pointsEarned = $isCorrect ? $question->points : 0;

            $totalPoints += $question->points;
            $earnedPoints += $pointsEarned;

            TrainingQuizResponse::create([
                'attempt_id' => $attempt->id,
                'question_id' => $questionId,
                'selected_options' => $selectedOptionIds,
                'is_correct' => $isCorrect,
                'points_earned' => $pointsEarned,
            ]);
        }

        $score = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;
        $passed = $score >= $quiz->passing_score;

        $attempt->update([
            'score' => $score,
            'total_points' => $totalPoints,
            'earned_points' => $earnedPoints,
            'passed' => $passed,
            'completed_at' => now(),
        ]);

        // If passed and linked to content/course, mark progress
        if ($passed) {
            if ($quiz->content_id) {
                $this->progressService->markComplete($quiz->content_id, $quiz->course_id, $attempt->user);
            }
        }

        return [
            'success' => true,
            'result' => [
                'score' => $score,
                'passed' => $passed,
                'earned_points' => $earnedPoints,
                'total_points' => $totalPoints,
                'passing_score' => $quiz->passing_score,
            ],
        ];
    }

    /**
     * Get attempt review with answers (respects show_answers setting).
     */
    public function getAttemptReview(TrainingQuizAttempt $attempt): ?array
    {
        if (! $attempt->is_completed) {
            return null;
        }

        $quiz = $attempt->quiz;
        $responses = $attempt->responses()->with('question.options')->get();

        return [
            'attempt' => [
                'id' => $attempt->id,
                'score' => $attempt->score,
                'passed' => $attempt->passed,
                'earned_points' => $attempt->earned_points,
                'total_points' => $attempt->total_points,
                'attempt_number' => $attempt->attempt_number,
                'duration_minutes' => $attempt->duration_minutes,
                'started_at' => $attempt->started_at->format('d/m/Y H:i'),
                'completed_at' => $attempt->completed_at->format('d/m/Y H:i'),
            ],
            'quiz' => [
                'title' => $quiz->title,
                'passing_score' => $quiz->passing_score,
                'show_answers' => $quiz->show_answers,
            ],
            'responses' => $responses->map(function ($response) use ($quiz) {
                $data = [
                    'question_text' => $response->question->question_text,
                    'question_type' => $response->question->question_type,
                    'is_correct' => $response->is_correct,
                    'points_earned' => $response->points_earned,
                    'points_possible' => $response->question->points,
                    'selected_options' => $response->selected_options,
                ];

                // Only show correct answers if quiz allows it
                if ($quiz->show_answers) {
                    $data['options'] = $response->question->options->map(fn ($o) => [
                        'id' => $o->id,
                        'option_text' => $o->option_text,
                        'is_correct' => $o->is_correct,
                    ]);
                    $data['explanation'] = $response->question->explanation;
                }

                return $data;
            }),
        ];
    }
}
