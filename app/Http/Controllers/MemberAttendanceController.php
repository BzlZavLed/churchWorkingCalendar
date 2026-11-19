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
            ->orderBy('name')->get(['id','name']);
        $present = DB::table('member_attendances')->where('church_id',$church)->where('attendance_date',$date)->pluck('church_member_id')->flip();
        return ['date'=>$date,'members'=>$members->map(fn($member)=>['id'=>$member->id,'name'=>$member->name,'present'=>isset($present[$member->id])])];
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
