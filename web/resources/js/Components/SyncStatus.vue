<template>
  <div class="flex items-center gap-2 text-sm">
    <span :class="['h-2 w-2 rounded-full', dotClass]"></span>
    <span :class="textClass">
      <template v-if="status === 'synced'">Synced {{ lastSyncFormatted }}</template>
      <template v-else-if="status === 'pending'">Sync pending</template>
      <template v-else>Sync error</template>
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { SyncStatus } from '@/types';

const props = defineProps<{
  lastSyncAt: string | null;
  status: SyncStatus;
}>();

const dotClass = computed(() => ({
  'bg-green-500': props.status === 'synced',
  'bg-yellow-400': props.status === 'pending',
  'bg-red-500':   props.status === 'error',
}));

const textClass = computed(() => ({
  'text-green-700': props.status === 'synced',
  'text-yellow-700': props.status === 'pending',
  'text-red-700':   props.status === 'error',
}));

const lastSyncFormatted = computed(() => {
  if (!props.lastSyncAt) return '';
  return new Intl.RelativeTimeFormat('en', { numeric: 'auto' }).format(
    Math.round((new Date(props.lastSyncAt).getTime() - Date.now()) / 60000),
    'minute',
  );
});
</script>
