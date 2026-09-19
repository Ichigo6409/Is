<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'
const kpis = [{"label": "Activos", "value": "128"}, {"label": "Variantes", "value": "246"}, {"label": "Stock mínimo", "value": "19"}, {"label": "Sin movimiento", "value": "4"}]
const columns = ["SKU", "Producto", "Categoría", "Variantes", "Activo"]
const rows = [{"SKU": "PR-001", "Producto": "Playera Campus Digital", "Categoría": "Ropa", "Variantes": "8", "Activo": "Sí"}, {"SKU": "PR-002", "Producto": "Taza Campus Digital", "Categoría": "Souvenirs", "Variantes": "2", "Activo": "Sí"}]
</script>
<template><Equipo4Layout><Team4Module title="Productos" subtitle="Catálogo inventariable y variantes." :kpis="kpis" :columns="columns" :rows="rows" /></Equipo4Layout></template>
