<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 px-6 py-4">
      <div class="max-w-7xl mx-auto flex items-center gap-4">
        <a href="/projects" class="text-gray-400 hover:text-gray-600">← Projects</a>
        <h1 class="text-xl font-bold text-gray-900">{{ project.name }}</h1>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8">
      <p v-if="project.path" class="text-sm text-gray-400 font-mono mb-6">{{ project.path }}</p>

      <h2 class="text-lg font-semibold text-gray-700 mb-4">
        Sessions ({{ sessions.length }})
      </h2>

      <div v-if="sessions.length === 0" class="text-gray-400 text-sm">
        No sessions in this project.
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <SessionCard
          v-for="session in sessions"
          :key="session.id"
          :session="session"
        />
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import SessionCard from '@/Components/SessionCard.vue';
import type { AiSession, Project } from '@/types';

defineProps<{
  project: Project;
  sessions: AiSession[];
}>();
</script>
