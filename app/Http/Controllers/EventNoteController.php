<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventNoteController extends Controller
{
    public function store(Request $request, Event $event)
    {
        $data = $request->validate([
            'note' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isSecretary()) {
            abort(403);
        }

        $note = DB::transaction(function () use ($event, $user, $data) {
            return EventNote::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'note' => $data['note'],
                'seen_note' => false,
            ]);
        });

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
            ->whereHas('author', function ($query) {
                $query->whereIn('role', ['secretary', 'superadmin']);
            })
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

        $hasSecretaryNote = EventNote::query()
            ->where('event_id', $event->id)
            ->whereHas('author', function ($query) {
                $query->whereIn('role', ['secretary', 'superadmin']);
            })
            ->exists();

        if (!$hasSecretaryNote) {
            throw ValidationException::withMessages([
                'reply' => ['A secretary note is required before replying.'],
            ]);
        }

        $hasDepartmentReply = EventNote::query()
            ->where('event_id', $event->id)
            ->whereHas('author', function ($query) {
                $query->whereNotIn('role', ['secretary', 'superadmin']);
            })
            ->exists();

        if ($hasDepartmentReply) {
            throw ValidationException::withMessages([
                'reply' => ['A reply already exists for this event.'],
            ]);
        }

        return DB::transaction(function () use ($event, $user, $reply) {
            return EventNote::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'note' => $reply,
                'seen_note' => true,
                'seen_at' => now(),
                'seen_by_user_id' => $user->id,
            ]);
        });
    }
}
