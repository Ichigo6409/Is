<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import logoUrl from '@/../images/campus-digital-logo.png'

const page = usePage()

const navGroups = [
  {
    label: 'Dashboard',
    href: '/equipo4',
    match: (url) => url === '/equipo4',
  },
  {
    label: 'Catálogo',
    href: '/equipo4/productos',
    match: (url) =>
      url.startsWith('/equipo4/productos') ||
      url.startsWith('/equipo4/souvenirs') ||
      url.startsWith('/equipo4/costos'),
  },
  {
    label: 'Stock',
    href: '/equipo4/inventario',
    match: (url) =>
      url.startsWith('/equipo4/inventario') ||
      url.startsWith('/equipo4/almacenes') ||
      url.startsWith('/equipo4/kardex') ||
      url.startsWith('/equipo4/reservas'),
  },
  {
    label: 'Compras',
    href: '/equipo4/proveedores',
    match: (url) =>
      url.startsWith('/equipo4/proveedores') ||
      url.startsWith('/equipo4/compras') ||
      url.startsWith('/equipo4/recepciones'),
  },
  {
    label: 'Operación',
    href: '/equipo4/devoluciones',
    match: (url) =>
      url.startsWith('/equipo4/devoluciones') ||
      url.startsWith('/equipo4/alertas') ||
      url.startsWith('/equipo4/conteos'),
  },
]

const groupModules = {
  'Catálogo': [
    { label: 'Productos', href: '/equipo4/productos' },
    { label: 'Souvenirs', href: '/equipo4/souvenirs' },
    { label: 'Costos', href: '/equipo4/costos' },
  ],
  'Stock': [
    { label: 'Inventario', href: '/equipo4/inventario' },
    { label: 'Almacenes', href: '/equipo4/almacenes' },
    { label: 'Kardex', href: '/equipo4/kardex' },
    { label: 'Reservas', href: '/equipo4/reservas' },
  ],
  'Compras': [
    { label: 'Proveedores', href: '/equipo4/proveedores' },
    { label: 'Compras', href: '/equipo4/compras' },
    { label: 'Recepciones', href: '/equipo4/recepciones' },
  ],
  'Operación': [
    { label: 'Devoluciones', href: '/equipo4/devoluciones' },
    { label: 'Alertas', href: '/equipo4/alertas' },
    { label: 'Conteos', href: '/equipo4/conteos' },
  ],
}

function isActive(group) {
  return group.match(page.url)
}

const activeGroup = computed(() => {
  return navGroups.find(g => isActive(g))?.label ?? null
})

function isModuleActive(module) {
  return page.url.startsWith(module.href)
}
</script>

<template>
  <div class="min-h-screen bg-[#F5F8FC] text-slate-800 antialiased">
    <header class="sticky top-0 z-40 bg-white border-b border-slate-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
          <a href="/equipo4" class="flex items-center">
            <img :src="logoUrl" alt="Campus Digital" class="h-8 w-auto">
          </a>

          <nav class="hidden md:flex items-center gap-1">
            <a
              v-for="group in navGroups"
              :key="group.label"
              :href="group.href"
              :class="isActive(group)
                ? 'inline-flex items-center px-3 py-2 text-sm font-semibold text-[#00338D] bg-[#F5F8FC] rounded-md'
                : 'inline-flex items-center px-3 py-2 text-sm font-medium text-slate-600 hover:text-[#00338D] hover:bg-[#F5F8FC] rounded-md transition'"
            >
              {{ group.label }}
            </a>
          </nav>

          <div class="flex items-center gap-3">
            <span class="hidden md:inline text-sm text-slate-600">Administrador de inventario</span>
            <div class="w-9 h-9 rounded-full bg-[#00338D] text-white grid place-items-center text-xs font-bold">E4</div>
          </div>
        </div>
      </div>
    </header>

    <div
      v-if="activeGroup && groupModules[activeGroup]"
      class="bg-white border-b border-slate-200"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="flex gap-6 -mb-px overflow-x-auto">
          <a
            v-for="mod in groupModules[activeGroup]"
            :key="mod.href"
            :href="mod.href"
            :class="isModuleActive(mod)
              ? 'inline-flex items-center py-3 text-sm font-semibold text-[#00338D] border-b-2 border-[#00338D] whitespace-nowrap'
              : 'inline-flex items-center py-3 text-sm font-medium text-slate-500 hover:text-[#00338D] hover:border-b-2 hover:border-slate-300 border-b-2 border-transparent whitespace-nowrap transition'"
          >
            {{ mod.label }}
          </a>
        </nav>
      </div>
    </div>

    <main>
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        <slot />
      </div>
    </main>

    <footer class="border-t border-slate-200 bg-white mt-12">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 text-xs text-slate-500">
        Campus Digital · Equipo 4 · 2026
      </div>
    </footer>
  </div>
</template>
