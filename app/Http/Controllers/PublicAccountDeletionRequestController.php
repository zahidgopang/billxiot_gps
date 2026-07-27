<?php

namespace App\Http\Controllers;

use App\Services\Account\AccountDeletionRequestService;
use App\Services\BotProtectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public (no login) account deletion request form.
 * Intentionally not linked from web/app navigation — share the URL directly.
 */
class PublicAccountDeletionRequestController extends Controller
{
    public function __construct(
        private AccountDeletionRequestService $requests,
    ) {}

    public function show(): View
    {
        return view('frontend.account-deletion-request');
    }

    public function store(Request $request, BotProtectionService $bots): RedirectResponse
    {
        $guard = $bots->inspect($request, 'account_deletion_request', 'account_deletion_request');

        if (! $guard['ok']) {
            if (! empty($guard['fake_success'])) {
                return redirect()
                    ->route('account.deletion-request')
                    ->with('status', __('app.public_account_deletion.success_generic'));
            }

            return back()
                ->withInput()
                ->withErrors(['email' => $guard['message'] ?? __('app.public_account_deletion.failed')]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => __('app.public_account_deletion.name'),
            'username' => __('app.public_account_deletion.username'),
            'email' => __('app.public_account_deletion.email'),
            'phone' => __('app.public_account_deletion.phone'),
            'reason' => __('app.public_account_deletion.reason'),
        ]);

        $bots->recordAttempt($request, 'account_deletion_request', $validated['email']);

        $deletionRequest = $this->requests->createFromPublicForm($validated, $request);

        return redirect()
            ->route('account.deletion-request')
            ->with('status', __('app.public_account_deletion.success', [
                'ticket' => $deletionRequest->ticketNumber(),
            ]));
    }
}
