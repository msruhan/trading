<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { tradesAPI } from '@/services/api'
import { useAccountsStore } from '@/stores/accounts'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const accountsStore = useAccountsStore()

const loading = ref(false)
const error = ref('')

const form = reactive({
  account_id: '',
  pair: '',
  type: 'buy',
  open_time: new Date().toISOString().slice(0, 16),
  close_time: new Date().toISOString().slice(0, 16),
  open_price: '',
  close_price: '',
  lots: 0.01,
  profit: '',
  swap: 0,
  commission: 0,
  stop_loss: '',
  take_profit: '',
  comment: '',
  notes: '',
})

const popularPairs = [
  'EURUSD', 'GBPUSD', 'USDJPY', 'USDCHF', 'AUDUSD',
  'NZDUSD', 'USDCAD', 'EURJPY', 'GBPJPY', 'XAUUSD',
]

onMounted(async () => {
  await accountsStore.fetchAccounts()
  if (accountsStore.accounts.length > 0) {
    form.account_id = accountsStore.accounts[0].id
  }
})

const handleSubmit = async () => {
  if (!form.account_id || !form.pair || !form.open_price) {
    error.value = 'Please fill in all required fields'
    return
  }

  loading.value = true
  error.value = ''

  try {
    await tradesAPI.createManual({
      ...form,
      pair: form.pair.toUpperCase(),
    })
    router.push('/trades')
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to add trade'
  } finally {
    loading.value = false
  }
}

const selectPair = (pair) => {
  form.pair = pair
}
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <button
        class="p-2 rounded-lg hover:bg-dark-800 text-dark-400"
        @click="router.back()"
      >
        <ArrowLeftIcon class="w-5 h-5" />
      </button>
      <div>
        <h1 class="text-2xl font-bold text-dark-100">Add Manual Trade</h1>
        <p class="text-dark-400">Record a trade manually</p>
      </div>
    </div>

    <!-- Form -->
    <form @submit.prevent="handleSubmit" class="card p-6 space-y-6">
      <!-- Error -->
      <div v-if="error" class="p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
        {{ error }}
      </div>

      <!-- Account -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-dark-300">Account *</label>
        <select v-model="form.account_id" class="input" required>
          <option value="">Select account</option>
          <option v-for="account in accountsStore.accounts" :key="account.id" :value="account.id">
            {{ account.name || account.broker_name }} ({{ account.login_masked }})
          </option>
        </select>
      </div>

      <!-- Pair -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-dark-300">Pair *</label>
        <input
          v-model="form.pair"
          type="text"
          class="input uppercase"
          placeholder="e.g., EURUSD"
          required
        />
        <div class="flex flex-wrap gap-2 mt-2">
          <button
            v-for="pair in popularPairs"
            :key="pair"
            type="button"
            :class="[
              'px-2 py-1 text-xs rounded-md transition-colors',
              form.pair === pair
                ? 'bg-primary-500/20 text-primary-400'
                : 'bg-dark-800 text-dark-400 hover:text-dark-300'
            ]"
            @click="selectPair(pair)"
          >
            {{ pair }}
          </button>
        </div>
      </div>

      <!-- Type and Lots -->
      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-dark-300">Type *</label>
          <select v-model="form.type" class="input">
            <option value="buy">Buy</option>
            <option value="sell">Sell</option>
            <option value="buy_limit">Buy Limit</option>
            <option value="sell_limit">Sell Limit</option>
            <option value="buy_stop">Buy Stop</option>
            <option value="sell_stop">Sell Stop</option>
          </select>
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-dark-300">Lots *</label>
          <input v-model.number="form.lots" type="number" step="0.01" min="0.01" class="input" required />
        </div>
      </div>

      <!-- Times -->
      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-dark-300">Open Time *</label>
          <input v-model="form.open_time" type="datetime-local" class="input" required />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-dark-300">Close Time</label>
          <input v-model="form.close_time" type="datetime-local" class="input" />
        </div>
      </div>

      <!-- Prices -->
      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-dark-300">Open Price *</label>
          <input v-model.number="form.open_price" type="number" step="0.00001" class="input" required />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-dark-300">Close Price</label>
          <input v-model.number="form.close_price" type="number" step="0.00001" class="input" />
        </div>
      </div>

      <!-- SL/TP -->
      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-dark-300">Stop Loss</label>
          <input v-model.number="form.stop_loss" type="number" step="0.00001" class="input" />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-dark-300">Take Profit</label>
          <input v-model.number="form.take_profit" type="number" step="0.00001" class="input" />
        </div>
      </div>

      <!-- Profit/Swap/Commission -->
      <div class="grid grid-cols-3 gap-4">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-dark-300">Profit</label>
          <input v-model.number="form.profit" type="number" step="0.01" class="input" />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-dark-300">Swap</label>
          <input v-model.number="form.swap" type="number" step="0.01" class="input" />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-dark-300">Commission</label>
          <input v-model.number="form.commission" type="number" step="0.01" class="input" />
        </div>
      </div>

      <!-- Comment -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-dark-300">Comment</label>
        <input v-model="form.comment" type="text" class="input" placeholder="Short comment" />
      </div>

      <!-- Notes -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-dark-300">Notes</label>
        <textarea v-model="form.notes" rows="3" class="input" placeholder="Trade analysis, lessons learned..." />
      </div>

      <!-- Actions -->
      <div class="flex gap-3 pt-4">
        <button type="button" class="btn-secondary flex-1" @click="router.back()">
          Cancel
        </button>
        <button type="submit" class="btn-primary flex-1" :disabled="loading">
          {{ loading ? 'Adding...' : 'Add Trade' }}
        </button>
      </div>
    </form>
  </div>
</template>

