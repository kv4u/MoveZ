<template>
  <nav v-if="links.length > 3" class="mt-8 flex flex-wrap items-center justify-center gap-1" aria-label="Pagination">
    <template v-for="(link, i) in links" :key="i">
      <Link
        v-if="link.url"
        :href="link.url"
        preserve-scroll
        :class="[
          'min-w-9 rounded-md border px-3 py-1.5 text-center text-sm transition-colors',
          link.active
            ? 'border-indigo-600 bg-indigo-600 text-white'
            : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300',
        ]"
        :aria-current="link.active ? 'page' : undefined"
      >
        {{ label(link.label) }}
      </Link>
      <span v-else class="min-w-9 rounded-md px-3 py-1.5 text-center text-sm text-gray-300">
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
