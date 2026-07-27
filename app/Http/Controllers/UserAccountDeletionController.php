<?php

namespace App\Http\Controllers;

use App\Services\Account\AccountDeletionRequestService;
use App\Services\Account\EndUserAccountDeletionService;
use App\Services\Auth\UserPasswordVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserAccountDeletionController extends Controller
{
    private const SESSION_VERIFIED_AT = 'account_deletion.verified_at';

    private const SESSION_USER_ID = 'account_deletion.user_id';

    private const VERIFY_TTL_MINUTES = 15;

    public function __construct(
        private EndUserAccountDeletionService $deletion,
        private AccountDeletionRequestService $requests,
        private UserPasswordVerifier $passwords,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->deletion->canDeleteAccount($user), 403);

        $this->forgetVerification($request);

        return view('user.account.delete', [
            'user' => $user,
            'summary' => $this->deletion->deletionSummary($user),
            'pendingRequest' => $this->requests->pendingForUser($user),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->deletion->canDeleteAccount($user), 403);

        if ($this->requests->pendingForUser($user)) {
            return redirect()
                ->route('user.account.delete')
                ->with('status', __('app.user.account_delete.request_already_pending'));
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], [
            'email' => __('app.user.account_delete.email'),
            'password' => __('app.user.account_delete.password'),
        ]);

        $email = strtolower(trim($validated['email']));
        $accountEmail = strtolower(trim((string) $user->email));

        if ($email !== $accountEmail) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['email' => __('app.user.account_delete.email_mismatch')]);
        }

        if (! $this->passwords->verify($user, $validated['password'])) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['password' => __('app.user.account_delete.password_mismatch')]);
        }

        $request->session()->put(self::SESSION_VERIFIED_AT, now()->timestamp);
        $request->session()->put(self::SESSION_USER_ID, (int) $user->id);

        return redirect()->route('user.account.delete.confirm');
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->deletion->canDeleteAccount($user), 403);

        if (! $this->hasFreshVerification($request, $user->id)) {
            return redirect()
                ->route('user.account.delete')
                ->withErrors(['email' => __('app.user.account_delete.session_expired')]);
        }

        return view('user.account.delete_confirm', [
            'user' => $user,
            'summary' => $this->deletion->deletionSummary($user),
        ]);
    }

    /**
     * Submit a deletion request for super-admin approval (does not delete immediately).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->deletion->canDeleteAccount($user), 403);

        if (! $this->hasFreshVerification($request, $user->id)) {
            return redirect()
                ->route('user.account.delete')
                ->withErrors(['email' => __('app.user.account_delete.session_expired')]);
        }

        $validated = $request->validate([
            'confirmation' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'confirmation' => __('app.user.account_delete.confirmation'),
            'reason' => __('app.user.account_delete.reason'),
        ]);

        if (strtolower(trim($validated['confirmation'])) !== 'delete') {
            return back()->withErrors([
                'confirmation' => __('app.user.account_delete.confirmation_mismatch'),
            ]);
        }

        try {
            $deletionRequest = $this->requests->createFromAuthenticatedUser(
                $user,
                $request,
                $validated['reason'] ?? null,
            );
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('user.account.delete.confirm')
                ->withErrors(['confirmation' => __('app.user.account_delete.failed')]);
        }

        $this->forgetVerification($request);

        return redirect()
            ->route('user.profile')
            ->with('success', __('app.user.account_delete.request_submitted', [
                'ticket' => $deletionRequest->ticketNumber(),
            ]));
    }

    private function hasFreshVerification(Request $request, int $userId): bool
    {
        $verifiedAt = (int) $request->session()->get(self::SESSION_VERIFIED_AT, 0);
        $sessionUserId = (int) $request->session()->get(self::SESSION_USER_ID, 0);

        if ($sessionUserId !== $userId || $verifiedAt <= 0) {
            return false;
        }

        return now()->timestamp - $verifiedAt <= (self::VERIFY_TTL_MINUTES * 60);
    }

    private function forgetVerification(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_VERIFIED_AT,
            self::SESSION_USER_ID,
        ]);
    }
}
