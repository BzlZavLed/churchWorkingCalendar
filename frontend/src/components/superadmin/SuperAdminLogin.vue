<template>
  <section class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-8 col-lg-5">
        <div class="bg-white border rounded p-4">
          <h1 class="h4 mb-3">Superadmin Login</h1>
          <form @submit.prevent="submit">
            <div class="mb-3">
              <label class="form-label">
                Email
                <input v-model="form.email" class="form-control" type="email" required />
              </label>
            </div>
            <div class="mb-3">
              <label class="form-label">
                Password
                <input v-model="form.password" class="form-control" type="password" required />
              </label>
            </div>
            <button class="btn btn-primary w-100" type="submit">Login</button>
          </form>
          <p v-if="error" class="text-danger mt-2 mb-0">{{ error }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/authStore'

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
    if (authStore.user?.role !== 'superadmin') {
      await authStore.logout()
      error.value = 'Superadmin access required.'
      return
    }
    await router.push('/superadmin/churches')
  } catch {
    error.value = 'Login failed.'
  }
}
</script>
