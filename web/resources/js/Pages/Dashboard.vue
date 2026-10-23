<template>
  <div class="min-h-screen bg-canvas">
    <header class="sticky top-0 z-10 border-b border-line bg-canvas/80 px-6 py-4 backdrop-blur">
      <div class="max-w-6xl mx-auto flex items-center justify-between">
        <h1 class="flex items-center gap-2.5 text-lg font-bold tracking-tight text-gray-100">
          <img :src="'/favicon.svg'" alt="" class="h-8 w-8 rounded-lg" />
          MoveZ
        </h1>
        <nav class="flex items-center gap-6 text-sm font-medium">
          <Link href="/projects" class="text-gray-400 hover:text-gray-100 transition-colors">Projects</Link>
          <Link href="/migration/wizard" class="rounded-full bg-accent px-4 py-2 font-semibold text-ink hover:bg-accent-hover transition-colors">
            Migration Wizard
          </Link>
          <span v-if="user" class="text-gray-500" data-testid="current-user">{{ user.name }}</span>
          <Link
            href="/logout"
            method="post"
            as="button"
            class="text-gray-400 hover:text-gray-100 transition-colors"
          >
            Sign out
          </Link>
        </nav>
      </div>
    </header>

    <main class="max-w-6xl mx-auto px-6 py-10">
      <div class="mb-6">
        <h2 class="text-4xl font-light tracking-tight text-gray-100">Overview</h2>
        <p class="mt-1 text-sm text-gray-400">Sessions synced to this server</p>
      </div>

      <!-- Summary -->
      <div class="hero-gradient rounded-[28px] p-8 mb-4">
        <div class="grid grid-cols-1 gap-8 sm:grid-cols-3">
          <div>
            <p class="text-sm text-white/70">Total Sessions</p>
            <p class="mt-1 text-6xl font-light tracking-tighter text-white">{{ stats.total_sessions }}</p>
          </div>
          <div>
            <p class="text-sm text-white/70">Total Projects</p>
            <p class="mt-1 text-6xl font-light tracking-tighter text-white">{{ stats.total_projects }}</p>
          </div>
          <div class="flex flex-col gap-3 sm:items-end">
            <p class="text-sm text-white/70">Sync Status</p>
            <div class="rounded-full bg-black/30 px-3 py-1.5 backdrop-blur">
              <SyncStatus
                :last-sync-at="stats.last_sync"
                :status="stats.last_sync ? 'synced' : 'pending'"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Quick actions -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Link
          href="/migration/wizard"
          class="group flex items-center gap-4 rounded-3xl border border-line bg-surface p-5 hover:bg-surface-2 transition-colors"
        >
          <span class="flex h-12 w-12 items-center justify-center rounded-full bg-accent text-ink">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h13l-3-3M20 16H7l3 3" /></svg>
          </span>
          <div class="flex-1">
            <p class="font-semibold text-gray-100">Migration Wizard</p>
            <p class="text-sm text-gray-400">Transfer sessions between AI tools</p>
          </div>
          <span class="text-gray-500 transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
        </Link>
        <Link
          href="/projects"
          class="group flex items-center gap-4 rounded-3xl border border-line bg-surface p-5 hover:bg-surface-2 transition-colors"
        >
          <span class="flex h-12 w-12 items-center justify-center rounded-full border border-line-2 bg-surface-2 text-gray-100">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /></svg>
          </span>
          <div class="flex-1">
            <p class="font-semibold text-gray-100">Browse Projects</p>
            <p class="text-sm text-gray-400">View all projects and sessions</p>
          </div>
          <span class="text-gray-500 transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
        </Link>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SyncStatus from '@/Components/SyncStatus.vue';
import type { DashboardStats, SharedProps } from '@/types';

defineProps<{ stats: DashboardStats }>();

const user = computed(() => usePage<SharedProps>().props.auth.user);
</script>
