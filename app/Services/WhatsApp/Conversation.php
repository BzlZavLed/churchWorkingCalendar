<?php

namespace App\Services\WhatsApp;

use App\Http\Controllers\EventController;
use App\Jobs\WhatsAppHoldWarning;
use App\Models\ChurchContact;
use App\Models\Event;
use App\Models\Objective;
use App\Models\WhatsAppAccount;
use App\Services\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class Conversation
{
    public function reply(WhatsAppAccount $account, string $text): ?string
    {
        if (! app(Access::class)->allowed($account)) {
            return null;
        }
        $user = $account->user()->firstOrFail();
        $state = $account->state ?? [];
        $command = mb_strtoupper(trim($text));
        if ($command === 'AYUDA') {
            return 'Cuenta vinculada. Describe tu evento con título, fecha, horas, zona horaria y objetivo. Comandos: OBJETIVOS, ESTADO, EDITAR número, CONFIRMAR, EXTENDER, CANCELAR. Para visitas indica las fechas. BAJA desactiva el asistente.';
        }
        if ($command === 'CANCELAR') {
            if ($id = $state['hold_id'] ?? null) {
                $event = $this->ownEvent($account, $id);
                if ($event->status === 'hold') {
                    $this->eventAction($account, 'destroy', [], $event);
                }
            }
            $account->update(['state' => []]);

            return 'Borrador cancelado. No se ha enviado.';
        }
        if ($command === 'OBJETIVOS') {
            abort_unless(app(ModuleAccess::class)->read($user, 'objectives') && $user->department_id, 403);

            return Objective::where('department_id', $user->department_id)->limit(30)->get()->map(fn ($o) => "{$o->id}: {$o->name}")->implode("\n") ?: 'Tu departamento aún no tiene objetivos. Registra uno en el sistema.';
        }
        if ($command === 'ESTADO') {
            abort_unless(app(ModuleAccess::class)->read($user, 'calendar'), 403);
            if (!$user->department_id && $user->hasCapability('calendar.conference')) {
                return Event::where('church_id', $user->church_id)->where('is_conference_event', true)->where('created_by', $user->id)->latest()->limit(10)->get()->map(fn ($e) => "#{$e->id} {$e->title}: {$e->status} · ".($e->publish_to_feed ? 'publicado' : 'sin publicar'))->implode("\n") ?: 'Sin eventos de conferencia registrados.';
            }
            abort_unless($user->department_id, 403);

            return Event::where('department_id', $user->department_id)->where('status', '!=', 'hold')->latest()->limit(10)->get()->map(fn ($e) => "#{$e->id} {$e->title}: {$e->review_status}")->implode("\n") ?: 'Sin eventos enviados.';
        }
        if (preg_match('/^EDITAR ([0-9]+)$/D', $command, $m)) {
            abort_if($state['hold_id'] ?? null, 422, 'Cancela la reserva actual antes de editar otro evento.');
            $event = $this->ownEvent($account, (int) $m[1]);
            abort_unless(app(ModuleAccess::class)->write($user, 'calendar') && $user->can('update', $event) && $event->review_status === 'changes_requested', 403);
            $draft = $event->only(['title', 'description', 'location', 'objective_id']);
            $draft['start_at'] = $event->start_at->toIso8601String();
            $draft['end_at'] = $event->end_at->toIso8601String();
            $account->update(['state' => ['edit_id' => $event->id, 'version' => $this->version($event), 'draft' => $draft]]);

            return "Editando #{$event->id}: {$event->title}. Indica los cambios; después escribe CONFIRMAR para reenviar el mismo evento.";
        }
        if ($command === 'EXTENDER') {
            $event = $this->ownEvent($account, (int) ($state['hold_id'] ?? 0));
            $this->eventAction($account, 'extendHold', [], $event);
            $this->warning($account, $event->fresh());

            return 'Reserva extendida cinco minutos. Escribe CONFIRMAR para enviar.';
        }
        if ($command === 'CONFIRMAR') {
            return DB::transaction(function () use ($account, $state) {
                // Serialize against the same church lock used by browser changes.
                \App\Models\Church::whereKey($account->user->church_id)->lockForUpdate()->firstOrFail();
                if (! app(Access::class)->allowed($account)) {
                    abort(403);
                }
                if (($state['draft']['event_type'] ?? null) === 'conference') {
                    $user = $account->user()->firstOrFail();
                    abort_unless(app(ModuleAccess::class)->write($user, 'calendar') && $user->hasCapability('calendar.conference'), 403);
                    $data = $this->validateDraft($state['draft'], true);
                    $request = Request::create('/api/conference-events', 'POST', $data);
                    $request->setUserResolver(fn () => $user);
                    $response = app(\App\Http\Controllers\ConferenceEventController::class)->store($request);
                    $id = $response->getData(true)['id'];
                    $account->update(['state' => []]);
                    return "Evento de conferencia #$id registrado. El horario queda bloqueado. No está publicado; puedes publicarlo desde el calendario después de consultar con el pastor.";
                }
                if (empty($state['draft'])) return 'No hay un borrador pendiente. Describe primero el evento.';
                $data = $this->validateDraft($state['draft'] ?? []);
                if ($id = $state['edit_id'] ?? null) {
                    $event = $this->ownEvent($account, $id);
                    abort_unless(($state['version'] ?? '') === $this->version($event), 422, 'El evento cambió. Escribe EDITAR y su número para revisarlo nuevamente.');
                    $this->eventAction($account, 'update', $data, $event);
                } else {
                    $event = $this->ownEvent($account, (int) ($state['hold_id'] ?? 0));
                    $this->eventAction($account, 'update', $data, $event);
                    $this->eventAction($account, 'lockEvent', [], $event->fresh());
                }
                $account->update(['state' => []]);

                return "Evento #{$event->id} enviado a revisión. Aún requiere aceptación del comité ejecutivo.";
            });
        }
        $objectives = $user->department_id ? Objective::where('department_id', $user->department_id)->limit(30)->get(['id', 'name'])->toArray() : [];
        $parsed = app(Interpreter::class)->parse($account, $text, ['draft' => $state['draft'] ?? [], 'objectives' => $objectives]);
        // Recheck opt-in and current role after the network call.
        if (! app(Access::class)->allowed($account)) {
            return null;
        }
        if (($parsed['intent'] ?? '') === 'visitors') {
            abort_unless(app(ModuleAccess::class)->read($user->fresh(), 'visitors'), 403);
            if (empty($parsed['from']) || empty($parsed['to'])) {
                return mb_substr($parsed['reply'] ?? 'Indica la fecha inicial y final de las visitas.', 0, 1500);
            }
            $dates = Validator::make($parsed, ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from'])->validate();
            $count = ChurchContact::where('church_id', $user->church_id)->whereDate('created_at', '>=', $dates['from'])->whereDate('created_at', '<=', $dates['to'])->count();
            $url = rtrim(config('app.url'), '/').'/greeting/contacts?'.http_build_query($dates);

            return "$count registros entre {$dates['from']} y {$dates['to']} (UTC). Consulta la lista con tu sesión del sistema:\n$url";
        }
        if (($parsed['intent'] ?? '') !== 'event') {
            return mb_substr($parsed['reply'] ?? 'Describe un evento o pide una lista de visitas por fecha.', 0, 1500);
        }
        $isConference = ($parsed['event_type'] ?? $state['draft']['event_type'] ?? 'department') === 'conference';
        if (!$isConference && ($state['draft']['event_type'] ?? null) === 'conference') return 'Escribe CANCELAR antes de cambiar a un evento departamental.';
        if ($isConference) {
            if (!app(ModuleAccess::class)->write($user->fresh(), 'calendar') || !$user->fresh()->hasCapability('calendar.conference')) return 'Tu cuenta no tiene permiso para crear eventos de conferencia.';
            if (isset($state['hold_id']) || isset($state['edit_id'])) return 'Escribe CANCELAR antes de preparar un evento de conferencia.';
        } elseif (!$user->department_id || !app(ModuleAccess::class)->write($user->fresh(), 'calendar')) {
            return 'Esta cuenta no puede crear eventos departamentales por WhatsApp. Si es un evento de conferencia, indícalo explícitamente.';
        }
        $draft = $state['draft'] ?? [];
        foreach (['title', 'description', 'location', 'objective_id', 'start_at', 'end_at'] as $key) {
            if (isset($parsed[$key])) {
                $draft[$key] = $parsed[$key];
            }
        }
        if ($isConference) {
            $draft['event_type'] = 'conference';
            unset($draft['objective_id']);
            $account->update(['state' => ['draft' => $draft]]);
            $missing = [];
            foreach (['title' => 'título', 'start_at' => 'fecha y hora de inicio con zona horaria', 'end_at' => 'fecha y hora de fin con zona horaria'] as $key => $label) {
                if (empty($draft[$key])) $missing[] = $label;
            }
            $summary = "Borrador de conferencia:\nTítulo: ".($draft['title'] ?? 'pendiente')."\nInicio: ".($draft['start_at'] ?? 'pendiente')."\nFin: ".($draft['end_at'] ?? 'pendiente')."\nLugar: ".($draft['location'] ?? 'pendiente');
            if ($missing) return $summary."\nFalta: ".implode(', ', $missing).". ".($parsed['reply'] ?? '');
            $this->validateDraft($draft, true);
            return $summary."\nEscribe CONFIRMAR para guardar o CANCELAR. Al confirmar se bloqueará el horario y los eventos departamentales que coincidan recibirán una solicitud de cambios. No se publicará automáticamente.";
        }
        // Never change a submitted event until exact CONFIRMAR; holds are temporary.
        if (! isset($state['edit_id']) && ! empty($draft['start_at']) && ! empty($draft['end_at'])) {
            Validator::make($draft, ['start_at' => ['required', 'date', 'regex:/T.*(?:Z|[+-][0-9]{2}:[0-9]{2})$/'], 'end_at' => ['required', 'date', 'after:start_at', 'regex:/T.*(?:Z|[+-][0-9]{2}:[0-9]{2})$/']])->validate();
            $hold = isset($state['hold_id']) ? Event::find($state['hold_id']) : null;
            if ($hold && $hold->status === 'hold' && $hold->expires_at?->isFuture()) {
                $this->ownEvent($account, $hold->id);
                $this->eventAction($account, 'update', $draft, $hold);
            } else {
                $result = $this->eventAction($account, 'storeHold', $draft);
                $state['hold_id'] = $result['id'];
                $hold = Event::findOrFail($result['id']);
                $this->warning($account, $hold);
            }
        }
        $state['draft'] = $draft;
        $account->update(['state' => $state]);
        $summary = "Borrador:\n";
        foreach (['title' => 'Título', 'start_at' => 'Inicio', 'end_at' => 'Fin', 'location' => 'Lugar', 'objective_id' => 'Objetivo'] as $key => $label) {
            $summary .= "$label: ".($draft[$key] ?? 'pendiente')."\n";
        }
        if (! empty($draft['description'])) {
            $summary .= "Descripción: {$draft['description']}\n";
        }

        return $summary.($parsed['reply'] ?? '')."\nEscribe CONFIRMAR para enviar, EXTENDER para renovar la reserva o CANCELAR. La reserva dura cinco minutos.";
    }

    private function ownEvent(WhatsAppAccount $account, int $id): Event
    {
        $user = $account->user()->firstOrFail();
        abort_unless($user->department_id, 403);

        return Event::where('department_id', $user->department_id)->whereHas('department', fn ($q) => $q->where('church_id', $user->church_id))->findOrFail($id);
    }

    private function validateDraft(array $draft, bool $conference = false): array
    {
        return Validator::make($draft, ['title' => 'required|string|max:255', 'objective_id' => $conference ? 'nullable|integer' : 'required|integer', 'description' => 'nullable|string|max:4000', 'location' => 'nullable|string|max:255',
            'start_at' => ['required', 'date', 'regex:/T.*(?:Z|[+-][0-9]{2}:[0-9]{2})$/'], 'end_at' => ['required', 'date', 'after:start_at', 'regex:/T.*(?:Z|[+-][0-9]{2}:[0-9]{2})$/']])->validate();
    }

    private function version(Event $event): string
    {
        return hash('sha256', json_encode($event->getAttributes()));
    }

    private function eventAction(WhatsAppAccount $account, string $method, array $data, ?Event $event = null): array
    {
        $user = $account->user()->firstOrFail();
        abort_unless(app(Access::class)->allowed($account) && app(ModuleAccess::class)->write($user, 'calendar'), 403);
        $request = Request::create('/api/events', 'POST', $data);
        $request->setUserResolver(fn () => $user);
        $guard = Auth::guard();
        $previous = $guard->user();
        $guard->setUser($user);
        try {
            $response = $event ? app(EventController::class)->$method($request, $event) : app(EventController::class)->$method($request);

            return $response->getData(true);
        } finally {
            if ($previous) {
                $guard->setUser($previous);
            } else {
                $guard->forgetUser();
            }
        }
    }

    private function warning(WhatsAppAccount $account,Event $event): void
    {
        WhatsAppHoldWarning::dispatch($account->id,$event->id,$event->expires_at->toIso8601String())->delay($event->expires_at->copy()->subMinute())->afterCommit();
    }
}
