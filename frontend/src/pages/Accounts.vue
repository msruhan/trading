<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAccountsStore } from '@/stores/accounts'
import {
  PlusIcon,
  ArrowPathIcon,
  EllipsisVerticalIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const accountsStore = useAccountsStore()

const showAddModal = ref(false)
const addingAccount = ref(false)
const newAccount = reactive({
  name: '',
  broker_name: '',
  server: '',
  login: '',
  investor_password: '',
  account_type: 'demo',
  platform: 'mt4',
  currency: 'USD',
  leverage: '1:500',
  initial_balance: 0,
})

const generatedToken = ref('')

onMounted(async () => {
  await accountsStore.fetchAccounts()
})

const getStatusColor = (status) => {
  switch (status) {
    case 'active': return 'text-green-400'
    case 'syncing': return 'text-blue-400'
    case 'error': return 'text-red-400'
    default: return 'text-gray-400'
  }
}

const getStatusIcon = (status) => {
  switch (status) {
    case 'active': return CheckCircleIcon
    case 'syncing': return ClockIcon
    case 'error': return ExclamationCircleIcon
    default: return ClockIcon
  }
}

const formatDate = (date) => {
  if (!date) return 'Never'
  return new Date(date).toLocaleString()
}

const handleSync = async (accountId) => {
  try {
    await accountsStore.syncAccount(accountId)
  } catch (error) {
    console.error('Sync failed:', error)
  }
}

const handleAddAccount = async () => {
  addingAccount.value = true
  try {
    const result = await accountsStore.createAccount(newAccount)
    generatedToken.value = result.api_token
    // Reset form
    Object.assign(newAccount, {
      name: '',
      broker_name: '',
      server: '',
      login: '',
      investor_password: '',
      account_type: 'demo',
      platform: 'mt4',
      currency: 'USD',
      leverage: '1:500',
      initial_balance: 0,
    })
  } catch (error) {
    console.error('Failed to add account:', error)
  } finally {
    addingAccount.value = false
  }
}

const closeAddModal = () => {
  showAddModal.value = false
  generatedToken.value = ''
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-dark-100">Trading Accounts</h1>
        <p class="text-dark-400">Manage your connected broker accounts</p>
      </div>
      <button
        class="btn-primary flex items-center gap-2"
        @click="showAddModal = true"
      >
        <PlusIcon class="w-4 h-4" />
        Add Account
      </button>
    </div>

    <!-- Accounts grid -->
    <div v-if="accountsStore.loading" class="flex justify-center py-12">
      <div class="spinner" />
    </div>

    <div v-else-if="accountsStore.accounts.length === 0" class="card p-12 text-center">
      <div class="w-16 h-16 mx-auto rounded-full bg-dark-800 flex items-center justify-center mb-4">
        <PlusIcon class="w-8 h-8 text-dark-500" />
      </div>
      <h3 class="text-lg font-medium text-dark-200">No accounts connected</h3>
      <p class="text-dark-400 mt-1">Add your first trading account to get started</p>
      <button
        class="btn-primary mt-4"
        @click="showAddModal = true"
      >
        Add Account
      </button>
    </div>

    <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="account in accountsStore.accounts"
        :key="account.id"
        class="card p-4 hover:border-dark-700 transition-colors cursor-pointer"
        @click="router.push(`/accounts/${account.id}`)"
      >
        <!-- Header -->
        <div class="flex items-start justify-between mb-4">
          <div>
            <h3 class="font-medium text-dark-100">{{ account.name || account.broker_name }}</h3>
            <p class="text-sm text-dark-400">{{ account.server }}</p>
          </div>
          <div class="flex items-center gap-2">
            <span :class="['flex items-center gap-1 text-xs', getStatusColor(account.status)]">
              <component :is="getStatusIcon(account.status)" class="w-4 h-4" />
              {{ account.status }}
            </span>
          </div>
        </div>

        <!-- Balance info -->
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <p class="text-xs text-dark-500 uppercase">Balance</p>
            <p class="text-lg font-semibold text-dark-100 font-numeric">
              ${{ (account.latest_balance?.balance || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-dark-500 uppercase">Equity</p>
            <p class="text-lg font-semibold text-dark-100 font-numeric">
              ${{ (account.latest_balance?.equity || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between pt-4 border-t border-dark-800">
          <div class="text-xs text-dark-500">
            <span class="uppercase">{{ account.platform }}</span>
            <span class="mx-1">•</span>
            <span>{{ account.account_type }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs text-dark-500">
              Last sync: {{ formatDate(account.last_sync_at) }}
            </span>
            <button
              class="p-1 rounded hover:bg-dark-800 text-dark-400 hover:text-dark-100"
              @click.stop="handleSync(account.id)"
            >
              <ArrowPathIcon :class="['w-4 h-4', account.status === 'syncing' && 'animate-spin']" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Account Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="fixed inset-0 bg-dark-950/80 backdrop-blur-sm" @click="closeAddModal" />
      
      <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative w-full max-w-lg card p-6" @click.stop>
          <!-- Token display -->
          <div v-if="generatedToken" class="space-y-4">
            <h2 class="text-xl font-semibold text-dark-100">Account Created!</h2>
            <p class="text-dark-400">
              Save this API token. It won't be shown again.
            </p>
            <div class="p-4 bg-dark-800 rounded-lg font-mono text-sm text-primary-400 break-all">
              {{ generatedToken }}
            </div>
            <button class="btn-primary w-full" @click="closeAddModal">
              Done
            </button>
          </div>

          <!-- Add form -->
          <form v-else @submit.prevent="handleAddAccount" class="space-y-4">
            <h2 class="text-xl font-semibold text-dark-100">Add Trading Account</h2>

            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="block text-sm font-medium text-dark-300">Broker Name</label>
                <input v-model="newAccount.broker_name" type="text" class="input" placeholder="e.g., IC Markets" required />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-dark-300">Server</label>
                <input v-model="newAccount.server" type="text" class="input" placeholder="e.g., ICMarkets-Demo" required />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="block text-sm font-medium text-dark-300">Login</label>
                <input v-model="newAccount.login" type="text" class="input" placeholder="Account number" required />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-dark-300">Investor Password</label>
                <input v-model="newAccount.investor_password" type="password" class="input" placeholder="Read-only password" required />
              </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
              <div class="space-y-2">
                <label class="block text-sm font-medium text-dark-300">Platform</label>
                <select v-model="newAccount.platform" class="input">
                  <option value="mt4">MT4</option>
                  <option value="mt5">MT5</option>
                  <option value="ctrader">cTrader</option>
                </select>
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-dark-300">Type</label>
                <select v-model="newAccount.account_type" class="input">
                  <option value="demo">Demo</option>
                  <option value="live">Live</option>
                </select>
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-dark-300">Currency</label>
                <select v-model="newAccount.currency" class="input">
                  <option value="USD">USD</option>
                  <option value="EUR">EUR</option>
                  <option value="GBP">GBP</option>
                </select>
              </div>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-dark-300">Initial Balance</label>
              <input v-model.number="newAccount.initial_balance" type="number" step="0.01" class="input" placeholder="Starting balance" />
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" class="btn-secondary flex-1" @click="closeAddModal">
                Cancel
              </button>
              <button type="submit" class="btn-primary flex-1" :disabled="addingAccount">
                {{ addingAccount ? 'Adding...' : 'Add Account' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

