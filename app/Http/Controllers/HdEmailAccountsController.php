<?php

namespace App\Http\Controllers;

use App\Models\HdDepartment;
use App\Services\Helpdesk\ImapAccountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Admin page backend for managing IMAP mailbox accounts that feed the
 * helpdesk email intake pipeline.
 *
 * Accounts are stored inside `hd_channels.config.imap_accounts` (see
 * ImapAccountService) and gate on the MANAGE_HD_DEPARTMENTS permission —
 * same scope as the other helpdesk admin pages.
 *
 * Passwords are encrypted at rest and NEVER round-tripped to the client.
 * The `has_password` flag in listings is the only signal the UI gets that
 * a password exists. Updates that omit the password field keep whatever
 * was already stored.
 *
 * Connection testing happens server-side and blocks until the IMAP
 * handshake completes (or the timeout kicks in). The endpoint is intended
 * for manual "test this before I save" clicks — not for health monitoring.
 */
class HdEmailAccountsController extends Controller
{
    public function __construct(private ImapAccountService $accounts) {}

    public function index()
    {
        return Inertia::render('Helpdesk/EmailAccounts', [
            'accounts' => $this->accounts->list(),
            'departments' => HdDepartment::orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'is_active']),
            'defaults' => [
                'port' => 993,
                'encryption' => 'ssl',
                'processed_folder' => ImapAccountService::DEFAULT_PROCESSED_FOLDER,
                'validate_cert' => true,
            ],
            'encryptionOptions' => [
                ['value' => 'ssl', 'label' => 'SSL (porta 993)'],
                ['value' => 'tls', 'label' => 'TLS'],
                ['value' => 'starttls', 'label' => 'STARTTLS (porta 143)'],
                ['value' => '', 'label' => 'Sem criptografia (não recomendado)'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request, isUpdate: false);

        $account = $this->accounts->create($validated);

        return back()->with('success', "Conta {$account['email_address']} criada.");
    }

    public function update(Request $request, string $id)
    {
        $validated = $this->validateRequest($request, isUpdate: true);

        $this->accounts->update($id, $validated);

        return back()->with('success', 'Conta atualizada.');
    }

    public function destroy(string $id)
    {
        $deleted = $this->accounts->delete($id);

        if (! $deleted) {
            return back()->withErrors(['account' => 'Conta não encontrada.']);
        }

        return back()->with('success', 'Conta removida.');
    }

    /**
     * Blocking test — connects to the account, selects INBOX, disconnects.
     * Returns JSON so the UI can render an inline status pill next to the row.
     */
    public function test(string $id)
    {
        return response()->json($this->accounts->testConnection($id));
    }

    /**
     * Validation rules. On update, password is optional (blank = keep current).
     *
     * @return array<string, mixed>
     */
    protected function validateRequest(Request $request, bool $isUpdate): array
    {
        return $request->validate([
            'label' => 'required|string|max:80',
            'email_address' => 'required|email|max:180',
            'department_id' => ['required', 'integer', Rule::exists('hd_departments', 'id')],
            'host' => 'required|string|max:180',
            'port' => 'required|integer|between:1,65535',
            'encryption' => 'nullable|string|in:ssl,tls,starttls,',
            'username' => 'nullable|string|max:180',
            'password' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'processed_folder' => 'nullable|string|max:120',
            'validate_cert' => 'boolean',
            'active' => 'boolean',
        ]);
    }
}
