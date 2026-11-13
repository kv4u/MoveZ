<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ToolBadge from '@/components/ToolBadge.vue'
import TurnBlock from '@/components/TurnBlock.vue'
import type { SessionDetail } from '@/types/bridge'
import { useSessionsStore } from '@/stores/sessions'

const route  = useRoute()
const router = useRouter()

const sessions = useSessionsStore()
const session = ref<SessionDetail | null>(null)
const loading = ref(true)
const error   = ref<string | null>(null)
const exporting = ref(false)
const exportMsg = ref('')
const encryptBundle = ref(true)

// Session titles are free text; keep the suggested file name valid on every OS
function fileSafe(title?: string): string {
  return (title ?? '').replace(/[\\/:*?"<>|\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 80)
}

onMounted(async () => {
  const tool = route.params.tool as string
  const id   = route.params.id as string

  try {
    // The list may only hold metadata (no turns) for large tools like Claude Code,
    // so fetch the full session from the CLI when the turns are missing.
    let found = sessions.find(tool, id)
    if (!found) {
      await sessions.load(tool)
      found = sessions.find(tool, id)
    }

    if (found && (found.turns?.length ?? 0) === 0 && (found.turn_count ?? 0) > 0) {
      found = await window.bridge.getSession(tool, id)
    }

    session.value = found ?? null
    if (!found) error.value = 'Session not found'
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load'
  } finally {
    loading.value = false
  }
})

async function exportSession() {
  const outputPath = await window.bridge.saveFile({
    title:       'Export Session Bundle',
    defaultPath: `${fileSafe(session.value?.title) || 'session'}.cbz`,
    filters:     [{ name: 'MoveZ Bundle', extensions: ['cbz'] }]
  })
  if (!outputPath) return

  exporting.value = true
  exportMsg.value = ''
  try {
    await window.bridge.exportSessions({
      tool:       route.params.tool as string,
      ids:        [route.params.id as string],
      outputPath,
      encrypt:    encryptBundle.value
    })
    exportMsg.value = `Exported to ${outputPath.split(/[\\/]/).pop()}`
      + (encryptBundle.value ? ' (encrypted: import it on a machine with the same key)' : '')
  } catch (e) {
    exportMsg.value = 'Export failed: ' + (e instanceof Error ? e.message : String(e))
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <div class="p-8 max-w-3xl mx-auto">
    <button
      class="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-300 mb-6 transition-colors"
      @click="router.back()"
    >
      ← Back
    </button>

    <div v-if="loading" class="py-12 text-center text-slate-500 text-sm">Loading…</div>
    <div v-else-if="error" class="py-4 px-4 bg-red-900/20 rounded-xl text-red-400 text-sm">{{ error }}</div>

    <template v-else-if="session">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-light tracking-tight text-slate-100 mb-3">{{ session.title || 'Untitled Session' }}</h1>
          <div class="flex items-center gap-3">
            <ToolBadge :tool="session.source_tool" />
            <span class="text-xs text-slate-500">
              {{ new Date(session.last_active_at).toLocaleString() }}
            </span>
          </div>
        </div>
        <div class="flex items-center gap-3 shrink-0">
          <label
            class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer select-none"
            title="Encrypt with your MoveZ key (AES-256-GCM). The importing machine needs the same key."
          >
            <input v-model="encryptBundle" type="checkbox" class="w-3.5 h-3.5 rounded accent-[#eeeaa2]" />
            Encrypt
          </label>
          <button
            class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-sm text-slate-300
                   hover:border-brand-500/50 hover:text-brand-300 transition-colors disabled:opacity-50"
            :disabled="exporting"
            @click="exportSession"
          >
            {{ exporting ? 'Exporting…' : 'Export .cbz' }}
          </button>
        </div>
      </div>

      <div v-if="exportMsg" class="mb-6 px-4 py-2 rounded-xl bg-slate-800 text-xs text-slate-300">
        {{ exportMsg }}
      </div>

      <!-- Turns -->
      <div class="space-y-6">
        <TurnBlock
          v-for="(turn, i) in session.turns ?? []"
          :key="i"
          :turn="turn"
          :index="i"
        />
        <div v-if="!session.turns?.length" class="py-8 text-center text-slate-500 text-sm">
          No turns in this session.
        </div>
      </div>
    </template>
  </div>
</template>
