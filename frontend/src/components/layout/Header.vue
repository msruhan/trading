<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  Bars3Icon,
  SunIcon,
  MoonIcon,
  BellIcon,
  ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  title: {
    type: String,
    default: 'Dashboard',
  },
})

const emit = defineEmits(['toggle-sidebar'])

const authStore = useAuthStore()

const isDark = ref(document.documentElement.classList.contains('dark'))

const toggleTheme = () => {
  isDark.value = !isDark.value
  authStore.setTheme(isDark.value ? 'dark' : 'light')
}

const currentTime = ref(new Date())

// Update time every second
setInterval(() => {
  currentTime.value = new Date()
}, 1000)

const formattedTime = computed(() => {
  return currentTime.value.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  })
})

const formattedDate = computed(() => {
  return currentTime.value.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  })
})
</script>

<template>
  <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-dark-800 bg-dark-900/95 backdrop-blur px-4 md:px-6">
    <!-- Mobile menu button -->
    <button
      class="lg:hidden p-2 rounded-lg hover:bg-dark-800 text-dark-400"
      @click="emit('toggle-sidebar')"
    >
      <Bars3Icon class="w-5 h-5" />
    </button>

    <!-- Page title -->
    <h1 class="text-lg font-semibold text-dark-100">
      {{ title }}
    </h1>

    <!-- Spacer -->
    <div class="flex-1" />

    <!-- Time display -->
    <div class="hidden md:flex items-center gap-3 text-sm">
      <span class="text-dark-400">{{ formattedDate }}</span>
      <span class="font-mono text-dark-100">{{ formattedTime }}</span>
    </div>

    <!-- Action buttons -->
    <div class="flex items-center gap-2">
      <!-- Refresh button -->
      <button
        class="p-2 rounded-lg hover:bg-dark-800 text-dark-400 hover:text-dark-100 transition-colors"
        title="Refresh data"
        @click="$router.go(0)"
      >
        <ArrowPathIcon class="w-5 h-5" />
      </button>

      <!-- Notifications -->
      <button
        class="relative p-2 rounded-lg hover:bg-dark-800 text-dark-400 hover:text-dark-100 transition-colors"
        title="Notifications"
      >
        <BellIcon class="w-5 h-5" />
        <span class="absolute top-1 right-1 w-2 h-2 bg-primary-500 rounded-full" />
      </button>

      <!-- Theme toggle -->
      <button
        class="p-2 rounded-lg hover:bg-dark-800 text-dark-400 hover:text-dark-100 transition-colors"
        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        @click="toggleTheme"
      >
        <MoonIcon v-if="isDark" class="w-5 h-5" />
        <SunIcon v-else class="w-5 h-5" />
      </button>
    </div>
  </header>
</template>

