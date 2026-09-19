<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'

const columns = ["Folio", "Proveedor", "Estado", "Importe", "Entrega"]
const rows = [{"Folio": "OC-00025", "Proveedor": "Textiles MX", "Estado": "Autorizada", "Importe": "$18,500", "Entrega": "12/09/2026"}, {"Folio": "OC-00026", "Proveedor": "Promocionales del Centro", "Estado": "Solicitada", "Importe": "$7,200", "Entrega": "15/09/2026"}]
</script>

<template>
<Equipo4Layout>
<Team4Module title="Órdenes de compra" subtitle="Solicitud, autorización, partidas, cantidades, costo esperado y fechas." :columns="columns" :rows="rows" />
</Equipo4Layout>
</template>
