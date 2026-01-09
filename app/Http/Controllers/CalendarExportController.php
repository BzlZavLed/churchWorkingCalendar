<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class CalendarExportController extends Controller
{
    public function export(Request $request)
    {
        $data = $request->validate([
            'view' => ['required', 'in:calendar,list'],
            'include_history' => ['nullable', 'boolean'],
        ]);

        $query = Event::query()
            ->with(['department', 'objective', 'histories.user', 'notes.author', 'notes.replyAuthor'])
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_at');

        if (!$request->user()->isSuperAdmin()) {
            $query->whereHas('department', function ($departmentQuery) use ($request) {
                $departmentQuery->where('church_id', $request->user()->church_id);
            });
        }

        $events = $query->get();

        $months = $events->groupBy(function ($event) {
            return $event->start_at->format('Y-m');
        })->map(function ($monthEvents, $monthKey) {
            $monthStart = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();
            $monthEnd = (clone $monthStart)->endOfMonth();

            $eventsByDay = $monthEvents->groupBy(function ($event) {
                return $event->start_at->toDateString();
            });

            $weeks = [];
            $cursor = $monthStart->copy()->startOfWeek();
            while ($cursor->lte($monthEnd)) {
                $week = [];
                for ($i = 0; $i < 7; $i += 1) {
                    $dayKey = $cursor->toDateString();
                    $week[] = [
                        'date' => $cursor->copy(),
                        'in_month' => $cursor->month === $monthStart->month,
                        'events' => $eventsByDay->get($dayKey, collect()),
                    ];
                    $cursor->addDay();
                }
                $weeks[] = $week;
            }

            return [
                'label' => $monthStart->format('F Y'),
                'events' => $monthEvents,
                'weeks' => $weeks,
                'eventsByDay' => $eventsByDay,
            ];
        });

        $view = $data['view'] === 'calendar' ? 'exports.calendar' : 'exports.list';
        $pdf = Pdf::loadView($view, [
            'months' => $months,
            'includeHistory' => (bool) ($data['include_history'] ?? false),
        ])->setPaper('letter', 'portrait');

        $filename = $data['view'] === 'calendar' ? 'calendar-export.pdf' : 'calendar-list-export.pdf';

        return $pdf->download($filename);
    }
}
