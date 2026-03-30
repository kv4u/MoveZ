<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 px-6 py-4">
      <div class="max-w-7xl mx-auto flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">MoveZ</h1>
          <p class="text-sm text-gray-500">AI session transfer platform</p>
        </div>
        <nav class="flex items-center gap-6 text-sm font-medium">
          <a href="/projects" class="text-gray-600 hover:text-indigo-600">Projects</a>
          <a href="/migration/wizard" class="rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 transition-colors">
            Migration Wizard
          </a>
        </nav>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8">
      <h2 class="text-lg font-semibold text-gray-700 mb-6">Overview</h2>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-10">
        <StatCard
          label="Total Sessions"
          :value="stats.total_sessions"
          icon="💬"
        />
        <StatCard
          label="Total Projects"
          :value="stats.total_projects"
          icon="📁"
        />
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm flex flex-col gap-2">
          <p class="text-sm text-gray-500">Sync Status</p>
          <SyncStatus
            :last-sync-at="stats.last_sync"
            :status="stats.last_sync ? 'synced' : 'pending'"
          />
        </div>
      </div>

      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-700">Quick Actions</h2>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="/migration/wizard" class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 hover:shadow-md transition-all">
          <span class="text-3xl">⚡</span>
          <div>
            <p class="font-semibold">Migration Wizard</p>
            <p class="text-sm text-gray-500">Transfer sessions between AI tools</p>
          </div>
        </a>
        <a href="/projects" class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 hover:shadow-md transition-all">
          <span class="text-3xl">📂</span>
          <div>
            <p class="font-semibold">Browse Projects</p>
            <p class="text-sm text-gray-500">View all projects and sessions</p>
          </div>
        </a>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import SyncStatus from '@/Components/SyncStatus.vue';
import type { DashboardStats } from '@/types';

defineProps<{ stats: DashboardStats }>();

// Inline StatCard to avoid extra file
const StatCard = {
  template: `<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <p class="text-sm text-gray-500">{{ label }}</p>
    <p class="mt-1 text-3xl font-bold text-gray-900">{{ icon }} {{ value }}</p>
  </div>`,
  props: ['label', 'value', 'icon'],
};
</script>
