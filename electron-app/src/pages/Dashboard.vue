<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useSessionsStore } from '@/stores/sessions'
import ToolBadge from '@/components/ToolBadge.vue'

const sessions = useSessionsStore()
const router   = useRouter()

onMounted(() => sessions.load())

const recentSessions = computed(() => [...sessions.all]
  .sort((a, b) => new Date(b.last_active_at).getTime() - new Date(a.last_active_at).getTime())
  .slice(0, 5)
)

const toolCount = computed(() => Object.keys(sessions.byTool).length)

const actions = [
  { label: 'Migrate Sessions',   icon: '⇄', route: '/migrate',  color: 'bg-brand-600 hover:bg-brand-500' },
  { label: 'Sync to Server',     icon: '↻', route: '/sync',     color: 'bg-slate-700 hover:bg-slate-600' },
  { label: 'Browse Sessions',    icon: '◫', route: '/sessions', color: 'bg-slate-700 hover:bg-slate-600' },
  { label: 'Run Doctor',         icon: '✦', route: '/doctor',   color: 'bg-slate-700 hover:bg-slate-600' },
]
</script>

<template>
  <div class="p-8 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-slate-100 mb-1">Dashboard</h1>
      <p class="text-slate-500 text-sm">Transfer AI sessions across tools and machines</p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-8">
      <div class="bg-slate-800/50 rounded-xl p-5 border border-slate-700/50">
        <div class="text-3xl font-bold text-brand-400 mb-1">{{ sessions.all.length }}</div>
        <div class="text-sm text-slate-500">Total Sessions</div>
      </div>
      <div class="bg-slate-800/50 rounded-xl p-5 border border-slate-700/50">
        <div class="text-3xl font-bold text-brand-400 mb-1">{{ toolCount }}</div>
        <div class="text-sm text-slate-500">Tools Detected</div>
      </div>
      <div class="bg-slate-800/50 rounded-xl p-5 border border-slate-700/50">
        <div class="flex flex-wrap gap-1.5 min-h-8">
          <template v-if="Object.keys(sessions.byTool).length">
            <ToolBadge v-for="tool in Object.keys(sessions.byTool)" :key="tool" :tool="tool" size="sm" />
          </template>
          <span v-else class="text-slate-600 text-sm self-center">No tools detected</span>
        </div>
        <div class="text-sm text-slate-500 mt-2">Active Tools</div>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="mb-8">
      <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-3">Quick Actions</h2>
      <div class="grid grid-cols-2 gap-3">
        <button
          v-for="action in actions"
          :key="action.route"
          class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-white transition-colors text-left"
          :class="action.color"
          @click="router.push(action.route)"
        >
          <span class="text-base">{{ action.icon }}</span>
          {{ action.label }}
        </button>
      </div>
    </div>

    <!-- Recent sessions -->
    <div>
      <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-3">Recent Sessions</h2>
      <div v-if="sessions.loading" class="text-slate-500 text-sm py-8 text-center">Loading sessions…</div>
      <div v-else-if="sessions.error" class="text-red-400 text-sm py-4 bg-red-900/20 rounded-lg px-4">
        {{ sessions.error }}
      </div>
      <div v-else-if="!recentSessions.length" class="text-slate-500 text-sm py-8 text-center">
        No sessions found. Make sure the CLI is installed and AI tools have been used.
      </div>
      <div v-else class="space-y-2">
        <div
          v-for="s in recentSessions"
          :key="s.id"
          class="flex items-center gap-3 p-3 rounded-lg bg-slate-800/40 border border-slate-700/40
                 hover:border-brand-500/30 hover:bg-slate-800 cursor-pointer transition-all"
          @click="router.push(`/sessions/${s.source_tool}/${s.id}`)"
        >
          <ToolBadge :tool="s.source_tool" size="sm" />
          <span class="flex-1 text-sm text-slate-200 truncate">{{ s.title || 'Untitled' }}</span>
          <span class="text-xs text-slate-600">
            {{ new Date(s.last_active_at).toLocaleDateString() }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
