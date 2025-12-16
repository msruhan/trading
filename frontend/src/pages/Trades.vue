<script setup>
import { ref, onMounted, watch, reactive } from 'vue'
import { Teleport, Transition } from 'vue'
import { useRouter } from 'vue-router'
import { tradesAPI, eaCommandsAPI, accountsAPI } from '@/services/api'
import { useAccountsStore } from '@/stores/accounts'
import TradeTable from '@/components/common/TradeTable.vue'
import {
  FunnelIcon,
  PlusIcon,
  DocumentArrowUpIcon,
  MagnifyingGlassIcon,
  XMarkIcon,
  StopIcon,
  PlayIcon,
  PauseIcon,
  ClockIcon,
  ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const accountsStore = useAccountsStore()

const trades = ref([])
const loading = ref(false)
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })

const filters = reactive({
  account_id: '',
  pair: '',
  type: '',
  status: 'closed',
  start_date: '',
  end_date: '',
  profitable: '',
})

const showFilters = ref(false)
const showScheduleModal = ref(false)
const selectedAccountId = ref('')
const scheduleDays = ref([false, false, false, false, false, false, false]) // Monday to Sunday
const scheduleStartTime = ref('10:00')
const scheduleEndTime = ref('15:00')
const sendingCommand = ref(false)
const targetMagicBuy = ref('') // Magic number for BUY orders of EA to control
const targetMagicSell = ref('') // Magic number for SELL orders of EA to control
const interceptAllTrading = ref(false) // Intercept ALL trading on this account
const tradingMode = ref(0) // Trading Mode: 0=Auto, 1=Buy Only, 2=Sell Only
const syncing = ref(false)
const eaInfo = ref(null)
const loadingEAInfo = ref(false)

onMounted(async () => {
  try {
  await accountsStore.fetchAccounts()
    // Check if account_id is in query params (from AccountDetail page)
    const accountId = new URLSearchParams(window.location.search).get('account_id')
    if (accountId) {
      filters.account_id = accountId
    }
  await fetchTrades()
  } catch (error) {
    console.error('Failed to initialize trades page:', error)
  }
})

const fetchTrades = async (page = 1) => {
  loading.value = true
  try {
    const params = { page, per_page: 20 }
    
    // Add filters
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        params[key] = value
      }
    })

    console.log('Fetching trades with params:', params)
    const response = await tradesAPI.getAll(params)
    console.log('Trades API response (full):', response)
    console.log('Trades API response.data:', response.data)
    console.log('Trades API response.data type:', typeof response.data)
    console.log('Is array?', Array.isArray(response.data))
    
    // Handle different response structures
    let tradesData = []
    let paginationData = {
      current_page: 1,
      last_page: 1,
      total: 0,
    }
    
    if (response.data?.data && Array.isArray(response.data.data)) {
      // Paginated response (Laravel pagination)
      console.log('Detected paginated response')
      tradesData = response.data.data
      paginationData = {
        current_page: response.data.current_page || 1,
        last_page: response.data.last_page || 1,
        total: response.data.total || 0,
    }
    } else if (Array.isArray(response.data)) {
      // Direct array response
      console.log('Detected direct array response')
      tradesData = response.data
      paginationData = {
        current_page: 1,
        last_page: 1,
        total: response.data.length,
      }
    } else if (response.data?.trades && Array.isArray(response.data.trades)) {
      // Wrapped in trades property
      console.log('Detected trades property response')
      tradesData = response.data.trades
      paginationData = {
        current_page: 1,
        last_page: 1,
        total: response.data.trades.length,
      }
    } else {
      // Unexpected structure
      console.warn('Unexpected response structure:', response.data)
      console.warn('Response keys:', Object.keys(response.data || {}))
      tradesData = []
    }
    
    // Assign trades - ensure it's a proper array
    if (Array.isArray(tradesData)) {
      trades.value = tradesData
      console.log('Trades assigned:', trades.value.length, 'items')
    } else {
      console.error('TradesData is not an array!', tradesData)
      trades.value = []
    }
    
    pagination.value = paginationData
    console.log('Pagination:', pagination.value)
  } catch (error) {
    console.error('Failed to fetch trades:', error)
    console.error('Error details:', error.response?.data || error.message)
    trades.value = []
    pagination.value = {
      current_page: 1,
      last_page: 1,
      total: 0,
    }
  } finally {
    loading.value = false
    console.log('Loading finished, trades count:', trades.value?.length || 0)
  }
}

const handleFilterChange = async () => {
  // Reset to page 1 when filter changes
  await fetchTrades(1)
}

const resetFilters = () => {
  Object.assign(filters, {
    account_id: '',
    pair: '',
    type: '',
    status: 'closed',
    start_date: '',
    end_date: '',
    profitable: '',
  })
  fetchTrades(1)
}

const handlePageChange = (page) => {
  fetchTrades(page)
}

// Show upload modal
const showUploadModal = ref(false)
const uploading = ref(false)
const uploadAccountId = ref('')
const uploadFile = ref(null)

const handleFileUpload = async () => {
  if (!uploadAccountId.value || !uploadFile.value) return
  
  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('account_id', uploadAccountId.value)
    formData.append('file', uploadFile.value)
    
    const response = await tradesAPI.uploadStatement(formData)
    alert(response.data.message)
    showUploadModal.value = false
    uploadFile.value = null
    await fetchTrades()
  } catch (error) {
    alert(error.response?.data?.message || 'Upload failed')
  } finally {
    uploading.value = false
  }
}

const sendCommand = async (command) => {
  if (!filters.account_id) {
    alert('Please select an account first')
    return
  }

  const magicBuy = targetMagicBuy.value ? parseInt(targetMagicBuy.value) : 0
  const magicSell = targetMagicSell.value ? parseInt(targetMagicSell.value) : 0
  const interceptAll = interceptAllTrading.value
  
  const commandText = command === 'close_all' 
    ? (interceptAll ? 'close all trades (intercept all trading)' : ((magicBuy > 0 || magicSell > 0) ? `close all trades with magic BUY ${magicBuy || 'all'} and magic SELL ${magicSell || 'all'}` : 'close all trades'))
    : command === 'pause' 
    ? (interceptAll ? 'pause trading (intercept all trading)' : 'pause trading')
    : (interceptAll ? 'resume trading (intercept all trading)' : 'resume trading')

  if (!confirm(`Are you sure you want to ${commandText}?`)) {
    return
  }

  sendingCommand.value = true
  try {
    const params = {}
    if (interceptAll) {
      params.intercept_all = true
    } else if (command === 'close_all' && (magicBuy > 0 || magicSell > 0)) {
      if (magicBuy > 0) params.magic_buy = magicBuy
      if (magicSell > 0) params.magic_sell = magicSell
    }
    
    await eaCommandsAPI.create(filters.account_id, { 
      command,
      params: Object.keys(params).length > 0 ? params : undefined
    })
    alert(`Command "${command}" sent successfully. The EA will process it on the next sync.`)
  } catch (error) {
    console.error('Failed to send command:', error)
    alert('Failed to send command. Please try again.')
  } finally {
    sendingCommand.value = false
  }
}

const openScheduleModal = () => {
  console.log('openScheduleModal called')
  if (!filters.account_id) {
    alert('Please select an account first')
    return
  }
  selectedAccountId.value = filters.account_id
  showScheduleModal.value = true
  console.log('showScheduleModal set to:', showScheduleModal.value)
}

const sendScheduleCommand = async () => {
  const selectedDays = scheduleDays.value
    .map((selected, index) => selected ? index + 1 : null)
    .filter(day => day !== null)

  if (selectedDays.length === 0) {
    alert('Please select at least one day')
    return
  }

  sendingCommand.value = true
  try {
    const params = {
      days: selectedDays,
      start_time: scheduleStartTime.value,
      end_time: scheduleEndTime.value,
    }
    
    // Include intercept_all or magic buy and sell if specified
    if (interceptAllTrading.value) {
      params.intercept_all = true
    } else {
      const magicBuy = targetMagicBuy.value ? parseInt(targetMagicBuy.value) : 0
      const magicSell = targetMagicSell.value ? parseInt(targetMagicSell.value) : 0
      if (magicBuy > 0) {
        params.magic_buy = magicBuy
      }
      if (magicSell > 0) {
        params.magic_sell = magicSell
      }
    }
    
    await eaCommandsAPI.create(selectedAccountId.value, {
      command: 'schedule',
      params,
    })
    alert('Schedule command sent successfully. The EA will process it on the next sync.')
    showScheduleModal.value = false
  } catch (error) {
    console.error('Failed to send schedule command:', error)
    alert('Failed to send schedule command. Please try again.')
  } finally {
    sendingCommand.value = false
  }
}

const fetchEAInfo = async () => {
  if (!filters.account_id) {
    eaInfo.value = null
    return
  }

  loadingEAInfo.value = true
  try {
    const response = await accountsAPI.getEAInfo(filters.account_id)
    eaInfo.value = response.data.ea_info
  } catch (error) {
    console.error('Failed to fetch EA info:', error)
    eaInfo.value = null
  } finally {
    loadingEAInfo.value = false
  }
}

const triggerSync = async () => {
  if (!filters.account_id) {
    alert('Please select an account first')
    return
  }

  syncing.value = true
  try {
    await accountsAPI.triggerSync(filters.account_id)
    // Wait a bit then fetch EA info
    setTimeout(() => {
      fetchEAInfo()
    }, 2000)
    alert('Sync request sent to EA. The EA will sync on the next check.')
  } catch (error) {
    console.error('Failed to trigger sync:', error)
    alert('Failed to trigger sync. Please try again.')
  } finally {
    syncing.value = false
  }
}

const sendTradingModeCommand = async () => {
  if (!filters.account_id) {
    alert('Please select an account first')
    return
  }

  sendingCommand.value = true
  try {
    await eaCommandsAPI.create(filters.account_id, {
      command: 'set_trading_mode',
      params: {
        trading_mode: tradingMode.value,
      },
    })
    alert(`Trading mode set to ${getTradingModeLabel(tradingMode.value)}. The EA will process it on the next sync.`)
    // Refresh EA info after a delay
    setTimeout(() => {
      fetchEAInfo()
    }, 2000)
  } catch (error) {
    console.error('Failed to send trading mode command:', error)
    alert('Failed to send trading mode command. Please try again.')
  } finally {
    sendingCommand.value = false
  }
}

const getTradingModeLabel = (mode) => {
  switch (mode) {
    case 1:
      return 'Buy Only'
    case 2:
      return 'Sell Only'
    default:
      return 'Auto'
  }
}

const getMarketRegimeClass = (regime) => {
  switch (regime) {
    case 'BULLISH':
      return 'text-green-400'
    case 'BEARISH':
      return 'text-red-400'
    case 'SIDEWAYS':
      return 'text-yellow-400'
    default:
      return 'text-gray-400'
  }
}

// Watch for account filter changes
watch(() => filters.account_id, (newAccountId) => {
  if (newAccountId) {
    fetchEAInfo()
  } else {
    eaInfo.value = null
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-100">Trade History</h1>
        <p class="text-dark-400">View and manage all your trades</p>
      </div>
      <div class="flex items-center gap-3">
        <button
          class="btn-secondary flex items-center gap-2"
          @click="showUploadModal = true"
        >
          <DocumentArrowUpIcon class="w-4 h-4" />
          Upload
        </button>
        <button
          class="btn-primary flex items-center gap-2"
          @click="router.push('/trades/new')"
        >
          <PlusIcon class="w-4 h-4" />
          Add Trade
        </button>
      </div>
    </div>

    <!-- EA Control Panel -->
    <div class="card p-6 mb-4">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-dark-200">EA Trading Control</h3>
        <button
          class="btn btn-secondary flex items-center gap-2"
          :disabled="syncing || loadingEAInfo || !filters.account_id"
          @click="triggerSync"
        >
          <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': syncing }" />
          {{ syncing ? 'Syncing...' : 'Sync' }}
        </button>
      </div>

      <div v-if="!filters.account_id" class="text-center py-8">
        <p class="text-sm text-dark-500">
          ⚠️ Please select an account from the filters below to enable EA controls
        </p>
      </div>

      <div v-else>
        <!-- EA Information Display -->
        <div v-if="eaInfo || loadingEAInfo" class="mb-6 p-4 bg-dark-800 rounded-lg border border-dark-700">
          <h4 class="text-sm font-semibold text-dark-300 mb-3">EA Information</h4>
          <div v-if="loadingEAInfo" class="text-center py-4">
            <ArrowPathIcon class="w-5 h-5 animate-spin mx-auto text-primary-500" />
            <p class="text-xs text-dark-500 mt-2">Loading EA information...</p>
          </div>
          <div v-else-if="eaInfo" class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
              <p class="text-xs text-dark-500 mb-1">Magic Buy</p>
              <p class="text-dark-200 font-medium">{{ eaInfo.magic_buy ?? 'N/A' }}</p>
            </div>
            <div>
              <p class="text-xs text-dark-500 mb-1">Magic Sell</p>
              <p class="text-dark-200 font-medium">{{ eaInfo.magic_sell ?? 'N/A' }}</p>
            </div>
            <div>
              <p class="text-xs text-dark-500 mb-1">Trading Mode</p>
              <p class="text-dark-200 font-medium">{{ getTradingModeLabel(eaInfo.trading_mode ?? 0) }}</p>
            </div>
            <div>
              <p class="text-xs text-dark-500 mb-1">Market Regime</p>
              <p :class="['font-medium', getMarketRegimeClass(eaInfo.market_regime?.regime ?? 'UNKNOWN')]">
                {{ eaInfo.market_regime?.regime ?? 'UNKNOWN' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-dark-500 mb-1">ADX Current</p>
              <p class="text-dark-200 font-medium">{{ eaInfo.adx_current ?? 'N/A' }}</p>
            </div>
            <div>
              <p class="text-xs text-dark-500 mb-1">Open Positions</p>
              <p class="text-dark-200 font-medium">{{ eaInfo.open_positions?.total ?? 0 }}</p>
            </div>
            <div>
              <p class="text-xs text-dark-500 mb-1">Intercepted Buy</p>
              <p class="text-dark-200 font-medium">{{ eaInfo.open_positions?.intercepted_buy ?? 0 }}</p>
            </div>
            <div>
              <p class="text-xs text-dark-500 mb-1">Intercepted Sell</p>
              <p class="text-dark-200 font-medium">{{ eaInfo.open_positions?.intercepted_sell ?? 0 }}</p>
            </div>
            <div v-if="eaInfo.last_sync_at" class="col-span-full">
              <p class="text-xs text-dark-500">Last Sync: {{ new Date(eaInfo.last_sync_at).toLocaleString() }}</p>
            </div>
          </div>
        </div>

        <!-- Trading Mode Selection -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-dark-300 mb-2">
            Trading Mode
          </label>
          <div class="flex items-center gap-4">
            <select
              v-model="tradingMode"
              class="input flex-1"
              @change="sendTradingModeCommand"
            >
              <option :value="0">Auto (based on EMA200+ADX)</option>
              <option :value="1">Buy Only</option>
              <option :value="2">Sell Only</option>
            </select>
          </div>
          <p class="text-xs text-dark-500 mt-1">
            Auto: Blocks SELL when BULLISH, blocks BUY when BEARISH. Buy Only: Only allows BUY orders. Sell Only: Only allows SELL orders.
          </p>
        </div>

        <!-- Intercept All Trading Checkbox -->
        <div class="mb-4">
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              v-model="interceptAllTrading"
              type="checkbox"
              class="w-4 h-4 rounded border-dark-600 bg-dark-800 text-primary-500 focus:ring-primary-500"
            />
            <span class="text-sm font-medium text-dark-300">
              Intercept All Trading
            </span>
          </label>
          <p class="text-xs text-dark-500 mt-1 ml-6">
            If checked, will intercept ALL trading on this account regardless of magic numbers. Magic Buy/Sell inputs will be ignored.
          </p>
        </div>

        <!-- Magic Buy and Sell Input -->
        <div class="mb-4" v-if="!interceptAllTrading">
          <label class="block text-sm font-medium text-dark-300 mb-2">
            Target Magic Numbers (Optional)
          </label>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-dark-400 mb-1">Magic BUY</label>
              <input
                v-model="targetMagicBuy"
                type="number"
                min="0"
                placeholder="Enter magic BUY (0 = all)"
                class="input w-full"
              />
            </div>
            <div>
              <label class="block text-xs text-dark-400 mb-1">Magic SELL</label>
              <input
                v-model="targetMagicSell"
                type="number"
                min="0"
                placeholder="Enter magic SELL (0 = all)"
                class="input w-full"
              />
            </div>
          </div>
          <p class="text-xs text-dark-500 mt-2">
            Leave empty or 0 to control all EAs. Enter specific magic numbers for BUY and SELL orders (e.g., Robot Grid uses separate magic for buy and sell).
          </p>
        </div>
        
        <div class="flex flex-wrap gap-3">
          <button
            class="btn btn-danger flex items-center gap-2"
            :disabled="sendingCommand || !filters.account_id"
            @click="sendCommand('close_all')"
          >
            <StopIcon class="w-4 h-4" />
            Close All Trades
          </button>
          <button
            class="btn btn-warning flex items-center gap-2"
            :disabled="sendingCommand || !filters.account_id"
            @click="sendCommand('pause')"
          >
            <PauseIcon class="w-4 h-4" />
            Pause Trading
          </button>
          <button
            class="btn btn-success flex items-center gap-2"
            :disabled="sendingCommand || !filters.account_id"
            @click="sendCommand('resume')"
          >
            <PlayIcon class="w-4 h-4" />
            Resume Trading
          </button>
          <button
            class="btn btn-secondary flex items-center gap-2"
            :disabled="sendingCommand || !filters.account_id"
            @click="openScheduleModal"
          >
            <ClockIcon class="w-4 h-4" />
            Set Schedule
          </button>
        </div>
        <p v-if="interceptAllTrading" class="text-xs text-primary-400 mt-2">
          ℹ️ Intercepting ALL trading on this account (Magic Buy/Sell ignored)
        </p>
        <p v-else-if="(targetMagicBuy && parseInt(targetMagicBuy) > 0) || (targetMagicSell && parseInt(targetMagicSell) > 0)" class="text-xs text-primary-400 mt-2">
          ℹ️ Controlling EA with Magic BUY: {{ targetMagicBuy || '0 (all)' }}, Magic SELL: {{ targetMagicSell || '0 (all)' }}
        </p>
      </div>
    </div>

    <!-- Filters -->
    <div class="card p-4">
      <div class="flex items-center justify-between mb-4">
        <button
          class="flex items-center gap-2 text-sm text-dark-400 hover:text-dark-300"
          @click="showFilters = !showFilters"
        >
          <FunnelIcon class="w-4 h-4" />
          {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
        </button>
        <span class="text-sm text-dark-500">
          {{ pagination.total }} trades found
        </span>
      </div>

      <div v-if="showFilters" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 pt-4 border-t border-dark-800">
        <div>
          <label class="block text-xs text-dark-500 mb-1">Account</label>
          <select v-model="filters.account_id" class="input text-sm" @change="handleFilterChange">
            <option value="">All accounts</option>
            <option v-for="account in accountsStore.accounts" :key="account.id" :value="account.id">
              {{ account.name || account.broker_name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-dark-500 mb-1">Pair</label>
          <input
            v-model="filters.pair"
            type="text"
            class="input text-sm"
            placeholder="e.g., EURUSD"
            @input="handleFilterChange"
          />
        </div>
        <div>
          <label class="block text-xs text-dark-500 mb-1">Type</label>
          <select v-model="filters.type" class="input text-sm" @change="handleFilterChange">
            <option value="">All types</option>
            <option value="buy">Buy</option>
            <option value="sell">Sell</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-dark-500 mb-1">Status</label>
          <select v-model="filters.status" class="input text-sm" @change="handleFilterChange">
            <option value="">All</option>
            <option value="open">Open</option>
            <option value="closed">Closed</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-dark-500 mb-1">Result</label>
          <select v-model="filters.profitable" class="input text-sm" @change="handleFilterChange">
            <option value="">All</option>
            <option value="true">Profit</option>
            <option value="false">Loss</option>
          </select>
        </div>
        <div class="flex items-end">
          <button class="btn-ghost text-sm" @click="resetFilters">
            <XMarkIcon class="w-4 h-4 mr-1" />
            Reset
          </button>
        </div>
      </div>
    </div>

    <!-- Trades table -->
    <div class="card overflow-hidden">
      <TradeTable :trades="trades || []" :loading="loading" />

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-dark-800">
        <span class="text-sm text-dark-500">
          Page {{ pagination.current_page }} of {{ pagination.last_page }}
        </span>
        <div class="flex items-center gap-2">
          <button
            class="btn-secondary text-sm"
            :disabled="pagination.current_page === 1"
            @click="handlePageChange(pagination.current_page - 1)"
          >
            Previous
          </button>
          <button
            class="btn-secondary text-sm"
            :disabled="pagination.current_page === pagination.last_page"
            @click="handlePageChange(pagination.current_page + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Upload Modal -->
    <div v-if="showUploadModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="fixed inset-0 bg-dark-950/80 backdrop-blur-sm" @click="showUploadModal = false"></div>
      
      <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative w-full max-w-md card p-6" @click.stop>
          <h2 class="text-xl font-semibold text-dark-100 mb-4">Upload Statement</h2>
          
          <form @submit.prevent="handleFileUpload" class="space-y-4">
            <div class="space-y-2">
              <label class="block text-sm font-medium text-dark-300">Account</label>
              <select v-model="uploadAccountId" class="input" required>
                <option value="">Select account</option>
                <option v-for="account in accountsStore.accounts" :key="account.id" :value="account.id">
                  {{ account.name || account.broker_name }}
                </option>
              </select>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-dark-300">Statement File</label>
              <input
                type="file"
                accept=".csv,.html,.htm"
                class="input"
                @change="uploadFile = $event.target.files[0]"
                required
              />
              <p class="text-xs text-dark-500">Supported: CSV, HTML (MT4/MT5 format)</p>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" class="btn-secondary flex-1" @click="showUploadModal = false">
                Cancel
              </button>
              <button type="submit" class="btn-primary flex-1" :disabled="uploading">
                {{ uploading ? 'Uploading...' : 'Upload' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Schedule Modal -->
    <Teleport to="body">
      <div
        v-if="showScheduleModal"
        class="fixed inset-0 z-50 overflow-y-auto"
        @click.self="showScheduleModal = false"
      >
        <div class="fixed inset-0 bg-dark-950/90 backdrop-blur-sm"></div>
        <div class="relative min-h-screen flex items-center justify-center p-4">
          <div class="relative w-full max-w-md card p-6 bg-dark-900" @click.stop>
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-xl font-bold text-dark-100">Trading Schedule</h2>
              <button
                @click="showScheduleModal = false"
                class="p-2 rounded-lg hover:bg-dark-800 text-dark-400 hover:text-dark-200 transition-colors"
              >
                <XMarkIcon class="w-6 h-6" />
              </button>
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-dark-300 mb-2">
                  Days of Week
                </label>
                <div class="grid grid-cols-7 gap-2">
                  <label
                    v-for="(day, index) in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']"
                    :key="index"
                    class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors"
                    :class="scheduleDays[index] ? 'bg-primary-500/20 border-primary-500' : 'bg-dark-800 border-dark-700'"
                  >
                    <input
                      type="checkbox"
                      v-model="scheduleDays[index]"
                      class="w-4 h-4 text-primary-500 rounded"
                    />
                    <span class="text-sm text-dark-200">{{ day }}</span>
                  </label>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-dark-300 mb-2">
                    Start Time
                  </label>
                  <input
                    v-model="scheduleStartTime"
                    type="time"
                    class="input w-full"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-dark-300 mb-2">
                    End Time
                  </label>
                  <input
                    v-model="scheduleEndTime"
                    type="time"
                    class="input w-full"
                  />
                </div>
              </div>

              <!-- Intercept All Trading Checkbox -->
              <div class="mb-4">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    v-model="interceptAllTrading"
                    type="checkbox"
                    class="w-4 h-4 rounded border-dark-600 bg-dark-800 text-primary-500 focus:ring-primary-500"
                  />
                  <span class="text-sm font-medium text-dark-300">
                    Intercept All Trading
                  </span>
                </label>
                <p class="text-xs text-dark-500 mt-1 ml-6">
                  If checked, will intercept ALL trading on this account regardless of magic numbers. Magic Buy/Sell inputs will be ignored.
                </p>
              </div>

              <div v-if="!interceptAllTrading">
                <label class="block text-sm font-medium text-dark-300 mb-2">
                  Target Magic Numbers (Optional)
                </label>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs text-dark-400 mb-1">Magic BUY</label>
                    <input
                      v-model="targetMagicBuy"
                      type="number"
                      min="0"
                      placeholder="Enter magic BUY (0 = all)"
                      class="input w-full"
                    />
                  </div>
                  <div>
                    <label class="block text-xs text-dark-400 mb-1">Magic SELL</label>
                    <input
                      v-model="targetMagicSell"
                      type="number"
                      min="0"
                      placeholder="Enter magic SELL (0 = all)"
                      class="input w-full"
                    />
                  </div>
                </div>
                <p class="text-xs text-dark-500 mt-1">
                  Enter the magic numbers for BUY and SELL orders of the EA to control (e.g., Robot Grid uses separate magic for buy and sell). Leave empty or 0 to control all EAs.
                </p>
              </div>

              <div class="p-3 bg-primary-500/10 border border-primary-500/30 rounded-lg">
                <p class="text-xs text-primary-400">
                  💡 Trading will only be allowed during the selected days and time range. 
                  Outside this schedule, trading will be paused automatically.
                </p>
              </div>

              <div class="flex items-center gap-3 pt-4">
                <button
                  @click="sendScheduleCommand"
                  :disabled="sendingCommand"
                  class="btn btn-primary flex-1"
                >
                  {{ sendingCommand ? 'Saving...' : 'Save Schedule' }}
                </button>
                <button
                  @click="showScheduleModal = false"
                  class="btn btn-secondary"
                >
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

