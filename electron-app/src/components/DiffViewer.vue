<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ file: string; diff: string }>()

const lines = computed(() =>
  props.diff.split('\n').map((line) => ({
    text: line,
    type: line.startsWith('+') && !line.startsWith('+++') ? 'add'
        : line.startsWith('-') && !line.startsWith('---') ? 'del'
        : line.startsWith('@@') ? 'hunk'
        : 'ctx'
  }))
)
</script>

<template>
  <div class="rounded-lg border border-slate-700 overflow-hidden text-xs font-mono">
    <div class="px-3 py-2 bg-slate-800 border-b border-slate-700 flex items-center gap-2">
      <span class="text-slate-400">📄</span>
      <span class="text-slate-300">{{ file }}</span>
    </div>
    <div class="overflow-x-auto max-h-64 overflow-y-auto bg-slate-900">
      <div
        v-for="(line, i) in lines"
        :key="i"
        class="px-3 py-0.5 leading-5 whitespace-pre"
        :class="{
          'bg-green-950/60 text-green-300': line.type === 'add',
          'bg-red-950/60 text-red-300':    line.type === 'del',
          'text-brand-400 bg-slate-800/50':  line.type === 'hunk',
          'text-slate-400':                  line.type === 'ctx'
        }"
      >{{ line.text }}</div>
    </div>
  </div>
</template>
