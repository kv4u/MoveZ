<template>
  <Head title="Sign in" />

  <div class="min-h-screen bg-canvas flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <img :src="'/favicon.svg'" alt="" class="mx-auto mb-4 h-14 w-14 rounded-2xl" />
        <h1 class="text-3xl font-light tracking-tight text-gray-100">MoveZ</h1>
        <p class="mt-1 text-sm text-gray-400">Sign in to your sync server</p>
      </div>

      <form
        class="rounded-3xl border border-line bg-surface p-6 space-y-4"
        data-testid="login-form"
        @submit.prevent="submit"
      >
        <div>
          <label for="email" class="block text-sm font-medium text-gray-300 mb-1.5">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="username"
            required
            autofocus
            class="block w-full rounded-xl border border-line-2 bg-surface-2 px-3.5 py-2.5 text-sm text-gray-100 focus:border-accent focus:outline-none"
          />
          <p v-if="form.errors.email" class="mt-1 text-sm text-red-300">{{ form.errors.email }}</p>
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-300 mb-1.5">Password</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            required
            class="block w-full rounded-xl border border-line-2 bg-surface-2 px-3.5 py-2.5 text-sm text-gray-100 focus:border-accent focus:outline-none"
          />
          <p v-if="form.errors.password" class="mt-1 text-sm text-red-300">{{ form.errors.password }}</p>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-400">
          <input v-model="form.remember" type="checkbox" class="rounded border-line-2 bg-surface-2 accent-[#eeeaa2]" />
          Remember me
        </label>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full rounded-full bg-accent px-4 py-2.5 text-sm font-semibold text-ink hover:bg-accent-hover disabled:opacity-50 transition-colors"
        >
          {{ form.processing ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <p class="mt-6 text-center text-xs text-gray-500">
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
