<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Services\Account\AccountDeletionRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountDeletionRequestController extends Controller
{
    public function __construct(
        private AccountDeletionRequestService $requests,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));

        $items = AccountDeletionRequest::query()
            ->with('user')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'pending' => AccountDeletionRequest::pending()->count(),
            'approved' => AccountDeletionRequest::where('status', AccountDeletionRequest::STATUS_APPROVED)->count(),
            'rejected' => AccountDeletionRequest::where('status', AccountDeletionRequest::STATUS_REJECTED)->count(),
        ];

        return view('admin.account-deletion-requests.index', compact('items', 'counts', 'status', 'search'));
    }

    public function show(AccountDeletionRequest $accountDeletionRequest): View
    {
        $accountDeletionRequest->load('user', 'reviewer');

        return view('admin.account-deletion-requests.show', [
            'item' => $accountDeletionRequest,
        ]);
    }

    public function approve(Request $request, AccountDeletionRequest $accountDeletionRequest): RedirectResponse
    {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->requests->approve(
                $accountDeletionRequest,
                $request->user(),
                $validated['review_note'] ?? null,
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'review_note' => $e->getMessage() ?: __('app.admin.account_deletion_requests.approve_failed'),
            ]);
        }

        return redirect()
            ->route('admin.account-deletion-requests.show', $accountDeletionRequest)
            ->with('success', __('app.admin.account_deletion_requests.approved'));
    }

    public function reject(Request $request, AccountDeletionRequest $accountDeletionRequest): RedirectResponse
    {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->requests->reject(
                $accountDeletionRequest,
                $request->user(),
                $validated['review_note'] ?? null,
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'review_note' => $e->getMessage() ?: __('app.admin.account_deletion_requests.reject_failed'),
            ]);
        }

        return redirect()
            ->route('admin.account-deletion-requests.show', $accountDeletionRequest)
            ->with('success', __('app.admin.account_deletion_requests.rejected'));
    }
}
