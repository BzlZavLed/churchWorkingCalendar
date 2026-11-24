<template>
 <section class="layout-editor surface">
 <h2>Diseño de la tarjeta</h2><p>Las imágenes, textos y preguntas se usan en el formulario público y en la tarjeta PDF. Los cambios se aplican al publicar.</p>
 <div class="layout-options"><label>Título<input class="form-control" v-model="modelValue.title" maxlength="150" /></label><label>Color principal<input type="color" v-model="modelValue.accent" /></label></div>
 <label>Instrucciones<textarea class="form-control" v-model="modelValue.introduction" maxlength="1000" rows="3" /></label>
 <details v-for="(image,i) in modelValue.images" :key="image.id" class="layout-image-editor">
 <summary><span class="type-icon" aria-hidden="true">▧</span><span><strong>{{ image.alt || 'Imagen sin descripción' }}</strong><small>Imagen · {{ {header:'Encabezado',after:'Después de las preguntas',footer:'Pie de página'}[image.position] }}</small></span></summary>
 <div class="image-content">
 <div class="layout-options"><strong>{{ image.alt }}</strong><button class="btn btn-outline-secondary" @click="modelValue.images.splice(i,1)">Quitar imagen</button></div>
 <label>Descripción<input class="form-control" v-model="image.alt" maxlength="150" /></label>
 <div class="layout-options"><label>Sección<select class="form-select" v-model="image.position"><option value="header">Encabezado</option><option value="after">Después de las preguntas</option><option value="footer">Pie de página</option></select></label><label>Ancho (%)<input type="number" class="form-control" min="15" max="100" v-model.number="image.width" /></label><label>Alineación<select class="form-select" v-model="image.alignment"><option value="left">Izquierda</option><option value="center">Centro</option><option value="right">Derecha</option></select></label></div>
 <label>Subir o reemplazar imagen<input class="form-control" type="file" accept="image/png,image/jpeg,image/webp" :disabled="uploading" @change="upload($event,image)" /></label>
 </div></details>
 <button class="btn btn-outline-primary" :disabled="modelValue.images.length>=8||uploading" @click="add">+ Espacio para imagen</button><small>PNG, JPG o WebP · máximo 2 MB. Las imágenes de versiones anteriores se conservan.</small>
 <p v-if="error" role="alert" class="alert alert-danger">{{ error }}</p>
 </section>
</template>
<script setup>
import {ref} from 'vue'
import {apiClient} from '../services/authApi'
const props=defineProps({modelValue:{type:Object,required:true}})
const emit=defineEmits(['uploading'])
const uploading=ref(false),error=ref('')
function add(){props.modelValue.images.push({id:crypto.randomUUID(),source:'church',asset:null,position:'header',alignment:'center',width:45,alt:'Logo de la iglesia'})}
async function upload(event,image){
 const file=event.target.files?.[0];if(!file)return
 uploading.value=true;emit('uploading',true);error.value=''
 try{const body=new FormData();body.append('image',file);const {data}=await apiClient.post('/visitor-form/images',body);image.source='upload';image.asset=data.id}
 catch(e){error.value=Object.values(e.response?.data?.errors||{}).flat().join(' ')||'No se pudo subir la imagen.'}
 finally{uploading.value=false;emit('uploading',false);event.target.value=''}
}
</script>
<style scoped>
.layout-editor{margin-bottom:18px}.layout-editor label{display:block;font-size:13px}.layout-options{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.layout-options label{flex:1;min-width:120px}.layout-image-editor{border:1px solid #dce5df;border-radius:10px;margin:12px 0}.layout-image-editor summary{cursor:pointer;display:flex;align-items:center;gap:12px;padding:12px 14px;list-style:none}.layout-image-editor summary::-webkit-details-marker{display:none}.layout-image-editor summary::after{content:'⌄';margin-left:auto;font-size:20px}.layout-image-editor[open] summary::after{transform:rotate(180deg)}.type-icon{display:grid;place-items:center;width:36px;height:36px;border-radius:8px;background:#e9f4ed;color:#1f6f5c;font-size:22px;flex-shrink:0}.layout-image-editor summary small{margin:2px 0 0}.image-content{padding:0 14px 14px;display:grid;gap:10px}.layout-editor small{display:block;margin-top:8px;color:#52645a}.layout-editor h2{font-size:16px}
</style>
