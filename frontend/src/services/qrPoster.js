import QRCode from 'qrcode'
import {jsPDF} from 'jspdf'
export function pageSize(d){const size=d.format==='letter'?[215.9,279.4]:[210,297];return d.landscape?size.reverse():size}
export function posterTemplate(kind,name,style='branded'){
 const element=(type,x,y,width,text='',size=22)=>({type,x,y,width,text,size,color:'#173d35',align:'center',centerLogo:false})
 return {format:'a4',landscape:false,sdaDesign:false,sdaColor:'#8cc5b7',background:'#ffffff',elements:[
 ...(style==='branded'?[element('logo',40,4,20)]:[]),
 element('text',10,style==='branded'?23:8,80,name,22),
 element('text',10,style==='branded'?32:20,80,kind==='attendance'?'Registra tu asistencia':'Registra tu visita',28),
 element('qr',style==='large'?15:25,40,style==='large'?70:50),
 element('text',10,style==='large'?92:82,80,'Escanea con la cámara de tu teléfono',14)
 ]}
}
export async function decodeImage(src){const img=new Image();img.src=src;await img.decode();return img}
export async function qrImage(url){return decodeImage(await QRCode.toDataURL(url,{width:1600,margin:4,errorCorrectionLevel:'H'}))}
export function drawPoster(canvas,d,qr,originalLogo,cleanLogo=null){
 const [w,h]=pageSize(d),scale=5;canvas.width=Math.round(w*scale);canvas.height=Math.round(h*scale)
 const ctx=canvas.getContext('2d');ctx.fillStyle=d.background;ctx.fillRect(0,0,canvas.width,canvas.height)
 if(d.sdaDesign){ctx.fillStyle=d.sdaColor||'#8cc5b7';ctx.fillRect(canvas.width*6/7,0,canvas.width/7,canvas.height)}
 const boxes=[]
 for(const e of d.elements){
  const logo=e.removeWhiteBackground&&cleanLogo?cleanLogo:originalLogo
  let width=w*Math.max(5,Math.min(100,Number(e.width)||5))/100,height=width
  if(e.type==='logo')height=logo?Math.min(width*logo.height/logo.width,h*.3):width*.4
  const font=(Number(e.size)||22)/2.83465,lines=[]
  if(e.type==='text'){
   ctx.font=`${font*scale}px Arial`
   for(const paragraph of (e.text||'').split('\n')){
    let line=''
    for(const word of paragraph.split(' ')){const candidate=line?line+' '+word:word;if(ctx.measureText(candidate).width>width*scale&&line){lines.push(line);line=word}else line=candidate}
    lines.push(line)
   }
   height=Math.max(font*1.25,lines.length*font*1.25)
  }
  if(e.type==='qr'){width=Math.max(40,Math.min(width,w,h));height=width}
  width=Math.min(width,w);height=Math.min(height,h)
  const x=Math.max(0,Math.min(w-width,w*(Number(e.x)||0)/100)),y=Math.max(0,Math.min(h-height,h*(Number(e.y)||0)/100))
  boxes.push({x:x/w*100,y:y/h*100,width:width/w*100,height:height/h*100})
  ctx.save();ctx.beginPath();ctx.rect(x*scale,y*scale,width*scale,height*scale);ctx.clip()
  if(e.type==='qr'){
   ctx.drawImage(qr,x*scale,y*scale,width*scale,width*scale)
   if(e.centerLogo&&logo){
    const box=width*.16,cx=x+width/2,cy=y+width/2
    ctx.fillStyle='#fff';ctx.fillRect((cx-box/2)*scale,(cy-box/2)*scale,box*scale,box*scale)
    const ratio=Math.min(box*.8/logo.width,box*.8/logo.height)
    ctx.drawImage(logo,(cx-logo.width*ratio/2)*scale,(cy-logo.height*ratio/2)*scale,logo.width*ratio*scale,logo.height*ratio*scale)
   }
  }else if(e.type==='logo'&&logo){
   const ratio=Math.min(width/logo.width,height/logo.height)
   ctx.drawImage(logo,(x+(width-logo.width*ratio)/2)*scale,y*scale,logo.width*ratio*scale,logo.height*ratio*scale)
  }else if(e.type==='text'){
   ctx.fillStyle=e.color;ctx.textAlign=e.align;ctx.textBaseline='top'
   const tx=x+(e.align==='center'?width/2:e.align==='right'?width:0)
   lines.forEach((line,i)=>ctx.fillText(line,tx*scale,(y+i*font*1.25)*scale,width*scale))
  }
  ctx.restore()
 }
 return boxes
}
export function downloadPoster(canvas,d,kind){
 const [w,h]=pageSize(d),pdf=new jsPDF({unit:'mm',format:d.format,orientation:d.landscape?'landscape':'portrait'})
 pdf.addImage(canvas.toDataURL('image/png'),'PNG',0,0,w,h)
 pdf.save(kind==='attendance'?'asistencia-qr.pdf':'visitas-qr.pdf')
}
