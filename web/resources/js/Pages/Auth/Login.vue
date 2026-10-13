<template>
  <Head title="Sign in" />

  <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900">MoveZ</h1>
        <p class="text-sm text-gray-500">Sign in to your sync server</p>
      </div>

      <form
        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4"
        data-testid="login-form"
        @submit.prevent="submit"
      >
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="username"
            required
            autofocus
            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
          <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            required
            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
          <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-600">
          <input v-model="form.remember" type="checkbox" class="rounded border-gray-300" />
          Remember me
        </label>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors"
        >
          {{ form.processing ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <p class="mt-6 text-center text-xs text-gray-400">
        Accounts are created on the server with <span class="font-mono">php artisan movez:user</span>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

function submit(): void {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
}
</script>
