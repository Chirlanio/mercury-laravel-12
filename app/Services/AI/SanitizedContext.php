<?php

namespace App\Services\AI;

/**
 * Immutable DTO carrying the PII-safe context that may be sent to an
 * external LLM provider.
 *
 * Construction goes exclusively through SanitizedContext::build(), which
 * forces the caller to run every user-supplied string through PiiSanitizer
 * before the object even exists. Once built, the object cannot be mutated.
 * TicketClassifierInterface accepts ONLY SanitizedContext, so leaking raw
 * text to Groq is impossible by type constraint.
 *
 * The fields are deliberately minimal:
 *   - title / description: the ticket body, sanitized
 *   - employee_first_name: FIRST name only (never full name)
 *   - store_code: internal store identifier (e.g. "Z421"), not the name
 *   - department_name / categories: metadata the model needs to produce
 *     a structured answer
 */
final class SanitizedContext
{
    private function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $departmentName,
        /** @var array<int, array{id:int, name:string}> */
        public readonly array $categories,
        public readonly ?string $employeeFirstName,
        public readonly ?string $storeCode,
    ) {}

    /**
     * Build a sanitized context. Every free-text field is run through
     * PiiSanitizer here; there is no other code path that produces this
     * type, so holding a SanitizedContext means the input has been cleaned.
     *
     * @param  array<int, array{id:int, name:string}>  $categories
     */
    public static function build(
        PiiSanitizer $sanitizer,
        string $rawTitle,
        string $rawDescription,
        string $departmentName,
        array $categories,
        ?string $employeeFirstName = null,
        ?string $storeCode = null,
    ): self {
        return new self(
            title: $sanitizer->sanitize($rawTitle),
            description: $sanitizer->sanitize($rawDescription),
            departmentName: $departmentName,
            categories: array_values(array_map(
                fn ($c) => ['id' => (int) $c['id'], 'name' => (string) $c['name']],
                $categories,
            )),
            // First name only — already no PII if the caller respected
            // Employee::first_name, but we sanitize defensively anyway.
            employeeFirstName: $employeeFirstName ? $sanitizer->sanitize($employeeFirstName) : null,
            storeCode: $storeCode,
        );
    }

    /**
     * Plain-array form used by classifiers to construct prompts. Always
     * safe to log/dump.
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'department_name' => $this->departmentName,
            'categories' => $this->categories,
            'employee_first_name' => $this->employeeFirstName,
            'store_code' => $this->storeCode,
        ];
    }
}
