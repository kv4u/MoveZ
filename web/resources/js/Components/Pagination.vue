<template>
  <nav v-if="links.length > 3" class="mt-8 flex flex-wrap items-center justify-center gap-1" aria-label="Pagination">
    <template v-for="(link, i) in links" :key="i">
      <Link
        v-if="link.url"
        :href="link.url"
        preserve-scroll
        :class="[
          'min-w-9 rounded-lg border px-3 py-1.5 text-center text-sm transition-colors',
          link.active
            ? 'border-accent bg-accent text-ink'
            : 'border-line bg-surface text-gray-200 hover:border-line-2',
        ]"
        :aria-current="link.active ? 'page' : undefined"
      >
        {{ label(link.label) }}
      </Link>
      <span v-else class="min-w-9 rounded-lg px-3 py-1.5 text-center text-sm text-gray-600">
        {{ label(link.label) }}
      </span>
    </template>
  </nav>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PaginationLink } from '@/types';

defineProps<{ links: PaginationLink[] }>();

// Laravel's labels are HTML entities ("&laquo; Previous"); render them as plain text
function label(raw: string): string {
  return raw.replace('&laquo;', '«').replace('&raquo;', '»');
}
</script>
