<template>
  <div :class="['rounded-lg p-4 mb-3', roleClass]">
    <div class="flex items-center gap-2 mb-2">
      <span class="text-xs font-bold uppercase tracking-wide opacity-60">
        {{ turn.role }}
      </span>
      <span class="text-xs text-gray-400">
        {{ new Date(turn.timestamp).toLocaleString() }}
      </span>
    </div>

    <div class="whitespace-pre-wrap text-sm leading-relaxed">{{ turn.content }}</div>

    <!-- File Diffs -->
    <template v-if="turn.file_diffs && turn.file_diffs.length">
      <div v-for="diff in turn.file_diffs" :key="diff.file" class="mt-3">
        <p class="text-xs text-gray-400 mb-1 font-mono">{{ diff.file }}</p>
        <DiffViewer :diff="diff.diff" />
      </div>
    </template>

    <!-- Reasoning trace -->
    <details v-if="turn.reasoning_trace" class="mt-3">
      <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600">
        Reasoning trace
      </summary>
      <pre class="mt-2 text-xs text-gray-500 whitespace-pre-wrap">{{ turn.reasoning_trace }}</pre>
    </details>

    <!-- Tool calls -->
    <template v-if="turn.tool_calls && turn.tool_calls.length">
      <details class="mt-3">
        <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600">
          {{ turn.tool_calls.length }} tool call(s)
        </summary>
        <pre class="mt-2 text-xs bg-gray-100 rounded p-2 overflow-x-auto">{{ JSON.stringify(turn.tool_calls, null, 2) }}</pre>
      </details>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import DiffViewer from './DiffViewer.vue';
import type { TurnDTO } from '@/types';

const props = defineProps<{ turn: TurnDTO }>();

const roleClass = computed(() =>
  props.turn.role === 'user'
    ? 'bg-blue-50 border border-blue-100'
    : 'bg-amber-50 border border-amber-100',
);
</script>
