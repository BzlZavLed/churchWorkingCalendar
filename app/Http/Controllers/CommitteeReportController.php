<?php
namespace App\Http\Controllers;
use App\Models\{Committee,CommitteeReport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class CommitteeReportController extends Controller {
 private function scope(Request $request,Committee $committee): void {abort_unless($request->user()->church_id && $committee->church_id===$request->user()->church_id,404);}
 public function store(Request $request,Committee $committee){
  $this->scope($request,$committee);abort_unless($request->user()->hasCapability('committees.results'),403);
  $data=$request->validate(['title'=>['required','string','max:255'],'file'=>['required','file','max:20480','extensions:doc,docx,xlsx,csv,pdf','mimes:doc,docx,xlsx,csv,txt,pdf']], ['file.uploaded'=>'The server could not receive the file. Check the server upload limit or try a smaller file.', 'file.max'=>'The report must not exceed 20 MB.']);
  $file=$data['file'];$name=basename(str_replace('\\','/',$file->getClientOriginalName()));
  abort_if(mb_strlen($name)>255,422,'File name is too long.');
  $path=$file->store('committee-reports/'.$committee->church_id.'/'.$committee->id,'local');
  abort_unless($path,500,'Unable to store the report.');
  try {
   $report=$committee->reports()->create(['title'=>$data['title'],'original_name'=>$name,'path'=>$path,'mime_type'=>$file->getMimeType() ?: 'application/octet-stream','size'=>$file->getSize(),'uploaded_by'=>$request->user()->id]);
  } catch(\Throwable $error){Storage::disk('local')->delete($path);throw $error;}
  return response()->json($report->load('uploader:id,name'),201);
 }
 public function preview(Request $request,Committee $committee,CommitteeReport $report){
  $this->scope($request,$committee);abort_unless($report->committee_id===$committee->id,404);
  $disk=Storage::disk('local');abort_unless($disk->exists($report->path),404);
  $extension=strtolower(pathinfo($report->original_name,PATHINFO_EXTENSION));
  if($extension==='pdf')return response()->file($disk->path($report->path),['Content-Type'=>'application/pdf','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
  try {
   if(in_array($extension,['csv','xlsx'])){
    $reader=$extension==='csv'?new \PhpOffice\PhpSpreadsheet\Reader\Csv():new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setReadDataOnly(true);
    $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
     public function readCell(string $columnAddress,int $row,string $worksheetName=''): bool {return $row<=200 && \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($columnAddress)<=30;}
    });
    $book=$reader->load($disk->path($report->path));$sheets=[];
    foreach($book->getWorksheetIterator() as $sheet){
     if(count($sheets)>=10)break;
     $rows=[];foreach($sheet->getRowIterator(1,min(200,$sheet->getHighestDataRow())) as $row){
      $cells=[];$last=min(30,\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
      for($column=1;$column<=$last;$column++)$cells[]=(string)$sheet->getCell([$column,$row->getRowIndex()])->getValue();
      $rows[]=$cells;
     }
     $sheets[]=['name'=>$sheet->getTitle(),'rows'=>$rows];
    }
    $book->disconnectWorksheets();return response()->json(['type'=>'table','sheets'=>$sheets,'limited'=>true]);
   }
   if($extension==='docx'){
    $zip=new \ZipArchive();if($zip->open($disk->path($report->path))!==true)abort(422);
    $stat=$zip->statName('word/document.xml');if(!$stat || $stat['size']>5000000){$zip->close();abort(422);}
    $xml=$zip->getFromName('word/document.xml');$zip->close();
    $document=new \DOMDocument();if(!$document->loadXML($xml,LIBXML_NONET))abort(422);
    $xpath=new \DOMXPath($document);$xpath->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');$paragraphs=[];
    foreach($xpath->query('//w:p') as $paragraph){$text='';foreach($xpath->query('.//w:t',$paragraph) as $node)$text.=$node->textContent;$paragraphs[]=$text;}
    return response()->json(['type'=>'text','text'=>implode("\n",$paragraphs)]);
   }
  }catch(\Throwable $error){abort(422,'Unable to preview this document. Download the original file.');}
  return response()->json(['type'=>'unsupported']);
 }
 public function download(Request $request,Committee $committee,CommitteeReport $report){
  $this->scope($request,$committee);abort_unless($report->committee_id===$committee->id,404);
  abort_unless(Storage::disk('local')->exists($report->path),404);
  return Storage::disk('local')->download($report->path,$report->original_name,['Content-Type'=>'application/octet-stream','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
 }
}
