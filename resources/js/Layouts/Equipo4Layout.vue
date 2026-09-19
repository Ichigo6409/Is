<script setup>
import logoUrl from '@/../images/campus-digital-logo.png'
const links=[
 ['Dashboard','/equipo4'],['Inventario','/equipo4/inventario'],['Productos','/equipo4/productos'],['Almacenes','/equipo4/almacenes'],['Kardex','/equipo4/kardex'],['Proveedores','/equipo4/proveedores'],['Compras','/equipo4/compras'],['Recepciones','/equipo4/recepciones'],['Devoluciones','/equipo4/devoluciones'],['Alertas','/equipo4/alertas'],['Conteos y ajustes','/equipo4/conteos'],['Souvenirs','/equipo4/souvenirs']]
</script>
<template><div class="cd-app"><aside class="cd-sidebar"><div class="cd-brand"><img :src="logoUrl" alt="Campus Digital"></div><div class="cd-nav-title">Equipo 4 · Inventarios</div><nav class="cd-nav"><a v-for="l in links" :key="l[1]" :href="l[1]">{{l[0]}}</a></nav></aside><main class="cd-main"><header class="cd-topbar"><h1>Campus Digital · Equipo 4</h1><div class="cd-user"><span>Administrador de inventario</span><div class="cd-avatar">E4</div></div></header><div class="cd-content"><slot /></div></main></div></template>
