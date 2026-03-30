<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 px-6 py-4">
      <div class="max-w-4xl mx-auto flex items-center gap-4">
        <a
          v-if="session.project"
          :href="`/projects/${session.project_id}`"
          class="text-gray-400 hover:text-gray-600"
        >← Project</a>
        <h1 class="text-xl font-bold text-gray-900">{{ session.title }}</h1>
        <ToolBadge :tool="session.source_tool" />
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-8">
      <div class="mb-6 flex items-center gap-6 text-sm text-gray-500">
        <span>Session ID: <code class="font-mono text-xs bg-gray-100 rounded px-1">{{ session.session_id }}</code></span>
        <span v-if="session.exported_at">
          Exported: {{ new Date(session.exported_at).toLocaleString() }}
        </span>
      </div>

      <div v-if="!sessionData.turns || sessionData.turns.length === 0" class="text-gray-400 text-sm">
        No turns in this session (session data not parsed).
      </div>

      <div v-else>
        <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4">
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
import ToolBadge from '@/Components/ToolBadge.vue';
import TurnBlock from '@/Components/TurnBlock.vue';
import type { AiSession, SessionDTO } from '@/types';

defineProps<{
  session: AiSession;
  sessionData: Partial<SessionDTO>;
}>();
</script>
