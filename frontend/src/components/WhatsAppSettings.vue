<template>
 <section class="container py-4 management-page">
  <header class="management-heading"><h1>{{ tr('Asistente WhatsApp','WhatsApp assistant') }}</h1><p>{{ tr('Activa o desactiva el asistente para tu propia cuenta.','Enable or disable the assistant for your own account.') }}</p></header>
  <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
  <p v-if="loading" role="status">{{ tr('Cargando…','Loading…') }}</p>
  <section v-else class="management-surface p-3">
   <p>{{ tr('La integración está en preparación. Guardar esta preferencia no inicia conversaciones ni genera llamadas de IA.','The integration is being prepared. Saving this preference does not start conversations or make AI calls.') }}</p>
   <label class="d-flex align-items-start gap-2"><input type="checkbox" :checked="enabled" :disabled="saving" @change="save($event.target.checked)" /><span>{{ tr('Permitir el asistente WhatsApp en mi cuenta','Allow the WhatsApp assistant for my account') }}</span></label>
   <p class="small mt-3">{{ tr('Desactivado de forma predeterminada. El interruptor general y tus permisos siguen aplicando. Antes de usarlo será necesario verificar y vincular tu número.','Off by default. The system switch and your permissions still apply. Your number must be verified and linked before use.') }}</p>
   <p v-if="notice" class="text-success" role="status">{{ notice }}</p>
  </section>
 </section>
</template>
<script setup>
import {ref,onMounted} from 'vue'
import {apiClient} from '../services/authApi'
import {useUiStore} from '../stores/uiStore'
import '../styles/clerk-management.css'
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const loading=ref(true),saving=ref(false),enabled=ref(false),error=ref(''),notice=ref('')
onMounted(async()=>{try{const {data}=await apiClient.get('/auth/whatsapp-preferences');enabled.value=data.enabled}catch{error.value=tr('No se pudo cargar la preferencia.','Unable to load preference.')}finally{loading.value=false}})
async function save(value){saving.value=true;error.value='';notice.value='';try{const {data}=await apiClient.patch('/auth/whatsapp-preferences',{enabled:value});enabled.value=data.enabled;notice.value=tr('Preferencia guardada.','Preference saved.')}catch{error.value=tr('No se pudo guardar.','Unable to save.')}finally{saving.value=false}}
</script>
<style scoped>input[type=checkbox]{flex:none;width:18px;height:18px;margin:2px 0 0}</style>
