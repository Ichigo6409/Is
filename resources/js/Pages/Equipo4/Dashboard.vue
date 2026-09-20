<script setup>
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
defineProps({ stats: { type: Object, default: () => ({}) } })
</script>

<template>
  <Equipo4Layout>
    <section class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-[#00338D]">Panel de Inventarios y Abastecimiento</h1>
          <p class="mt-1 text-sm text-slate-500">Equipo 4 · Campus Digital</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-[#10B981]/10 text-[#10B981]">
          Sistema protegido
        </span>
      </div>

      <!-- KPIs principales -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <span class="text-sm text-slate-500">Productos activos</span>
          <strong class="mt-2 block text-2xl text-[#00338D]">{{ stats.products_active ?? 0 }}</strong>
          <span class="text-xs text-slate-400">de {{ stats.products ?? 0 }} totales</span>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <span class="text-sm text-slate-500">Unidades en stock</span>
          <strong class="mt-2 block text-2xl text-[#00338D]">{{ stats.inventory_units ?? 0 }}</strong>
          <span class="text-xs text-slate-400">{{ stats.inventory_available ?? 0 }} disponibles</span>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <span class="text-sm text-slate-500">Reservas activas</span>
          <strong class="mt-2 block text-2xl text-[#00338D]">{{ stats.reservations_active ?? 0 }}</strong>
          <span class="text-xs text-slate-400">{{ stats.inventory_reserved ?? 0 }} unidades apartadas</span>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <span class="text-sm text-slate-500">Alertas activas</span>
          <strong
            class="mt-2 block text-2xl"
            :class="(stats.alerts_active ?? 0) > 0 ? 'text-rose-600' : 'text-[#00338D]'"
          >
            {{ stats.alerts_active ?? 0 }}
          </strong>
          <span class="text-xs text-slate-400">
            <span v-if="(stats.alerts_critical ?? 0) > 0" class="text-rose-500 font-semibold">
              {{ stats.alerts_critical }} urgentes
            </span>
            <span v-else>sin urgencias</span>
          </span>
        </div>
      </div>

      <!-- KPIs secundarios -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <span class="text-sm text-slate-500">OCs pendientes</span>
          <strong class="mt-2 block text-xl text-[#00338D]">{{ stats.purchase_orders_pending ?? 0 }}</strong>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <span class="text-sm text-slate-500">Proveedores activos</span>
          <strong class="mt-2 block text-xl text-[#00338D]">{{ stats.suppliers_active ?? 0 }}</strong>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <span class="text-sm text-slate-500">Movimientos (7 días)</span>
          <strong class="mt-2 block text-xl text-[#00338D]">{{ stats.movements_week ?? 0 }}</strong>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <span class="text-sm text-slate-500">Devoluciones del mes</span>
          <strong class="mt-2 block text-xl text-[#00338D]">{{ stats.returns_month ?? 0 }}</strong>
        </div>
      </div>

      <!-- Infraestructura resumida -->
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-bold text-[#00338D] mb-3">Infraestructura</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <span class="text-slate-500 block">Almacenes</span>
            <strong class="text-slate-800 text-lg">{{ stats.warehouses ?? 0 }}</strong>
          </div>
          <div>
            <span class="text-slate-500 block">Ubicaciones</span>
            <strong class="text-slate-800 text-lg">{{ stats.locations ?? 0 }}</strong>
          </div>
        </div>
      </div>
    </section>
  </Equipo4Layout>
</template>
