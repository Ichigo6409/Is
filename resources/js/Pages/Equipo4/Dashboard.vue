<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
defineProps({ stats:{type:Object,default:()=>({})} })
</script>
<template>
<Equipo4Layout>
<section class="cd-module">
  <div class="cd-module-head"><div><h1>Panel de Inventarios y Abastecimiento</h1><p>Equipo 4 · Campus Digital</p></div><span class="cd-status">Sistema protegido</span></div>
  <div class="cd-kpis">
    <div class="cd-card"><span>Productos</span><strong>{{ stats.products ?? 128 }}</strong></div>
    <div class="cd-card"><span>Almacenes</span><strong>{{ stats.warehouses ?? 4 }}</strong></div>
    <div class="cd-card"><span>Unidades</span><strong>{{ stats.inventory ?? 1842 }}</strong></div>
    <div class="cd-card"><span>Reservas activas</span><strong>{{ stats.reservations ?? 16 }}</strong></div>
  </div>
  <div class="cd-grid-2">
    <div class="cd-card"><h3>Integraciones</h3><p>Equipo 3 · ventas y disponibilidad</p><p>Equipo 7 · recompensas y reservas</p><p>Equipo 6 · abastecimiento de tiendas</p><p>Equipo 2 · confirmación de pagos</p></div>
    <div class="cd-card"><h3>Seguridad</h3><p>Validación de entrada y autorización por rol.</p><p>Rate limiting, CSRF, validación estricta y auditoría.</p><p>Contratos de integración con firma HMAC e idempotencia.</p></div>
  </div>
</section>
</Equipo4Layout>
</template>
