<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'
const kpis = [{"label": "Activas", "value": "16"}, {"label": "Unidades", "value": "42"}, {"label": "Por checkout", "value": "11"}, {"label": "Por recompensas", "value": "5"}]
const columns = ["Referencia", "Producto", "Cantidad", "Origen", "Expira", "Estado"]
const rows = [{"Referencia": "ORD-1042", "Producto": "Playera", "Cantidad": "2", "Origen": "CHECKOUT", "Expira": "10:30", "Estado": "Reservada"}, {"Referencia": "CAN-0081", "Producto": "Taza", "Cantidad": "1", "Origen": "REWARD", "Expira": "18:00", "Estado": "Reservada"}]
</script>
<template><Equipo4Layout><Team4Module title="Reserva de stock" subtitle="Apartado de existencias para checkout, pedidos y recompensas." :kpis="kpis" :columns="columns" :rows="rows" /></Equipo4Layout></template>
