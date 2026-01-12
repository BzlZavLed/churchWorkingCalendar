<template>
  <section>
    <h1>{{ t.title }}</h1>
    <form @submit.prevent="submit">
      <label>
        {{ t.code }}
        <div>
          <input v-model="form.invite_code" type="text" required /><br><br>
      <button class="btn-accent" type="button" @click="lookupInvite">{{ t.check }}</button>
        </div>
      </label>
      <p v-if="inviteStatus === 'active'">{{ t.active }} {{ inviteChurchName }}</p>
      <p v-else-if="inviteStatus === 'inactive'">{{ t.inactive }}</p>
      <p v-else-if="inviteStatus === 'not_found'">{{ t.notFound }}</p>

      <div v-if="inviteStatus === 'active'">
        <label>
          {{ t.church }}
          <input type="text" :value="inviteChurchName" readonly />
        </label>
        <label>
          {{ t.department }}
          <select v-model="form.department_id">
            <option value="">{{ t.select }}</option>
            <option v-for="department in departments" :key="department.id" :value="department.id">
              {{ department.name }}
            </option>
          </select>
        </label>
        <label>
          {{ t.name }}
          <input v-model="form.name" type="text" required />
        </label>
        <label>
          {{ t.email }}
          <input v-model="form.email" type="email" required />
        </label>
        <label>
          {{ t.password }}
          <input v-model="form.password" type="password" required />
        </label>
        <label>
          {{ t.confirm }}
          <input v-model="form.password_confirmation" type="password" required />
        </label>
        <button class="btn-accent" type="submit">{{ t.submit }}</button>
      </div>
    </form>
    <p v-if="error">{{ error }}</p>
    <p><router-link to="/login">{{ t.back }}</router-link></p>
  </section>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { publicApi } from '../services/publicApi'
import { useUiStore } from '../stores/uiStore'
import { storeToRefs } from 'pinia'
import { translations } from '../i18n/translations'

const authStore = useAuthStore()
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const router = useRouter()
const error = ref('')
const t = computed(() => translations[locale.value].register)
const departments = ref([])
const inviteStatus = ref('')
const inviteChurchId = ref('')
const inviteChurchName = ref('')

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  invite_code: '',
  department_id: '',
})

const loadDepartments = async (churchId) => {
  if (!churchId) {
    departments.value = []
    form.department_id = ''
    return
  }
  departments.value = await publicApi.listDepartments(churchId)
}

const lookupInvite = async () => {
  error.value = ''
  inviteStatus.value = ''
  inviteChurchId.value = ''
  inviteChurchName.value = ''
  departments.value = []
  form.department_id = ''

  if (!form.invite_code) {
    inviteStatus.value = 'not_found'
    return
  }

  try {
    const response = await publicApi.lookupInvitation(form.invite_code)
    inviteStatus.value = response.status
    inviteChurchId.value = response.church?.id || ''
    inviteChurchName.value = response.church?.name || ''
    if (inviteStatus.value === 'active' && inviteChurchId.value) {
      await loadDepartments(inviteChurchId.value)
    }
  } catch (err) {
    if (err?.response?.status === 404) {
      inviteStatus.value = 'not_found'
    } else {
      inviteStatus.value = 'inactive'
    }
  }
}

const submit = async () => {
  error.value = ''
  try {
    if (inviteStatus.value !== 'active') {
      error.value = t.value.errorInactive
      return
    }
    const payload = {
      ...form,
      department_id: form.department_id || null,
    }
    await authStore.register(payload)
    await router.push('/login')
  } catch (err) {
    error.value = t.value.errorRegister
  }
}
</script>
