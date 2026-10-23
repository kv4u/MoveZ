<template>
  <div class="min-h-screen bg-canvas">
    <header class="bg-surface border-b border-line px-6 py-4">
      <div class="max-w-7xl mx-auto flex items-center gap-4">
        <Link href="/" class="text-gray-500 hover:text-gray-300">← Home</Link>
        <h1 class="text-2xl font-light tracking-tight text-gray-100">Projects</h1>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8">
      <div v-if="projects.data.length === 0" class="text-center py-16 text-gray-500">
        <p>No projects yet. Use the CLI to export sessions.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <Link
          v-for="project in projects.data"
          :key="project.id"
          :href="`/projects/${project.id}`"
          class="rounded-3xl border border-line bg-surface p-5 hover:bg-surface-2 transition-all"
        >
          <div class="flex items-start justify-between">
            <div>
              <p class="font-semibold text-gray-100">{{ project.name }}</p>
              <p v-if="project.path" class="text-xs text-gray-500 mt-1 font-mono truncate max-w-48">
                {{ project.path }}
              </p>
            </div>
            <span class="text-xs text-gray-500">
              {{ project.ai_sessions_count }} session(s)
            </span>
          </div>
        </Link>
      </div>

      <Pagination :links="projects.links" />
    </main>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import type { PaginationLink, Project } from '@/types';

defineProps<{
  projects: {
    data: Project[];
    current_page: number;
    last_page: number;
    links: PaginationLink[];
  };
}>();
</script>
