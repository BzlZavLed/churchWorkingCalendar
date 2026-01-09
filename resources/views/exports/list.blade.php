<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Calendar Export List</title>
    <style>
      body { font-family: Arial, sans-serif; color: #1f1f1f; }
      h1 { font-size: 18px; margin: 0 0 12px; }
      h2 { font-size: 14px; margin: 20px 0 8px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #d5d5d5; padding: 6px; font-size: 10px; }
      th { background: #f2f2f2; text-align: left; }
      .muted { color: #666; }
      .child-row { background: #f3f8f5; }
      .child-row td { font-size: 9px; }
      .child-row ul { margin: 6px 0 0; padding-left: 14px; }
    </style>
  </head>
  <body>
    <h1>Calendar Export (List)</h1>

    @foreach ($months as $month)
      <h2>{{ $month['label'] }}</h2>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Title</th>
            <th>Department</th>
            <th>Objective</th>
            <th>Location</th>
            <th>Status</th>
            <th>Final Validation</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($month['events'] as $event)
            <tr>
              <td>{{ $event->start_at->format('Y-m-d') }}</td>
              <td>{{ $event->start_at->format('H:i') }} - {{ $event->end_at->format('H:i') }}</td>
              <td>{{ $event->title }}</td>
              <td>{{ $event->department->name ?? '—' }}</td>
              <td>{{ $event->objective->name ?? '—' }}</td>
              <td>{{ $event->location ?? '—' }}</td>
              <td class="muted">{{ $event->status }}</td>
              <td class="muted">{{ $event->final_validation ?? '—' }}</td>
            </tr>
            @if ($includeHistory)
              <tr class="child-row">
                <td colspan="8">
                  <strong>Status history</strong>
                  <ul>
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
                      <li>
                        {{ $from }} → {{ $to }} · {{ $history->user->name ?? '—' }} · {{ $history->created_at->format('Y-m-d H:i') }}
                        @if ($history->note)
                          — {{ $history->note }}
                        @endif
                      </li>
                    @empty
                      <li class="muted">No status history</li>
                    @endforelse
                  </ul>
                  <strong>Notes</strong>
                  <ul>
                    @forelse ($event->notes as $note)
                      <li>
                        {{ $note->author->name ?? '—' }} · {{ $note->created_at->format('Y-m-d H:i') }} — {{ $note->note }}
                        @if ($note->reply)
                          <div class="muted">Reply: {{ $note->reply }}</div>
                        @endif
                      </li>
                    @empty
                      <li class="muted">No notes</li>
                    @endforelse
                  </ul>
                </td>
              </tr>
            @endif
          @endforeach
        </tbody>
      </table>
    @endforeach
  </body>
</html>
