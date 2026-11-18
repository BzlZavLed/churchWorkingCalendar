<template>
  <section class="form-section">
    <header class="form-section-heading"><h2>{{ title }}</h2><button type="button" class="form-section-toggle" :aria-label="open ? cancelLabel : title" :title="open ? cancelLabel : title" :aria-expanded="open" :disabled="busy" @click="open = !open"><span v-if="!open" aria-hidden="true">+</span><template v-else>{{ cancelLabel }}</template></button></header>
    <div v-if="open" class="form-section-body"><slot /></div>
  </section>
</template>
<script setup>
import { ref, computed } from 'vue'
import { useUiStore } from '../stores/uiStore'
defineProps({ title: {type:String,required:true}, busy: Boolean })
const open=ref(false),ui=useUiStore()
const cancelLabel=computed(()=>ui.locale==='es'?'Cancelar':'Cancel')
</script>
<style scoped>
.form-section-heading{display:flex;align-items:center;gap:10px}.form-section-heading h2{font-size:15px!important;font-weight:500;margin:0!important}.form-section-toggle{display:inline-flex;align-items:center;justify-content:center;min-width:32px;min-height:32px;width:auto;padding:4px 6px;border:0;background:transparent;color:#1f6f5c;font-size:13px}.form-section-toggle span{font-size:24px;line-height:1}.form-section-toggle:focus-visible{outline:3px solid #85b9a6;outline-offset:2px}.form-section-body{margin-top:16px;min-width:0}
</style>
