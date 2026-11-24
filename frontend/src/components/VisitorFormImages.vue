<template>
 <div v-if="blocks.length" class="visitor-image-row">
 <figure v-for="b in blocks" :key="b.id" :style="{flexBasis:b.width+'%',textAlign:b.alignment}" class="visitor-image-block">
 <img v-if="sources[b.id]" :src="sources[b.id]" :alt="b.alt" />
 <div v-else-if="editable" class="visitor-image-placeholder">{{ b.source==='church'?'Logo de la iglesia':b.alt }}<small>{{ errors[b.id]?'No se pudo cargar la imagen.':'Espacio para imagen' }}</small></div>
 </figure>
 </div>
</template>
<script setup>
import {computed,ref,watch,onBeforeUnmount} from 'vue'
import {visitorImage} from '../services/visitorFormDesign'
const props=defineProps({layout:Object,position:String,churchId:Number,logoUrl:String,editable:Boolean})
const blocks=computed(()=>(props.layout?.images||[]).filter(b=>b.position===props.position)),sources=ref({}),errors=ref({})
let version=0
watch(()=>[blocks.value,props.churchId,props.logoUrl],async()=>{
 const v=++version;sources.value={};errors.value={}
 await Promise.all(blocks.value.map(async b=>{try{const src=await visitorImage(b,props.churchId,props.logoUrl);if(v===version)sources.value[b.id]=src}catch{if(v===version)errors.value[b.id]=true}}))
},{deep:true,immediate:true})
onBeforeUnmount(()=>version++)
</script>
<style scoped>
.visitor-image-row{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:12px;margin:14px 0;min-width:0}.visitor-image-block{flex-grow:0;flex-shrink:1;min-width:0;margin:0}.visitor-image-block img{max-width:100%;max-height:180px;object-fit:contain;height:auto}.visitor-image-placeholder{padding:20px;border:1px dashed #a7b9ae;border-radius:8px;background:#f4f7f5;color:#536b5c;font-size:.85rem}.visitor-image-placeholder small{display:block}@media(max-width:600px){.visitor-image-block{flex-basis:100%!important}.visitor-image-block img{max-height:135px}}
</style>
