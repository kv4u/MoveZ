<template>
  <a
    :href="`/sessions/${session.id}`"
    class="block rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all group"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0 flex-1">
        <p class="truncate font-semibold text-gray-900 group-hover:text-indigo-700">
          {{ session.title }}
        </p>
        <p class="mt-1 text-xs text-gray-500">
          Last active: {{ formatDate(session.exported_at ?? session.created_at) }}
        </p>
      </div>
      <ToolBadge :tool="session.source_tool" />
    </div>
    <div class="mt-3 flex items-center gap-4 text-xs text-gray-400">
      <span>Session {{ session.session_id?.substring(0, 8) }}</span>
    </div>
  </a>
</template>

<script setup lang="ts">
import ToolBadge from './ToolBadge.vue';
import type { AiSession } from '@/types';

defineProps<{ session: AiSession }>();

function formatDate(dateStr: string | null): string {
  if (!dateStr) return 'Unknown';
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
  });
}
</script>
