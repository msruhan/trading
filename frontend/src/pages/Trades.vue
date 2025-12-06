<script setup>
import { ref, onMounted, watch, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { tradesAPI } from '@/services/api'
import { useAccountsStore } from '@/stores/accounts'
import TradeTable from '@/components/common/TradeTable.vue'
import {
  FunnelIcon,
  PlusIcon,
  DocumentArrowUpIcon,
  MagnifyingGlassIcon,
  XMarkIcon,
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

onMounted(async () => {
  await accountsStore.fetchAccounts()
  await fetchTrades()
})

const fetchTrades = async (page = 1) => {
  loading.value = true
  try {
    const params = { page, per_page: 20 }
    
    // Add filters
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '') {
        params[key] = value
      }
    })

    const response = await tradesAPI.getAll(params)
    trades.value = response.data.data
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
    }
  } catch (error) {
    console.error('Failed to fetch trades:', error)
  } finally {
    loading.value = false
  }
}

const handleFilterChange = () => {
  fetchTrades(1)
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
      <TradeTable :trades="trades" :loading="loading" />

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
      <div class="fixed inset-0 bg-dark-950/80 backdrop-blur-sm" @click="showUploadModal = false" />
      
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
  </div>
</template>

