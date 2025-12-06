import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { accountsAPI } from '@/services/api'

export const useAccountsStore = defineStore('accounts', () => {
  // State
  const accounts = ref([])
  const currentAccount = ref(null)
  const loading = ref(false)
  const error = ref(null)

  // Getters
  const activeAccounts = computed(() => 
    accounts.value.filter(a => a.status === 'active')
  )
  
  const totalBalance = computed(() => 
    accounts.value.reduce((sum, a) => sum + (a.latest_balance?.balance || 0), 0)
  )

  const totalEquity = computed(() => 
    accounts.value.reduce((sum, a) => sum + (a.latest_balance?.equity || 0), 0)
  )

  // Actions
  async function fetchAccounts() {
    loading.value = true
    error.value = null
    
    try {
      const response = await accountsAPI.getAll()
      accounts.value = response.data.accounts
      return response.data.accounts
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch accounts'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchAccount(id) {
    loading.value = true
    error.value = null
    
    try {
      const response = await accountsAPI.getOne(id)
      currentAccount.value = response.data.account
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch account'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createAccount(data) {
    loading.value = true
    error.value = null
    
    try {
      const response = await accountsAPI.create(data)
      accounts.value.push(response.data.account)
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create account'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateAccount(id, data) {
    loading.value = true
    error.value = null
    
    try {
      const response = await accountsAPI.update(id, data)
      const index = accounts.value.findIndex(a => a.id === id)
      if (index !== -1) {
        accounts.value[index] = response.data.account
      }
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update account'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteAccount(id) {
    loading.value = true
    error.value = null
    
    try {
      await accountsAPI.delete(id)
      accounts.value = accounts.value.filter(a => a.id !== id)
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete account'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function syncAccount(id) {
    try {
      const response = await accountsAPI.sync(id)
      // Update account status
      const index = accounts.value.findIndex(a => a.id === id)
      if (index !== -1) {
        accounts.value[index].status = 'syncing'
      }
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to sync account'
      throw err
    }
  }

  async function regenerateToken(id) {
    try {
      const response = await accountsAPI.regenerateToken(id)
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to regenerate token'
      throw err
    }
  }

  function reset() {
    accounts.value = []
    currentAccount.value = null
    error.value = null
  }

  return {
    // State
    accounts,
    currentAccount,
    loading,
    error,
    // Getters
    activeAccounts,
    totalBalance,
    totalEquity,
    // Actions
    fetchAccounts,
    fetchAccount,
    createAccount,
    updateAccount,
    deleteAccount,
    syncAccount,
    regenerateToken,
    reset,
  }
})

