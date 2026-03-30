<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 px-6 py-4">
      <div class="max-w-2xl mx-auto flex items-center gap-4">
        <a href="/" class="text-gray-400 hover:text-gray-600">← Home</a>
        <h1 class="text-xl font-bold text-gray-900">Migration Wizard</h1>
      </div>
    </header>

    <main class="max-w-2xl mx-auto px-6 py-10">
      <!-- Step indicator -->
      <div class="flex items-center gap-2 mb-10">
        <template v-for="(label, i) in stepLabels" :key="i">
          <div
            :class="[
              'flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold transition-colors',
              currentStep === i + 1
                ? 'bg-indigo-600 text-white'
                : currentStep > i + 1
                  ? 'bg-green-500 text-white'
                  : 'bg-gray-200 text-gray-500',
            ]"
          >
            {{ currentStep > i + 1 ? '✓' : i + 1 }}
          </div>
          <div v-if="i < stepLabels.length - 1" class="h-px flex-1 bg-gray-200" />
        </template>
      </div>

      <!-- Step 1: Source Tool -->
      <div v-if="currentStep === 1" data-testid="step-source">
        <h2 class="text-lg font-semibold mb-4">1. Select Source Tool</h2>
        <p class="text-sm text-gray-500 mb-4">Which AI tool are you migrating sessions from?</p>
        <div class="grid grid-cols-2 gap-3">
          <button
            v-for="tool in supportedTools"
            :key="tool"
            @click="form.fromTool = tool"
            :class="[
              'rounded-lg border p-3 text-left text-sm font-medium transition-colors',
              form.fromTool === tool
                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                : 'border-gray-200 hover:border-gray-300',
            ]"
          >
            {{ tool }}
          </button>
        </div>
        <div class="mt-6 flex justify-end">
          <button
            :disabled="!form.fromTool"
            @click="currentStep = 2"
            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-40 hover:bg-indigo-700 transition-colors"
          >
            Next →
          </button>
        </div>
      </div>

      <!-- Step 2: Target Tool -->
      <div v-if="currentStep === 2" data-testid="step-target">
        <h2 class="text-lg font-semibold mb-4">2. Select Target Tool</h2>
        <p class="text-sm text-gray-500 mb-4">Which AI tool are you migrating sessions into?</p>
        <div class="grid grid-cols-2 gap-3">
          <button
            v-for="tool in writableTools"
            :key="tool"
            @click="form.toTool = tool"
            :class="[
              'rounded-lg border p-3 text-left text-sm font-medium transition-colors',
              form.toTool === tool
                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                : 'border-gray-200 hover:border-gray-300',
            ]"
          >
            {{ tool }}
          </button>
        </div>
        <div class="mt-6 flex justify-between">
          <button @click="currentStep = 1" class="text-sm text-gray-500 hover:text-gray-700">← Back</button>
          <button
            :disabled="!form.toTool"
            @click="currentStep = 3"
            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-40 hover:bg-indigo-700 transition-colors"
          >
            Next →
          </button>
        </div>
      </div>

      <!-- Step 3: Select Project -->
      <div v-if="currentStep === 3" data-testid="step-project">
        <h2 class="text-lg font-semibold mb-4">3. Select Project (optional)</h2>
        <select
          v-model="form.projectId"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
        >
          <option :value="null">All projects / auto-detect</option>
          <option v-for="project in projects" :key="project.id" :value="project.id">
            {{ project.name }}
          </option>
        </select>
        <div class="mt-6 flex justify-between">
          <button @click="currentStep = 2" class="text-sm text-gray-500 hover:text-gray-700">← Back</button>
          <button @click="currentStep = 4" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
            Next →
          </button>
        </div>
      </div>

      <!-- Step 4: Path Remapping -->
      <div v-if="currentStep === 4" data-testid="step-paths">
        <h2 class="text-lg font-semibold mb-4">4. Path Remapping (optional)</h2>
        <p class="text-sm text-gray-500 mb-4">
          If you're migrating between machines, remap file paths.
        </p>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">From path</label>
            <input v-model="form.fromPath" type="text" placeholder="/Users/alice/project" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">To path</label>
            <input v-model="form.toPath" type="text" placeholder="/home/bob/project" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono" />
          </div>
        </div>
        <div class="mt-6 flex justify-between">
          <button @click="currentStep = 3" class="text-sm text-gray-500 hover:text-gray-700">← Back</button>
          <button @click="currentStep = 5" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
            Next →
          </button>
        </div>
      </div>

      <!-- Step 5: Confirm -->
      <div v-if="currentStep === 5" data-testid="step-confirm">
        <h2 class="text-lg font-semibold mb-4">5. Confirm Migration</h2>
        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-3">
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500">From</span>
            <ToolBadge :tool="form.fromTool!" />
          </div>
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500">To</span>
            <ToolBadge :tool="form.toTool!" />
          </div>
          <div v-if="form.fromPath" class="flex items-center justify-between text-sm">
            <span class="text-gray-500">Remap</span>
            <span class="font-mono text-xs">{{ form.fromPath }} → {{ form.toPath }}</span>
          </div>
        </div>

        <div v-if="migrationResult" class="mt-4 rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-800">
          ✅ {{ migrationResult }}
        </div>

        <div v-if="migrationError" class="mt-4 rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-800">
          ❌ {{ migrationError }}
        </div>

        <div class="mt-6 flex justify-between">
          <button @click="currentStep = 4" :disabled="isSubmitting" class="text-sm text-gray-500 hover:text-gray-700 disabled:opacity-40">← Back</button>
          <button
            @click="submit"
            :disabled="isSubmitting"
            class="rounded-lg bg-green-600 px-6 py-2 text-sm font-medium text-white disabled:opacity-40 hover:bg-green-700 transition-colors"
            data-testid="confirm-button"
          >
            {{ isSubmitting ? 'Migrating...' : '✓ Start Migration' }}
          </button>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import ToolBadge from '@/Components/ToolBadge.vue';
import type { Project } from '@/types';

const props = defineProps<{
  supportedTools: string[];
  projects: Pick<Project, 'id' | 'name'>[];
}>();

const writableTools = ['cursor', 'windsurf', 'claude-code', 'codex', 'copilot-cli'];
const stepLabels   = ['Source', 'Target', 'Project', 'Paths', 'Confirm'];
const currentStep  = ref(1);
const isSubmitting = ref(false);
const migrationResult = ref<string | null>(null);
const migrationError  = ref<string | null>(null);

const form = reactive({
  fromTool:  null as string | null,
  toTool:    null as string | null,
  projectId: null as number | null,
  fromPath:  '',
  toPath:    '',
});

async function submit(): Promise<void> {
  isSubmitting.value = true;
  migrationError.value  = null;
  migrationResult.value = null;

  try {
    await fetch('/migration/start', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        from_tool:  form.fromTool,
        to_tool:    form.toTool,
        project_id: form.projectId,
        from_path:  form.fromPath || null,
        to_path:    form.toPath   || null,
      }),
    });

    migrationResult.value = `Migration queued from ${form.fromTool} → ${form.toTool}. Sessions will appear shortly.`;
  } catch (err) {
    migrationError.value = String(err);
  } finally {
    isSubmitting.value = false;
  }
}
</script>
