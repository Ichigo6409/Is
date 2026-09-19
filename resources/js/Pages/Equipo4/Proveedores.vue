<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'

const columns = ["Código", "Proveedor", "Estatus", "Condición", "Productos"]
const rows = [{"Código": "PRV-001", "Proveedor": "Textiles MX", "Estatus": "Activo", "Condición": "30 días", "Productos": "18"}, {"Código": "PRV-002", "Proveedor": "Promocionales del Centro", "Estatus": "Activo", "Condición": "Contado", "Productos": "11"}]
</script>

<template>
<Equipo4Layout>
<Team4Module title="Proveedores" subtitle="Alta, contactos, condiciones y productos suministrados." :columns="columns" :rows="rows" />
</Equipo4Layout>
</template>
