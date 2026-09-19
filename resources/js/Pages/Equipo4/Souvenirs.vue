<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'
const kpis = [{"label": "Colecciones", "value": "8"}, {"label": "Productos", "value": "54"}, {"label": "Temporadas", "value": "3"}, {"label": "Preventas", "value": "4"}]
const columns = ["SKU", "Producto", "Colección", "Temporada", "Variantes", "Estado"]
const rows = [{"SKU": "PR-001", "Producto": "Playera Campus", "Colección": "Oficial", "Temporada": "2026", "Variantes": "8", "Estado": "Activo"}, {"SKU": "PR-002", "Producto": "Taza Campus", "Colección": "Regreso a clases", "Temporada": "2026", "Variantes": "2", "Estado": "Activo"}]
</script>
<template><Equipo4Layout><Team4Module title="Operación especializada de souvenirs" subtitle="Colecciones, tallas, colores, temporadas, paquetes y productos oficiales." :kpis="kpis" :columns="columns" :rows="rows" /></Equipo4Layout></template>
