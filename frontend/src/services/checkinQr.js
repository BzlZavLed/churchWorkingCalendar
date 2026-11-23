import {churchLogoImage} from './churchLogoImage'
import QRCode from 'qrcode'
import {jsPDF} from 'jspdf'
import {apiClient} from './authApi'
export async function exportCheckinQr(churchId, attendance, es=true){
 const {data}=await apiClient.get('/public/churches/'+churchId+'/visit')
 const url=window.location.origin+(attendance?'/attendance/':'/visit/')+churchId
 const doc=new jsPDF({unit:'mm',format:'a4'})
 let y=20
 if(data.logo_url){
  const image=await churchLogoImage(churchId,data.logo_url)
  const scale=Math.min(60/image.width,30/image.height)
  const w=image.width*scale,h=image.height*scale
  doc.addImage(image.png,'PNG',(210-w)/2,y,w,h);y+=h+8
 }
 doc.setFontSize(18);const names=doc.splitTextToSize(data.name||'',170);doc.text(names,105,y,{align:'center'});y+=names.length*8+8
 doc.setFontSize(22);doc.text(attendance?(es?'Registra tu asistencia':'Member check-in'):(es?'Registra tu visita':'Visitor registration'),105,y,{align:'center'});y+=12
 doc.setFontSize(12);doc.text(es?'Escanea con la cámara de tu teléfono':'Scan with your phone camera',105,y,{align:'center'});y+=8
 const qr=await QRCode.toDataURL(url,{width:1200,margin:4,errorCorrectionLevel:'M'})
 doc.addImage(qr,'PNG',50,y,110,110);y+=120
 doc.setFontSize(10);doc.text(doc.splitTextToSize(url,170),105,y,{align:'center'})
 doc.save(attendance?'asistencia-qr.pdf':'visitas-qr.pdf')
}
