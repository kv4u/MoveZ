<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useSessionsStore } from '@/stores/sessions'
import ToolBadge from '@/components/ToolBadge.vue'
import Icon from '@/components/Icon.vue'

const sessions = useSessionsStore()
const router   = useRouter()

onMounted(() => sessions.load())

const recentSessions = computed(() => [...sessions.all]
  .sort((a, b) => new Date(b.last_active_at).getTime() - new Date(a.last_active_at).getTime())
  .slice(0, 5)
)

const toolCount = computed(() => Object.keys(sessions.byTool).length)
const turnCount = computed(() => sessions.all.reduce((sum, s) => sum + (s.turn_count ?? 0), 0))

const actions = [
  { label: 'Migrate', icon: 'migrate',  route: '/migrate'  },
  { label: 'Sync',    icon: 'sync',     route: '/sync'     },
  { label: 'Browse',  icon: 'sessions', route: '/sessions' },
  { label: 'Doctor',  icon: 'doctor',   route: '/doctor'   },
]
</script>

<template>
  <div class="p-8 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-3xl font-light tracking-tight text-slate-100 mb-1">Dashboard</h1>
      <p class="text-slate-500 text-sm">Transfer AI sessions across tools and machines</p>
    </div>

    <!-- Summary card -->
    <div class="hero-gradient rounded-[28px] p-7 mb-4 relative overflow-hidden">
      <div class="flex items-start justify-between gap-6">
        <div>
          <div class="text-sm text-white/70 mb-1">Sessions on this machine</div>
          <div class="flex items-baseline gap-3">
            <span class="text-6xl font-light tracking-tighter text-white">{{ sessions.all.length }}</span>
            <span class="text-sm font-medium text-brand-400">{{ turnCount }} {{ turnCount === 1 ? 'turn' : 'turns' }}</span>
          </div>
        </div>
        <div class="text-right">
          <div class="text-sm text-white/70 mb-1">Tools detected</div>
          <div class="text-4xl font-light tracking-tight text-white">{{ toolCount }}</div>
        </div>
      </div>

      <div class="flex flex-wrap gap-1.5 mt-6 min-h-6">
        <template v-if="toolCount">
          <ToolBadge v-for="tool in Object.keys(sessions.byTool)" :key="tool" :tool="tool" size="sm"
                     class="!bg-black/30 backdrop-blur" />
        </template>
        <span v-else class="text-white/60 text-sm">No tools detected yet</span>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="grid grid-cols-4 gap-3 mb-10">
      <button
        v-for="(action, i) in actions"
        :key="action.route"
        class="group flex flex-col items-center gap-2.5 py-4 rounded-3xl bg-slate-900 border border-slate-800
               hover:bg-slate-800 transition-colors"
        @click="router.push(action.route)"
      >
        <span
          class="w-11 h-11 rounded-full flex items-center justify-center transition-transform group-hover:-translate-y-0.5"
          :class="i === 0 ? 'bg-brand-600 text-ink' : 'bg-slate-800 text-slate-100 group-hover:bg-slate-700'"
        >
          <Icon :name="action.icon" />
        </span>
        <span class="text-xs font-medium text-slate-300">{{ action.label }}</span>
      </button>
    </div>

    <!-- Recent sessions -->
    <div>
      <div class="flex items-baseline justify-between mb-3">
        <h2 class="text-base font-semibold text-slate-100">Recent sessions</h2>
        <button class="text-xs text-slate-500 hover:text-slate-200 transition-colors" @click="router.push('/sessions')">
          See all
        </button>
      </div>
      <div v-if="sessions.loading" class="text-slate-500 text-sm py-8 text-center">Loading sessions…</div>
      <div v-else-if="sessions.error" class="text-red-400 text-sm py-4 bg-red-900/20 rounded-2xl px-4">
        {{ sessions.error }}
      </div>
      <div v-else-if="!recentSessions.length" class="text-slate-500 text-sm py-8 text-center">
        No sessions found. Make sure the CLI is installed and AI tools have been used.
      </div>
      <div v-else class="rounded-3xl bg-slate-900 border border-slate-800 p-2">
        <div
          v-for="s in recentSessions"
          :key="s.id"
          class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-slate-800 cursor-pointer transition-colors"
          @click="router.push(`/sessions/${s.source_tool}/${s.id}`)"
        >
          <ToolBadge :tool="s.source_tool" size="sm" />
          <span class="flex-1 text-sm text-slate-200 truncate">{{ s.title || 'Untitled' }}</span>
          <span class="text-xs text-slate-500 tabular-nums">
            {{ new Date(s.last_active_at).toLocaleDateString() }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
