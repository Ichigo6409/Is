<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'
const kpis = [{"label": "Productos", "value": "128"}, {"label": "Cambios este mes", "value": "14"}, {"label": "Margen prom.", "value": "28%"}, {"label": "Alertas costo", "value": "3"}]
const columns = ["SKU", "Producto", "Último costo", "Costo promedio", "Margen sugerido"]
const rows = [{"SKU": "PR-001", "Producto": "Playera", "Último costo": "$350", "Costo promedio": "$342", "Margen sugerido": "35%"}, {"SKU": "PR-002", "Producto": "Taza", "Último costo": "$90", "Costo promedio": "$88", "Margen sugerido": "42%"}]
</script>
<template><Equipo4Layout><Team4Module title="Costos y precios de referencia" subtitle="Costo promedio/referencia, último costo, margen y trazabilidad." :kpis="kpis" :columns="columns" :rows="rows" /></Equipo4Layout></template>
