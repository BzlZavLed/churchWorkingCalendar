<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventNote;
use App\Support\DomainUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventNoteController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
    public function store(Request $request, Event $event)
    {
        $data = $request->validate([
            'note' => ['required', 'string'],
        ]);

        $user = $request->user();
        $this->authorize('comment', $event);

        $note = DB::transaction(function () use ($event, $user, $data) {
            return EventNote::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'note' => $data['note'],
                'author_role' => $user->role,
                'author_is_reviewer' => $user->hasCapability('calendar.review'),
                'seen_note' => !$user->hasCapability('calendar.review'),
            ]);
        });

        DomainUpdate::forEventNote('created', $note, $user);

        return response()->json($note->load(['author', 'replyAuthor']), 201);
    }

    public function reply(Request $request, Event $event, EventNote $note)
    {
        if ($note->event_id !== $event->id) {
            abort(404);
        }

        return $this->replyForEvent($request, $event);
    }

    public function replyForEvent(Request $request, Event $event)
    {
        $data = $request->validate([
            'reply' => ['required', 'string'],
        ]);

        $note = $this->createDepartmentReply($request->user(), $event, $data['reply']);
        DomainUpdate::forEventNote('replied', $note, $request->user());

        return response()->json($note->load(['author', 'replyAuthor']));
    }

    public function unseen(Request $request)
    {
        $user = $request->user();
        if (!$user->department_id || $user->isSecretary()) {
            return response()->json([
                'count' => 0,
                'notes' => [],
            ]);
        }

        $notes = EventNote::query()
            ->with(['event.department', 'author', 'replyAuthor'])
            ->where('seen_note', false)
            ->whereHas('event', function ($query) use ($user) {
                $query->where('department_id', $user->department_id);
            })
            ->where('author_is_reviewer', true)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'count' => $notes->count(),
            'notes' => $notes,
        ]);
    }

    public function markSeen(Request $request, EventNote $note)
    {
        $user = $request->user();
        if ($user->isSecretary() || $user->isSuperAdmin()) {
            abort(403);
        }

        $note->loadMissing('event');
        if (!$note->event || $note->event->department_id !== $user->department_id) {
            abort(403);
        }

        $note->update([
            'seen_note' => true,
            'seen_at' => now(),
            'seen_by_user_id' => $user->id,
        ]);

        DomainUpdate::forEventNote('seen', $note, $user);

        return response()->json($note->load(['event.department', 'author', 'replyAuthor']));
    }

    private function createDepartmentReply($user, Event $event, string $reply): EventNote
    {
        if ($user->isSecretary() || $user->isSuperAdmin()) {
            abort(403);
        }

        if ($user->department_id === null || $user->department_id !== $event->department_id) {
            abort(403);
        }

        $this->authorize('comment', $event);

        return DB::transaction(function () use ($event, $user, $reply) {
            return EventNote::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'note' => $reply,
                'author_role' => $user->role,
                'author_is_reviewer' => $user->hasCapability('calendar.review'),
                'seen_note' => true,
                'seen_at' => now(),
                'seen_by_user_id' => $user->id,
            ]);
        });
    }
}
