<!DOCTYPE html>
<html lang="{{ $locale ?? 'es' }}">
  <head>
    <meta charset="utf-8" />
    <title>{{ $locale === 'en' ? 'Meeting Summary' : 'Resumen de Junta' }}</title>
    <style>
      body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1b1f23; }
      h1, h2, h3 { margin: 0 0 8px; }
      .section { margin-bottom: 18px; }
      .meta { margin-bottom: 12px; }
      .meta div { margin-bottom: 4px; }
      .point { padding: 10px 0; border-bottom: 1px solid #e0e0e0; }
      .point:last-child { border-bottom: none; }
      .label { font-weight: bold; }
      .note { margin: 4px 0; }
      .muted { color: #6c757d; }
    </style>
  </head>
  <body>
    <h1>{{ $locale === 'en' ? 'Meeting Summary' : 'Resumen de Junta' }}</h1>

    <div class="section meta">
      <div><span class="label">{{ $locale === 'en' ? 'Church' : 'Iglesia' }}:</span> {{ $meeting->church?->name ?? '—' }}</div>
      <div><span class="label">{{ $locale === 'en' ? 'Meeting date' : 'Fecha de junta' }}:</span> {{ $meeting->meeting_date?->format('Y-m-d') }}</div>
      <div><span class="label">{{ $locale === 'en' ? 'Planned start' : 'Hora planificada' }}:</span> {{ optional($meeting->planned_start_at)->format('Y-m-d H:i') }}</div>
      <div><span class="label">{{ $locale === 'en' ? 'Started at' : 'Inicio real' }}:</span> {{ optional($meeting->start_at)->format('Y-m-d H:i') ?? '—' }}</div>
      <div><span class="label">{{ $locale === 'en' ? 'Ended at' : 'Fin real' }}:</span> {{ optional($meeting->end_at)->format('Y-m-d H:i') ?? '—' }}</div>
      <div><span class="label">{{ $locale === 'en' ? 'Location' : 'Lugar' }}:</span> {{ $meeting->location ?? '—' }}</div>
    </div>

    <div class="section">
      <h2>{{ $locale === 'en' ? 'Opening Notes' : 'Notas de apertura' }}</h2>
      <div class="note"><span class="label">{{ $locale === 'en' ? 'Opened by' : 'Abierta por' }}:</span> {{ $meeting->opener?->name ?? '—' }}</div>
      <div class="note"><span class="label">{{ $locale === 'en' ? 'Opening prayer' : 'Oracion inicial' }}:</span> {{ $meeting->opening_prayer ?? '' }}</div>
      <div class="note"><span class="label">{{ $locale === 'en' ? 'Opening remarks' : 'Observaciones iniciales' }}:</span> {{ $meeting->opening_remarks ?? '' }}</div>
    </div>

    <div class="section">
      <h2>{{ $locale === 'en' ? 'Meeting Points' : 'Puntos de junta' }}</h2>

      @foreach($meeting->points->sortBy('agenda_order') as $point)
        <div class="point">
          <h3>{{ $point->title }}</h3>
          <div class="muted">{{ $point->department?->name ?? '—' }}</div>
          <div class="note">{{ $point->description }}</div>
          <div class="note"><span class="label">{{ $locale === 'en' ? 'Status' : 'Estado' }}:</span> {{ $point->final_status ?? $point->status }}</div>
          @if($point->review_note)
            <div class="note"><span class="label">{{ $locale === 'en' ? 'Review note' : 'Nota de revision' }}:</span> {{ $point->review_note }}</div>
          @endif
          @if($point->final_note)
            <div class="note">
              <span class="label">{{ $locale === 'en' ? 'Point note' : 'Nota del punto' }}:</span>
              {{ $point->final_note }}
              @if($point->finalized_at)
                ({{ optional($point->finalized_at)->format('Y-m-d H:i') }})
              @endif
            </div>
          @endif
        </div>
      @endforeach
    </div>

    <div class="section">
      <h2>{{ $locale === 'en' ? 'General Summary' : 'Resumen general' }}</h2>
      <div class="note">{{ $meeting->summary_text ?? '' }}</div>
    </div>

    <div class="section">
      <h2>{{ $locale === 'en' ? 'General Notes' : 'Notas generales' }}</h2>
      @if($meeting->meetingNotes->count())
        <ul>
          @foreach($meeting->meetingNotes->sortBy('created_at') as $note)
            <li>
              {{ optional($note->created_at)->format('Y-m-d H:i') }} - {{ $note->note }}
              @if($note->author)
                ({{ $note->author->name }})
              @endif
            </li>
          @endforeach
        </ul>
      @else
        <div class="note">{{ $locale === 'en' ? 'No general notes.' : 'Sin notas generales.' }}</div>
      @endif
    </div>
  </body>
</html>
