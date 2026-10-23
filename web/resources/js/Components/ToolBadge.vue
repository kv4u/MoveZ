<template>
  <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs font-semibold', colorClass]">
    {{ tool }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ tool: string }>();

// Soft pastel per tool, the same set the website and desktop app use
const colorMap: Record<string, string> = {
  cursor: 'bg-[#e8e8e6]/10 text-[#e8e8e6] border-[#e8e8e6]/20',
  windsurf: 'bg-[#9fd3d8]/10 text-[#9fd3d8] border-[#9fd3d8]/25',
  'claude-code': 'bg-[#e7b68f]/10 text-[#e7b68f] border-[#e7b68f]/25',
  codex: 'bg-[#a9d4c4]/10 text-[#a9d4c4] border-[#a9d4c4]/25',
  'copilot-cli': 'bg-[#b8bff0]/10 text-[#b8bff0] border-[#b8bff0]/25',
  cline: 'bg-[#eeeaa2]/10 text-[#eeeaa2] border-[#eeeaa2]/25',
  continue: 'bg-[#c9b6e6]/10 text-[#c9b6e6] border-[#c9b6e6]/25',
};

const colorClass = computed(
  () => colorMap[props.tool] ?? 'bg-gray-500/10 text-gray-300 border-gray-500/30',
);
</script>
