<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { ChartBarIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const form = reactive({
  email: '',
  password: '',
})

const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

const handleSubmit = async () => {
  if (!form.email || !form.password) {
    error.value = 'Please fill in all fields'
    return
  }

  loading.value = true
  error.value = ''

  try {
    await authStore.login(form)
    const redirect = route.query.redirect || '/'
    router.push(redirect)
  } catch (err) {
    error.value = err.response?.data?.message || 'Invalid credentials'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-12 bg-dark-950">
    <!-- Background pattern -->
    <div class="absolute inset-0 overflow-hidden">
      <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/10 rounded-full blur-3xl" />
      <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-accent-500/10 rounded-full blur-3xl" />
    </div>

    <div class="relative w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-primary-500 to-accent-500 mb-4">
          <ChartBarIcon class="w-8 h-8 text-white" />
        </div>
        <h1 class="text-2xl font-bold gradient-text">Trading Journal</h1>
        <p class="mt-2 text-dark-400">Sign in to your account</p>
      </div>

      <!-- Login form -->
      <form @submit.prevent="handleSubmit" class="card p-8 space-y-6">
        <!-- Error message -->
        <div v-if="error" class="p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
          {{ error }}
        </div>

        <!-- Email -->
        <div class="space-y-2">
          <label for="email" class="block text-sm font-medium text-dark-300">
            Email
          </label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="input"
            placeholder="Enter your email"
            autocomplete="email"
          />
        </div>

        <!-- Password -->
        <div class="space-y-2">
          <label for="password" class="block text-sm font-medium text-dark-300">
            Password
          </label>
          <div class="relative">
            <input
              id="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="input pr-10"
              placeholder="Enter your password"
              autocomplete="current-password"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 flex items-center pr-3 text-dark-400 hover:text-dark-300"
              @click="showPassword = !showPassword"
            >
              <EyeSlashIcon v-if="showPassword" class="w-5 h-5" />
              <EyeIcon v-else class="w-5 h-5" />
            </button>
          </div>
        </div>

        <!-- Submit -->
        <button
          type="submit"
          :disabled="loading"
          class="btn-primary w-full flex items-center justify-center gap-2"
        >
          <span v-if="loading" class="spinner w-4 h-4" />
          <span>{{ loading ? 'Signing in...' : 'Sign In' }}</span>
        </button>

        <!-- Demo credentials -->
        <div class="text-center text-sm text-dark-500">
          <p>Demo: demo@tradingjournal.local / password</p>
        </div>

        <!-- Register link -->
        <p class="text-center text-sm text-dark-400">
          Don't have an account?
          <router-link to="/register" class="text-primary-400 hover:text-primary-300">
            Sign up
          </router-link>
        </p>
      </form>
    </div>
  </div>
</template>

