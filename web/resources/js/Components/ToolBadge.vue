<template>
  <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold', colorClass]">
    {{ tool }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ tool: string }>();

const colorMap: Record<string, string> = {
  cursor: 'bg-blue-100 text-blue-800',
  windsurf: 'bg-cyan-100 text-cyan-800',
  'claude-code': 'bg-orange-100 text-orange-800',
  codex: 'bg-green-100 text-green-800',
  'copilot-cli': 'bg-gray-100 text-gray-800',
  cline: 'bg-purple-100 text-purple-800',
  continue: 'bg-yellow-100 text-yellow-800',
};

const colorClass = computed(
  () => colorMap[props.tool] ?? 'bg-slate-100 text-slate-800',
);
</script>
