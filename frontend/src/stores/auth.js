import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authAPI } from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const token = ref(null)
  const initialized = ref(false)
  const loading = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  // Actions
  async function initAuth() {
    const savedToken = localStorage.getItem('auth_token')
    const savedUser = localStorage.getItem('user')

    if (savedToken && savedUser) {
      token.value = savedToken
      user.value = JSON.parse(savedUser)
      
      // Verify token is still valid
      try {
        const response = await authAPI.getUser()
        user.value = response.data.user
        localStorage.setItem('user', JSON.stringify(user.value))
      } catch (error) {
        logout()
      }
    }

    initialized.value = true
  }

  async function login(credentials) {
    loading.value = true
    try {
      const response = await authAPI.login(credentials)
      token.value = response.data.token
      user.value = response.data.user
      
      localStorage.setItem('auth_token', token.value)
      localStorage.setItem('user', JSON.stringify(user.value))
      
      return response.data
    } finally {
      loading.value = false
    }
  }

  async function register(data) {
    loading.value = true
    try {
      const response = await authAPI.register(data)
      token.value = response.data.token
      user.value = response.data.user
      
      localStorage.setItem('auth_token', token.value)
      localStorage.setItem('user', JSON.stringify(user.value))
      
      return response.data
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await authAPI.logout()
    } catch (error) {
      // Ignore errors during logout
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
    }
  }

  async function updateProfile(data) {
    loading.value = true
    try {
      const response = await authAPI.updateProfile(data)
      user.value = response.data.user
      localStorage.setItem('user', JSON.stringify(user.value))
      return response.data
    } finally {
      loading.value = false
    }
  }

  async function updatePassword(data) {
    loading.value = true
    try {
      const response = await authAPI.updatePassword(data)
      return response.data
    } finally {
      loading.value = false
    }
  }

  function setTheme(theme) {
    if (user.value) {
      user.value.settings = { ...user.value.settings, theme }
      localStorage.setItem('user', JSON.stringify(user.value))
    }
    
    if (theme === 'dark') {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
    
    localStorage.setItem('theme', theme)
  }

  function initTheme() {
    const savedTheme = localStorage.getItem('theme') || user.value?.settings?.theme || 'dark'
    setTheme(savedTheme)
  }

  return {
    // State
    user,
    token,
    initialized,
    loading,
    // Getters
    isAuthenticated,
    isAdmin,
    // Actions
    initAuth,
    login,
    register,
    logout,
    updateProfile,
    updatePassword,
    setTheme,
    initTheme,
  }
})

