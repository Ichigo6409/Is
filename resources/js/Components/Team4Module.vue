<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  pagination: {
    type: Object,
    default: () => ({
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: 0,
      from: 0,
      to: 0,
    })
  },
  filters: {
    type: Object,
    default: () => ({ q: '' })
  },
  searchRoute: {
    type: String,
    required: true
  }
})

const search = ref(props.filters.q || '')

let debounceTimer = null
watch(search, (value) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    router.get(props.searchRoute, {
      q: value || undefined,
      page: 1
    }, {
      preserveState: true,
      preserveScroll: true,
      replace: true
    })
  }, 300)
})

function goToPage(page) {
  if (page < 1 || page > props.pagination.last_page) return
  router.get(props.searchRoute, {
    q: search.value || undefined,
    page: page
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  })
}

function goToPrev() {
  goToPage(props.pagination.current_page - 1)
}

function goToNext() {
  goToPage(props.pagination.current_page + 1)
}

const visiblePages = computed(() => {
  const current = props.pagination.current_page
  const last = props.pagination.last_page
  const delta = 2
  const pages = []
  const start = Math.max(1, current - delta)
  const end = Math.min(last, current + delta)
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
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
        <input
          v-model="search"
          placeholder="Buscar..."
          class="px-3 py-2 text-sm rounded-lg border border-slate-300 bg-white focus:border-[#0284C7] focus:ring-[#0284C7]"
        />
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
            <tr v-for="(r, i) in rows" :key="r._id || i" class="hover:bg-slate-50 transition">
              <td v-for="c in columns" :key="c" class="px-6 py-4 text-slate-700">{{ r[c] ?? '—' }}</td>
              <td v-if="$slots.actions" class="px-6 py-4 text-right whitespace-nowrap">
                <slot name="actions" :row="r" :index="i" />
              </td>
            </tr>
            <tr v-if="!rows.length">
              <td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="px-6 py-8 text-center text-slate-500">
                {{ search ? 'No se encontraron resultados para "' + search + '"' : 'Sin registros para mostrar.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.total > 0" class="border-t border-slate-200 px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
        <p class="text-xs text-slate-500">
          Mostrando <strong>{{ pagination.from }}</strong> a <strong>{{ pagination.to }}</strong> de <strong>{{ pagination.total }}</strong> registros
        </p>

        <div class="flex items-center gap-1">
          <button
            @click="goToPrev"
            :disabled="pagination.current_page === 1"
            class="px-3 py-1 text-xs font-medium rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition"
          >
            ← Anterior
          </button>

          <button
            v-for="p in visiblePages"
            :key="p"
            @click="goToPage(p)"
            :class="p === pagination.current_page
              ? 'px-3 py-1 text-xs font-semibold rounded bg-[#00338D] text-white'
              : 'px-3 py-1 text-xs font-medium rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 transition'"
          >
            {{ p }}
          </button>

          <button
            @click="goToNext"
            :disabled="pagination.current_page === pagination.last_page"
            class="px-3 py-1 text-xs font-medium rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition"
          >
            Siguiente →
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
