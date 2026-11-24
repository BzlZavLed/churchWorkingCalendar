<template>
  <main class="reset-page"><section class="reset-card"><h1>Crear contraseña</h1><p>Escribe una contraseña para activar tu cuenta o recuperar el acceso.</p><form v-if="!done" @submit.prevent="submit"><label>Correo<input class="form-control" type="email" v-model="form.email" required autocomplete="email" /></label><label>Nueva contraseña<input class="form-control" type="password" v-model="form.password" required minlength="8" autocomplete="new-password" /></label><label>Confirmar contraseña<input class="form-control" type="password" v-model="form.password_confirmation" required minlength="8" autocomplete="new-password" /></label><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><button class="btn btn-primary" :disabled="busy">{{ busy?'Guardando…':'Guardar contraseña' }}</button></form><p v-else class="alert alert-success">Tu contraseña fue guardada. Ya puedes iniciar sesión.</p><RouterLink to="/login">Ir a iniciar sesión</RouterLink></section></main>
</template>
<script setup>
import { reactive,ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiClient } from '../services/authApi'
import { useAuthStore } from '../stores/authStore'
const route=useRoute(),router=useRouter(),auth=useAuthStore(),form=reactive({email:String(route.query.email||''),token:String(route.query.token||''),password:'',password_confirmation:''}),busy=ref(false),done=ref(false),error=ref('')
async function submit(){busy.value=true;error.value='';try{await apiClient.post('/auth/reset-password',form);form.password='';form.password_confirmation='';if(auth.isAuthenticated){try{await auth.logout()}catch{/* An expired session is cleared by logout. */}}await router.replace({path:'/login',query:{password_reset:'success'}})}catch(e){error.value=Object.values(e.response?.data?.errors||{}).flat().join(' ')||'No se pudo guardar la contraseña.'}finally{busy.value=false}}
</script>
<style scoped>.reset-page{min-height:100vh;display:grid;place-items:center;padding:20px;background:#f1f5f2}.reset-card{width:min(100%,480px);padding:30px;background:white;border:1px solid #dce5df;border-radius:14px}.reset-card h1{font-size:24px}.reset-card form{display:grid;gap:15px;margin:20px 0}.reset-card label{display:grid;gap:6px}.reset-card .btn{background:#1f6f5c;color:white}</style>
