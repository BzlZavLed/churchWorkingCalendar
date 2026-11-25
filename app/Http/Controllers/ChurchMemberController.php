<?php
namespace App\Http\Controllers;
use App\Models\{Church,ChurchMember};
use App\Services\MemberRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChurchMemberController extends Controller {
    private function church(Request $request): int {
        abort_unless($request->user()?->church_id && !$request->user()->isSuperAdmin() && ($request->user()->hasCapability('members.manage') || ($request->isMethod('get') && !$request->user()->isSuperAdmin() && app(\App\Services\ModuleAccess::class)->read($request->user(),'members'))),403);
        return $request->user()->church_id;
    }
    public function index(Request $request, MemberRecords $records) {
        $church=$this->church($request);
        if ($request->query('view') === 'family-map') {
            return $this->familyMap($church);
        }
        $template=$records->current($church);
        $members=ChurchMember::where('church_id',$church)->orderBy('name')->get(['id','name','given_name','first_surname','second_surname','email','phone','missing_fields','data']);
        foreach($members as $member) {
            $member->setAttribute('dob',app(\App\Services\MemberExports::class)->dob($member,$template->fields));
            $member->setAttribute('family_id',$member->data['FamilyId']??null);
            $member->makeHidden('data');
        }
        return ['template'=>$template,'members'=>$members,'church'=>Church::findOrFail($church)->only(['id','name','conference_name','organization_code'])];
    }
    private function familyMap(int $church): array
    {
        $families = ChurchMember::where('church_id', $church)->orderBy('id')->get(['id', 'name', 'data'])
            ->groupBy(fn ($member) => !empty($member->data['FamilyId'])
                ? 'family:'.$member->data['FamilyId'] : 'member:'.$member->id);
        $locations = [];
        $directory = [];
        $missing = 0;
        foreach ($families as $id => $members) {
            if ($members->contains(fn ($member) => !in_array(
                strtolower(trim((string) ($member->data['IsConfidential'] ?? ''))),
                ['', '0', 'false', 'n', 'no'], true
            ))) {
                continue;
            }
            foreach ($members as $member) {
                $data = $member->data ?? [];
                $lat = $data['Latitude'] ?? null;
                $lng = $data['Longitude'] ?? null;
                if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90
                    || abs((float) $lng) > 180 || ((float) $lat === 0.0 && (float) $lng === 0.0)) {
                    continue;
                }
                $locations[$id] = [
                    'id' => $id,
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'address' => $this->memberAddress($data),
                    'names' => $members->pluck('name')->filter()->values(),
                ];
                break;
            }
            if (!isset($locations[$id])) {
                $missing++;
            }
            foreach ($members as $member) {
                $data = $member->data ?? [];
                $directory[] = [
                    'id' => $member->id,
                    'name' => $member->name,
                    'address' => $this->memberAddress($data),
                    'missing_address' => trim($data['AddressLine'] ?? '') === '',
                    'family_unmapped' => !isset($locations[$id]),
                ];
            }
        }
        return ['locations' => array_values($locations), 'missing' => $missing, 'directory' => $directory];
    }

    private function memberAddress(array $data): string
    {
        return implode(', ', array_filter(array_map(
            fn ($field) => trim($data[$field] ?? ''),
            ['AddressLine', 'OptionalLine', 'City', 'State', 'PostalCode', 'Country']
        ), fn ($part) => $part !== ''));
    }

    public function show(Request $request, MemberRecords $records, ChurchMember $member) {
        abort_unless($member->church_id===$this->church($request),404);
        $template=$records->current($member->church_id);
        // Reuse a previously captured DOB when upgrading an older member record.
        $data=$member->data ?? [];
        if(empty($data['BornOn'])) foreach($template->fields as $field) {
            if($field['type']==='dob' && !empty($data[$field['id']])) { $data['BornOn']=$data[$field['id']]; break; }
        }
        $currentChurch=Church::findOrFail($member->church_id);
        $data['OrgName']=$currentChurch->name;$data['OrgCode']=$currentChurch->organization_code;
        $member->data=$data;
        return ['member'=>$member,'template'=>$template,'church'=>Church::findOrFail($member->church_id)->only(['id','name','conference_name','organization_code'])];
    }
    public function destroy(Request $request, ChurchMember $member) {
        abort_unless($member->church_id===$this->church($request),404);
        return DB::transaction(function() use($member) {
            $member=ChurchMember::whereKey($member->id)->lockForUpdate()->firstOrFail();
            if(DB::table('committee_members')->where('church_member_id',$member->id)->exists() || DB::table('member_attendances')->where('church_member_id',$member->id)->exists()) {
                throw ValidationException::withMessages(['member'=>['This member has committee or attendance records and cannot be deleted.']]);
            }
            $member->delete();
            return response()->noContent();
        });
    }
    public function save(Request $request, MemberRecords $records, ?ChurchMember $member=null) {
        $church=$this->church($request);
        if($member) abort_unless($member->church_id===$church,404);
        $request->validate(['template_id'=>['required','integer'],'family_mode'=>['sometimes','in:existing,new,none'],'family_id'=>['nullable','required_if:family_mode,existing','string','max:500']]);
        return DB::transaction(function() use($request,$records,$member,$church) {
            Church::whereKey($church)->lockForUpdate()->firstOrFail();
            $template=$records->current($church);
            abort_unless($template && $template->id===$request->integer('template_id'),409,'Template changed. Reload before saving.');
            $data=$records->validate($template,$request->all(),array_intersect($member?->missing_data_authorization['fields'] ?? [],$member?->missing_fields ?? []));
            if($member) { $member->refresh(); $data['data']=array_replace($member->data ?? [],$data['data']); }
            if($request->has('family_mode')) $data['data']['FamilyId']=app(\App\Services\MemberFamilies::class)->resolve($church,$request->input('family_mode'),$request->input('family_id'));
            // Manual creation always starts a family unless joining an existing one.
            if(!$member && blank($data['data']['FamilyId']??null)) $data['data']['FamilyId']=app(\App\Services\MemberFamilies::class)->resolve($church,'new',null);
            $position=$data['data']['FamilyPosition']??null;
            if(filled($position) && $position!==($member?->data['FamilyPosition']??null) && !in_array($position,\App\Services\MemberDirectorySchema::FAMILY_POSITIONS,true))
                throw ValidationException::withMessages(['data.FamilyPosition'=>['Choose Primary, Spouse, Son or Daughter.']]);
            $data['data']['OrgName']=Church::findOrFail($church)->name;
            $data['source_person_id']=filled($data['data']['PersonId'] ?? null)?$data['data']['PersonId']:null;
            if($data['source_person_id']!==null && ChurchMember::where('church_id',$church)->where('source_person_id',$data['source_person_id'])->when($member,fn($q)=>$q->where('id','!=',$member->id))->exists()) throw ValidationException::withMessages(['data.PersonId'=>['This PersonId already belongs to a member in this church.']]);
            $member ??= new ChurchMember();
            $member->fill($data+['church_id'=>$church,'member_template_id'=>$template->id]); $member->save();
            return response()->json($member);
        });
    }
    public function download(Request $request, MemberRecords $records) {
        $template=$records->current($this->church($request));
        return response()->streamDownload(function() use($template,$records) {
            $stream=fopen('php://output','w'); fwrite($stream,"\xEF\xBB\xBF");
            $headers=array_map(fn($v)=>preg_match('/^[=+@\-\t\r\n]/u',$v)?"'".$v:$v,$records->headers($template));
            fputcsv($stream,$headers,',','"',''); fclose($stream);
        },'members-template-v'.$template->version.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function import(Request $request, MemberRecords $records) {
        $church=$this->church($request);
        $request->validate(['file'=>['required','file','max:2048'],'preview'=>['required','boolean'],'editable'=>['sometimes','boolean']]);
        return DB::transaction(function() use($request,$church,$records) {
            Church::whereKey($church)->lockForUpdate()->firstOrFail();
            $template=$records->current($church);
            $content=file_get_contents($request->file('file')->getRealPath());
            if(!mb_check_encoding($content,'UTF-8')) $content=mb_convert_encoding($content,'UTF-8','Windows-1252');
            $handle=fopen('php://temp','r+'); fwrite($handle,$content); rewind($handle);
            try {
                $headers=fgetcsv($handle,0,',','"','');
                if($headers) $headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0]);
                $expected=$records->headers($template);
                if(!$headers || count($headers)!==count(array_unique($headers)) || array_diff($headers,$expected) || array_diff(['FirstName','Email','BornOn'],$headers) || !array_intersect(['MobilePhone','FamilyPhone','WorkPhone'],$headers)) throw ValidationException::withMessages(['file'=>['Use the member directory CSV headers. FirstName, Email, BornOn and at least one phone column are required.']]);
                $raw=[];$line=1;
                while(($row=fgetcsv($handle,0,',','"',''))!==false){
                    $line++;if($line>1001)throw ValidationException::withMessages(['file'=>['Maximum 1,000 rows per import.']]);
                    if(!array_filter($row,fn($v)=>$v!==null && trim($v)!==''))continue;
                    if(count($row)!==count($headers))throw ValidationException::withMessages(['row_'.$line=>['Column count does not match.']]);
                    $raw[]=['line'=>$line,'values'=>array_combine($headers,array_map(fn($v)=>trim((string)$v),$row))];
                }
                if(!$raw)throw ValidationException::withMessages(['file'=>['No member rows found.']]);
                $analyzer=app(\App\Services\MemberImportReview::class);$review=$analyzer->analyze($template,$raw);
                $rows=[];$errors=[];
                foreach($review as $row){if($row['errors'])$errors['row_'.$row['line']]=collect($row['errors'])->flatten()->all();elseif($row['duplicate_matches'])$errors['row_'.$row['line']]=['Existing member detected. Use the review screen to update or skip.'];else $rows[]=$row['payload'];}
                if($request->boolean('preview') && $request->boolean('editable')) {
                    return response()->json($analyzer->response($review))->header('Cache-Control','no-store');
                }
                if($errors) throw ValidationException::withMessages($errors);
                if(!$rows) throw ValidationException::withMessages(['file'=>['No member rows found.']]);
                if($request->boolean('preview')) return ['count'=>count($rows),'sample'=>array_slice($rows,0,5)];
                foreach($rows as $data) ChurchMember::create($data+['church_id'=>$church,'member_template_id'=>$template->id]);
                return ['created'=>count($rows)];
            } finally { fclose($handle); }
        });
    }
}
