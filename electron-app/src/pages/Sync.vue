<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useSettingsStore } from '@/stores/settings'
import LogStream from '@/components/LogStream.vue'
import ToolBadge from '@/components/ToolBadge.vue'

const TOOLS = ['cursor', 'windsurf', 'claude-code', 'codex', 'copilot-cli', 'cline', 'continue']

const settingsStore = useSettingsStore()
onMounted(() => settingsStore.load())

const mode      = ref<'push' | 'pull'>('push')
const tool      = ref('')
const fromPath  = ref('')
const toPath    = ref('')
const logs      = ref<string[]>([])
const running   = ref(false)
const lastResult = ref<{ success: boolean } | null>(null)

async function run() {
  const { serverUrl, token } = settingsStore.settings
  if (!serverUrl || !token) {
    logs.value = ['ERROR: Server URL and token must be configured in Settings.']
    return
  }

  running.value     = true
  logs.value        = []
  lastResult.value  = null

  const off = window.bridge.onSyncLog((line) => logs.value.push(line))

  try {
    if (mode.value === 'push') {
      lastResult.value = await window.bridge.syncPush({
        token, server: serverUrl,
        tool: tool.value || undefined
      })
    } else {
      if (!tool.value) {
        logs.value = ['ERROR: Target tool is required for pull.']
        running.value = false
        off()
        return
      }
      lastResult.value = await window.bridge.syncPull({
        token, server: serverUrl, tool: tool.value,
        fromPath: fromPath.value || undefined,
        toPath:   toPath.value   || undefined
      })
    }
  } finally {
    off()
    running.value = false
  }
}
</script>

<template>
  <div class="p-8 max-w-xl mx-auto">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-slate-100 mb-1">Sync</h1>
      <p class="text-slate-500 text-sm">Push/pull sessions via encrypted sync server</p>
    </div>

    <!-- Config check -->
    <div
      v-if="!settingsStore.settings.serverUrl || !settingsStore.settings.token"
      class="mb-6 px-4 py-3 rounded-lg bg-yellow-900/20 border border-yellow-700/30 text-yellow-400 text-sm"
    >
      Configure server URL and token in <strong>Settings</strong> first.
    </div>

    <!-- Mode toggle -->
    <div class="flex rounded-lg overflow-hidden border border-slate-700 mb-6 w-fit">
      <button
        class="px-5 py-2 text-sm font-medium transition-colors"
        :class="mode === 'push' ? 'bg-brand-600 text-white' : 'text-slate-400 hover:text-slate-200'"
        @click="mode = 'push'"
      >↑ Push</button>
      <button
        class="px-5 py-2 text-sm font-medium transition-colors"
        :class="mode === 'pull' ? 'bg-brand-600 text-white' : 'text-slate-400 hover:text-slate-200'"
        @click="mode = 'pull'"
      >↓ Pull</button>
    </div>

    <!-- Options -->
    <div class="space-y-4 mb-6">
      <div>
        <label class="block text-xs text-slate-400 mb-1">
          {{ mode === 'push' ? 'Tool (leave blank for all)' : 'Target tool *' }}
        </label>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="t in TOOLS"
            :key="t"
            class="p-2 rounded-lg border text-left transition-all"
            :class="tool === t
              ? 'border-brand-500 bg-brand-600/15'
              : 'border-slate-700 bg-slate-800/40 hover:border-slate-600'"
            @click="tool = tool === t ? '' : t"
          >
            <ToolBadge :tool="t" size="sm" />
          </button>
        </div>
      </div>

      <template v-if="mode === 'pull'">
        <div>
          <label class="block text-xs text-slate-400 mb-1">Old path (optional)</label>
          <input
            v-model="fromPath"
            type="text"
            placeholder="/old/machine/project"
            class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-slate-200
                   placeholder-slate-600 focus:outline-none focus:border-brand-500"
          />
        </div>
        <div>
          <label class="block text-xs text-slate-400 mb-1">New path (optional)</label>
          <input
            v-model="toPath"
            type="text"
            placeholder="/new/machine/project"
            class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-slate-200
                   placeholder-slate-600 focus:outline-none focus:border-brand-500"
          />
        </div>
      </template>
    </div>

    <!-- Run button -->
    <button
      class="w-full py-2.5 rounded-lg bg-brand-600 hover:bg-brand-500 text-sm font-medium text-white
             transition-colors disabled:opacity-50 mb-5"
      :disabled="running"
      @click="run"
    >
      {{ running ? (mode === 'push' ? 'Pushing…' : 'Pulling…') : (mode === 'push' ? '↑ Push Sessions' : '↓ Pull Sessions') }}
    </button>

    <!-- Status -->
    <div
      v-if="lastResult"
      class="mb-4 px-4 py-2 rounded-lg text-sm font-medium"
      :class="lastResult.success ? 'bg-green-900/20 text-green-400' : 'bg-red-900/20 text-red-400'"
    >
      {{ lastResult.success ? 'Success' : 'Failed' }}
    </div>

    <LogStream :lines="logs" title="Sync log" />
  </div>
</template>
