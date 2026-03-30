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

onMounted(async () => {
  const tool = route.params.tool as string
  const id   = route.params.id as string

  // Use cached store data first — avoids reloading all sessions
  const cached = sessions.find(tool, id)
  if (cached) {
    session.value = cached
    loading.value = false
    return
  }
  // Store not loaded yet — load just this tool then find
  try {
    await sessions.load(tool)
    const found = sessions.find(tool, id)
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
    defaultPath: `${session.value?.title ?? 'session'}.cbz`,
    filters:     [{ name: 'MoveZ Bundle', extensions: ['cbz'] }]
  })
  if (!outputPath) return
  await window.bridge.exportSessions({
    tool:       route.params.tool as string,
    outputPath,
    encrypt:    false
  })
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
    <div v-else-if="error" class="py-4 px-4 bg-red-900/20 rounded-lg text-red-400 text-sm">{{ error }}</div>

    <template v-else-if="session">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4 mb-6">
        <div>
          <h1 class="text-xl font-bold text-slate-100 mb-2">{{ session.title || 'Untitled Session' }}</h1>
          <div class="flex items-center gap-3">
            <ToolBadge :tool="session.source_tool" />
            <span class="text-xs text-slate-500">
              {{ new Date(session.last_active_at).toLocaleString() }}
            </span>
          </div>
        </div>
        <button
          class="px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-slate-300
                 hover:border-brand-500/50 hover:text-brand-300 transition-colors shrink-0"
          @click="exportSession"
        >
          Export .cbz
        </button>
      </div>

      <!-- Turns -->
      <div class="space-y-6">
        <TurnBlock
          v-for="(turn, i) in (session as any).turns ?? []"
          :key="i"
          :turn="turn"
          :index="i"
        />
        <div v-if="!(session as any).turns?.length" class="py-8 text-center text-slate-500 text-sm">
          No turns in this session. The CLI may need to be updated to include full turn data.
        </div>
      </div>
    </template>
  </div>
</template>
