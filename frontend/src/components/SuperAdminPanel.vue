<template>
  <section>
    <h1>{{ t.title }}</h1>

    <form @submit.prevent="submit">
      <label>
        {{ t.churchName }}
        <input v-model="form.name" type="text" required />
      </label>
      <label>
        {{ t.inviteRole }}
        <select v-model="form.invite_role">
          <option value="admin">{{ roleLabels.admin }}</option>
          <option value="member">{{ roleLabels.member }}</option>
        </select>
      </label>
      <label>
        {{ t.inviteEmail }}
        <input v-model="form.invite_email" type="email" />
      </label>
      <label>
        {{ t.inviteMax }}
        <input v-model.number="form.invite_max_uses" type="number" min="1" />
      </label>
      <label>
        {{ t.inviteExpires }}
        <input v-model="form.invite_expires_at" type="datetime-local" />
      </label>
      <button type="submit">{{ t.submit }}</button>
    </form>

    <p v-if="error">{{ error }}</p>

    <div v-if="result">
      <h2>{{ t.createdTitle }}</h2>
      <p>{{ t.createdChurch }}: {{ result.church.name }} (ID {{ result.church.id }})</p>
      <p>{{ t.inviteCode }}: <strong>{{ result.invitation.code }}</strong></p>
    </div>
  </section>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useUiStore } from '../stores/uiStore'
import { superAdminApi } from '../services/superAdminApi'
import { translations } from '../i18n/translations'

const error = ref('')
const result = ref(null)
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const t = computed(() => translations[locale.value].superadminPanel)
const roleLabels = computed(() => translations[locale.value].appLayout.roleLabels)

const form = reactive({
  name: '',
  invite_role: 'admin',
  invite_email: '',
  invite_max_uses: 1,
  invite_expires_at: '',
})

const submit = async () => {
  error.value = ''
  result.value = null

  try {
    const payload = {
      name: form.name,
      invite_role: form.invite_role,
      invite_email: form.invite_email || null,
      invite_max_uses: form.invite_max_uses || 1,
      invite_expires_at: form.invite_expires_at || null,
    }

    result.value = await superAdminApi.createChurch(payload)
  } catch (err) {
    error.value = t.value.error
  }
}
</script>
