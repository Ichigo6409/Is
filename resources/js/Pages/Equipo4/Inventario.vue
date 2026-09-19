<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'

const columns = ["SKU", "Producto", "Almacén", "Existencia", "Reservado", "Disponible", "Estado"]
const rows = [{"SKU": "PR-001", "Producto": "Playera Campus Digital", "Almacén": "Principal", "Existencia": "35", "Reservado": "0", "Disponible": "35", "Estado": "Disponible"}, {"SKU": "PR-002", "Producto": "Taza Campus Digital", "Almacén": "Souvenirs", "Existencia": "22", "Reservado": "3", "Disponible": "19", "Estado": "Disponible"}]
</script>

<template>
<Equipo4Layout>
<Team4Module title="Inventario multi-negocio" subtitle="Existencias por negocio, producto/variante, ubicación y estado." :columns="columns" :rows="rows" />
</Equipo4Layout>
</template>
