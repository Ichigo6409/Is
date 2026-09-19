<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'

const columns=['Folio','Tipo','Referencia','Producto','Cantidad','Motivo','Resolución','Estado']
const rows=[
 {Folio:'DEV-PROV-0001',Tipo:'A proveedor',Referencia:'OC-00025',Producto:'Playera Campus',Cantidad:5,Motivo:'Defectuoso',Resolución:'Devolución',Estado:'Confirmada'},
 {Folio:'DEV-CLI-0001',Tipo:'De cliente',Referencia:'VENTA-00041',Producto:'Playera Campus',Cantidad:1,Motivo:'No deseado',Resolución:'Reingreso',Estado:'Recibida'},
 {Folio:'DEV-CLI-0002',Tipo:'De cliente',Referencia:'VENTA-00039',Producto:'Taza Campus',Cantidad:1,Motivo:'Daño',Resolución:'Reemplazo',Estado:'En revisión'}
]
</script>

<template>
<Equipo4Layout>
<Team4Module title="Devoluciones" subtitle="Control de devoluciones a proveedores y devoluciones de clientes, con trazabilidad del inventario." :columns="columns" :rows="rows" />
</Equipo4Layout>
</template>
