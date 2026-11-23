<?php
namespace App\Http\Controllers;

use App\Models\ChurchMember;
use App\Services\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberAttendanceController extends Controller
{
    private function church(Request $request): int
    {
        if($request->attributes->has('attendance_church'))return $request->attributes->get('attendance_church');
        $user = $request->user();
        abort_unless($user?->church_id && !$user->isSuperAdmin() && app(ModuleAccess::class)->read($user, 'greeting'), 403);
        return $user->church_id;
    }

    public function surnames(Request $request)
    {
        $church = $this->church($request);
        $data = $request->validate(['q' => ['required','string','min:2','max:80']]);
        // Escape LIKE metacharacters: autocomplete is a literal prefix search.
        $prefix = addcslashes(mb_strtolower(trim($data['q'])), '\\%_');
        return ['surnames' => ChurchMember::where('church_id', $church)
            ->whereRaw('LOWER(first_surname) LIKE ?', [$prefix.'%'])
            ->selectRaw('LOWER(first_surname) as surname_key, MIN(first_surname) as surname')
            ->groupByRaw('LOWER(first_surname)')->orderBy('surname')->limit(20)->get()->pluck('surname')];
    }

    public function family(Request $request)
    {
        $church = $this->church($request);
        $data = $request->validate(['surname'=>['required','string','max:80'], 'timezone'=>['required','timezone']]);
        $date = now($data['timezone'])->toDateString();
        $members = ChurchMember::where('church_id',$church)->whereRaw('LOWER(first_surname) = ?', [mb_strtolower(trim($data['surname']))])
            ->orderBy('name')->get();
        $familyIds=$members->map(fn($m)=>$m->data['FamilyId']??null)->filter(fn($id)=>filled($id))->unique()->values()->all();
        if($familyIds) $members=ChurchMember::where('church_id',$church)->where(function($q)use($members,$familyIds){
            $q->whereIn('id',$members->pluck('id'))->orWhereIn('data->FamilyId',$familyIds);
        })->orderBy('name')->get();
        $present = DB::table('member_attendances')->where('church_id',$church)->where('attendance_date',$date)->pluck('church_member_id')->flip();
        return ['date'=>$date,'token'=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode(['church'=>$church,'ids'=>$members->pluck('id')->all(),'date'=>$date,'timezone'=>$data['timezone'],'expires'=>now()->addMinutes(15)->timestamp])),'members'=>$members->map(fn($member)=>['id'=>$member->id,'name'=>$member->name,'family_id'=>$member->data['FamilyId']??null,'present'=>isset($present[$member->id])])];
    }

    public function publicSurnames(Request $r, \App\Models\Church $church) {$r->attributes->set('attendance_church',$church->id);return $this->surnames($r);}
    public function publicFamily(Request $r, \App\Models\Church $church) {$r->attributes->set('attendance_church',$church->id);return $this->family($r);}
    public function publicSave(Request $r, \App\Models\Church $church) {
        $r->attributes->set('attendance_church',$church->id);
        return $this->batch($r);
    }
    public function batch(Request $r) {
        $church=$this->church($r);$public=$r->attributes->has('attendance_church');
        if(!$public)abort_unless($r->user()->hasCapability('greeting.attendance'),403);
        $data=$r->validate(['member_ids'=>['required','array','min:1','max:100'],'member_ids.*'=>['integer','distinct'],'token'=>['required','string']]);
        try{$token=json_decode(\Illuminate\Support\Facades\Crypt::decryptString($data['token']),true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){abort(422,'Search the family again.');}
        abort_unless(($token['church']??null)===$church && ($token['expires']??0)>=now()->timestamp && !array_diff($data['member_ids'],$token['ids']??[]),422,'Search the family again.');
        abort_unless($token['date']===now($token['timezone'])->toDateString(),409,'The day changed. Search again.');
        return DB::transaction(function()use($r,$church,$data,$token,$public){
            $members=ChurchMember::where('church_id',$church)->whereIn('id',$data['member_ids'])->orderBy('id')->lockForUpdate()->get();
            abort_unless($members->count()===count($data['member_ids']),404);
            foreach($members as $member)DB::table('member_attendances')->insertOrIgnore(['church_id'=>$church,'church_member_id'=>$member->id,'attendance_date'=>$token['date'],'recorded_by'=>$public?null:$r->user()->id,'created_at'=>now(),'updated_at'=>now()]);
            return ['present'=>true,'date'=>$token['date']];
        });
    }

    public function save(Request $request)
    {
        $church = $this->church($request);
        abort_unless($request->user()->hasCapability('greeting.attendance'),403);
        $data = $request->validate(['member_id'=>['required','integer'], 'present'=>['required','boolean'], 'timezone'=>['required','timezone'], 'date'=>['required','date_format:Y-m-d']]);
        $date = now($data['timezone'])->toDateString();
        abort_unless($date === $data['date'],409,'The day changed. Search the family again.');
        return DB::transaction(function() use($request,$church,$data,$date) {
            ChurchMember::where('church_id',$church)->whereKey($data['member_id'])->lockForUpdate()->firstOrFail();
            $query = DB::table('member_attendances')->where('church_member_id',$data['member_id'])->where('attendance_date',$date);
            if ($data['present']) {
                if (!$query->exists()) DB::table('member_attendances')->insert(['church_id'=>$church,'church_member_id'=>$data['member_id'],'attendance_date'=>$date,'recorded_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
            } else $query->delete();
            return ['present'=>$data['present'],'date'=>$date];
        });
    }
}
