<script setup lang="ts">
import logo from '@/assets/logo.png'
import { RouterLink, useRoute } from 'vue-router'
import Icon from './Icon.vue'

const route = useRoute()

const nav = [
  { to: '/dashboard', icon: 'dashboard', label: 'Dashboard' },
  { to: '/sessions',  icon: 'sessions',  label: 'Sessions'  },
  { to: '/migrate',   icon: 'migrate',   label: 'Migrate'   },
  { to: '/sync',      icon: 'sync',      label: 'Sync'      },
  { to: '/doctor',    icon: 'doctor',    label: 'Doctor'    },
]

const bottom = [
  { to: '/settings', icon: 'settings', label: 'Settings' }
]
</script>

<template>
  <aside class="w-56 flex flex-col bg-slate-950 border-r border-slate-800 shrink-0">
    <!-- Logo -->
    <div class="px-5 pt-6 pb-5">
      <div class="flex items-center gap-2.5">
        <img :src="logo" alt="" class="w-7 h-7 rounded-lg" />
        <span class="font-bold text-[15px] text-slate-100 tracking-tight">MoveZ</span>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 px-3 py-2 space-y-1">
      <RouterLink
        v-for="item in nav"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 px-3 py-2.5 rounded-full text-sm transition-colors"
        :class="route.path.startsWith(item.to)
          ? 'bg-slate-800 text-slate-100 font-semibold'
          : 'text-slate-400 hover:text-slate-100 hover:bg-slate-900'"
      >
        <Icon :name="item.icon" :class="route.path.startsWith(item.to) ? 'text-brand-400' : ''" />
        {{ item.label }}
      </RouterLink>
    </nav>

    <!-- Bottom -->
    <div class="px-3 py-3 border-t border-slate-800 space-y-1">
      <RouterLink
        v-for="item in bottom"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 px-3 py-2.5 rounded-full text-sm transition-colors"
        :class="route.path.startsWith(item.to)
          ? 'bg-slate-800 text-slate-100 font-semibold'
          : 'text-slate-400 hover:text-slate-100 hover:bg-slate-900'"
      >
        <Icon :name="item.icon" :class="route.path.startsWith(item.to) ? 'text-brand-400' : ''" />
        {{ item.label }}
      </RouterLink>
    </div>
  </aside>
</template>
