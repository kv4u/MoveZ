<script setup lang="ts">
import { ref, computed } from 'vue'
import LogStream from '@/components/LogStream.vue'
import ToolBadge from '@/components/ToolBadge.vue'

const TOOLS = ['cursor', 'windsurf', 'claude-code', 'codex', 'copilot-cli', 'cline', 'continue']

// Wizard state
const step = ref(1)
const STEPS = ['Source Tool', 'Target Tool', 'Paths', 'Confirm', 'Done']

const fromTool  = ref('')
const toTool    = ref('')
const fromPath  = ref('')
const toPath    = ref('')
const logs      = ref<string[]>([])
const running   = ref(false)
const result    = ref<{ success: boolean; output: string } | null>(null)

const canNext = computed(() => {
  if (step.value === 1) return !!fromTool.value
  if (step.value === 2) return !!toTool.value
  return true
})

function next() { if (step.value < 4) step.value++ }
function back() { if (step.value > 1) step.value-- }

async function run() {
  running.value = true
  logs.value    = []
  result.value  = null

  const off = window.bridge.onMigrateProgress((line) => {
    logs.value.push(line)
  })

  try {
    result.value = await window.bridge.transfer({
      fromTool:  fromTool.value,
      toTool:    toTool.value,
      fromPath:  fromPath.value || undefined,
      toPath:    toPath.value   || undefined
    })
  } finally {
    off()
    running.value = false
    step.value = 5
  }
}

function reset() {
  step.value   = 1
  fromTool.value = ''
  toTool.value   = ''
  fromPath.value = ''
  toPath.value   = ''
  logs.value     = []
  result.value   = null
}
</script>

<template>
  <div class="p-8 max-w-xl mx-auto">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-slate-100 mb-1">Migration Wizard</h1>
      <p class="text-slate-500 text-sm">Transfer sessions between AI tools</p>
    </div>

    <!-- Step indicator -->
    <div class="flex items-center gap-1 mb-8">
      <template v-for="(label, i) in STEPS" :key="i">
        <div
          class="flex items-center gap-1.5 text-xs font-medium transition-colors"
          :class="step === i + 1 ? 'text-brand-400'
                : step > i + 1 ? 'text-slate-500'
                : 'text-slate-700'"
        >
          <div
            class="w-5 h-5 rounded-full flex items-center justify-center text-xs"
            :class="step > i + 1 ? 'bg-brand-600 text-white'
                  : step === i + 1 ? 'bg-brand-600/30 text-brand-300 ring-1 ring-brand-500'
                  : 'bg-slate-800 text-slate-600'"
          >{{ i + 1 }}</div>
          <span class="hidden sm:block">{{ label }}</span>
        </div>
        <div v-if="i < STEPS.length - 1" class="flex-1 h-px bg-slate-800 mx-1" />
      </template>
    </div>

    <!-- Step 1: Source tool -->
    <div v-if="step === 1">
      <h2 class="text-sm font-semibold text-slate-300 mb-4">Select source tool</h2>
      <div class="grid grid-cols-2 gap-2">
        <button
          v-for="tool in TOOLS"
          :key="tool"
          class="p-3 rounded-xl border text-left transition-all"
          :class="fromTool === tool
            ? 'border-brand-500 bg-brand-600/15'
            : 'border-slate-700 bg-slate-800/40 hover:border-slate-600'"
          @click="fromTool = tool"
        >
          <ToolBadge :tool="tool" size="sm" />
        </button>
      </div>
    </div>

    <!-- Step 2: Target tool -->
    <div v-else-if="step === 2">
      <h2 class="text-sm font-semibold text-slate-300 mb-4">Select target tool</h2>
      <div class="grid grid-cols-2 gap-2">
        <button
          v-for="tool in TOOLS"
          :key="tool"
          class="p-3 rounded-xl border text-left transition-all"
          :class="toTool === tool
            ? 'border-brand-500 bg-brand-600/15'
            : 'border-slate-700 bg-slate-800/40 hover:border-slate-600'"
          @click="toTool = tool"
        >
          <ToolBadge :tool="tool" size="sm" />
        </button>
      </div>
    </div>

    <!-- Step 3: Path remapping -->
    <div v-else-if="step === 3">
      <h2 class="text-sm font-semibold text-slate-300 mb-2">Path remapping</h2>
      <p class="text-xs text-slate-500 mb-4">
        Optional: remap project paths if transferring to a different machine.
      </p>
      <div class="space-y-4">
        <div>
          <label class="block text-xs text-slate-400 mb-1">Old machine project path</label>
          <input
            v-model="fromPath"
            type="text"
            placeholder="/Users/old/projects/myapp"
            class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-slate-200
                   placeholder-slate-600 focus:outline-none focus:border-brand-500"
          />
        </div>
        <div>
          <label class="block text-xs text-slate-400 mb-1">New machine project path</label>
          <input
            v-model="toPath"
            type="text"
            placeholder="/home/new/projects/myapp"
            class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-slate-200
                   placeholder-slate-600 focus:outline-none focus:border-brand-500"
          />
        </div>
      </div>
    </div>

    <!-- Step 4: Confirm -->
    <div v-else-if="step === 4">
      <h2 class="text-sm font-semibold text-slate-300 mb-4">Confirm migration</h2>
      <div class="space-y-3 mb-6">
        <div class="flex items-center justify-between py-3 border-b border-slate-800">
          <span class="text-sm text-slate-500">From</span>
          <ToolBadge :tool="fromTool" />
        </div>
        <div class="flex items-center justify-between py-3 border-b border-slate-800">
          <span class="text-sm text-slate-500">To</span>
          <ToolBadge :tool="toTool" />
        </div>
        <div v-if="fromPath" class="flex items-center justify-between py-3 border-b border-slate-800">
          <span class="text-sm text-slate-500">Path remap</span>
          <span class="text-xs text-slate-400 font-mono">{{ fromPath }} → {{ toPath }}</span>
        </div>
      </div>
    </div>

    <!-- Step 5: Done -->
    <div v-else-if="step === 5">
      <div class="text-center py-4 mb-4">
        <div class="text-4xl mb-3">{{ result?.success ? '✓' : '✗' }}</div>
        <div class="text-lg font-semibold" :class="result?.success ? 'text-green-400' : 'text-red-400'">
          {{ result?.success ? 'Migration complete!' : 'Migration failed' }}
        </div>
      </div>
      <LogStream :lines="logs" title="Output" />
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between mt-8">
      <button
        v-if="step > 1 && step < 5"
        class="px-4 py-2 rounded-lg text-sm text-slate-400 hover:text-slate-200 transition-colors"
        @click="back"
      >
        ← Back
      </button>
      <div v-else class="flex-1" />

      <div class="flex gap-2">
        <button
          v-if="step === 5"
          class="px-4 py-2 rounded-lg bg-slate-800 text-sm text-slate-300 hover:bg-slate-700 transition-colors"
          @click="reset"
        >
          Start over
        </button>
        <button
          v-else-if="step === 4"
          class="px-5 py-2 rounded-lg bg-brand-600 hover:bg-brand-500 text-sm font-medium text-white
                 transition-colors disabled:opacity-50"
          :disabled="running"
          @click="run"
        >
          {{ running ? 'Migrating…' : 'Run Migration' }}
        </button>
        <button
          v-else
          class="px-5 py-2 rounded-lg bg-brand-600 hover:bg-brand-500 text-sm font-medium text-white
                 transition-colors disabled:opacity-50"
          :disabled="!canNext"
          @click="next"
        >
          Next →
        </button>
      </div>
    </div>
  </div>
</template>
