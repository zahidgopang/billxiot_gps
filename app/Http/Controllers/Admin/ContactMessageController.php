<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));

        $messages = ContactMessage::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'new' => ContactMessage::where('status', 'new')->count(),
            'read' => ContactMessage::where('status', 'read')->count(),
            'replied' => ContactMessage::where('status', 'replied')->count(),
            'closed' => ContactMessage::where('status', 'closed')->count(),
        ];

        return view('admin.contact-messages.index', compact('messages', 'counts', 'status', 'search'));
    }

    public function show(ContactMessage $contactMessage)
    {
        if ($contactMessage->status === 'new') {
            $contactMessage->markAsRead();
        }

        return view('admin.contact-messages.show', [
            'message' => $contactMessage->fresh(),
        ]);
    }

    public function update(Request $request, ContactMessage $contactMessage)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,read,replied,closed',
        ]);

        $contactMessage->update(['status' => $validated['status']]);

        return redirect()
            ->route('admin.contact-messages.show', $contactMessage)
            ->with('success', __('app.admin.contact_messages.status_updated'));
    }
}
