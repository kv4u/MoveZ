<script setup lang="ts">
import { ref, onMounted } from 'vue'
import LogStream from '@/components/LogStream.vue'

const lines   = ref<string[]>([])
const running = ref(false)
const done    = ref(false)
const success = ref(false)

async function run() {
  lines.value   = []
  running.value = true
  done.value    = false

  const off = window.bridge.onDoctorLine((line) => lines.value.push(line))

  try {
    const result = await window.bridge.runDoctor()
    success.value = result.success
  } finally {
    off()
    running.value = false
    done.value    = true
  }
}

onMounted(run)
</script>

<template>
  <div class="p-8 max-w-xl mx-auto">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-slate-100 mb-1">Doctor</h1>
      <p class="text-slate-500 text-sm">Verify system requirements for MoveZ</p>
    </div>

    <div
      v-if="done"
      class="mb-5 px-4 py-3 rounded-lg text-sm font-medium"
      :class="success ? 'bg-green-900/20 text-green-400 border border-green-700/30'
                      : 'bg-red-900/20 text-red-400 border border-red-700/30'"
    >
      {{ success ? '✓ All checks passed' : '✗ Some checks failed — see output below' }}
    </div>

    <LogStream :lines="lines" title="Diagnostics" />

    <button
      class="mt-5 px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-slate-300
             hover:bg-slate-700 transition-colors disabled:opacity-50"
      :disabled="running"
      @click="run"
    >
      {{ running ? 'Running…' : '↻ Re-run checks' }}
    </button>
  </div>
</template>
