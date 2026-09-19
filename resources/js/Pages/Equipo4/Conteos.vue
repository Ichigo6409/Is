<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'

const columns = ["Folio", "Ubicación", "Responsable", "Estado", "Diferencias"]
const rows = [{"Folio": "CNT-0031", "Ubicación": "Bodega souvenirs", "Responsable": "Administrador", "Estado": "En curso", "Diferencias": "2"}, {"Folio": "CNT-0030", "Ubicación": "Principal", "Responsable": "Administrador", "Estado": "Cerrado", "Diferencias": "0"}]
</script>

<template>
<Equipo4Layout>
<Team4Module title="Conteos y ajustes" subtitle="Inventario físico, conteo cíclico, diferencias, autorización y bitácora." :columns="columns" :rows="rows" />
</Equipo4Layout>
</template>
