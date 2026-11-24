<template>
  <div class="visitor-fields">
    <template v-for="field in fields" :key="field.id">
      <p :style="{gridColumn:'span '+(field.span||12)}" v-if="field.type==='label'" class="field-note">{{ field.label }}</p>
      <label :style="{gridColumn:'span '+(field.span||12)}" v-else-if="field.type==='checkbox'" class="field-check"><input type="checkbox" :checked="!!modelValue[field.id]" :required="field.required" @change="set(field.id,$event.target.checked)" /><span>{{ field.label }}{{ field.required ? ' *' : '' }}</span></label>
      <label v-else :style="{gridColumn:'span '+(field.span||12)}">{{ field.label }}{{ field.required ? ' *' : '' }}
        <textarea v-if="field.type==='textarea'" class="form-control" :value="modelValue[field.id] || ''" :required="field.required" maxlength="5000" rows="3" @input="set(field.id,$event.target.value)"></textarea>
        <select v-else-if="field.type==='select'" class="form-select" :value="modelValue[field.id] || ''" :required="field.required" @change="set(field.id,$event.target.value)"><option value="">—</option><option v-for="option in field.options" :key="option" :value="option">{{ option }}</option></select>
        <input v-else class="form-control" :type="field.type" :value="modelValue[field.id] ?? ''" :required="field.required" :maxlength="field.type==='text'?500:undefined" :step="field.type==='number'?'any':undefined" :min="field.type==='number'?-1000000000:undefined" :max="field.type==='number'?1000000000:undefined" @input="set(field.id,$event.target.value)" />
      </label>
    </template>
  </div>
</template>
<script setup>
const props=defineProps({fields:{type:Array,default:()=>[]},modelValue:{type:Object,default:()=>({})}})
const emit=defineEmits(['update:modelValue'])
const set=(id,value)=>emit('update:modelValue',{...props.modelValue,[id]:value})
</script>
<style scoped>
.visitor-fields{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:16px;min-width:0}.visitor-fields label{font-size:13px;overflow-wrap:anywhere}.visitor-fields .form-control,.visitor-fields .form-select{margin-top:6px;width:100%;min-width:0}.field-check{display:flex;gap:10px;align-items:flex-start}.field-check input{width:16px;height:16px;flex:0 0 16px;margin-top:2px}.field-note{white-space:pre-wrap;overflow-wrap:anywhere;padding:12px;background:#f2f6f3;border-radius:6px;color:#52645a;margin:0}
@media(max-width:600px){.visitor-fields>label,.visitor-fields>p{grid-column:span 12!important}}
</style>
