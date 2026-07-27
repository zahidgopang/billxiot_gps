<?php

namespace App\Services\Account;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Services\AdminAuditService;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AccountDeletionRequestService
{
    public function __construct(
        private EndUserAccountDeletionService $deletion,
        private AdminAuditService $audit,
    ) {}

    public function pendingForUser(User $user): ?AccountDeletionRequest
    {
        return AccountDeletionRequest::query()
            ->pending()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('email', strtolower(trim((string) $user->email)));
            })
            ->latest('id')
            ->first();
    }

    public function pendingForEmail(string $email): ?AccountDeletionRequest
    {
        return AccountDeletionRequest::query()
            ->pending()
            ->where('email', strtolower(trim($email)))
            ->latest('id')
            ->first();
    }

    /**
     * Logged-in end user confirms identity → queue for super admin (no delete yet).
     */
    public function createFromAuthenticatedUser(User $user, Request $request, ?string $reason = null): AccountDeletionRequest
    {
        if (! $this->deletion->canDeleteAccount($user)) {
            throw new RuntimeException(__('app.user.account_delete.not_allowed'));
        }

        if ($existing = $this->pendingForUser($user)) {
            return $existing;
        }

        return AccountDeletionRequest::query()->create([
            'user_id' => $user->id,
            'source' => AccountDeletionRequest::SOURCE_AUTHENTICATED,
            'name' => $user->name,
            'email' => strtolower(trim((string) $user->email)),
            'phone' => $user->phone,
            'username' => $user->name,
            'reason' => $reason,
            'status' => AccountDeletionRequest::STATUS_PENDING,
            'summary' => $this->deletion->deletionSummary($user),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'form_source' => 'user_account_delete',
                'verified_email_password' => true,
                'typed_delete_confirmation' => true,
                'submitted_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Public no-login form → queue for super admin.
     *
     * @param  array{name: string, email: string, phone?: ?string, username?: ?string, reason?: ?string}  $data
     */
    public function createFromPublicForm(array $data, Request $request): AccountDeletionRequest
    {
        $email = strtolower(trim($data['email']));

        if ($existing = $this->pendingForEmail($email)) {
            return $existing;
        }

        $matchedUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $summary = null;
        if ($matchedUser && $this->deletion->canDeleteAccount($matchedUser)) {
            $summary = $this->deletion->deletionSummary($matchedUser);
        }

        return AccountDeletionRequest::query()->create([
            'user_id' => $matchedUser?->id,
            'source' => AccountDeletionRequest::SOURCE_PUBLIC,
            'name' => $data['name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'username' => $data['username'] ?? null,
            'reason' => $data['reason'] ?? null,
            'status' => AccountDeletionRequest::STATUS_PENDING,
            'summary' => $summary,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'form_source' => 'public_deletion_request',
                'matched_user' => (bool) $matchedUser,
                'matched_deletable' => $matchedUser ? $this->deletion->canDeleteAccount($matchedUser) : false,
                'referrer' => $request->headers->get('referer'),
                'submitted_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Super admin approves → permanently delete the end-user account.
     */
    public function approve(AccountDeletionRequest $request, User $admin, ?string $note = null): void
    {
        if (! $request->isPending()) {
            throw new RuntimeException(__('app.admin.account_deletion_requests.not_pending'));
        }

        $user = $this->resolveTargetUser($request);

        if (! $user) {
            throw new RuntimeException(__('app.admin.account_deletion_requests.user_not_found'));
        }

        if (! $this->deletion->canDeleteAccount($user)) {
            throw new RuntimeException(__('app.admin.account_deletion_requests.user_not_deletable'));
        }

        // Delete first (includes history purge). Mark request approved only after success.
        $this->deletion->delete($user);

        $request->update([
            'status' => AccountDeletionRequest::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
            'reviewed_by_name' => $admin->name,
            'review_note' => $note,
            'reviewed_at' => now(),
            'user_id' => $user->id,
        ]);

        try {
            $this->audit->log('approved', "Approved account deletion request {$request->ticketNumber()}", null, [
                'request_id' => $request->id,
                'email' => $request->email,
                'source' => $request->source,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function reject(AccountDeletionRequest $request, User $admin, ?string $note = null): void
    {
        if (! $request->isPending()) {
            throw new RuntimeException(__('app.admin.account_deletion_requests.not_pending'));
        }

        $request->update([
            'status' => AccountDeletionRequest::STATUS_REJECTED,
            'reviewed_by' => $admin->id,
            'reviewed_by_name' => $admin->name,
            'review_note' => $note,
            'reviewed_at' => now(),
        ]);

        try {
            $this->audit->log('rejected', "Rejected account deletion request {$request->ticketNumber()}", null, [
                'request_id' => $request->id,
                'email' => $request->email,
                'source' => $request->source,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function resolveTargetUser(AccountDeletionRequest $request): ?User
    {
        if ($request->user_id) {
            $user = User::query()->find($request->user_id);
            if ($user) {
                return $user;
            }
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($request->email))])
            ->first();
    }
}
