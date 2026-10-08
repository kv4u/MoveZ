<template>
  <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <p class="text-sm text-gray-500">{{ label }}</p>
    <p class="mt-1 text-3xl font-bold text-gray-900">{{ icon }} {{ value }}</p>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  label: string;
  value: number | string;
  icon?: string;
}>();
</script>
