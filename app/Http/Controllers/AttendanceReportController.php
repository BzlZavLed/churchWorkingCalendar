<?php
namespace App\Http\Controllers;

use App\Models\ChurchMember;
use App\Services\ModuleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceReportController extends Controller
{
    public function index(Request $request)
    {
        $user=$request->user();
        $access=app(ModuleAccess::class);
        abort_unless($user?->church_id && !$user->isSuperAdmin() && $access->read($user,'reports') && $access->read($user,'members'),403);
        $data=$request->validate(['from'=>['required','date_format:Y-m-d'],'to'=>['required','date_format:Y-m-d','after_or_equal:from'],'member_id'=>['nullable','integer'],'service_id'=>['nullable','integer'],'session_id'=>['nullable','integer'],'legacy'=>['sometimes','boolean']]);
        $from=CarbonImmutable::parse($data['from']);$to=CarbonImmutable::parse($data['to']);
        if($from->diffInDays($to)>365) throw ValidationException::withMessages(['to'=>['Select a maximum of 366 days.']]);
        $members=ChurchMember::where('church_id',$user->church_id)->orderBy('name')->get(['id','name']);
        if(!empty($data['member_id'])) abort_unless($members->contains('id',$data['member_id']),404);
        $services=\App\Models\AttendanceService::where('church_id',$user->church_id)->orderBy('name')->get(['id','name']);
        $sessions=\App\Models\AttendanceSession::where('church_id',$user->church_id)->whereBetween('service_date',[$data['from'],$data['to']])->orderByDesc('service_date')->get(['id','name','service_date','attendance_service_id']);
        if(!empty($data['service_id']))abort_unless($services->contains('id',$data['service_id']),404);
        if(!empty($data['session_id']))abort_unless($sessions->contains('id',$data['session_id']),404);
        $rows=DB::table('member_attendances')->join('church_members','church_members.id','=','member_attendances.church_member_id')
            ->leftJoin('attendance_sessions','attendance_sessions.id','=','member_attendances.attendance_session_id')
            ->where('member_attendances.church_id',$user->church_id)->where('church_members.church_id',$user->church_id)
            ->whereBetween('attendance_date',[$data['from'],$data['to']])
            ->when($data['member_id']??null,fn($q,$id)=>$q->where('church_member_id',$id))
            ->when($data['service_id']??null,fn($q,$id)=>$q->where('attendance_sessions.attendance_service_id',$id))
            ->when($data['session_id']??null,fn($q,$id)=>$q->where('attendance_sessions.id',$id))
            ->when($data['legacy']??false,fn($q)=>$q->whereNull('attendance_session_id'))
            ->orderByDesc('attendance_date')->orderBy('church_members.name')
            ->get(['church_member_id as member_id','church_members.name','attendance_date as date','attendance_session_id as session_id','attendance_sessions.name as service_name','attendance_sessions.attendance_service_id as service_id']);
        $counts=$rows->countBy('date');$series=[];
        for($day=$from;$day->lte($to);$day=$day->addDay()) $series[]=['date'=>$day->toDateString(),'count'=>$counts[$day->toDateString()]??0];
        return ['services'=>$services,'sessions'=>$sessions,'members'=>$members,'series'=>$series,'records'=>$rows,'summary'=>['check_ins'=>$rows->count(),'members'=>$rows->pluck('member_id')->unique()->count(),'recorded_days'=>$counts->count()]];
    }
}
