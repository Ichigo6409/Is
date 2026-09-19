<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'

const columns = ["Fecha", "Tipo", "Producto", "Cantidad", "Referencia", "Usuario"]
const rows = [{"Fecha": "08/09/2026", "Tipo": "ENTRADA", "Producto": "Playera", "Cantidad": "20", "Referencia": "OC-00025", "Usuario": "Administrador"}, {"Fecha": "08/09/2026", "Tipo": "TRANSFERENCIA", "Producto": "Taza", "Cantidad": "5", "Referencia": "TR-0008", "Usuario": "Administrador"}]
</script>

<template>
<Equipo4Layout>
<Team4Module title="Kardex y movimientos" subtitle="Entradas, salidas, ventas, devoluciones, ajustes, transferencias y mermas." :columns="columns" :rows="rows" />
</Equipo4Layout>
</template>
