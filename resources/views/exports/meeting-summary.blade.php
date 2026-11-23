<!DOCTYPE html>
<html lang="{{ $locale ?? 'es' }}">
  <head>
    <meta charset="utf-8" />
    <title>{{ $locale === 'en' ? 'Meeting Summary' : 'Resumen de Junta' }}</title>
    <style>
      body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1b1f23; }
      h1, h2, h3 { page-break-after: avoid; margin: 0 0 8px; }
      .section { margin-bottom: 18px; }
      .meta { margin-bottom: 12px; }
      .meta div { margin-bottom: 4px; }
      .point { page-break-inside: avoid; padding: 10px 0; border-bottom: 1px solid #e0e0e0; }
      .point:last-child { border-bottom: none; }
      .label { font-weight: bold; }
      .note { margin: 4px 0; }
      .muted { color: #6c757d; }
    </style>
  </head>
  <body>
    @include('exports.branding')
    <h1>{{ $locale === 'en' ? 'Meeting Summary' : 'Resumen de Junta' }} #{{ $meeting->minutes_number }}</h1>

    <div class="section meta">
      <div class="muted">{{ $locale === 'en' ? 'Time zone' : 'Zona horaria' }}: {{ $timezone }}</div>
      <div><span class="label">{{ $locale === 'en' ? 'Church' : 'Iglesia' }}:</span> {{ $meeting->church?->name ?? '—' }}</div>
      <div><span class="label">{{ $locale === 'en' ? 'Meeting date' : 'Fecha de junta' }}:</span> {{ $meeting->meeting_date?->format('Y-m-d') }}</div>
      <div><span class="label">{{ $locale === 'en' ? 'Planned start' : 'Hora planificada' }}:</span> {{ $meeting->planned_start_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') }}</div>
      <div><span class="label">{{ $locale === 'en' ? 'Started at' : 'Inicio real' }}:</span> {{ $meeting->start_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') ?? '—' }}</div>
      <div><span class="label">{{ $locale === 'en' ? 'Ended at' : 'Fin real' }}:</span> {{ $meeting->end_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') ?? '—' }}</div>
      <div><span class="label">{{ $locale === 'en' ? 'Location' : 'Lugar' }}:</span> {{ $meeting->location ?? '—' }}</div>
    </div>

    <div class="section">
      <h2>{{ $locale === 'en' ? 'Opening Notes' : 'Notas de apertura' }}</h2>
      <div class="note"><span class="label">{{ $locale === 'en' ? 'Opened by' : 'Abierta por' }}:</span> {{ $meeting->opener?->name ?? '—' }}</div>
      <div class="note"><span class="label">{{ $locale === 'en' ? 'Opening prayer' : 'Oracion inicial' }}:</span> {{ $meeting->opening_prayer ?? '' }} <span class="muted">{{ $meeting->opening_prayer_recorded_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') ?? ($locale === 'en' ? 'Recording time unavailable' : 'Fecha de registro no disponible') }}</span></div>
      <div class="note"><span class="label">{{ $locale === 'en' ? 'Opening remarks' : 'Observaciones iniciales' }}:</span> {{ $meeting->opening_remarks ?? '' }} <span class="muted">{{ $meeting->opening_remarks_recorded_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') ?? ($locale === 'en' ? 'Recording time unavailable' : 'Fecha de registro no disponible') }}</span></div>
    </div>

    <div class="section">
      <h2>{{ $locale === 'en' ? 'Attendance' : 'Asistencia' }}</h2>
      @foreach(($meeting->attendance ?? []) as $person)
        <div>{{ $person['name'] }} — {{ ($locale === 'en' ? ['present' => 'Present', 'absent' => 'Absent', 'online' => 'Online'] : ['present' => 'Presente', 'absent' => 'Ausente', 'online' => 'En línea'])[$person['status']] ?? $person['status'] }} <span class="muted">{{ !empty($person['recorded_at']) ? \Carbon\Carbon::parse($person['recorded_at'])->setTimezone($timezone)->format('Y-m-d H:i') : ($locale === 'en' ? 'Recording time unavailable' : 'Fecha de registro no disponible') }}</span></div>
      @endforeach
    </div>
    <div class="section">
      <h2>{{ $locale === 'en' ? 'Meeting Points' : 'Puntos de junta' }}</h2>

      @foreach($meeting->points->sortBy('agenda_order') as $point)
        <div class="point">
          <h3>{{ $point->title }}</h3>
          <div class="muted">{{ $point->department?->name ?? ($locale === 'en' ? 'Clerk' : 'Secretaría') }}</div>

          <div class="note"><span class="label">{{ $locale === 'en' ? 'Status' : 'Estado' }}:</span> {{ ($locale === 'en' ? ['approved' => 'Accepted', 'denied' => 'Rejected', 'postponed' => 'Deferred to next meeting', 'needs_update' => 'Updates requested', 'informative' => 'Informative'] : ['approved' => 'Aceptado', 'denied' => 'Rechazado', 'postponed' => 'Próxima junta', 'needs_update' => 'Actualización solicitada', 'informative' => 'Informativo'])[$point->final_status] ?? $point->final_status }}</div>
          @if($point->calendar_review)
            <h3>{{ $locale === 'en' ? 'Calendar review' : 'Revisión del calendario' }}</h3>
            @forelse($point->calendar_decisions ?? [] as $decision)
              <p><strong>{{ $decision['title'] }}</strong> — {{ $decision['department'] }}<br>
                {{ \Carbon\Carbon::parse($decision['start_at'])->setTimezone($timezone)->format('Y-m-d H:i') }} —
                {{ ['approved'=>($locale === 'en' ? 'Accepted':'Aceptado'),'denied'=>($locale === 'en' ? 'Rejected':'Rechazado'),'changes_requested'=>($locale === 'en' ? 'Changes requested':'Cambios solicitados')][$decision['status']] ?? $decision['status'] }}<br>
                <span class="muted">{{ \Carbon\Carbon::parse($decision['at'])->setTimezone($timezone)->format('Y-m-d H:i') }} · {{ $decision['actor'] }}</span>
              </p>
            @empty
              <p>{{ $locale === 'en' ? 'No event decisions recorded.' : 'Sin decisiones de eventos registradas.' }}</p>
            @endforelse
          @endif
          <div class="muted">{{ $point->finalized_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') }}</div>
        </div>
      @endforeach
    </div>

    @if($meeting->closing_notes)
      <div class="section">
        <h2>{{ $locale === 'en' ? 'Closing notes' : 'Notas de cierre' }}</h2>
        <p class="muted">{{ $meeting->end_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') }}</p>
        <div>{!! nl2br(e($meeting->closing_notes)) !!}</div>
      </div>
    @endif
  </body>
</html>
