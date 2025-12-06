<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  UserIcon,
  KeyIcon,
  BellIcon,
  ShieldCheckIcon,
  MoonIcon,
  SunIcon,
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()

const activeTab = ref('profile')
const saving = ref(false)
const message = ref('')

const profile = reactive({
  name: '',
  email: '',
  timezone: 'Asia/Jakarta',
})

const password = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const timezones = [
  'Asia/Jakarta',
  'Asia/Singapore',
  'Asia/Tokyo',
  'UTC',
  'America/New_York',
  'America/Los_Angeles',
  'Europe/London',
]

onMounted(() => {
  if (authStore.user) {
    profile.name = authStore.user.name
    profile.email = authStore.user.email
    profile.timezone = authStore.user.timezone || 'Asia/Jakarta'
  }
})

const updateProfile = async () => {
  saving.value = true
  message.value = ''
  try {
    await authStore.updateProfile(profile)
    message.value = 'Profile updated successfully'
  } catch (error) {
    message.value = error.response?.data?.message || 'Failed to update profile'
  } finally {
    saving.value = false
  }
}

const updatePassword = async () => {
  if (password.password !== password.password_confirmation) {
    message.value = 'Passwords do not match'
    return
  }
  
  saving.value = true
  message.value = ''
  try {
    await authStore.updatePassword(password)
    message.value = 'Password updated successfully'
    password.current_password = ''
    password.password = ''
    password.password_confirmation = ''
  } catch (error) {
    message.value = error.response?.data?.message || 'Failed to update password'
  } finally {
    saving.value = false
  }
}

const isDark = ref(document.documentElement.classList.contains('dark'))

const toggleTheme = () => {
  isDark.value = !isDark.value
  authStore.setTheme(isDark.value ? 'dark' : 'light')
}
</script>

<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-dark-100">Settings</h1>
      <p class="text-dark-400">Manage your account and preferences</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <!-- Sidebar tabs -->
      <div class="card p-2 h-fit">
        <button
          v-for="tab in [
            { id: 'profile', label: 'Profile', icon: UserIcon },
            { id: 'security', label: 'Security', icon: KeyIcon },
            { id: 'appearance', label: 'Appearance', icon: MoonIcon },
          ]"
          :key="tab.id"
          :class="[
            'w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left transition-colors',
            activeTab === tab.id
              ? 'bg-primary-500/10 text-primary-400'
              : 'text-dark-400 hover:text-dark-300 hover:bg-dark-800'
          ]"
          @click="activeTab = tab.id"
        >
          <component :is="tab.icon" class="w-5 h-5" />
          {{ tab.label }}
        </button>
      </div>

      <!-- Content -->
      <div class="md:col-span-3 space-y-6">
        <!-- Message -->
        <div
          v-if="message"
          :class="[
            'p-3 rounded-lg text-sm',
            message.includes('success')
              ? 'bg-green-500/10 border border-green-500/20 text-green-400'
              : 'bg-red-500/10 border border-red-500/20 text-red-400'
          ]"
        >
          {{ message }}
        </div>

        <!-- Profile tab -->
        <div v-if="activeTab === 'profile'" class="card p-6">
          <h2 class="text-lg font-semibold text-dark-100 mb-4">Profile Information</h2>
          
          <form @submit.prevent="updateProfile" class="space-y-4">
            <div class="space-y-2">
              <label class="block text-sm font-medium text-dark-300">Name</label>
              <input v-model="profile.name" type="text" class="input" required />
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-dark-300">Email</label>
              <input v-model="profile.email" type="email" class="input" required />
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-dark-300">Timezone</label>
              <select v-model="profile.timezone" class="input">
                <option v-for="tz in timezones" :key="tz" :value="tz">
                  {{ tz }}
                </option>
              </select>
            </div>

            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </form>
        </div>

        <!-- Security tab -->
        <div v-if="activeTab === 'security'" class="card p-6">
          <h2 class="text-lg font-semibold text-dark-100 mb-4">Change Password</h2>
          
          <form @submit.prevent="updatePassword" class="space-y-4">
            <div class="space-y-2">
              <label class="block text-sm font-medium text-dark-300">Current Password</label>
              <input v-model="password.current_password" type="password" class="input" required />
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-dark-300">New Password</label>
              <input v-model="password.password" type="password" class="input" required />
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-dark-300">Confirm New Password</label>
              <input v-model="password.password_confirmation" type="password" class="input" required />
            </div>

            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Updating...' : 'Update Password' }}
            </button>
          </form>
        </div>

        <!-- Appearance tab -->
        <div v-if="activeTab === 'appearance'" class="card p-6">
          <h2 class="text-lg font-semibold text-dark-100 mb-4">Appearance</h2>
          
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="font-medium text-dark-200">Theme</p>
                <p class="text-sm text-dark-400">Choose between dark and light mode</p>
              </div>
              <button
                :class="[
                  'relative inline-flex h-10 w-20 items-center rounded-full transition-colors',
                  isDark ? 'bg-dark-700' : 'bg-primary-500'
                ]"
                @click="toggleTheme"
              >
                <span
                  :class="[
                    'inline-flex h-8 w-8 transform items-center justify-center rounded-full bg-white shadow-lg transition-transform',
                    isDark ? 'translate-x-1' : 'translate-x-10'
                  ]"
                >
                  <MoonIcon v-if="isDark" class="w-4 h-4 text-dark-700" />
                  <SunIcon v-else class="w-4 h-4 text-yellow-500" />
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

