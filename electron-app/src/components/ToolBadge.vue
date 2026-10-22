<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ tool: string; size?: 'sm' | 'md' }>()

// Soft pastel per tool, the same set the website uses
const config: Record<string, { label: string; color: string }> = {
  'cursor':      { label: 'Cursor',      color: 'bg-[#e8e8e6]/10 text-[#e8e8e6] border-[#e8e8e6]/20' },
  'windsurf':    { label: 'Windsurf',    color: 'bg-[#9fd3d8]/10 text-[#9fd3d8] border-[#9fd3d8]/20' },
  'claude-code': { label: 'Claude Code', color: 'bg-[#e7b68f]/10 text-[#e7b68f] border-[#e7b68f]/25' },
  'codex':       { label: 'Codex',       color: 'bg-[#a9d4c4]/10 text-[#a9d4c4] border-[#a9d4c4]/25' },
  'copilot-cli': { label: 'Copilot',     color: 'bg-[#b8bff0]/10 text-[#b8bff0] border-[#b8bff0]/25' },
  'cline':       { label: 'Cline',       color: 'bg-[#eeeaa2]/10 text-[#eeeaa2] border-[#eeeaa2]/25' },
  'continue':    { label: 'Continue',    color: 'bg-[#c9b6e6]/10 text-[#c9b6e6] border-[#c9b6e6]/25' },
}

const item = computed(() => config[props.tool] ?? { label: props.tool, color: 'bg-slate-500/10 text-slate-300 border-slate-500/30' })
const sz   = computed(() => props.size === 'sm' ? 'text-[11px] px-2 py-0.5' : 'text-xs px-2.5 py-1')
</script>

<template>
  <span class="inline-flex items-center rounded-full border font-semibold whitespace-nowrap" :class="[item.color, sz]">
    {{ item.label }}
  </span>
</template>
