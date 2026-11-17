<?php
namespace App\Http\Controllers;

use App\Models\{Church, Event};
use App\Support\DomainUpdate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConferenceEventController extends Controller
{
    private function church(Request $request): int
    {
        abort_unless($request->user()->hasCapability('calendar.conference') || $request->user()->isSuperAdmin(), 403);
        $id = $request->user()->isSuperAdmin() ? $request->integer('church_id') : $request->user()->church_id;
        abort_unless($id && Church::whereKey($id)->exists(), 422, 'Select a church first.');
        return $id;
    }

    private function validateEvent(array $data): array
    {
        return Validator::make($data, [
            'title'=>['required','string','max:255'], 'description'=>['nullable','string'],
            'location'=>['nullable','string','max:255'], 'start_at'=>['required','date'],
            'end_at'=>['required','date','after:start_at'],
        ])->validate();
    }

    public function store(Request $request)
    {
        $church = $this->church($request);
        $data = $this->validateEvent($request->all());
        return response()->json(DB::transaction(function () use ($request, $church, $data) {
            Church::whereKey($church)->lockForUpdate()->firstOrFail();
            return $this->save($request, $church, $data);
        }), 201);
    }

    public function update(Request $request, Event $event)
    {
        $church = $this->church($request);
        abort_unless($event->is_conference_event && $event->church_id === $church, 403);
        $data = $this->validateEvent($request->all());
        return DB::transaction(function () use ($request, $church, $data, $event) {
            Church::whereKey($church)->lockForUpdate()->firstOrFail();
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_if($event->status === 'cancelled', 422, 'Event is cancelled.');
            return $this->save($request, $church, $data, $event);
        });
    }

    public function destroy(Request $request, Event $event)
    {
        $church = $this->church($request);
        abort_unless($event->is_conference_event && $event->church_id === $church, 403);
        return DB::transaction(function () use ($request, $church, $event) {
            Church::whereKey($church)->lockForUpdate()->firstOrFail();
            $event->update(['status'=>'cancelled','updated_by'=>$request->user()->id]);
            $event->recordAction($request->user(), 'cancelled');
            DomainUpdate::forEvent('cancelled', $event, $request->user());
            return $event;
        });
    }

    public function publish(Request $request, ?Event $event = null)
    {
        $church = $this->church($request);
        if ($event) abort_unless($event->is_conference_event && $event->church_id === $church, 403);
        $data = $request->validate(['published' => ['sometimes', 'boolean']]);
        $published = $data['published'] ?? true;
        return DB::transaction(function () use ($request, $church, $event, $published) {
            Church::whereKey($church)->lockForUpdate()->firstOrFail();
            $events = Event::where('church_id', $church)->where('is_conference_event', true)->where('status', 'locked')
                ->when($event, fn ($q) => $q->whereKey($event->id))->lockForUpdate()->get();
            $changed = 0;
            foreach ($events as $item) {
                if ($item->publish_to_feed === $published) continue;
                $item->update(['publish_to_feed' => $published, 'published_at' => $published ? now() : null, 'updated_by' => $request->user()->id]);
                $item->recordAction($request->user(), $published ? 'conference_published' : 'conference_unpublished');
                DomainUpdate::forEvent('published', $item, $request->user());
                $changed++;
            }
            return response()->json(['changed' => $changed]);
        });
    }

    public function import(Request $request)
    {
        $church = $this->church($request);
        $request->validate(['file'=>['required','file','max:2048']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (!$headers) throw ValidationException::withMessages(['file'=>'CSV is empty.']);
            $headers = array_map(fn($h)=>trim(ltrim($h, "\xEF\xBB\xBF")), $headers);
            if (count(array_unique($headers)) !== count($headers) || array_diff(['title','start_at','end_at'], $headers)) {
                throw ValidationException::withMessages(['file'=>'Required headers: title,start_at,end_at. Optional: description,location.']);
            }
            $rows=[]; $line=1;
            while (($row=fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($row === [null]) continue;
                if (count($rows)>=500 || count($row)!==count($headers)) throw ValidationException::withMessages(['file'=>"Invalid CSV row $line (maximum 500 events)."]);
                try { $rows[]=$this->validateEvent(array_combine($headers,$row)); }
                catch (ValidationException $e) { throw ValidationException::withMessages(['file'=>"Row $line: ".implode(' ',array_merge(...array_values($e->errors())))]); }
            }
            if (!$rows) throw ValidationException::withMessages(['file'=>'CSV contains no events.']);
        } finally { fclose($handle); }
        return DB::transaction(function () use ($request,$church,$rows) {
            Church::whereKey($church)->lockForUpdate()->firstOrFail();
            $created=[]; $skipped=0;
            foreach ($rows as $row) {
                $start=Carbon::parse($row['start_at'])->utc(); $end=Carbon::parse($row['end_at'])->utc();
                if (Event::where('church_id',$church)->where('is_conference_event',true)->where('status','locked')->where('title',$row['title'])->where('start_at',$start)->where('end_at',$end)->exists()) { $skipped++; continue; }
                $created[]=$this->save($request,$church,$row);
            }
            return response()->json(['created'=>count($created),'skipped'=>$skipped,'events'=>$created],201);
        });
    }

    private function save(Request $request, int $church, array $data, ?Event $event=null): Event
    {
        $start=Carbon::parse($data['start_at'])->utc(); $end=Carbon::parse($data['end_at'])->utc();
        $conflicts=Event::forChurch($church)->overlapping($start,$end)->activeBlocking()
            ->when($event,fn($q)=>$q->where('id','!=',$event->id))->lockForUpdate()->get();
        // Conference events may overlap each other; all of them block departmental time.
        foreach ($conflicts->where('is_conference_event',false) as $conflict) {
            $note='Evento de conferencia: '.$data['title'].'. Cambia la fecha u hora para evitar el conflicto.';
            if ($conflict->status === 'hold') {
                $conflict->update(['status'=>'cancelled','expires_at'=>null,'updated_by'=>$request->user()->id]);
                $conflict->recordAction($request->user(),'cancelled',$note);
            } else {
                $conflict->transitionReview('changes_requested',$request->user(),'changes_requested',$note);
            }
            DomainUpdate::forEvent('reviewed',$conflict,$request->user());
        }
        $values=$data+['church_id'=>$church,'department_id'=>null,'is_conference_event'=>true,
            'status'=>'locked','review_status'=>'approved','publish_to_feed'=>false,'published_at'=>null,
            'created_by'=>$request->user()->id];
        $values['start_at']=$start; $values['end_at']=$end; $values['updated_by']=$request->user()->id;
        if ($event) { unset($values['created_by']); $event->update($values); }
        else $event=Event::create($values);
        $event->recordAction($request->user(),'conference_saved');
        DomainUpdate::forEvent('updated',$event,$request->user());
        return $event->load(['histories.user']);
    }
}
