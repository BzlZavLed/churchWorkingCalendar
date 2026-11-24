import referenceUrl from '../assets/visitor-card-reference.jpeg'
import {apiClient} from './authApi'
import {churchLogoImage} from './churchLogoImage'
import {decodeImage} from './qrPoster'
const cache=new Map()
const crops={welcome:[810,0,726,245],blessing:[1255,595,265,205],footer:[0,900,1536,124]}
export function blankVisitorLayout(){return {title:'¡Bienvenido!',introduction:'Nos alegra que nos visites. Completa tus datos para registrar tu visita.',accent:'#123c70',images:[]}}
export function welcomeVisitorLayout(){
 const block=(source,position,width,alt)=>({id:crypto.randomUUID(),source,asset:null,position,alignment:'center',width,alt})
 return {...blankVisitorLayout(),images:[block('church','header',45,'Logo de la iglesia'),block('welcome','header',50,'¡Bienvenido! Nos alegra que nos visite.'),block('blessing','after',25,'¡Dios le bendiga!'),block('footer','footer',100,'Adoramos · Crecemos · Amamos · Servimos')]}
}
export function welcomeVisitorFields(){
 const f=(type,label,span=6,options=[])=>({id:crypto.randomUUID(),type,label,required:false,options,span})
 return [f('select','Tratamiento',6,['Sr.','Sra.','Srta.']),f('date','Fecha de visita'),f('text','Ciudad',4),f('text','Estado',4),f('text','Código postal',4),
 ...['Primera vez que visito','Nuevo en el vecindario','Me gustaría pertenecer a una iglesia','Me gustaría saber más sobre la iglesia','Me gustaría que el pastor me llame'].map(label=>f('checkbox',label)),
 f('number','Hijos en edad escolar o menores'),f('select','Edad',6,['Menos de 20','20–29','30–39','40–49','50–59','60–69','70 o más']),f('text','Vine invitado por'),f('text','Miembro de qué iglesia',12)]
}
export async function visitorImage(block,churchId,logoUrl){
 const key=JSON.stringify([block.source,block.asset,churchId,block.source==='church'?logoUrl:null])
 if(cache.has(key))return cache.get(key)
 const promise=(async()=>{
  if(block.source==='church')return logoUrl?(await churchLogoImage(churchId,logoUrl)).png:null
  if(block.source==='upload'){
   const {data}=await apiClient.get('/public/churches/'+churchId+'/visitor-form-images/'+block.asset,{responseType:'blob'})
   const url=URL.createObjectURL(data)
   try{const img=await decodeImage(url);return raster(img)}finally{URL.revokeObjectURL(url)}
  }
  const img=await decodeImage(referenceUrl),crop=crops[block.source]
  if(!crop)throw new Error('Unknown image section')
  const canvas=document.createElement('canvas');canvas.width=crop[2];canvas.height=crop[3]
  canvas.getContext('2d').drawImage(img,...crop,0,0,crop[2],crop[3])
  return canvas.toDataURL('image/png')
 })()
 cache.set(key,promise)
 try{return await promise}catch(e){cache.delete(key);throw e}
}
function raster(img){const c=document.createElement('canvas');c.width=img.width;c.height=img.height;c.getContext('2d').drawImage(img,0,0);return c.toDataURL('image/png')}
