<template>
  <div class="min-h-screen bg-canvas">
    <header class="bg-surface border-b border-line px-6 py-4">
      <div class="max-w-2xl mx-auto flex items-center gap-4">
        <Link href="/" class="text-gray-500 hover:text-gray-300">← Home</Link>
        <h1 class="text-2xl font-light tracking-tight text-gray-100">Migration Wizard</h1>
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
                ? 'bg-accent text-ink'
                : currentStep > i + 1
                  ? 'bg-accent/15 text-accent'
                  : 'bg-line-2 text-gray-400',
            ]"
          >
            {{ currentStep > i + 1 ? '✓' : i + 1 }}
          </div>
          <div v-if="i < stepLabels.length - 1" class="h-px flex-1 bg-line-2" />
        </template>
      </div>

      <!-- Step 1: Source Tool -->
      <div v-if="currentStep === 1" data-testid="step-source">
        <h2 class="text-lg font-semibold mb-4">1. Select Source Tool</h2>
        <p class="text-sm text-gray-400 mb-4">Which AI tool are you migrating sessions from?</p>
        <div class="grid grid-cols-2 gap-3">
          <button
            v-for="tool in supportedTools"
            :key="tool"
            @click="form.fromTool = tool"
            :class="[
              'rounded-xl border p-3 text-left text-sm font-medium transition-colors',
              form.fromTool === tool
                ? 'border-accent bg-accent/10 text-accent'
                : 'border-line hover:border-line-2',
            ]"
          >
            {{ tool }}
          </button>
        </div>
        <div class="mt-6 flex justify-end">
          <button
            :disabled="!form.fromTool"
            @click="currentStep = 2"
            class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-ink disabled:opacity-40 hover:bg-accent-hover transition-colors"
          >
            Next →
          </button>
        </div>
      </div>

      <!-- Step 2: Target Tool -->
      <div v-if="currentStep === 2" data-testid="step-target">
        <h2 class="text-lg font-semibold mb-4">2. Select Target Tool</h2>
        <p class="text-sm text-gray-400 mb-4">Which AI tool are you migrating sessions into?</p>
        <div class="grid grid-cols-2 gap-3">
          <button
            v-for="tool in targetTools"
            :key="tool"
            @click="form.toTool = tool"
            :class="[
              'rounded-xl border p-3 text-left text-sm font-medium transition-colors',
              form.toTool === tool
                ? 'border-accent bg-accent/10 text-accent'
                : 'border-line hover:border-line-2',
            ]"
          >
            {{ tool }}
          </button>
        </div>
        <div class="mt-6 flex justify-between">
          <button @click="currentStep = 1" class="text-sm text-gray-400 hover:text-gray-200">← Back</button>
          <button
            :disabled="!form.toTool"
            @click="currentStep = 3"
            class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-ink disabled:opacity-40 hover:bg-accent-hover transition-colors"
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
          class="block w-full rounded-xl border border-line-2 px-3 py-2 text-sm"
        >
          <option :value="null">All projects / auto-detect</option>
          <option v-for="project in projects" :key="project.id" :value="project.id">
            {{ project.name }}
          </option>
        </select>
        <div class="mt-6 flex justify-between">
          <button @click="currentStep = 2" class="text-sm text-gray-400 hover:text-gray-200">← Back</button>
          <button @click="currentStep = 4" class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-ink hover:bg-accent-hover transition-colors">
            Next →
          </button>
        </div>
      </div>

      <!-- Step 4: Path Remapping -->
      <div v-if="currentStep === 4" data-testid="step-paths">
        <h2 class="text-lg font-semibold mb-4">4. Path Remapping (optional)</h2>
        <p class="text-sm text-gray-400 mb-4">
          If you're migrating between machines, remap file paths.
        </p>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-300 mb-1">From path</label>
            <input v-model="form.fromPath" type="text" placeholder="/Users/alice/project" class="block w-full rounded-xl border border-line-2 px-3 py-2 text-sm font-mono" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-300 mb-1">To path</label>
            <input v-model="form.toPath" type="text" placeholder="/home/bob/project" class="block w-full rounded-xl border border-line-2 px-3 py-2 text-sm font-mono" />
          </div>
        </div>
        <div class="mt-6 flex justify-between">
          <button @click="currentStep = 3" class="text-sm text-gray-400 hover:text-gray-200">← Back</button>
          <button @click="currentStep = 5" class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-ink hover:bg-accent-hover transition-colors">
            Next →
          </button>
        </div>
      </div>

      <!-- Step 5: Confirm -->
      <div v-if="currentStep === 5" data-testid="step-confirm">
        <h2 class="text-lg font-semibold mb-4">5. Confirm Migration</h2>
        <div class="rounded-3xl border border-line bg-surface p-5 space-y-3">
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-400">From</span>
            <ToolBadge :tool="form.fromTool!" />
          </div>
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-400">To</span>
            <ToolBadge :tool="form.toTool!" />
          </div>
          <div v-if="form.fromPath" class="flex items-center justify-between text-sm">
            <span class="text-gray-400">Remap</span>
            <span class="font-mono text-xs">{{ form.fromPath }} → {{ form.toPath }}</span>
          </div>
        </div>

        <div v-if="migrationResult" class="mt-4 rounded-xl bg-green-400/10 border border-green-400/30 p-4 text-sm text-green-300" data-testid="migration-result">
          <p>✅ {{ migrationResult.message }}</p>
          <div class="mt-3 flex items-start gap-2">
            <pre class="flex-1 overflow-x-auto rounded-lg bg-canvas px-3 py-2 font-mono text-xs text-gray-100" data-testid="migration-command">{{ migrationResult.command }}</pre>
            <button
              type="button"
              @click="copyCommand"
              class="rounded-lg border border-green-400/30 bg-surface px-3 py-2 text-xs font-medium text-green-300 hover:bg-green-400/10"
            >
              {{ copied ? 'Copied' : 'Copy' }}
            </button>
          </div>
        </div>

        <div v-if="migrationError" class="mt-4 rounded-xl bg-red-400/10 border border-red-400/30 p-4 text-sm text-red-300">
          ❌ {{ migrationError }}
        </div>

        <div class="mt-6 flex justify-between">
          <button @click="currentStep = 4" :disabled="isSubmitting" class="text-sm text-gray-400 hover:text-gray-200 disabled:opacity-40">← Back</button>
          <button
            @click="submit"
            :disabled="isSubmitting"
            class="rounded-full bg-accent px-6 py-2 text-sm font-semibold text-ink disabled:opacity-40 hover:bg-accent-hover transition-colors"
            data-testid="confirm-button"
          >
            {{ isSubmitting ? 'Preparing...' : '✓ Get Migration Command' }}
          </button>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, reactive } from 'vue';
import ToolBadge from '@/Components/ToolBadge.vue';
import type { Project } from '@/types';

const props = defineProps<{
  supportedTools: string[];
  writableTools: string[];
  projects: Pick<Project, 'id' | 'name' | 'path'>[];
}>();

interface MigrationResult {
  message: string;
  command: string;
}

const stepLabels   = ['Source', 'Target', 'Project', 'Paths', 'Confirm'];
const currentStep  = ref(1);
const isSubmitting = ref(false);
const copied       = ref(false);
const migrationResult = ref<MigrationResult | null>(null);
const migrationError  = ref<string | null>(null);

const form = reactive({
  fromTool:  null as string | null,
  toTool:    null as string | null,
  projectId: null as number | null,
  fromPath:  '',
  toPath:    '',
});

// A tool can't be both source and target
const targetTools = computed(() => props.writableTools.filter((t) => t !== form.fromTool));

async function submit(): Promise<void> {
  isSubmitting.value = true;
  migrationError.value  = null;
  migrationResult.value = null;

  try {
    const res = await fetch('/migration/start', {
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

    const body = await res.json().catch(() => ({}));

    if (!res.ok) {
      const firstError = body.errors ? Object.values(body.errors as Record<string, string[]>)[0]?.[0] : null;
      throw new Error(firstError ?? body.message ?? `Request failed (HTTP ${res.status})`);
    }

    migrationResult.value = { message: body.message, command: body.command };
  } catch (err) {
    migrationError.value = err instanceof Error ? err.message : String(err);
  } finally {
    isSubmitting.value = false;
  }
}

async function copyCommand(): Promise<void> {
  if (!migrationResult.value) return;
  await navigator.clipboard.writeText(migrationResult.value.command);
  copied.value = true;
  setTimeout(() => (copied.value = false), 2000);
}
</script>
