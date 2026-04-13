<?php

namespace App\Services\Helpdesk;

use App\Models\Employee;
use App\Models\HdIdentityLookup;
use App\Rules\Cpf;

/**
 * Resolves the employee behind an inbound intake contact. Two strategies:
 *
 *   byPhone(): silent match against employees.phone_primary — used on the
 *              first message of a WhatsApp conversation when the driver
 *              already knows the number.
 *
 *   byCpf():  explicit match after the user types their CPF. Validates
 *              the check digits via App\Rules\Cpf, then looks up by the
 *              normalized 11-digit string. Active-only (dismissal_date IS NULL).
 *
 * Every lookup attempt — successful or not — is recorded in hd_identity_lookups
 * for LGPD auditing. Phone lookups that find nothing are NOT recorded (they're
 * passive and happen unconditionally; recording them would flood the table).
 *
 * enrichContext() returns only the subset of employee data that is safe to
 * propagate into higher layers (e.g. Phase 4 AI classification). Never
 * includes CPF, full name, or any other PII the admin hasn't explicitly
 * opted into sharing.
 */
class EmployeeIdentityResolver
{
    /**
     * Silent phone lookup. Compares the contact's digits against the digits
     * of employees.phone_primary (both normalized) so "(85) 98746-0451" and
     * "5585987460451" both match. Updates phone_last_used_at as an audit stamp.
     *
     * @param  array{channel_id?:int|null, ip?:string|null}  $audit
     */
    public function byPhone(string $rawContact, array $audit = []): ?Employee
    {
        $normalized = preg_replace('/\D+/', '', $rawContact) ?? '';
        if ($normalized === '') {
            return null;
        }

        // Strip Brazilian country code 55 if present so we can match local
        // numbers stored by admins as (85) 98746-0451.
        $localOnly = str_starts_with($normalized, '55') && strlen($normalized) > 11
            ? substr($normalized, 2)
            : $normalized;

        $employee = Employee::query()
            ->active()
            ->whereNotNull('phone_primary')
            ->where(function ($q) use ($normalized, $localOnly) {
                // Compare against a stripped version of the stored phone so
                // admin-entered formatting never blocks a match.
                $stripExpr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone_primary, '+', ''), '-', ''), ' ', ''), '(', ''), ')', '')";
                $q->whereRaw("{$stripExpr} = ?", [$normalized])
                  ->orWhereRaw("{$stripExpr} = ?", [$localOnly]);
            })
            ->first();

        if ($employee) {
            $this->recordLookup(
                externalContact: $rawContact,
                method: 'phone',
                matched: true,
                employeeId: $employee->id,
                attempt: 1,
                audit: $audit,
            );

            $employee->forceFill(['phone_last_used_at' => now()])->save();
        }

        return $employee;
    }

    /**
     * Explicit CPF lookup. Invalid CPFs (wrong format, bad checksum, blacklisted)
     * return null without touching the DB — we still log the failed attempt for
     * the rate limiter in the driver, but no employee table is queried.
     *
     * @param  array{channel_id?:int|null, ip?:string|null}  $audit
     */
    public function byCpf(string $rawCpf, int $attempt = 1, array $audit = []): ?Employee
    {
        if (! Cpf::isValid($rawCpf)) {
            $this->recordLookup(
                externalContact: $audit['external_contact'] ?? '(cpf-invalid)',
                method: 'cpf',
                matched: false,
                employeeId: null,
                attempt: $attempt,
                audit: $audit,
            );

            return null;
        }

        $normalized = Cpf::normalize($rawCpf);

        $employee = Employee::query()
            ->active()
            ->where('cpf', $normalized)
            ->first();

        $this->recordLookup(
            externalContact: $audit['external_contact'] ?? '(cpf-query)',
            method: 'cpf',
            matched: (bool) $employee,
            employeeId: $employee?->id,
            attempt: $attempt,
            audit: $audit,
        );

        return $employee;
    }

    /**
     * Returns the PII-safe subset of employee data that may be propagated to
     * other layers (e.g. session context, AI classification). DO NOT add
     * cpf, full name, email, address, or any identifier that could re-identify
     * the employee outside the Mercury boundary.
     *
     * @return array{employee_id:int, first_name:string, store_id:?string}
     */
    public function enrichContext(Employee $employee): array
    {
        return [
            'employee_id' => $employee->id,
            'first_name' => $employee->first_name,
            'store_id' => $employee->store_id,
        ];
    }

    protected function recordLookup(
        string $externalContact,
        string $method,
        bool $matched,
        ?int $employeeId,
        int $attempt,
        array $audit,
    ): void {
        HdIdentityLookup::create([
            'channel_id' => $audit['channel_id'] ?? null,
            'external_contact' => $externalContact,
            'method' => $method,
            'matched' => $matched,
            'employee_id' => $employeeId,
            'attempt' => $attempt,
            'ip_address' => $audit['ip'] ?? null,
            'created_at' => now(),
        ]);
    }
}
