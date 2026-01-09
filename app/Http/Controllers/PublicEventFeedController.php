<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Church;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicEventFeedController extends Controller
{
    public function index(Request $request, ?Church $church = null)
    {
        $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'church_id' => ['nullable', 'integer'],
        ]);

        $churchId = $this->resolveChurchId($request, $church);

        $query = Event::query()
            ->with(['department', 'objective'])
            ->where('final_validation', 'accepted')
            ->where('publish_to_feed', true)
            ->whereHas('department', function ($builder) use ($churchId) {
                $builder->where('church_id', $churchId);
            });

        if ($request->filled('start') && $request->filled('end')) {
            $start = Carbon::parse($request->input('start'))->utc();
            $end = Carbon::parse($request->input('end'))->utc();
            $query->overlapping($start, $end);
        }

        $events = $query->orderBy('start_at')->get();

        return response()->json($events);
    }

    public function ics(Request $request, ?Church $church = null)
    {
        $request->validate([
            'church_id' => ['nullable', 'integer'],
        ]);

        $churchId = $this->resolveChurchId($request, $church);

        $events = Event::query()
            ->with(['department', 'objective'])
            ->where('final_validation', 'accepted')
            ->where('publish_to_feed', true)
            ->whereHas('department', function ($builder) use ($churchId) {
                $builder->where('church_id', $churchId);
            })
            ->orderBy('start_at')
            ->get();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Church Working Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        $now = now()->utc()->format('Ymd\THis\Z');

        foreach ($events as $event) {
            $start = $event->start_at?->copy()->utc()->format('Ymd\THis\Z');
            $end = $event->end_at?->copy()->utc()->format('Ymd\THis\Z');
            if (!$start || !$end) {
                continue;
            }

            $summary = $this->escapeIcsText($event->title ?? 'Event');
            $description = $this->escapeIcsText(trim(implode(' - ', array_filter([
                $event->description,
                $event->objective?->name ? "Objective: {$event->objective->name}" : null,
                $event->department?->name ? "Department: {$event->department->name}" : null,
            ]))));
            $location = $this->escapeIcsText($event->location ?? '');
            $uid = "{$event->id}@{$request->getHost()}";

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            $lines[] = "DTSTAMP:{$now}";
            $lines[] = "DTSTART:{$start}";
            $lines[] = "DTEND:{$end}";
            $lines[] = "SUMMARY:{$summary}";
            if ($description !== '') {
                $lines[] = "DESCRIPTION:{$description}";
            }
            if ($location !== '') {
                $lines[] = "LOCATION:{$location}";
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $ical = implode("\r\n", $lines) . "\r\n";

        return response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="events.ics"',
        ]);
    }

    private function resolveChurchId(Request $request, ?Church $church): int
    {
        if ($church) {
            return $church->id;
        }

        $churchId = $request->input('church_id');
        if (!$churchId) {
            throw ValidationException::withMessages([
                'church_id' => ['Church is required.'],
            ]);
        }

        if (!Church::whereKey($churchId)->exists()) {
            throw ValidationException::withMessages([
                'church_id' => ['Church not found.'],
            ]);
        }

        return (int) $churchId;
    }

    private function escapeIcsText(string $value): string
    {
        $value = str_replace(["\r\n", "\n", "\r"], '\\n', $value);
        $value = str_replace(['\\', ';', ','], ['\\\\', '\;', '\,'], $value);
        return $value;
    }
}
