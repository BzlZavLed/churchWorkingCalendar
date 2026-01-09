<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Calendar Export</title>
    <style>
      body { font-family: Arial, sans-serif; color: #1f1f1f; }
      h1 { font-size: 18px; margin: 0 0 12px; }
      h2 { font-size: 14px; margin: 20px 0 8px; }
      table { width: 100%; border-collapse: collapse; table-layout: fixed; }
      th, td { border: 1px solid #d5d5d5; padding: 6px; vertical-align: top; font-size: 10px; }
      th { background: #f2f2f2; text-align: center; }
      .day-number { font-weight: 700; margin-bottom: 4px; }
      .muted { color: #999; }
      .event { margin-bottom: 4px; }
      .event-title { font-weight: 600; }
    </style>
  </head>
  <body>
    <h1>Calendar Export</h1>

    @foreach ($months as $month)
      <h2>{{ $month['label'] }}</h2>
      <table>
        <thead>
          <tr>
            <th>Sun</th>
            <th>Mon</th>
            <th>Tue</th>
            <th>Wed</th>
            <th>Thu</th>
            <th>Fri</th>
            <th>Sat</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($month['weeks'] as $week)
            <tr>
              @foreach ($week as $day)
                <td>
                  <div class="day-number {{ $day['in_month'] ? '' : 'muted' }}">
                    {{ $day['date']->format('j') }}
                  </div>
                  @foreach ($day['events'] as $event)
                    <div class="event">
                      <div class="event-title">{{ $event->title }}</div>
                      <div class="muted">{{ $event->start_at->format('H:i') }} - {{ $event->end_at->format('H:i') }}</div>
                      <div class="muted">{{ $event->department->name ?? '—' }}</div>
                    </div>
                  @endforeach
                </td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    @endforeach

    @if (!empty($includeHistory))
      <div style="page-break-before: always;"></div>
      <h2>Status History</h2>
      <table>
        <thead>
          <tr>
            <th>Event</th>
            <th>Status Change</th>
            <th>User</th>
            <th>Date</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
          @php $historyRows = $months->flatMap(fn ($month) => $month['events']); @endphp
          @forelse ($historyRows as $event)
            @php
              $statusHistory = $event->histories->filter(function ($history) {
                  return isset($history->changes['review_status']);
              });
            @endphp
            @forelse ($statusHistory as $history)
              @php
                $change = $history->changes['review_status'];
                $from = $change['from'] ?? '—';
                $to = $change['to'] ?? '—';
              @endphp
              <tr>
                <td>{{ $event->title }}</td>
                <td>{{ $from }} to {{ $to }}</td>
                <td>{{ $history->user->name ?? '—' }}</td>
                <td>{{ $history->created_at->format('Y-m-d H:i') }}</td>
                <td>{{ $history->note ?? '—' }}</td>
              </tr>
            @empty
              <tr>
                <td>{{ $event->title }}</td>
                <td colspan="4" class="muted">No status history</td>
              </tr>
            @endforelse
          @empty
            <tr>
              <td colspan="5" class="muted">No events</td>
            </tr>
          @endforelse
        </tbody>
      </table>

      <h2>Notes</h2>
      <table>
        <thead>
          <tr>
            <th>Event</th>
            <th>Author</th>
            <th>Date</th>
            <th>Note</th>
            <th>Reply</th>
          </tr>
        </thead>
        <tbody>
          @php $noteRows = $months->flatMap(fn ($month) => $month['events']); @endphp
          @forelse ($noteRows as $event)
            @forelse ($event->notes as $note)
              <tr>
                <td>{{ $event->title }}</td>
                <td>{{ $note->author->name ?? '—' }}</td>
                <td>{{ $note->created_at->format('Y-m-d H:i') }}</td>
                <td>{{ $note->note }}</td>
                <td>{{ $note->reply ?? '—' }}</td>
              </tr>
            @empty
              <tr>
                <td>{{ $event->title }}</td>
                <td colspan="4" class="muted">No notes</td>
              </tr>
            @endforelse
          @empty
            <tr>
              <td colspan="5" class="muted">No events</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    @endif
  </body>
</html>
