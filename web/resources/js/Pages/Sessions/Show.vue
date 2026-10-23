<template>
  <div class="min-h-screen bg-canvas">
    <header class="bg-surface border-b border-line px-6 py-4">
      <div class="max-w-4xl mx-auto flex items-center gap-4">
        <Link
          v-if="session.project"
          :href="`/projects/${session.project_id}`"
          class="text-gray-500 hover:text-gray-300"
        >← Project</Link>
        <h1 class="text-2xl font-light tracking-tight text-gray-100">{{ session.title }}</h1>
        <ToolBadge :tool="session.source_tool" />
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-8">
      <div class="mb-6 flex items-center gap-6 text-sm text-gray-400">
        <span>Session ID: <code class="font-mono text-xs bg-surface-2 rounded px-1">{{ session.session_id }}</code></span>
        <span v-if="session.exported_at">
          Exported: {{ new Date(session.exported_at).toLocaleString() }}
        </span>
      </div>

      <div v-if="!sessionData.turns || sessionData.turns.length === 0" class="text-gray-500 text-sm">
        No turns in this session (session data not parsed).
      </div>

      <div v-else>
        <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide mb-4">
          {{ sessionData.turns.length }} Turn(s)
        </h2>
        <TurnBlock
          v-for="(turn, i) in sessionData.turns"
          :key="i"
          :turn="turn"
        />
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ToolBadge from '@/Components/ToolBadge.vue';
import TurnBlock from '@/Components/TurnBlock.vue';
import type { AiSession, SessionDTO } from '@/types';

defineProps<{
  session: AiSession;
  sessionData: Partial<SessionDTO>;
}>();
</script>
