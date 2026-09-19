<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
defineProps({ stats:{type:Object,default:()=>({})} })
</script>

<template>
<Equipo4Layout>
<section class="space-y-6">
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-[#00338D]">Panel de Inventarios y Abastecimiento</h1>
      <p class="mt-1 text-sm text-slate-500">Equipo 4 · Campus Digital</p>
    </div>
    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-[#10B981]/10 text-[#10B981]">Sistema protegido</span>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <span class="text-sm text-slate-500">Productos</span>
      <strong class="mt-2 block text-2xl text-[#00338D]">{{ stats.products ?? 0 }}</strong>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <span class="text-sm text-slate-500">Almacenes</span>
      <strong class="mt-2 block text-2xl text-[#00338D]">{{ stats.warehouses ?? 0 }}</strong>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <span class="text-sm text-slate-500">Unidades en stock</span>
      <strong class="mt-2 block text-2xl text-[#00338D]">{{ stats.inventory ?? 0 }}</strong>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <span class="text-sm text-slate-500">Reservas activas</span>
      <strong class="mt-2 block text-2xl text-[#00338D]">{{ stats.reservations ?? 0 }}</strong>
    </div>
  </div>

  <div class="grid gap-6 md:grid-cols-2">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h3 class="text-base font-bold text-[#00338D]">Integraciones</h3>
      <ul class="mt-3 space-y-2 text-sm text-slate-600">
        <li>Equipo 3 · ventas y disponibilidad</li>
        <li>Equipo 7 · recompensas y reservas</li>
        <li>Equipo 6 · abastecimiento de tiendas</li>
        <li>Equipo 2 · confirmación de pagos</li>
      </ul>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h3 class="text-base font-bold text-[#00338D]">Seguridad</h3>
      <ul class="mt-3 space-y-2 text-sm text-slate-600">
        <li>Validación de entrada y autorización por rol.</li>
        <li>Rate limiting, CSRF, validación estricta y auditoría.</li>
        <li>Contratos de integración con firma HMAC e idempotencia.</li>
      </ul>
    </div>
  </div>
</section>
</Equipo4Layout>
</template>
