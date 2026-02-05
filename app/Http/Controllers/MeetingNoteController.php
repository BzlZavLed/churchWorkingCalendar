<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingNote;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class MeetingNoteController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        if (!$meeting->start_at) {
            abort(422, 'Meeting has not started.');
        }

        $data = $request->validate([
            'note' => ['required', 'string'],
        ]);

        $note = MeetingNote::create([
            'meeting_id' => $meeting->id,
            'note' => $data['note'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json($note->load('author'), 201);
    }
}
