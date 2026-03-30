<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useSettingsStore } from '@/stores/settings'

const store = useSettingsStore()
onMounted(() => store.load())

const form = ref({ ...store.settings })
watch(() => store.settings, (s) => { form.value = { ...s } }, { deep: true })

const saved    = ref(false)
const saving   = ref(false)

async function save() {
  saving.value = true
  await store.save({ ...form.value })
  saved.value  = true
  saving.value = false
  setTimeout(() => (saved.value = false), 2000)
}

async function browseCliPath() {
  const path = await window.bridge.openFile({
    title:   'Select movez CLI',
    filters: [{ name: 'Executable', extensions: ['*', 'phar', 'exe', 'bat'] }]
  })
  if (path) form.value.cliPath = path
}

async function browsePhpPath() {
  const path = await window.bridge.openFile({
    title:   'Select php.exe',
    filters: [{ name: 'PHP Executable', extensions: ['exe', '*'] }]
  })
  if (path) form.value.phpPath = path
}
</script>

<template>
  <div class="p-8 max-w-lg mx-auto">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-slate-100 mb-1">Settings</h1>
      <p class="text-slate-500 text-sm">Configure the CLI path and sync server</p>
    </div>

    <div class="space-y-6">
      <!-- CLI Path -->
      <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">CLI Path</label>
        <div class="flex gap-2">
          <input
            v-model="form.cliPath"
            type="text"
            placeholder="movez"
            class="flex-1 px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-slate-200
                   placeholder-slate-600 focus:outline-none focus:border-brand-500"
          />
          <button
            class="px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-sm text-slate-300
                   hover:bg-slate-600 transition-colors"
            @click="browseCliPath"
          >
            Browse
          </button>
        </div>
        <p class="text-xs text-slate-600 mt-1">
          Path to the movez PHAR or binary.
          <span class="text-slate-500">If using a .phar file, also set PHP Path below.</span>
        </p>
      </div>

      <!-- PHP Path (only needed for .phar) -->
      <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">PHP Path
          <span class="text-xs font-normal text-slate-500 ml-1">(required for .phar)</span>
        </label>
        <div class="flex gap-2">
          <input
            v-model="form.phpPath"
            type="text"
            placeholder="php  or  C:\xampp\php\php.exe"
            class="flex-1 px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-slate-200
                   placeholder-slate-600 focus:outline-none focus:border-brand-500"
          />
          <button
            class="px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-sm text-slate-300
                   hover:bg-slate-600 transition-colors"
            @click="browsePhpPath"
          >
            Browse
          </button>
        </div>
        <p class="text-xs text-slate-600 mt-1">e.g. <code class="text-slate-500">D:\xampp\php\php.exe</code></p>
      </div>


      <!-- Server URL -->
      <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">Sync Server URL</label>
        <input
          v-model="form.serverUrl"
          type="url"
          placeholder="https://your-sync-server.com"
          class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-slate-200
                 placeholder-slate-600 focus:outline-none focus:border-brand-500"
        />
        <p class="text-xs text-slate-600 mt-1">Leave blank to disable sync features</p>
      </div>

      <!-- API Token -->
      <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">API Token</label>
        <input
          v-model="form.token"
          type="password"
          placeholder="Your sync server API token"
          class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-slate-200
                 placeholder-slate-600 focus:outline-none focus:border-brand-500"
          autocomplete="off"
        />
        <p class="text-xs text-slate-600 mt-1">
          Stored locally. Sent only to your configured server.
        </p>
      </div>

      <!-- Dark mode -->
      <div class="flex items-center justify-between py-4 border-t border-slate-800">
        <div>
          <div class="text-sm font-medium text-slate-300">Dark Mode</div>
          <div class="text-xs text-slate-600">Use dark interface theme</div>
        </div>
        <button
          class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
          :class="form.darkMode ? 'bg-brand-600' : 'bg-slate-700'"
          @click="form.darkMode = !form.darkMode"
        >
          <span
            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
            :class="form.darkMode ? 'translate-x-6' : 'translate-x-1'"
          />
        </button>
      </div>
    </div>

    <!-- Save -->
    <div class="mt-8 flex items-center gap-3">
      <button
        class="px-5 py-2 rounded-lg bg-brand-600 hover:bg-brand-500 text-sm font-medium text-white
               transition-colors disabled:opacity-50"
        :disabled="saving"
        @click="save"
      >
        {{ saving ? 'Saving…' : 'Save Settings' }}
      </button>
      <span v-if="saved" class="text-sm text-green-400">✓ Saved</span>
    </div>
  </div>
</template>
