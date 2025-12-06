<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { ChartBarIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

const handleSubmit = async () => {
  if (!form.name || !form.email || !form.password) {
    error.value = 'Please fill in all required fields'
    return
  }

  if (form.password !== form.password_confirmation) {
    error.value = 'Passwords do not match'
    return
  }

  if (form.password.length < 8) {
    error.value = 'Password must be at least 8 characters'
    return
  }

  loading.value = true
  error.value = ''

  try {
    await authStore.register(form)
    router.push('/')
  } catch (err) {
    error.value = err.response?.data?.message || 'Registration failed'
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
        <p class="mt-2 text-dark-400">Create your account</p>
      </div>

      <!-- Register form -->
      <form @submit.prevent="handleSubmit" class="card p-8 space-y-6">
        <!-- Error message -->
        <div v-if="error" class="p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
          {{ error }}
        </div>

        <!-- Name -->
        <div class="space-y-2">
          <label for="name" class="block text-sm font-medium text-dark-300">
            Name
          </label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            class="input"
            placeholder="Enter your name"
            autocomplete="name"
          />
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
              placeholder="At least 8 characters"
              autocomplete="new-password"
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

        <!-- Confirm Password -->
        <div class="space-y-2">
          <label for="password_confirmation" class="block text-sm font-medium text-dark-300">
            Confirm Password
          </label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            class="input"
            placeholder="Confirm your password"
            autocomplete="new-password"
          />
        </div>

        <!-- Submit -->
        <button
          type="submit"
          :disabled="loading"
          class="btn-primary w-full flex items-center justify-center gap-2"
        >
          <span v-if="loading" class="spinner w-4 h-4" />
          <span>{{ loading ? 'Creating account...' : 'Create Account' }}</span>
        </button>

        <!-- Login link -->
        <p class="text-center text-sm text-dark-400">
          Already have an account?
          <router-link to="/login" class="text-primary-400 hover:text-primary-300">
            Sign in
          </router-link>
        </p>
      </form>
    </div>
  </div>
</template>

