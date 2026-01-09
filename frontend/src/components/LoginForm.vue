<template>
  <section>
    <h1>Login</h1>
    <form @submit.prevent="submit">
      <label>
        Email
        <input v-model="form.email" type="email" required />
      </label>
      <label>
        Password
        <input v-model="form.password" type="password" required />
      </label>
    <button type="submit">Login</button>
  </form>
  <button class="btn-accent" type="button" @click="goToRegister">Register</button>
    <p v-if="error">{{ error }}</p>
  </section>
</template>

<script setup>
import logoUrl from '../assets/logo.png'
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'

const authStore = useAuthStore()
const router = useRouter()
const error = ref('')

const form = reactive({
  email: '',
  password: '',
})

const submit = async () => {
  error.value = ''
  try {
    await authStore.login(form)
    if (authStore.user?.role === 'superadmin') {
      await router.push('/superadmin/churches')
    } else {
      await router.push('/calendar')
    }
  } catch (err) {
    error.value = 'Login failed.'
  }
}

const goToRegister = async () => {
  await router.push('/register')
}
</script>
