<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 px-6 py-4">
      <div class="max-w-7xl mx-auto flex items-center gap-4">
        <a href="/" class="text-gray-400 hover:text-gray-600">← Home</a>
        <h1 class="text-xl font-bold text-gray-900">Projects</h1>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8">
      <div v-if="projects.data.length === 0" class="text-center py-16 text-gray-400">
        <p class="text-4xl mb-4">📂</p>
        <p>No projects yet. Use the CLI to export sessions.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <a
          v-for="project in projects.data"
          :key="project.id"
          :href="`/projects/${project.id}`"
          class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md transition-all"
        >
          <div class="flex items-start justify-between">
            <div>
              <p class="font-semibold text-gray-900">{{ project.name }}</p>
              <p v-if="project.path" class="text-xs text-gray-400 mt-1 font-mono truncate max-w-48">
                {{ project.path }}
              </p>
            </div>
            <span class="text-xs text-gray-400">
              {{ project.ai_sessions_count }} session(s)
            </span>
          </div>
        </a>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import type { Project } from '@/types';

defineProps<{
  projects: {
    data: Project[];
    current_page: number;
    last_page: number;
  };
}>();
</script>
