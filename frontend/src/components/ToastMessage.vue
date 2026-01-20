<template>
  <div
    v-if="toastVisible"
    class="toast-message"
    :class="`toast-message--${toastType}`"
    role="status"
  >
    <span>{{ toastMessage }}</span>
    <button type="button" class="toast-close" @click="hideToast">×</button>
  </div>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { useUiStore } from '../stores/uiStore'

const uiStore = useUiStore()
const { toastMessage, toastType, toastVisible } = storeToRefs(uiStore)

const hideToast = () => {
  uiStore.hideToast()
}
</script>
