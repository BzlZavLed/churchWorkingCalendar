<?php
namespace App\Http\Controllers;
use App\Models\{Church,ChurchMember,MemberTemplate};
use App\Services\MemberRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChurchMemberController extends Controller {
    private function church(Request $request): int {
        abort_unless($request->user()?->church_id && !$request->user()->isSuperAdmin() && ($request->user()->hasCapability('members.manage') || ($request->isMethod('get') && !$request->user()->isSuperAdmin() && app(\App\Services\ModuleAccess::class)->read($request->user(),'members'))),403);
        return $request->user()->church_id;
    }
    public function index(Request $request, MemberRecords $records) {
        $church=$this->church($request);
        return ['template'=>$records->current($church), 'members'=>ChurchMember::where('church_id',$church)->orderBy('name')->get(),
            'historical_fields'=>MemberTemplate::where('church_id',$church)->orderBy('version')->get()->flatMap(fn($t)=>$t->fields)->keyBy('id')->values()];
    }
    public function template(Request $request, MemberRecords $records) {
        $church=$this->church($request);
        $data=$request->validate([
            'base_version'=>['required','integer','min:0'],'fields'=>['present','array','max:40'],
            'fields.*'=>['array:id,label,type,required,options'], 'fields.*.id'=>['required','uuid','distinct'],
            'fields.*.label'=>['required','string','max:150'], 'fields.*.type'=>['required','in:text,textarea,email,phone,dob,date,number,checkbox,select'],
            'fields.*.required'=>['required','boolean'], 'fields.*.options'=>['present','array','max:40'], 'fields.*.options.*'=>['required','string','max:150'],
        ]);
        return DB::transaction(function() use($church,$data,$records) {
            Church::whereKey($church)->lockForUpdate()->firstOrFail();
            $current=$records->current($church);
            abort_unless(($current?->version ?? 0)===$data['base_version'],409,'Template changed. Reload before saving.');
            $previous=MemberTemplate::where('church_id',$church)->get()->flatMap(fn($t)=>$t->fields)->keyBy('id');
            $labels = ['name','email','phone'];
            foreach($data['fields'] as $field) {
                $label = mb_strtolower(trim($field['label']));
                if (!$label || in_array($label,$labels,true)) throw ValidationException::withMessages(['fields'=>['Each field needs a unique label, different from Name, Email and Phone.']]);
                $labels[] = $label;
                if(isset($previous[$field['id']]) && $previous[$field['id']]['type']!==$field['type']) throw ValidationException::withMessages(['fields'=>['Existing field types cannot change. Create a new field.']]);
                if($field['type']==='select' && (!$field['options'] || count(array_unique($field['options']))!==count($field['options']))) throw ValidationException::withMessages(['fields'=>['Dropdown choices must be nonempty and unique.']]);
            }
            return response()->json(MemberTemplate::create(['church_id'=>$church,'version'=>($current?->version ?? 0)+1,'fields'=>$data['fields']]),201);
        });
    }
    public function save(Request $request, MemberRecords $records, ?ChurchMember $member=null) {
        $church=$this->church($request);
        if($member) abort_unless($member->church_id===$church,404);
        $request->validate(['template_id'=>['required','integer']]);
        return DB::transaction(function() use($request,$records,$member,$church) {
            Church::whereKey($church)->lockForUpdate()->firstOrFail();
            $template=$records->current($church);
            abort_unless($template && $template->id===$request->integer('template_id'),409,'Template changed. Reload before saving.');
            $data=$records->validate($template,$request->all());
            if(ChurchMember::where('church_id',$church)->where('email',$data['email'])->when($member,fn($q)=>$q->where('id','!=',$member->id))->exists()) throw ValidationException::withMessages(['email'=>['This email already belongs to a member in this church.']]);
            if($member) { $member->refresh(); $data['data']=array_replace($member->data ?? [],$data['data']); }
            $member ??= new ChurchMember();
            $member->fill($data+['church_id'=>$church,'member_template_id'=>$template->id]); $member->save();
            return response()->json($member);
        });
    }
    public function download(Request $request, MemberRecords $records) {
        $template=$records->current($this->church($request));
        abort_unless($template,422,'Save a template first.');
        return response()->streamDownload(function() use($template,$records) {
            $stream=fopen('php://output','w'); fwrite($stream,"\xEF\xBB\xBF");
            $headers=array_map(fn($v)=>preg_match('/^[=+@\-\t\r\n]/u',$v)?"'".$v:$v,$records->headers($template));
            fputcsv($stream,$headers,',','"',''); fclose($stream);
        },'members-template-v'.$template->version.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function import(Request $request, MemberRecords $records) {
        $church=$this->church($request);
        $request->validate(['file'=>['required','file','max:2048'],'preview'=>['required','boolean']]);
        return DB::transaction(function() use($request,$church,$records) {
            Church::whereKey($church)->lockForUpdate()->firstOrFail();
            $template=$records->current($church); abort_unless($template,422,'Save a template first.');
            $handle=fopen($request->file('file')->getRealPath(),'r');
            try {
                $headers=fgetcsv($handle,0,',','"','');
                if($headers) $headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0]);
                $expected=array_map(fn($v)=>preg_match('/^[=+@\-\t\r\n]/u',$v)?"'".$v:$v,$records->headers($template));
                if($headers!==$expected) throw ValidationException::withMessages(['file'=>['The CSV columns do not match the saved template. Download a new copy.']]);
                $rows=[]; $errors=[]; $emails=[]; $line=1;
                $existing=ChurchMember::where('church_id',$church)->pluck('email')->flip();
                while(($row=fgetcsv($handle,0,',','"',''))!==false) {
                    $line++; if($line>1001) throw ValidationException::withMessages(['file'=>['Maximum 1,000 rows per import.']]);
                    if(!array_filter($row,fn($v)=>$v!==null && trim($v)!=='')) continue;
                    if(count($row)!==count($expected)) { $errors['row_'.$line]=['Column count does not match.']; continue; }
                    $values=array_map(fn($v)=>trim((string)$v),$row); $custom=[];
                    foreach($template->fields as $i=>$field) {
                        $value=$values[$i+3];
                        if($field['type']==='checkbox' && $value!=='') $value=match(strtolower($value)) {'true','1','yes','si','sí'=>true,'false','0','no'=>false,default=>$value};
                        $custom[$field['id']]=$value===''?null:$value;
                    }
                    try {
                        $data=$records->validate($template,['name'=>$values[0],'email'=>$values[1],'phone'=>$values[2],'data'=>$custom]);
                        if(isset($existing[$data['email']]) || isset($emails[$data['email']])) throw ValidationException::withMessages(['email'=>['Duplicate email in this church or file.']]);
                        $emails[$data['email']]=true; $rows[]=$data;
                    } catch(ValidationException $e) { $errors['row_'.$line]=collect($e->errors())->flatten()->all(); }
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
