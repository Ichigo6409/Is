<script setup>defineProps({title:String,subtitle:String})</script>
<template><div class="cd-page-title"><div><h2>{{title}}</h2><p>{{subtitle}}</p></div><div class="cd-actions"><button class="cd-btn cd-btn-secondary">Exportar</button><button class="cd-btn cd-btn-primary">+ Nuevo</button></div></div><slot /></template>
