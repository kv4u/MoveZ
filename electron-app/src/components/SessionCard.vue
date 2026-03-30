<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import ToolBadge from './ToolBadge.vue'
import type { SessionSummary } from '@/types/bridge'

const props = defineProps<{
  session: SessionSummary
  selected?: boolean
}>()

const emit = defineEmits<{ (e: 'toggle-select'): void }>()

const router = useRouter()

const date = computed(() =>
  new Date(props.session.last_active_at).toLocaleDateString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric'
  })
)

function open() {
  router.push(`/sessions/${props.session.source_tool}/${props.session.id}`)
}

function handleClick(e: MouseEvent) {
  // Ctrl/Cmd+click or clicking the checkbox area → toggle selection
  if (e.ctrlKey || e.metaKey) {
    emit('toggle-select')
    return
  }
  open()
}
</script>

<template>
  <div
    class="group relative p-4 rounded-xl bg-slate-800/50 border cursor-pointer transition-all"
    :class="selected
      ? 'border-brand-500/70 bg-slate-800 ring-1 ring-brand-500/30'
      : 'border-slate-700/50 hover:border-brand-500/40 hover:bg-slate-800'"
    @click="handleClick"
  >
    <!-- Checkbox (top-right, visible on hover or when selected) -->
    <div
      class="absolute top-2.5 right-2.5 transition-opacity"
      :class="selected ? 'opacity-100' : 'opacity-0 group-hover:opacity-60'"
      @click.stop="emit('toggle-select')"
    >
      <div
        class="w-4 h-4 rounded border-2 flex items-center justify-center transition-colors"
        :class="selected ? 'bg-brand-500 border-brand-500' : 'border-slate-500'"
      >
        <svg v-if="selected" class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
        </svg>
      </div>
    </div>

    <div class="flex items-start justify-between gap-3 mb-1.5 pr-6">
      <h3 class="text-sm font-medium text-slate-100 line-clamp-2 group-hover:text-brand-300 transition-colors leading-snug">
        {{ session.title || 'Untitled Session' }}
      </h3>
      <ToolBadge :tool="session.source_tool" size="sm" class="shrink-0" />
    </div>
    <div v-if="session.project" class="text-xs text-brand-400/80 font-medium mb-1.5 truncate">
      {{ session.project }}
    </div>
    <div class="flex items-center gap-4 text-xs text-slate-500">
      <span>{{ session.turn_count ?? 0 }} turns</span>
      <span>{{ date }}</span>
    </div>
  </div>
</template>
