<script setup>
import { ref } from 'vue'
defineProps({ title:String, subtitle:String, kpis:{type:Array,default:()=>[]}, columns:{type:Array,default:()=>[]}, rows:{type:Array,default:()=>[]} })
const search = ref('')
</script>
<template>
  <section class="cd-module">
    <div class="cd-module-head">
      <div><h1>{{ title }}</h1><p>{{ subtitle }}</p></div>
      <div class="cd-actions"><input v-model="search" placeholder="Buscar..." /><button class="cd-btn">Nuevo</button></div>
    </div>
    <div class="cd-kpis">
      <div v-for="k in kpis" :key="k.label" class="cd-card"><span>{{ k.label }}</span><strong>{{ k.value }}</strong></div>
    </div>
    <div class="cd-card cd-table-card">
      <div class="cd-table-title">Información</div>
      <div class="cd-table-wrap">
        <table><thead><tr><th v-for="c in columns" :key="c">{{ c }}</th></tr></thead>
        <tbody><tr v-for="(r,i) in rows" :key="i"><td v-for="c in columns" :key="c">{{ r[c] ?? '—' }}</td></tr>
        <tr v-if="!rows.length"><td :colspan="columns.length || 1">Sin registros para mostrar.</td></tr></tbody></table>
      </div>
    </div>
  </section>
</template>
