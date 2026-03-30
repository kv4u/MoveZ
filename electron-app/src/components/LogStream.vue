<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'

const props = defineProps<{ lines: string[]; title?: string }>()
const container = ref<HTMLElement>()

watch(() => props.lines.length, async () => {
  await nextTick()
  if (container.value) {
    container.value.scrollTop = container.value.scrollHeight
  }
})
</script>

<template>
  <div class="rounded-lg border border-slate-700 overflow-hidden">
    <div v-if="title" class="px-3 py-2 bg-slate-800 border-b border-slate-700 text-xs text-slate-400">
      {{ title }}
    </div>
    <div ref="container" class="bg-slate-900 p-3 font-mono text-xs h-40 overflow-y-auto space-y-0.5">
      <div
        v-for="(line, i) in lines"
        :key="i"
        class="leading-5"
        :class="line.includes('✓') || line.includes('OK') ? 'text-green-400'
              : line.includes('✗') || line.includes('ERROR') || line.includes('FAIL') ? 'text-red-400'
              : line.includes('WARN') ? 'text-yellow-400'
              : 'text-slate-400'"
      >{{ line }}</div>
      <div v-if="!lines.length" class="text-slate-600 italic">Waiting…</div>
    </div>
  </div>
</template>
