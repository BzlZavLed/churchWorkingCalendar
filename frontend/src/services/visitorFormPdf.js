import {jsPDF} from 'jspdf'
import {visitorImage} from './visitorFormDesign'
import {decodeImage} from './qrPoster'
import {PDFDocument} from 'pdf-lib'
export async function exportVisitorForm({layout,fields,churchId,churchName,logoUrl,copiesPerPage=1}){
 const doc=new jsPDF({unit:'mm',format:[210,3000]}),left=15,width=180,bottom=2980
 let y=15
 const accent=layout?.accent||'#123c70'
 const space=h=>{if(y+h>bottom){doc.addPage();y=15}}
 const text=(value,size=11)=>{doc.setFontSize(size);const lines=doc.splitTextToSize(value||'',width);for(const line of lines){space(size*.4+2);doc.text(line,left,y+size*.35);y+=size*.4+2}}
 async function images(position){
  let row=[],rowWidth=0
  async function flush(){
   if(!row.length)return
   const height=Math.max(...row.map(x=>x.h));space(height+5)
   let x=left+(width-rowWidth)/2
   for(const item of row){const bx=x+(item.block.alignment==='right'?item.w-item.iw:item.block.alignment==='center'?(item.w-item.iw)/2:0);doc.addImage(item.src,'PNG',bx,y,item.iw,item.h);x+=item.w+3}
   y+=height+5;row=[];rowWidth=0
  }
  for(const block of layout?.images?.filter(b=>b.position===position)||[]){
   const src=await visitorImage(block,churchId,logoUrl);if(!src)continue
   const image=await decodeImage(src),w=width*block.width/100
   const ratio=Math.min(w/image.width,24/image.height),iw=image.width*ratio,h=image.height*ratio
   if(rowWidth+w+(row.length?3:0)>width)await flush()
   rowWidth+=w+(row.length?3:0);row.push({block,src,w,iw,h})
  }
  await flush()
 }
 doc.setTextColor(accent)
 await images('header')
 text(churchName||'',13);text(layout?.title||'Registro de visitantes',19)
 if(layout?.introduction)text(layout.introduction,10)
 y+=4
 const all=[{type:'text',label:'Nombre',span:12,required:true},{type:'text',label:'Teléfono',span:6},{type:'text',label:'Correo electrónico',span:6},...fields]
 let row=[],used=0
 const drawRow=()=>{
  if(!row.length)return
  const prepared=row.map(f=>{
   const w=width*(f.span||12)/12-4
   doc.setFontSize(10)
   const label=doc.splitTextToSize(f.label+(f.required?' *':''),w-(f.type==='checkbox'?7:0))
   const options=f.type==='select'?doc.splitTextToSize(f.options.map(o=>'[ ] '+o).join('   '),w):[]
   return {f,w,label,options,h:label.length*4.5+(options.length?options.length*4.5+5:f.type==='label'||f.type==='checkbox'?5:f.type==='textarea'?18:9)}
  })
  const h=Math.max(...prepared.map(p=>p.h));space(h)
  let x=left;doc.setDrawColor(accent);doc.setTextColor(accent)
  for(const p of prepared){
   const {f,w,label,options}=p
   if(f.type==='checkbox')doc.rect(x,y+1,3.5,3.5)
   doc.text(label,x+(f.type==='checkbox'?7:0),y+4)
   if(options.length)doc.text(options,x,y+label.length*4.5+5)
   else if(!['checkbox','label'].includes(f.type)){
    const ly=y+label.length*4.5+5
    doc.line(x,ly,x+w,ly)
    if(f.type==='textarea')doc.line(x,ly+8,x+w,ly+8)
   }
   x+=width*(f.span||12)/12
  }
  y+=h;row=[];used=0
 }
 for(const f of all){const span=f.span||12;if(used+span>12)drawRow();row.push(f);used+=span;if(used===12)drawRow()}
 drawRow()
 await images('after')
 text('[ ] Soy miembro de la Iglesia Adventista del Séptimo Día.',10)
 text('[ ] Autorizo contacto por SMS.     [ ] Autorizo contacto por correo electrónico.',10)
 await images('footer')
 if(doc.getNumberOfPages()!==1)throw new Error('El formulario es demasiado largo para imprimirlo en una tarjeta.')
 const source=await PDFDocument.load(doc.output('arraybuffer'))
 const output=await PDFDocument.create()
 const sourcePage=source.getPage(0), sourceHeight=sourcePage.getHeight(), usedHeight=Math.min(sourceHeight,(y+10)*72/25.4)
 const embedded=await output.embedPage(sourcePage,{left:0,bottom:sourceHeight-usedHeight,right:sourcePage.getWidth(),top:sourceHeight})
 const page=output.addPage([595.28,841.89]), margin=18, gap=12
 const count=[1,2,4].includes(copiesPerPage)?copiesPerPage:1, columns=count===4?2:1, rows=count/columns
 const cellWidth=(page.getWidth()-2*margin-(columns-1)*gap)/columns, cellHeight=(page.getHeight()-2*margin-(rows-1)*gap)/rows
 const scale=Math.min(cellWidth/embedded.width,cellHeight/embedded.height)
 for(let i=0;i<count;i++){
  const col=i%columns,row=Math.floor(i/columns), w=embedded.width*scale,h=embedded.height*scale
  page.drawPage(embedded,{x:margin+col*(cellWidth+gap)+(cellWidth-w)/2,y:page.getHeight()-margin-(row+1)*cellHeight-row*gap+(cellHeight-h)/2,width:w,height:h})
 }
 const blob=new Blob([await output.save()],{type:'application/pdf'}),url=URL.createObjectURL(blob),link=document.createElement('a')
 link.href=url;link.download='formulario-de-visitas.pdf';link.click();setTimeout(()=>URL.revokeObjectURL(url),60000)
}
