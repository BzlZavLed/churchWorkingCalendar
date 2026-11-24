<template>
 <Teleport to="body"><div class="poster-backdrop" @keydown.esc="$emit('close')"><section class="poster-dialog" role="dialog" aria-modal="true" aria-labelledby="poster-title">
 <header><div><h2 id="poster-title">Diseñar cartel QR · {{ kind==='attendance'?'Asistencia':'Visitas' }}</h2><p>Arrastra los elementos o ajusta su posición. El diseño se guarda para esta iglesia.</p></div><button class="btn btn-outline-secondary" @click="$emit('close')">Cerrar</button></header>
 <p v-if="error" role="alert" class="alert alert-danger">{{ error }}</p><p v-if="notice" role="status" class="alert alert-success">{{ notice }}</p>
 <p v-if="loading">Cargando diseñador…</p>
 <div v-else class="poster-workspace">
 <aside>
 <label>Plantilla<select class="form-select" v-model="template"><option value="branded">Con logo de iglesia</option><option value="simple">Simple</option><option value="large">QR grande</option><option value="blank">En blanco (solo QR)</option></select></label>
 <button class="btn btn-outline-secondary" @click="applyTemplate">Aplicar plantilla</button>
 <label>Tamaño de papel<select class="form-select" v-model="design.format"><option value="a4">A4</option><option value="letter">Carta / Letter</option></select></label>
 <label class="poster-checkbox"><input type="checkbox" v-model="design.landscape" /> <span>Horizontal</span></label>
 <label class="poster-checkbox"><input type="checkbox" v-model="design.sdaDesign" /> <span>Agregar diseño global SDA</span></label>
 <label v-if="design.sdaDesign">Color de la franja SDA<input type="color" v-model="design.sdaColor" /></label>
 <label>Color de fondo<input type="color" v-model="design.background" /></label>
 <div class="poster-buttons"><button class="btn btn-outline-primary" @click="add('text')" :disabled="design.elements.length>=15">+ Texto</button><button class="btn btn-outline-primary" @click="add('logo')" :disabled="!logo||design.elements.length>=15">+ Logo</button></div>
 <p v-if="!logo">Carga el logo de la iglesia en Configuración para usarlo aquí.</p>
 <label>Elemento<select class="form-select" v-model="selected"><option v-for="(e,i) in design.elements" :value="i" :key="i">{{ i+1 }} · {{ e.type==='qr'?'Código QR':e.type==='logo'?'Logo':e.text.slice(0,32)||'Texto' }}</option></select></label>
 <fieldset v-if="current"><legend>Editar elemento</legend>
 <label v-if="current.type==='text'">Texto<textarea class="form-control" maxlength="500" rows="3" v-model="current.text" /></label>
 <div class="poster-fields"><label>Izquierda (%)<input class="form-control" type="number" min="0" max="100" v-model.number="current.x" /></label><label>Arriba (%)<input class="form-control" type="number" min="0" max="100" v-model.number="current.y" /></label><label>Ancho (%)<input class="form-control" type="number" :min="current.type==='qr'?20:5" max="100" v-model.number="current.width" /></label></div>
 <div class="poster-center-actions"><button class="btn btn-outline-secondary" @click="centerElement('horizontal')">Centrar horizontalmente</button><button class="btn btn-outline-secondary" @click="centerElement('vertical')">Centrar verticalmente</button></div>
 <template v-if="current.type==='text'"><label>Tamaño de letra<input class="form-control" type="number" min="10" max="60" v-model.number="current.size" /></label><label>Color<input type="color" v-model="current.color" /></label><label>Alineación<select class="form-select" v-model="current.align"><option value="left">Izquierda</option><option value="center">Centro</option><option value="right">Derecha</option></select></label></template>
 <label v-if="current.type==='qr'">URL del código QR<input class="form-control" type="url" :value="qrUrl" readonly /><small>Este enlace está codificado en el QR; no se imprime como texto en el PDF.</small></label>
 <label v-if="current.type==='qr'" class="poster-checkbox"><input type="checkbox" v-model="current.centerLogo" :disabled="!logo" /> <span>Logo en el centro del QR</span></label>
 <section v-if="current.type==='logo' || (current.type==='qr' && current.centerLogo)" class="logo-background-tools">
 <button class="btn btn-outline-secondary" :disabled="!logo" @click="previewBackground">Quitar fondo blanco</button>
 <button v-if="current.removeWhiteBackground" class="btn btn-outline-secondary" @click="current.removeWhiteBackground=false">Restaurar original</button>
 <div v-if="backgroundPreview">
 <p>Revisa el resultado. Solo se elimina el blanco conectado a los bordes; algunos detalles blancos también pueden desaparecer.</p>
 <div class="logo-comparison"><figure><figcaption>Original</figcaption><img :src="logo.src" alt="Logo original" /></figure><figure><figcaption>Sin fondo blanco</figcaption><img :src="cleanLogo.src" alt="Resultado sin fondo blanco" /></figure></div>
 <div class="poster-buttons"><button class="btn btn-primary" @click="current.removeWhiteBackground=true;backgroundPreview=false">Usar resultado en PDF</button><button class="btn btn-outline-secondary" @click="current.removeWhiteBackground=false;backgroundPreview=false">Conservar original</button></div>
 </div><small>Se aplica solo a este elemento del cartel. El logo de la iglesia no cambia.</small>
 </section>
 <button v-if="current.type!=='qr'" class="btn btn-outline-danger" @click="remove">Quitar elemento</button>
 </fieldset>
 <p class="poster-tip">El QR conserva su margen blanco y alto contraste. Prueba escanear el PDF antes de imprimir muchas copias.</p>
 <div class="poster-buttons"><button v-if="canSave" class="btn btn-primary" :disabled="busy||!ready" @click="save">Guardar diseño</button><button class="btn btn-outline-primary" :disabled="!ready" @click="download">Descargar PDF</button></div>
 </aside>
 <div class="poster-preview"><p>Vista previa · selecciona y arrastra un elemento</p><div class="poster-paper" :style="{aspectRatio:dimensions[0]+'/'+dimensions[1]}"><canvas ref="canvas" /><button v-for="(box,i) in boxes" :key="i" :aria-label="'Mover elemento '+(i+1)" class="poster-handle" :class="{selected:i===selected}" :style="{left:box.x+'%',top:box.y+'%',width:box.width+'%',height:box.height+'%'}" @pointerdown="startDrag($event,i)" @pointermove="drag" @pointerup="stopDrag" @pointercancel="stopDrag" @click="selected=i"></button></div></div>
 </div></section></div></Teleport>
</template>
<script setup>
import {ref,computed,watch,onMounted,nextTick} from 'vue'
import {apiClient} from '../services/authApi'
import {removeEdgeWhite} from '../utils/removeWhiteBackground'
import {churchLogoImage} from '../services/churchLogoImage'
import {posterTemplate,pageSize,decodeImage,qrImage,drawPoster,downloadPoster} from '../services/qrPoster'
const props=defineProps({churchId:{type:Number,required:true},kind:{type:String,required:true}})
defineEmits(['close'])
const design=ref(posterTemplate(props.kind,'')),selected=ref(0),template=ref('branded'),loading=ref(true),busy=ref(false),error=ref(''),notice=ref(''),canSave=ref(false),canvas=ref(null),boxes=ref([]),ready=ref(false)
let logo=null,cleanLogo=null,qr=null,name='',moving=null
const backgroundPreview=ref(false)
watch(selected,()=>backgroundPreview.value=false)
const qrUrl=computed(()=>window.location.origin+(props.kind==='attendance'?'/attendance/':'/visit/')+props.churchId)
const dimensions=computed(()=>pageSize(design.value)),current=computed(()=>design.value.elements[selected.value])
function render(){if(canvas.value&&qr){boxes.value=drawPoster(canvas.value,design.value,qr,logo,cleanLogo);ready.value=true}}
watch(design,()=>{notice.value='';render()},{deep:true,flush:'post'})
onMounted(async()=>{try{
 const [{data:stored},{data:church}]=await Promise.all([apiClient.get('/qr-designs/'+props.kind),apiClient.get('/public/churches/'+props.churchId+'/visit')])
 name=church.name;canSave.value=stored.can_save
 qr=await qrImage(qrUrl.value)
 if(church.logo_url){try{logo=await decodeImage((await churchLogoImage(props.churchId,church.logo_url)).png);await prepareCleanLogo()}catch{error.value='No se pudo cargar el logo. Puedes continuar sin él.'}}
 design.value=stored.design?{sdaDesign:false,sdaColor:'#8cc5b7',...stored.design}:posterTemplate(props.kind,name);loading.value=false;await nextTick();render()
}catch{error.value='No se pudo cargar el diseñador. Cierra e intenta nuevamente.'}})
async function prepareCleanLogo(){
 const surface=document.createElement('canvas');surface.width=logo.width;surface.height=logo.height
 const ctx=surface.getContext('2d');ctx.drawImage(logo,0,0)
 const pixels=ctx.getImageData(0,0,surface.width,surface.height)
 const removed=removeEdgeWhite(pixels.data,surface.width,surface.height)
 if(!removed)return
 ctx.putImageData(pixels,0,0);cleanLogo=await decodeImage(surface.toDataURL('image/png'))
}
function previewBackground(){if(!cleanLogo){notice.value='No se encontró fondo blanco conectado a los bordes. Se conserva el original.';return}backgroundPreview.value=true}
function applyTemplate(){backgroundPreview.value=false;if(!window.confirm('¿Reemplazar el diseño actual con esta plantilla?'))return;design.value=posterTemplate(props.kind,name,template.value);if(template.value==='blank')design.value.elements=design.value.elements.filter(e=>e.type==='qr');selected.value=0}
function add(type){design.value.elements.push({type,x:15,y:10,width:type==='logo'?25:70,text:'Escribe tu texto',size:22,color:'#173d35',align:'center',centerLogo:false});selected.value=design.value.elements.length-1}
function centerElement(axis){render();const box=boxes.value[selected.value];if(!box||!current.value)return;if(axis==='horizontal')current.value.x=+( (100-box.width)/2 ).toFixed(2);else current.value.y=+( (100-box.height)/2 ).toFixed(2)}
function remove(){design.value.elements.splice(selected.value,1);selected.value=0}
function startDrag(event,i){selected.value=i;const rect=event.currentTarget.parentElement.getBoundingClientRect();moving={i,x:event.clientX,y:event.clientY,left:boxes.value[i].x,top:boxes.value[i].y,rect};event.currentTarget.setPointerCapture(event.pointerId)}
function drag(e){if(!moving)return;const item=design.value.elements[moving.i],box=boxes.value[moving.i];item.x=+Math.max(0,Math.min(100-box.width,moving.left+(e.clientX-moving.x)/moving.rect.width*100)).toFixed(1);item.y=+Math.max(0,Math.min(100-box.height,moving.top+(e.clientY-moving.y)/moving.rect.height*100)).toFixed(1)}
function stopDrag(){moving=null}
async function save(){busy.value=true;error.value='';try{await apiClient.put('/qr-designs/'+props.kind,design.value);notice.value='Diseño guardado.'}catch(e){error.value=Object.values(e.response?.data?.errors||{}).flat().join(' ')||'No se pudo guardar el diseño.'}finally{busy.value=false}}
function download(){render();downloadPoster(canvas.value,design.value,props.kind)}
</script>
<style scoped>
.poster-backdrop{position:fixed;inset:0;z-index:2000;background:#10251dcc;display:flex;align-items:center;justify-content:center;padding:20px}.poster-dialog{background:#f5f6f4;border-radius:16px;width:1200px;max-width:100%;max-height:94vh;overflow:auto;padding:24px}.poster-dialog header{display:flex;justify-content:space-between;gap:20px;align-items:start}.poster-dialog h2{font-size:1.3rem}.poster-dialog p{font-size:.85rem;color:#52645a}.poster-workspace{display:grid;grid-template-columns:310px minmax(0,1fr);gap:24px}.poster-workspace aside{display:flex;flex-direction:column;gap:12px}.poster-workspace label{display:flex;gap:6px;flex-wrap:wrap;font-size:.85rem}.poster-workspace label:has(.form-control),.poster-workspace label:has(select){display:block}.poster-workspace fieldset{border:1px solid #cbd8d0;border-radius:10px;padding:12px;display:flex;flex-direction:column;gap:12px}.poster-workspace legend{font-size:.95rem;float:none;width:auto}.poster-fields{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}.poster-buttons{display:flex;gap:8px;flex-wrap:wrap}.poster-preview{background:#e3e8e4;padding:20px;border-radius:12px;align-self:start}.poster-paper{position:relative;width:100%;box-shadow:0 4px 20px #0002;background:white}.poster-paper canvas{width:100%;height:100%;display:block}.poster-handle{position:absolute;background:transparent;border:1px dashed transparent;padding:0;touch-action:none;cursor:move}.poster-handle:hover,.poster-handle.selected{border:2px dashed #2875b8;background:#2875b80a}.poster-tip{margin:0}@media(max-width:760px){.poster-backdrop{padding:0}.poster-dialog{max-height:100dvh;border-radius:0;padding:16px}.poster-workspace{grid-template-columns:1fr}.poster-preview{grid-row:1}.poster-dialog header{gap:8px}}
.poster-workspace .poster-checkbox{display:flex;flex-direction:row;align-items:center;justify-content:flex-start;flex-wrap:nowrap;gap:8px}.poster-workspace .poster-checkbox input[type="checkbox"]{width:16px;height:16px;flex:0 0 16px;margin:0;padding:0}.poster-checkbox span{line-height:1.4}
.poster-center-actions{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:8px}.poster-center-actions .btn{width:100%;min-width:0;white-space:normal;padding:8px 6px;font-size:.8rem}
.logo-background-tools{display:flex;flex-direction:column;gap:10px}.logo-comparison{display:grid;grid-template-columns:1fr 1fr;gap:8px}.logo-comparison figure{margin:0;min-width:0}.logo-comparison img{width:100%;height:110px;object-fit:contain;background:repeating-conic-gradient(#ddd 0% 25%,#fff 0% 50%) 0 / 16px 16px}.logo-comparison figcaption{font-size:.8rem}
</style>
