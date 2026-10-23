<template>
  <Link
    :href="`/sessions/${session.id}`"
    class="block rounded-3xl border border-line bg-surface p-4 hover:bg-surface-2 hover:border-line-2 transition-all group"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0 flex-1">
        <p class="truncate font-semibold text-gray-100 group-hover:text-accent">
          {{ session.title }}
        </p>
        <p class="mt-1 text-xs text-gray-400">
          Last active: {{ formatDate(session.exported_at ?? session.created_at) }}
        </p>
      </div>
      <ToolBadge :tool="session.source_tool" />
    </div>
    <div class="mt-3 flex items-center gap-4 text-xs text-gray-500">
      <span>Session {{ session.session_id?.substring(0, 8) }}</span>
    </div>
  </Link>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
