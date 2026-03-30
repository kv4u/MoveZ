<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'

const route = useRoute()

const nav = [
  { to: '/dashboard', icon: '⬡', label: 'Dashboard' },
  { to: '/sessions',  icon: '◫', label: 'Sessions'  },
  { to: '/migrate',   icon: '⇄', label: 'Migrate'   },
  { to: '/sync',      icon: '↻', label: 'Sync'      },
  { to: '/doctor',    icon: '✦', label: 'Doctor'    },
]

const bottom = [
  { to: '/settings', icon: '⚙', label: 'Settings' }
]
</script>

<template>
  <aside class="w-56 flex flex-col bg-slate-900 border-r border-slate-800 shrink-0">
    <!-- Logo -->
    <div class="px-5 py-5 border-b border-slate-800">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-lg bg-brand-600 flex items-center justify-center text-sm font-bold text-white">
          MZ
        </div>
        <span class="font-semibold text-sm text-slate-100 tracking-wide">MoveZ</span>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 px-3 py-4 space-y-0.5">
      <RouterLink
        v-for="item in nav"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors"
        :class="route.path.startsWith(item.to)
          ? 'bg-brand-600/20 text-brand-400 font-medium'
          : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800'"
      >
        <span class="text-base w-5 text-center">{{ item.icon }}</span>
        {{ item.label }}
      </RouterLink>
    </nav>

    <!-- Bottom -->
    <div class="px-3 py-3 border-t border-slate-800 space-y-0.5">
      <RouterLink
        v-for="item in bottom"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors"
        :class="route.path.startsWith(item.to)
          ? 'bg-brand-600/20 text-brand-400 font-medium'
          : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800'"
      >
        <span class="text-base w-5 text-center">{{ item.icon }}</span>
        {{ item.label }}
      </RouterLink>
    </div>
  </aside>
</template>
