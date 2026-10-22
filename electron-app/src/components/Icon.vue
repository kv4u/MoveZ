<script setup lang="ts">
// Line icons drawn on a 24px grid with the current text colour
const props = defineProps<{ name: string; size?: number }>()

const paths: Record<string, string> = {
  dashboard: 'M4 5h7v6H4zM13 5h7v3h-7zM13 10h7v9h-7zM4 13h7v6H4z',
  sessions:  'M5 6h14M5 12h14M5 18h9',
  migrate:   'M4 8h13l-3-3M20 16H7l3 3',
  sync:      'M4 12a8 8 0 0 1 14-5.3M20 12a8 8 0 0 1-14 5.3M18 3v4h-4M6 21v-4h4',
  doctor:    'M4 12h4l2-5 4 10 2-5h4',
  settings:  'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z',
  up:        'M12 19V5m-6 6 6-6 6 6',
  down:      'M12 5v14m-6-6 6 6 6-6',
  back:      'M19 12H5m6-6-6 6 6 6',
  next:      'M5 12h14m-6-6 6 6-6 6',
  check:     'M5 12.5 10 17l9-10',
  x:         'M6 6l12 12M18 6 6 18',
  file:      'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 3v5h5',
  box:       'M21 8 12 3 3 8v8l9 5 9-5zM3 8l9 5 9-5M12 13v8',
}
</script>

<template>
  <svg
    :width="props.size ?? 18" :height="props.size ?? 18" viewBox="0 0 24 24"
    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
    aria-hidden="true"
  >
    <path :d="paths[props.name] ?? ''" />
  </svg>
</template>
