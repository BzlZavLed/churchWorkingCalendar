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
        $canManage = app(\App\Services\MeetingWorkflow::class)->canManage($user);

        if (!$meeting->end_at || (!$canManage && !$meeting->summary_public)) {
            abort(403, 'Summary is not public.');
        }

        $data = $request->validate(['timezone' => ['sometimes', 'timezone'], 'locale' => ['sometimes', 'in:en,es']]);
        $meeting->load(['church', 'points' => fn ($q) => $q->where('status', 'accepted')->whereNotNull('final_status')->with('department'), 'opener']);

        $pdf = Pdf::loadView('exports.meeting-summary', [
            'churchLogo' => app(\App\Services\PdfBranding::class)->logo($meeting->church),
            'meeting' => $meeting,
            'locale' => $data['locale'] ?? 'es',
            'timezone' => $data['timezone'] ?? 'UTC',
        ])->setPaper('letter', 'portrait');

        $filename = "meeting-minutes-{$meeting->minutes_number}.pdf";

        return $pdf->download($filename);
    }
}
