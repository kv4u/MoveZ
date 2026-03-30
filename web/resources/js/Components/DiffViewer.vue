<template>
  <pre class="text-xs leading-relaxed overflow-x-auto rounded-md bg-gray-900 text-gray-200 p-4 font-mono"><template
    v-for="(line, i) in lines"
    :key="i"
  ><span :class="lineClass(line)">{{ line }}</span>
</template></pre>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ diff: string }>();

const lines = computed(() => props.diff.split('\n'));

function lineClass(line: string): string {
  if (line.startsWith('+')) return 'text-green-400';
  if (line.startsWith('-')) return 'text-red-400';
  if (line.startsWith('@@')) return 'text-blue-400';
  return 'text-gray-300';
}
</script>
