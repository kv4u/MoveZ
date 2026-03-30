import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { SessionSummary, SessionDetail } from '@/types/bridge'

export const useSessionsStore = defineStore('sessions', () => {
  const all    = ref<SessionDetail[]>([])
  const loading = ref(false)
  const error   = ref<string | null>(null)
  const filter  = ref<string>('')

  const byTool = computed(() => {
    const map: Record<string, SessionDetail[]> = {}
    for (const s of all.value) {
      if (!map[s.source_tool]) map[s.source_tool] = []
      map[s.source_tool].push(s)
    }
    return map
  })

  const filtered = computed(() => {
    if (!filter.value) return all.value
    const q = filter.value.toLowerCase()
    return all.value.filter(s =>
      s.title.toLowerCase().includes(q) ||
      s.source_tool.toLowerCase().includes(q) ||
      (s.project ?? '').toLowerCase().includes(q)
    )
  })

  function find(tool: string, id: string): SessionDetail | undefined {
    return all.value.find(s => s.source_tool === tool && s.id === id)
  }

  async function load(tool?: string) {
    loading.value = true
    error.value   = null
    try {
      all.value = await window.bridge.listSessions(tool)
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load sessions'
    } finally {
      loading.value = false
    }
  }

  return { all, loading, error, filter, byTool, filtered, find, load }
})
