<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class MeetingSummaryExportController extends Controller
{
    use AuthorizesRequests;

    public function export(Request $request, Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        $user = $request->user();
        $canManage = $user->isSuperAdmin() || $user->isSecretary();

        if (!$canManage && !$meeting->summary_public) {
            abort(403, 'Summary is not public.');
        }

        $meeting->load(['church', 'points.department', 'points.notes.author', 'opener', 'meetingNotes.author']);

        $pdf = Pdf::loadView('exports.meeting-summary', [
            'meeting' => $meeting,
            'locale' => $request->input('locale', 'es'),
        ])->setPaper('letter', 'portrait');

        $filename = "meeting-summary-{$meeting->id}.pdf";

        return $pdf->download($filename);
    }
}
