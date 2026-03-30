<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ tool: string; size?: 'sm' | 'md' }>()

const config: Record<string, { label: string; color: string }> = {
  'cursor':      { label: 'Cursor',      color: 'bg-purple-500/20 text-purple-300 border-purple-500/30' },
  'windsurf':    { label: 'Windsurf',    color: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30' },
  'claude-code': { label: 'Claude Code', color: 'bg-orange-500/20 text-orange-300 border-orange-500/30' },
  'codex':       { label: 'Codex',       color: 'bg-green-500/20 text-green-300 border-green-500/30' },
  'copilot-cli': { label: 'Copilot',     color: 'bg-blue-500/20 text-blue-300 border-blue-500/30' },
  'cline':       { label: 'Cline',       color: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30' },
  'continue':    { label: 'Continue',    color: 'bg-pink-500/20 text-pink-300 border-pink-500/30' },
}

const item = computed(() => config[props.tool] ?? { label: props.tool, color: 'bg-slate-500/20 text-slate-300 border-slate-500/30' })
const sz   = computed(() => props.size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-xs px-2.5 py-1')
</script>

<template>
  <span class="inline-flex items-center rounded-full border font-medium" :class="[item.color, sz]">
    {{ item.label }}
  </span>
</template>
