<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'
const kpis = [{"label": "Almacenes", "value": "4"}, {"label": "Ubicaciones", "value": "16"}, {"label": "Ocupación", "value": "68%"}, {"label": "Transferencias", "value": "5"}]
const columns = ["Código", "Almacén", "Tipo", "Ubicaciones", "Estado"]
const rows = [{"Código": "ALM-001", "Almacén": "Almacén principal", "Tipo": "Principal", "Ubicaciones": "8", "Estado": "Activo"}, {"Código": "ALM-002", "Almacén": "Bodega souvenirs", "Tipo": "Bodega", "Ubicaciones": "4", "Estado": "Activo"}]
</script>
<template><Equipo4Layout><Team4Module title="Almacenes y ubicaciones" subtitle="Almacenes, mostradores, bodegas, consignación y ubicaciones virtuales." :kpis="kpis" :columns="columns" :rows="rows" /></Equipo4Layout></template>
