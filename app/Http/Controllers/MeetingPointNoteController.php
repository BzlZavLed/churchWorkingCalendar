<?php

namespace App\Http\Controllers;

use App\Models\MeetingPoint;
use App\Models\MeetingPointNote;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class MeetingPointNoteController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, MeetingPoint $point)
    {
        $this->authorize('manageAgenda', $point);

        $meeting = $point->meeting()->firstOrFail();
        if (!$meeting->start_at || $meeting->end_at) {
            abort(422, 'Meeting is not live.');
        }

        $data = $request->validate([
            'note' => ['required', 'string'],
        ]);

        $note = MeetingPointNote::create([
            'meeting_point_id' => $point->id,
            'note' => $data['note'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json($note->load('author'), 201);
    }
}
