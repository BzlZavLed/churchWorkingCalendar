<template><section class="container py-4"><h1>{{ ui.locale==='es'?'Sin acceso':'No access' }}</h1><p>{{ ui.locale==='es'?'Tu rol no tiene módulos habilitados. Contacta al administrador.':'Your role has no enabled modules. Contact your administrator.' }}</p></section></template>
<script setup>import {useUiStore} from '../stores/uiStore';const ui=useUiStore()</script>
