<template>
  <section class="container py-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
      <h1 class="h3 m-0">Departments</h1>
    </div>

    <div class="bg-white border rounded p-3 mb-4">
      <label class="form-label">
        Select Church
        <select v-model="selectedChurchId" class="form-select" @change="loadDepartments">
          <option value="">Select...</option>
          <option v-for="church in churches" :key="church.id" :value="church.id">
            {{ church.name }}
          </option>
        </select>
      </label>
    </div>

    <form v-if="selectedChurchId" class="bg-white border rounded p-4 mb-4" @submit.prevent="createDepartment">
      <h2 class="h5 mb-3">Create Department</h2>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">
            Name
            <input v-model="createForm.name" class="form-control" type="text" required />
          </label>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">
            Color
            <input v-model="createForm.color" class="form-control form-control-color" type="color" />
          </label>
        </div>
        <div class="col-12">
          <label class="form-label">
            Optional Username
            <input v-model="createForm.user_name" class="form-control" type="text" />
          </label>
        </div>
      </div>
      <button class="btn btn-primary mt-3" type="submit">Create Department</button>
    </form>

    <div v-if="loading">Loading departments...</div>
    <p v-if="error" class="text-danger">{{ error }}</p>

    <div v-if="departments.length === 0 && !loading && selectedChurchId">No departments yet.</div>
    <div v-else-if="selectedChurchId" class="table-responsive bg-white border rounded">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Color</th>
            <th>Optional Username</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="department in departments" :key="department.id">
            <td>{{ department.id }}</td>
            <td><input v-model="department.name" class="form-control" type="text" /></td>
            <td><input v-model="department.color" class="form-control form-control-color" type="color" /></td>
            <td><input v-model="department.user_name" class="form-control" type="text" /></td>
            <td class="text-end">
              <div class="d-flex flex-column flex-md-row justify-content-end gap-2">
                <button class="btn btn-sm btn-outline-secondary" type="button" @click="updateDepartment(department)">Save</button>
                <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteDepartment(department)">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { superAdminApi } from '../../services/superAdminApi'

const churches = ref([])
const selectedChurchId = ref('')
const departments = ref([])
const loading = ref(false)
const error = ref('')

const createForm = reactive({
  name: '',
  color: '',
  user_name: '',
})

const loadChurches = async () => {
  const response = await superAdminApi.listChurches()
  churches.value = response.data
}

const loadDepartments = async () => {
  if (!selectedChurchId.value) {
    departments.value = []
    return
  }
  loading.value = true
  error.value = ''
  try {
    departments.value = await superAdminApi.listDepartments(selectedChurchId.value)
  } catch {
    error.value = 'Unable to load departments.'
  } finally {
    loading.value = false
  }
}

const createDepartment = async () => {
  if (!selectedChurchId.value) {
    return
  }
  error.value = ''
  try {
    const payload = {
      ...createForm,
      color: createForm.color || null,
      user_name: createForm.user_name || null,
    }
    await superAdminApi.createDepartment(selectedChurchId.value, payload)
    createForm.name = ''
    createForm.color = ''
    createForm.user_name = ''
    await loadDepartments()
  } catch {
    error.value = 'Unable to create department.'
  }
}

const updateDepartment = async (department) => {
  if (!selectedChurchId.value) {
    return
  }
  error.value = ''
  try {
    await superAdminApi.updateDepartment(selectedChurchId.value, department.id, {
      name: department.name,
      color: department.color || null,
      user_name: department.user_name || null,
    })
  } catch {
    error.value = 'Unable to update department.'
  }
}

const deleteDepartment = async (department) => {
  if (!selectedChurchId.value) {
    return
  }
  error.value = ''
  try {
    await superAdminApi.deleteDepartment(selectedChurchId.value, department.id)
    await loadDepartments()
  } catch {
    error.value = 'Unable to delete department.'
  }
}

onMounted(async () => {
  await loadChurches()
})
</script>
