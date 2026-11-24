<template>
 <div class="qr-poster-summary">
 <figure class="qr-poster-thumbnail">
 <p v-if="loading" role="status">{{ tr('Cargando vista previa…','Loading preview…') }}</p>
 <p v-else-if="error" role="alert">{{ error }} <button class="btn btn-outline-secondary" @click="load">{{ tr('Reintentar','Retry') }}</button></p>
 <canvas v-show="!loading&&!error" ref="canvas" role="img" :aria-label="tr('Vista previa del PDF QR','QR PDF preview')" />
 </figure>
 <div class="qr-poster-summary-actions"><strong>{{ tr('Tu cartel QR','Your QR poster') }}</strong><p>{{ saved?tr('Vista previa del diseño guardado.','Preview of your saved design.'):tr('Diseño inicial. Personalízalo antes de imprimir.','Default design. Customize it before printing.') }}</p><p v-if="logoWarning" role="status">{{ logoWarning }}</p><slot /></div>
 </div>
</template>
<script setup>
import {ref,watch,nextTick,onBeforeUnmount} from 'vue'
import {apiClient} from '../services/authApi'
import {churchLogoImage} from '../services/churchLogoImage'
import {removeEdgeWhite} from '../utils/removeWhiteBackground'
import {posterTemplate,decodeImage,qrImage,drawPoster} from '../services/qrPoster'
import {useUiStore} from '../stores/uiStore'
const props=defineProps({churchId:Number,kind:String,revision:{type:Number,default:0}})
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const canvas=ref(null),loading=ref(true),error=ref(''),saved=ref(false),logoWarning=ref('')
let version=0
async function load(){
 const request=++version;loading.value=true;error.value='';logoWarning.value=''
 try{
  const [{data:stored},{data:church}]=await Promise.all([apiClient.get('/qr-designs/'+props.kind),apiClient.get('/public/churches/'+props.churchId+'/visit')])
  const design=stored.design||posterTemplate(props.kind,church.name)
  const qr=await qrImage(window.location.origin+(props.kind==='attendance'?'/attendance/':'/visit/')+props.churchId)
  let logo=null,clean=null
  if(church.logo_url){try{
   logo=await decodeImage((await churchLogoImage(props.churchId,church.logo_url)).png)
   if(design.elements.some(e=>e.removeWhiteBackground)){
    const surface=document.createElement('canvas');surface.width=logo.width;surface.height=logo.height
    const ctx=surface.getContext('2d');ctx.drawImage(logo,0,0);const pixels=ctx.getImageData(0,0,surface.width,surface.height)
    if(removeEdgeWhite(pixels.data,surface.width,surface.height)){ctx.putImageData(pixels,0,0);clean=await decodeImage(surface.toDataURL('image/png'))}
   }
  }catch{if(request===version)logoWarning.value=tr('No se pudo cargar el logo.','Unable to load the logo.')}}
  if(request!==version)return
  saved.value=!!stored.design;loading.value=false;await nextTick()
  if(request===version&&canvas.value)drawPoster(canvas.value,design,qr,logo,clean)
 }catch{if(request===version){error.value=tr('No se pudo cargar la vista previa.','Unable to load preview.');loading.value=false}}
}
watch(()=>[props.churchId,props.kind,props.revision],load,{immediate:true})
onBeforeUnmount(()=>version++)
</script>
<style scoped>
.qr-poster-summary{display:grid;grid-template-columns:minmax(0,240px) minmax(0,1fr);gap:24px;align-items:center;margin-top:20px}.qr-poster-thumbnail{margin:0;background:#e9eeeb;padding:16px;border:1px solid #dce5df;border-radius:12px;min-width:0}.qr-poster-thumbnail canvas{display:block;width:100%;height:auto;box-shadow:0 3px 12px #19372b22;background:white}.qr-poster-summary-actions{min-width:0}.qr-poster-summary-actions strong{font-size:.95rem}.qr-poster-summary-actions p{font-size:.85rem;color:#52645a;margin:8px 0 16px}@media(max-width:600px){.qr-poster-summary{grid-template-columns:1fr;gap:16px}.qr-poster-thumbnail{width:100%;max-width:260px;justify-self:center}}
</style>
