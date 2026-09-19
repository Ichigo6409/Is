<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] }
})

const search = ref('')

const filteredRows = computed(() => {
  if (!search.value) return props.rows
  const q = search.value.toLowerCase()
  return props.rows.filter(r =>
    Object.values(r).some(v => String(v ?? '').toLowerCase().includes(q))
  )
})
</script>

<template>
  <section class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-[#00338D]">{{ title }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
      </div>
      <div class="flex items-center gap-2">
        <input v-model="search" placeholder="Buscar..." class="px-3 py-2 text-sm rounded-lg border border-slate-300 bg-white focus:border-[#0284C7] focus:ring-[#0284C7]" />
        <slot name="toolbar">
          <button class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg bg-[#00338D] text-white hover:bg-[#0284C7] transition">Nuevo</button>
        </slot>
      </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div class="border-b border-slate-200 px-6 py-4 font-semibold text-[#00338D] text-sm uppercase tracking-wide">Información</div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
          <thead class="bg-slate-50 text-xs uppercase text-slate-600">
            <tr>
              <th v-for="c in columns" :key="c" class="px-6 py-3 font-semibold">{{ c }}</th>
              <th v-if="$slots.actions" class="px-6 py-3 font-semibold text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="(r, i) in filteredRows" :key="r._id || i" class="hover:bg-slate-50 transition">
              <td v-for="c in columns" :key="c" class="px-6 py-4 text-slate-700">{{ r[c] ?? '—' }}</td>
              <td v-if="$slots.actions" class="px-6 py-4 text-right whitespace-nowrap">
                <slot name="actions" :row="r" :index="i" />
              </td>
            </tr>
            <tr v-if="!filteredRows.length">
              <td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="px-6 py-8 text-center text-slate-500">Sin registros para mostrar.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>
