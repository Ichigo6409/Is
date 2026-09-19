<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'

const columns = ["SKU", "Producto", "Actual", "Mínimo", "Punto reorden", "Prioridad"]
const rows = [{"SKU": "PR-009", "Producto": "Sudadera", "Actual": "4", "Mínimo": "10", "Punto reorden": "15", "Prioridad": "Crítica"}, {"SKU": "PR-014", "Producto": "Termo", "Actual": "8", "Mínimo": "10", "Punto reorden": "12", "Prioridad": "Media"}]
</script>

<template>
<Equipo4Layout>
<Team4Module title="Alertas de reabastecimiento" subtitle="Mínimo, máximo, punto de reorden y productos sin movimiento." :columns="columns" :rows="rows" />
</Equipo4Layout>
</template>
