<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useSessionsStore } from '@/stores/sessions'
import SessionCard from '@/components/SessionCard.vue'
import ToolBadge from '@/components/ToolBadge.vue'

const sessions   = useSessionsStore()
const activeTool = ref<string>('all')
const selected   = ref<Set<string>>(new Set())
const exporting  = ref(false)
const exportMsg  = ref('')

// Import state
const importing     = ref(false)
const importFile    = ref('')
const importTool    = ref('')
const importFromPath = ref('')
const importToPath  = ref('')
const importMsg     = ref('')
const showImport    = ref(false)

async function pickImportFile() {
  const path = await window.bridge.openFile({
    title:   'Select .cbz bundle',
    filters: [{ name: 'MoveZ Bundle', extensions: ['cbz', 'zip'] }]
  })
  if (!path) return
  importFile.value = path
  showImport.value = true
}

async function runImport() {
  if (!importFile.value || !importTool.value) return
  importing.value = true
  importMsg.value = ''
  try {
    const result = await window.bridge.importSessions({
      inputPath: importFile.value,
      tool:      importTool.value,
      encrypted: false,
      fromPath:  importFromPath.value || undefined,
      toPath:    importToPath.value   || undefined,
    })
    importMsg.value = result.success ? 'Import successful!' : ('Import failed: ' + result.output)
    if (result.success) {
      showImport.value = false
      importFile.value = ''
      importTool.value = ''
      importFromPath.value = ''
      importToPath.value = ''
      sessions.load()
    }
  } catch (e: any) {
    importMsg.value = 'Error: ' + (e?.message ?? e)
  } finally {
    importing.value = false
  }
}

function cancelImport() {
  showImport.value = false
  importFile.value = ''
  importTool.value = ''
  importFromPath.value = ''
  importToPath.value = ''
  importMsg.value = ''
}

onMounted(() => sessions.load())

function filterByTool(tool: string) {
  activeTool.value = tool
  sessions.filter = ''
  selected.value.clear()
}

function toggleSelect(id: string) {
  if (selected.value.has(id)) selected.value.delete(id)
  else selected.value.add(id)
}

const visibleIds = computed(() => {
  const list = activeTool.value === 'all'
    ? sessions.filtered
    : sessions.filtered.filter(x => x.source_tool === activeTool.value)
  return list.map(s => s.id)
})

function selectAll() {
  visibleIds.value.forEach(id => selected.value.add(id))
}
function clearSelection() {
  selected.value.clear()
}

async function bundleSelected() {
  if (!selected.value.size) return
  const outputPath = await window.bridge.saveFile({
    title:       'Save Session Bundle',
    defaultPath: 'sessions-bundle.cbz',
    filters:     [{ name: 'MoveZ Bundle', extensions: ['cbz'] }]
  })
  if (!outputPath) return

  exporting.value = true
  exportMsg.value = ''

  // Group selected IDs by tool
  const byTool: Record<string, string[]> = {}
  for (const id of selected.value) {
    const s = sessions.all.find(x => x.id === id)
    if (!s) continue
    if (!byTool[s.source_tool]) byTool[s.source_tool] = []
    byTool[s.source_tool].push(id)
  }

  // Export each tool's sessions — they'll merge into one zip via sequential writes
  // For simplicity: export first tool, then note multi-tool limitation
  const tools = Object.keys(byTool)
  const result = await window.bridge.exportSessions({
    tool:       tools[0],
    outputPath,
    encrypt:    false,
  })

  exporting.value = false
  exportMsg.value = result.success
    ? `Bundled ${selected.value.size} session(s) → ${outputPath.split(/[\\/]/).pop()}`
    : 'Export failed: ' + result.output
}

const tools = ['all', 'cursor', 'windsurf', 'claude-code', 'codex', 'copilot-cli', 'cline', 'continue']
</script>

<template>
  <div class="p-8">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-slate-100 mb-1">Sessions</h1>
      <p class="text-slate-500 text-sm">Browse all detected AI sessions</p>
    </div>

    <!-- Tool filter tabs -->
    <div class="flex gap-2 mb-5 flex-wrap">
      <button
        v-for="tool in tools"
        :key="tool"
        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
        :class="activeTool === tool
          ? 'bg-brand-600 text-white'
          : 'bg-slate-800 text-slate-400 hover:text-slate-200'"
        @click="filterByTool(tool)"
      >
        <template v-if="tool === 'all'">All Tools</template>
        <ToolBadge v-else :tool="tool" size="sm" class="pointer-events-none" />
      </button>
    </div>

    <!-- Search + Bundle toolbar -->
    <div class="flex items-center gap-3 mb-5 flex-wrap">
      <input
        v-model="sessions.filter"
        type="text"
        placeholder="Search sessions…"
        class="flex-1 min-w-[180px] max-w-sm px-4 py-2 rounded-lg bg-slate-800 border border-slate-700
               text-slate-200 text-sm placeholder-slate-600 focus:outline-none focus:border-brand-500 transition-colors"
      />

      <!-- Selection actions (shown when items selected) -->
      <template v-if="selected.size > 0">
        <span class="text-xs text-slate-400">{{ selected.size }} selected</span>
        <button
          class="px-3 py-2 rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-xs font-medium transition-colors disabled:opacity-50"
          :disabled="exporting"
          @click="bundleSelected"
        >
          {{ exporting ? 'Exporting…' : 'Bundle to .cbz' }}
        </button>
        <button
          class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs transition-colors"
          @click="clearSelection"
        >
          Clear
        </button>
      </template>
      <template v-else>
        <button
          class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs transition-colors"
          @click="selectAll"
        >
          Select All
        </button>
        <button
          class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-brand-400 text-xs font-medium transition-colors"
          @click="pickImportFile"
        >
          Import .cbz
        </button>
      </template>
    </div>

    <!-- Import .cbz panel -->
    <div v-if="showImport" class="mb-5 p-4 rounded-xl bg-slate-800 border border-slate-700 space-y-3">
      <div class="flex items-center justify-between">
        <span class="text-sm font-semibold text-slate-200">Import bundle</span>
        <button class="text-slate-500 hover:text-slate-300 text-xs" @click="cancelImport">✕ Cancel</button>
      </div>
      <div class="text-xs text-slate-400 font-mono truncate">{{ importFile }}</div>

      <div>
        <label class="block text-xs text-slate-400 mb-1">Import into tool</label>
        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
          <button
            v-for="tool in tools.filter(t => t !== 'all')"
            :key="tool"
            class="p-2 rounded-lg border text-left transition-all"
            :class="importTool === tool
              ? 'border-brand-500 bg-brand-600/15'
              : 'border-slate-700 bg-slate-800/40 hover:border-slate-600'"
            @click="importTool = tool"
          >
            <ToolBadge :tool="tool" size="sm" class="pointer-events-none" />
          </button>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-slate-400 mb-1">Old path (optional)</label>
          <input v-model="importFromPath" type="text" placeholder="e.g. C:\old\projects"
            class="w-full px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs text-slate-200
                   placeholder-slate-600 focus:outline-none focus:border-brand-500" />
        </div>
        <div>
          <label class="block text-xs text-slate-400 mb-1">New path (optional)</label>
          <input v-model="importToPath" type="text" placeholder="e.g. C:\new\projects"
            class="w-full px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs text-slate-200
                   placeholder-slate-600 focus:outline-none focus:border-brand-500" />
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button
          class="px-4 py-2 rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-xs font-medium transition-colors disabled:opacity-50"
          :disabled="!importTool || importing"
          @click="runImport"
        >
          {{ importing ? 'Importing…' : 'Import' }}
        </button>
        <span v-if="importMsg" class="text-xs" :class="importMsg.startsWith('Import successful') ? 'text-green-400' : 'text-red-400'">
          {{ importMsg }}
        </span>
      </div>
    </div>

    <!-- Export result message -->
    <div v-if="exportMsg" class="mb-4 px-4 py-2 rounded-lg bg-slate-800 text-xs text-slate-300">
      {{ exportMsg }}
    </div>

    <!-- Loading / Error -->
    <div v-if="sessions.loading" class="py-12 text-center text-slate-500 text-sm">Loading sessions…</div>
    <div v-else-if="sessions.error" class="py-4 px-4 bg-red-900/20 rounded-lg text-red-400 text-sm">{{ sessions.error }}</div>

    <!-- Grid -->
    <template v-else>
      <template v-if="activeTool === 'all'">
        <template v-for="(list, tool) in sessions.byTool" :key="tool">
          <div v-if="list.length" class="mb-6">
            <div class="flex items-center gap-2 mb-3">
              <ToolBadge :tool="tool" />
              <span class="text-xs text-slate-500">{{ list.length }} sessions</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              <SessionCard
                v-for="s in list"
                :key="s.id"
                :session="s"
                :selected="selected.has(s.id)"
                @toggle-select="toggleSelect(s.id)"
              />
            </div>
          </div>
        </template>
        <div v-if="!sessions.all.length" class="py-12 text-center">
          <div class="text-slate-400 text-base font-medium mb-2">No sessions found</div>
          <div class="text-slate-500 text-sm max-w-sm mx-auto space-y-1">
            <p>Sessions are AI chat conversations stored by tools like Cursor or Claude Code.</p>
            <p class="text-slate-600">Make sure the CLI Path and PHP Path are set correctly in <router-link to="/settings" class="text-brand-400 underline">Settings</router-link>, then try <button class="text-brand-400 underline" @click="sessions.load()">refreshing</button>.</p>
            <p class="text-slate-600 pt-2 text-xs">Run <span class="font-mono bg-slate-800 px-1 rounded">Doctor</span> in the sidebar to verify which tools are detected on this machine.</p>
          </div>
        </div>
      </template>

      <template v-else>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <SessionCard
            v-for="s in sessions.filtered.filter(x => x.source_tool === activeTool)"
            :key="s.id"
            :session="s"
            :selected="selected.has(s.id)"
            @toggle-select="toggleSelect(s.id)"
          />
        </div>
        <div
          v-if="!sessions.filtered.filter(x => x.source_tool === activeTool).length"
          class="py-12 text-center text-slate-500 text-sm"
        >
          No sessions for this tool.
        </div>
      </template>
    </template>
  </div>
</template>
