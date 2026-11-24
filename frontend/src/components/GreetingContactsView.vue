<template>
 <section class="container py-4 management-page forms-hub">
  <header class="management-heading"><div><p class="management-eyebrow">{{ auth.user?.church?.name }}</p><h1>{{ tr('Registro de visitas','Visitor registration') }}</h1><p>{{ tr('Configura el formulario de visitas, comparte su QR y abre la página pública. Los registros se consultan en Reportes.','Configure the visitor form, share its QR and open the public page. View submitted records in Reports.') }}</p></div></header>
  <p v-if="error" role="alert" class="alert alert-danger">{{ error }}</p>
  <div class="forms-grid">
   <section v-for="item in forms" :key="item.id" class="management-surface form-card">
    <h2>{{ item.title }}</h2><p>{{ item.description }}</p>
    <QrPosterPreview :church-id="auth.user.church_id" kind="visitors" :revision="previewRevision">
    <div class="form-actions">
     <RouterLink v-if="item.id==='visitors' && auth.can('visitors.configure')" to="/greeting/form-builder" class="btn btn-primary">{{ tr('Configurar formulario','Configure form') }}</RouterLink>
     <a :href="item.url" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">{{ tr('Ver página pública','Preview public page') }}</a>
     <button class="btn btn-outline-secondary" :disabled="busy" @click="builderOpen=true">{{ tr('Diseñar y descargar QR','Design and download QR') }}</button>
     <button class="btn btn-outline-secondary" @click="copy(item.url)">{{ tr('Copiar enlace','Copy link') }}</button>
    </div>
    </QrPosterPreview>
   </section>
  </div>
  <QrPosterBuilder v-if="builderOpen" :church-id="auth.user.church_id" kind="visitors" @close="builderOpen=false;previewRevision++" />
 </section>
</template>
<script setup>
import {ref,computed} from 'vue'
import {useAuthStore} from '../stores/authStore'
import {useUiStore} from '../stores/uiStore'
import QrPosterPreview from './QrPosterPreview.vue'
import QrPosterBuilder from './QrPosterBuilder.vue'
import '../styles/clerk-management.css'
const auth=useAuthStore(),ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const builderOpen=ref(false),previewRevision=ref(0)
const error=ref(''),busy=ref(false)
const forms=computed(()=>[{id:'visitors',title:tr('Registro de visitantes','Visitor registration'),description:tr('Personaliza las preguntas que completan las visitas.','Customize the questions visitors complete.'),url:window.location.origin+'/visit/'+auth.user?.church_id}])
async function copy(url){try{await navigator.clipboard.writeText(url);ui.showToast(tr('Enlace copiado.','Link copied.'),'success')}catch{error.value=tr('No se pudo copiar el enlace.','Unable to copy link.')}}
</script>
<style scoped>
.forms-grid{display:grid;grid-template-columns:1fr;gap:20px}.form-card{padding:22px;min-width:0}.form-card h2{font-size:1.1rem}.form-card p{font-size:.85rem;color:#52645a}.church-logo{display:block;max-width:160px;max-height:80px;object-fit:contain;margin:16px auto 0}.form-qr{display:block;width:200px;max-width:100%;margin:0 auto 16px}.form-actions{display:flex;gap:10px;flex-wrap:wrap}.form-actions .btn{width:auto;white-space:normal}.flow-description{flex-basis:100%}@media(max-width:700px){.forms-grid{grid-template-columns:1fr}.form-card{padding:16px}.form-actions .btn{width:100%}}
</style>
