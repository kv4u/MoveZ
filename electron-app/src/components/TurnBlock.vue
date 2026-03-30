<script setup lang="ts">
import { ref } from 'vue'
import DiffViewer from './DiffViewer.vue'
import type { TurnDTO } from '@/types/bridge'

defineProps<{ turn: TurnDTO; index: number }>()
const showDiffs = ref(false)
</script>

<template>
  <div class="flex gap-3" :class="turn.role === 'user' ? 'flex-row' : 'flex-row'">
    <!-- Avatar -->
    <div
      class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5"
      :class="turn.role === 'user'
        ? 'bg-brand-600 text-white'
        : 'bg-slate-700 text-slate-300'"
    >
      {{ turn.role === 'user' ? 'U' : 'AI' }}
    </div>

    <div class="flex-1 min-w-0">
      <!-- Header -->
      <div class="flex items-center gap-2 mb-1.5">
        <span class="text-xs font-semibold text-slate-300 capitalize">{{ turn.role }}</span>
        <span class="text-xs text-slate-600">
          {{ new Date(turn.timestamp).toLocaleTimeString() }}
        </span>
        <span v-if="turn.files_referenced.length" class="text-xs text-slate-500">
          · {{ turn.files_referenced.length }} file(s)
        </span>
      </div>

      <!-- Content -->
      <div class="text-sm text-slate-200 leading-relaxed whitespace-pre-wrap break-words">{{ turn.content }}</div>

      <!-- Reasoning trace -->
      <details v-if="turn.reasoning_trace" class="mt-2">
        <summary class="text-xs text-slate-500 cursor-pointer hover:text-slate-400 select-none">
          Reasoning trace
        </summary>
        <p class="mt-1 text-xs text-slate-400 font-mono whitespace-pre-wrap pl-3 border-l border-slate-700">
          {{ turn.reasoning_trace }}
        </p>
      </details>

      <!-- Tool calls -->
      <details v-if="turn.tool_calls?.length" class="mt-2">
        <summary class="text-xs text-slate-500 cursor-pointer hover:text-slate-400 select-none">
          {{ turn.tool_calls.length }} tool call(s)
        </summary>
        <pre class="mt-1 text-xs text-slate-400 font-mono bg-slate-800/50 rounded p-2 overflow-x-auto">{{ JSON.stringify(turn.tool_calls, null, 2) }}</pre>
      </details>

      <!-- File diffs -->
      <div v-if="turn.file_diffs?.length" class="mt-3 space-y-2">
        <button
          class="text-xs text-brand-400 hover:text-brand-300 transition-colors"
          @click="showDiffs = !showDiffs"
        >
          {{ showDiffs ? 'Hide' : 'Show' }} {{ turn.file_diffs.length }} diff(s)
        </button>
        <div v-if="showDiffs" class="space-y-2">
          <DiffViewer
            v-for="d in turn.file_diffs"
            :key="d.file"
            :file="d.file"
            :diff="d.diff"
          />
        </div>
      </div>
    </div>
  </div>
</template>
