<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'
const kpis = [{"label": "Recepciones", "value": "12"}, {"label": "Parciales", "value": "4"}, {"label": "Diferencias", "value": "2"}, {"label": "Devoluciones", "value": "3"}]
const columns = ["Folio", "OC", "Tipo", "Cantidad", "Estado", "Fecha"]
const rows = [{"Folio": "REC-0012", "OC": "OC-00025", "Tipo": "Parcial", "Cantidad": "15", "Estado": "Confirmada", "Fecha": "08/09/2026"}, {"Folio": "DEV-0003", "OC": "OC-00021", "Tipo": "Devolución", "Cantidad": "5", "Estado": "Autorizada", "Fecha": "08/09/2026"}]
</script>
<template><Equipo4Layout><Team4Module title="Recepción y devolución a proveedor" subtitle="Recepciones parciales/totales, diferencias, lotes, evidencia y devoluciones." :kpis="kpis" :columns="columns" :rows="rows" /></Equipo4Layout></template>
