<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAccountsStore } from '@/stores/accounts'
import { accountsAPI } from '@/services/api'
import StatCard from '@/components/dashboard/StatCard.vue'
import EquityChart from '@/components/charts/EquityChart.vue'
import MonthlyPnLChart from '@/components/charts/MonthlyPnLChart.vue'
import TradeTable from '@/components/common/TradeTable.vue'
import {
  ArrowLeftIcon,
  ArrowPathIcon,
  TrashIcon,
  KeyIcon,
  CurrencyDollarIcon,
  ChartBarIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const accountsStore = useAccountsStore()

const loading = ref(false)
const accountData = ref(null)
const syncLogs = ref([])

// Watch accountData to ensure it never becomes invalid
watch(accountData, (newValue) => {
  if (newValue && (!newValue.account || Object.keys(newValue.account).length === 0)) {
    console.warn('AccountData account is missing, preventing invalid state')
    // Don't allow accountData to become invalid - restore previous structure
    if (newValue.account === null || newValue.account === undefined) {
      newValue.account = {}
    }
  }
}, { deep: true })

onMounted(async () => {
  await fetchAccountData()
  await fetchSyncLogs()
})

const fetchAccountData = async (showLoading = true) => {
  if (showLoading) {
    loading.value = true
  }
  try {
    const response = await accountsAPI.getOne(route.params.id)
    // Preserve existing data structure, only update with new data
    const newData = response.data
    
    // Ensure all required fields exist - merge with existing data to preserve structure
    const existingAccount = accountData.value?.account || {}
    const existingStats = accountData.value?.stats || {}
    
    accountData.value = {
      account: { ...existingAccount, ...(newData?.account || {}) },
      stats: { ...existingStats, ...(newData?.stats || {}) },
      recent_trades: newData?.recent_trades ?? accountData.value?.recent_trades ?? [],
      monthly_pnl: newData?.monthly_pnl ?? accountData.value?.monthly_pnl ?? [],
      equity_curve: newData?.equity_curve ?? accountData.value?.equity_curve ?? [],
      daily_pnl: newData?.daily_pnl ?? accountData.value?.daily_pnl ?? [],
    }
    
    // CRITICAL: Ensure account object always exists
    if (!accountData.value.account || Object.keys(accountData.value.account).length === 0) {
      console.warn('Account object is missing or empty, using fallback')
      accountData.value.account = existingAccount.id ? existingAccount : (newData?.account || {})
    }
    
    console.log('Account data loaded:', {
      hasAccount: !!accountData.value.account,
      accountId: accountData.value.account?.id,
      hasStats: !!accountData.value.stats,
      recentTradesCount: accountData.value.recent_trades?.length || 0,
      monthlyPnLCount: accountData.value.monthly_pnl?.length || 0,
    })
    
    // Final safety check - ensure accountData is never null
    if (!accountData.value || !accountData.value.account) {
      console.error('Account data is invalid after fetch!', accountData.value)
      // Don't reset to empty - keep existing data if available
      if (!accountData.value) {
        accountData.value = { account: {}, stats: {}, recent_trades: [], monthly_pnl: [], equity_curve: [], daily_pnl: [] }
      }
    }
  } catch (error) {
    console.error('Failed to fetch account:', error)
    // Don't clear accountData on error if it already exists - keep showing old data
    if (!accountData.value) {
      if (error.response?.status === 404) {
        alert('Account not found')
        router.push('/accounts')
      } else if (error.response?.status === 403) {
        alert('You do not have permission to view this account')
        router.push('/accounts')
      } else {
        alert('Failed to load account data. Please try again.')
      }
    }
  } finally {
    if (showLoading) {
      loading.value = false
    }
  }
}

const fetchSyncLogs = async () => {
  try {
    const response = await accountsAPI.getSyncLogs(route.params.id)
    console.log('Sync logs response:', response.data)
    // Handle paginated response
    if (response.data?.data) {
      syncLogs.value = response.data.data
    } else if (Array.isArray(response.data)) {
      syncLogs.value = response.data
    } else {
      syncLogs.value = []
    }
    console.log('Sync logs loaded:', syncLogs.value.length, 'items')
  } catch (error) {
    console.error('Failed to fetch sync logs:', error)
    syncLogs.value = []
  }
}

const handleSync = async () => {
  try {
    // Preserve current accountData before sync
    const previousData = { ...accountData.value }
    
    await accountsStore.syncAccount(route.params.id)
    
    // Refresh data after a delay, but don't show loading spinner to avoid hiding sections
    setTimeout(async () => {
      try {
        await fetchAccountData(false) // Don't show loading spinner
        await fetchSyncLogs() // Also refresh sync logs
      } catch (error) {
        console.error('Failed to refresh data after sync:', error)
        // Restore previous data if refresh fails
        if (previousData && Object.keys(previousData).length > 0) {
          accountData.value = previousData
        }
      }
    }, 2000)
  } catch (error) {
    console.error('Sync failed:', error)
    // Don't clear accountData on sync error
  }
}

const handleDelete = async () => {
  if (!confirm('Are you sure you want to delete this account? This action cannot be undone.')) {
    return
  }
  
  try {
    await accountsStore.deleteAccount(route.params.id)
    router.push('/accounts')
  } catch (error) {
    console.error('Failed to delete account:', error)
  }
}

const newToken = ref('')
const handleRegenerateToken = async () => {
  if (!confirm('This will invalidate the current API token. Continue?')) {
    return
  }
  
  try {
    const response = await accountsStore.regenerateToken(route.params.id)
    newToken.value = response.api_token
  } catch (error) {
    console.error('Failed to regenerate token:', error)
  }
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleString()
}

const getStatusClass = (status) => {
  switch (status) {
    case 'success': return 'badge-success'
    case 'failed': return 'badge-danger'
    case 'processing': return 'badge-warning'
    default: return 'badge-info'
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button
          class="p-2 rounded-lg hover:bg-dark-800 text-dark-400"
          @click="router.push('/accounts')"
        >
          <ArrowLeftIcon class="w-5 h-5" />
        </button>
        <div v-if="accountData">
          <h1 class="text-2xl font-bold text-dark-100">
            {{ accountData.account?.name || accountData.account?.broker_name || 'Account' }}
          </h1>
          <p class="text-dark-400">
            {{ accountData.account?.server || '' }} · {{ accountData.account?.login_masked || '' }}
          </p>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <button
          class="btn-secondary flex items-center gap-2"
          @click="handleSync"
        >
          <ArrowPathIcon class="w-4 h-4" />
          Sync Now
        </button>
        <button
          class="btn-danger flex items-center gap-2"
          @click="handleDelete"
        >
          <TrashIcon class="w-4 h-4" />
          Delete
        </button>
      </div>
    </div>

    <!-- Loading (only show on initial load when no data exists) -->
    <div v-if="loading && !accountData" class="flex justify-center py-12">
      <div class="spinner" />
    </div>

    <!-- Main content (show if accountData exists, even during refresh) -->
    <template v-if="accountData && accountData.account">
      <!-- Debug info (remove in production) -->
      <div v-if="false" class="p-4 bg-dark-800 rounded-lg mb-4 text-xs">
        <pre>{{ JSON.stringify(accountData, null, 2) }}</pre>
      </div>
      
      <!-- Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard
          label="Balance"
          :value="accountData.account?.latest_balance?.balance || accountData.account?.balance || 0"
          prefix="$"
          :icon="CurrencyDollarIcon"
        />
        <StatCard
          label="Equity"
          :value="accountData.account?.latest_balance?.equity || accountData.account?.equity || 0"
          prefix="$"
          :icon="ChartBarIcon"
        />
        <StatCard
          label="Win Rate"
          :value="accountData.stats?.winrate || 0"
          suffix="%"
        />
        <StatCard
          label="Total P/L"
          :value="accountData.stats?.total_profit || 0"
          prefix="$"
          :type="(accountData.stats?.total_profit || 0) >= 0 ? 'profit' : 'loss'"
        />
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <EquityChart :data="accountData.equity_curve || []" />
        <MonthlyPnLChart :data="accountData.monthly_pnl || []" />
      </div>

      <!-- Trade History -->
      <div class="card overflow-hidden">
        <div class="p-4 border-b border-dark-800">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-dark-300">Trade History</h3>
            <router-link 
              :to="`/trades?account_id=${accountData.account.id}`" 
              class="text-xs text-primary-400 hover:text-primary-300"
            >
              View all →
            </router-link>
          </div>
        </div>
        <div class="table-container">
          <TradeTable
            :trades="accountData.recent_trades || []"
            :loading="loading"
            compact
          />
          <div v-if="!loading && (!accountData.recent_trades || accountData.recent_trades.length === 0)" 
               class="text-center text-dark-500 py-8">
            No trades found
          </div>
        </div>
      </div>

      <!-- Account info -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Details -->
        <div class="card p-4">
          <h3 class="text-sm font-medium text-dark-300 mb-4">Account Details</h3>
          <div class="space-y-3">
            <div class="flex justify-between py-2 border-b border-dark-800">
              <span class="text-dark-400">Broker</span>
              <span class="text-dark-100">{{ accountData.account?.broker_name || '-' }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-dark-800">
              <span class="text-dark-400">Server</span>
              <span class="text-dark-100">{{ accountData.account?.server || '-' }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-dark-800">
              <span class="text-dark-400">Platform</span>
              <span class="text-dark-100 uppercase">{{ accountData.account?.platform || '-' }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-dark-800">
              <span class="text-dark-400">Type</span>
              <span :class="[
                'px-2 py-0.5 text-xs rounded',
                accountData.account?.account_type === 'live' 
                  ? 'bg-green-500/20 text-green-400' 
                  : 'bg-blue-500/20 text-blue-400'
              ]">
                {{ accountData.account?.account_type || '-' }}
              </span>
            </div>
            <div class="flex justify-between py-2 border-b border-dark-800">
              <span class="text-dark-400">Currency</span>
              <span class="text-dark-100">{{ accountData.account?.currency || '-' }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-dark-800">
              <span class="text-dark-400">Leverage</span>
              <span class="text-dark-100">{{ accountData.account?.leverage || '-' }}</span>
            </div>
            <div class="flex justify-between py-2">
              <span class="text-dark-400">Last Sync</span>
              <span class="text-dark-100">{{ formatDate(accountData.account?.last_sync_at) }}</span>
            </div>
          </div>
        </div>

        <!-- API Token -->
        <div class="card p-4">
          <h3 class="text-sm font-medium text-dark-300 mb-4">EA Bridge Configuration</h3>
          
          <div v-if="newToken" class="mb-4 p-3 bg-green-500/10 border border-green-500/30 rounded-lg">
            <p class="text-sm font-medium text-green-400 mb-2">⚠️ New API Token Generated!</p>
            <p class="text-xs text-dark-400 mb-2">Save this token - it won't be shown again:</p>
            <div class="p-3 bg-dark-800 rounded-lg font-mono text-xs text-primary-400 break-all mb-3">
              {{ newToken }}
            </div>
            <p class="text-xs text-dark-400">
              Update your EA with:<br/>
              <span class="font-mono text-primary-400">API_TOKEN = "{{ newToken }}"</span>
            </p>
          </div>
          
          <div class="space-y-3 mb-4">
            <div class="p-3 bg-dark-800 rounded-lg">
              <p class="text-xs text-dark-500 mb-1">Account ID (for EA):</p>
              <p class="font-mono text-lg font-bold text-primary-400">{{ accountData.account?.id || '-' }}</p>
            </div>
            
            <div class="p-3 bg-dark-800 rounded-lg">
              <p class="text-xs text-dark-500 mb-1">API URL (ngrok):</p>
              <p class="font-mono text-xs text-primary-400 break-all">
                https://ghislaine-boundless-billye.ngrok-free.dev/api/v1/incoming/trades
              </p>
            </div>
          </div>
          
          <div class="p-3 bg-blue-500/10 border border-blue-500/30 rounded-lg mb-4">
            <p class="text-xs font-medium text-blue-400 mb-2">📋 EA Configuration:</p>
            <div class="text-xs text-dark-400 space-y-1 font-mono">
              <div>API_URL = "https://ghislaine-boundless-billye.ngrok-free.dev/api/v1/incoming/trades"</div>
              <div>API_TOKEN = "[Token dari sini]"</div>
              <div>ACCOUNT_ID = {{ accountData.account?.id || '-' }}</div>
            </div>
          </div>
          
          <p class="text-xs text-dark-500 mb-4">
            💡 Token hanya ditampilkan sekali saat account dibuat atau di-regenerate. 
            Jika lupa, regenerate token baru.
          </p>
          
          <button
            class="btn-secondary flex items-center gap-2 w-full"
            @click="handleRegenerateToken"
          >
            <KeyIcon class="w-4 h-4" />
            Regenerate Token
          </button>
        </div>
      </div>

      <!-- Sync logs -->
      <div class="card overflow-hidden">
        <div class="p-4 border-b border-dark-800">
          <h3 class="text-sm font-medium text-dark-300">Recent Sync Logs</h3>
        </div>
        <div class="table-container">
          <table class="table">
            <thead>
              <tr>
                <th>Time</th>
                <th>Type</th>
                <th>Status</th>
                <th>Trades</th>
                <th>Duration</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in syncLogs" :key="log.id">
                <td class="text-dark-400 text-sm">{{ formatDate(log.created_at) }}</td>
                <td class="text-dark-300">{{ log.source || 'N/A' }}</td>
                <td>
                  <span :class="['badge', getStatusClass(log.status)]">
                    {{ log.status }}
                  </span>
                </td>
                <td class="font-mono text-dark-300">
                  {{ log.stats?.new_trades || 0 }} new, {{ log.stats?.updated_trades || 0 }} updated
                </td>
                <td class="text-dark-400 text-sm">{{ log.formatted_duration || (log.duration_ms ? (log.duration_ms < 1000 ? log.duration_ms + 'ms' : (log.duration_ms / 1000).toFixed(1) + 's') : '-') }}</td>
                <td class="text-dark-400 text-sm truncate max-w-xs">{{ log.message || '-' }}</td>
              </tr>
              <tr v-if="syncLogs.length === 0">
                <td colspan="6" class="text-center text-dark-500 py-8">
                  No sync logs yet
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

