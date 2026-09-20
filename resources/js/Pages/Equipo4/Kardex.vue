<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import Equipo4Layout from '../../Layouts/Equipo4Layout.vue'
import Team4Module from '../../Components/Team4Module.vue'

const props = defineProps({
  movements: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) }
})

const columns = ['Fecha', 'Tipo', 'SKU', 'Producto', 'Cantidad', 'Motivo', 'Referencia']

const typeOptions = [
  { value: '', label: 'Todos los tipos' },
  { value: 'RECEIPT', label: 'RECEIPT' },
  { value: 'SALE', label: 'SALE' },
  { value: 'RETURN_IN', label: 'RETURN_IN' },
  { value: 'RETURN_OUT', label: 'RETURN_OUT' },
  { value: 'ADJUSTMENT', label: 'ADJUSTMENT' },
  { value: 'TRANSFER_IN', label: 'TRANSFER_IN' },
  { value: 'TRANSFER_OUT', label: 'TRANSFER_OUT' },
  { value: 'QUARANTINE', label: 'QUARANTINE' },
  { value: 'SHRINKAGE', label: 'SHRINKAGE' },
]

const selectedType = ref(props.filters.type || '')

function onTypeChange() {
  router.get('/equipo4/kardex', {
    q: props.filters.q || undefined,
    type: selectedType.value || undefined,
    page: 1
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  })
}

const formattedMovements = computed(() => {
  return props.movements.map(m => ({
    ...m,
    Fecha: m.created_at,
    Tipo: m.type,
    SKU: m.product_sku,
    Producto: m.product_name,
    Cantidad: m.quantity,
    Motivo: m.reason,
    Referencia: m.external_reference,
  }))
})
</script>

<template>
  <Equipo4Layout>
    <Team4Module
      title="Kardex y movimientos"
      subtitle="Entradas, salidas, ventas, devoluciones, ajustes, transferencias y mermas."
      :columns="columns"
      :rows="formattedMovements"
      :pagination="pagination"
      :filters="filters"
      search-route="/equipo4/kardex"
    >
      <template #toolbar>
        <select
          v-model="selectedType"
          @change="onTypeChange"
          class="px-3 py-2 text-sm rounded-lg border border-slate-300 bg-white focus:border-[#0284C7] focus:ring-[#0284C7]"
        >
          <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </template>
    </Team4Module>
  </Equipo4Layout>
</template>
