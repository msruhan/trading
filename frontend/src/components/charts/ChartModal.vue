<script setup>
import { ref, computed, watch } from 'vue'
import { XMarkIcon, CalendarIcon } from '@heroicons/vue/24/outline'
import { Bar, Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  type: {
    type: String,
    required: true, // 'profit', 'balance', 'growth'
  },
  accountId: {
    type: [Number, String],
    required: true,
  },
  initialBalance: {
    type: Number,
    default: 0,
  },
})

const emit = defineEmits(['close'])

const loading = ref(false)
const chartData = ref([])
const now = new Date()
const selectedYear = ref(now.getFullYear())
const selectedMonth = ref(now.getMonth() + 1) // 1-12

// Available years (current year and previous years that might have data)
const availableYears = computed(() => {
  const years = []
  const currentYear = now.getFullYear()
  // Show current year and 5 years before (to cover more historical data)
  for (let year = currentYear; year >= currentYear - 5; year--) {
    years.push(year)
  }
  return years
})

// Available months (1-12)
const availableMonths = computed(() => {
  return [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
  ]
})

// Computed for selected month label
const selectedMonthLabel = computed(() => {
  const month = availableMonths.value.find(m => m.value === selectedMonth.value)
  return month ? month.label : ''
})

// Fetch data when modal opens or year/month changes
watch([() => props.show, selectedYear, selectedMonth], ([isOpen, year, month]) => {
  if (isOpen && year && month) {
    fetchMonthlyData(year, month)
  }
}, { immediate: true })

const fetchMonthlyData = async (year, month) => {
  loading.value = true
  try {
    const token = localStorage.getItem('auth_token')
    const response = await fetch(`/api/v1/accounts/${props.accountId}/equity-curve?year=${year}&month=${month}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    })
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    chartData.value = data.data || []
  } catch (error) {
    console.error('Failed to fetch monthly data:', error)
    chartData.value = []
  } finally {
    loading.value = false
  }
}

// Chart data based on type
const computedChartData = computed(() => {
  const validData = Array.isArray(chartData.value) 
    ? chartData.value.filter(d => d && d.date)
    : []
  
  if (validData.length === 0) return { labels: [], datasets: [] }
  
  switch (props.type) {
    case 'profit':
      const profits = validData.map((d, index) => {
        if (index === 0) {
          return Number(d.equity) || Number(d.balance) || 0
        }
        const prevDay = validData[index - 1]
        const currentBalance = Number(d.balance) || Number(d.equity) || 0
        const prevBalance = Number(prevDay.balance) || Number(prevDay.equity) || 0
        return currentBalance - prevBalance
      })
      
      return {
        labels: validData.map(d => {
          const date = new Date(d.date)
          return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
        }),
        datasets: [{
          label: 'Daily Profit',
          data: profits,
          backgroundColor: profits.map(p => p >= 0 ? 'rgba(34, 197, 94, 0.8)' : 'rgba(239, 68, 68, 0.8)'),
          borderColor: profits.map(p => p >= 0 ? '#22c55e' : '#ef4444'),
          borderWidth: 1,
          borderRadius: 6,
          barThickness: 'flex',
        }],
      }
      
    case 'balance':
      return {
        labels: validData.map(d => {
          const date = new Date(d.date)
          return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
        }),
        datasets: [{
          label: 'Balance',
          data: validData.map(d => Number(d.balance) || Number(d.equity) || 0),
          borderColor: '#8b5cf6',
          backgroundColor: 'rgba(139, 92, 246, 0.1)',
          fill: true,
          tension: 0.4,
          pointRadius: 3,
          pointBackgroundColor: '#8b5cf6',
          pointBorderColor: '#1e1b4b',
          pointBorderWidth: 2,
          borderWidth: 2,
        }],
      }
      
    case 'growth':
      const baseBalance = props.initialBalance > 0 
        ? props.initialBalance 
        : (Number(validData[0]?.balance) || Number(validData[0]?.equity) || 1)
      
      const growthData = validData.map(d => {
        const currentBalance = Number(d.balance) || Number(d.equity) || 0
        return ((currentBalance - baseBalance) / baseBalance) * 100
      })
      
      return {
        labels: validData.map(d => {
          const date = new Date(d.date)
          return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
        }),
        datasets: [{
          label: 'Growth %',
          data: growthData,
          borderColor: '#06b6d4',
          backgroundColor: 'rgba(6, 182, 212, 0.1)',
          fill: true,
          tension: 0.4,
          pointRadius: 3,
          pointBackgroundColor: '#06b6d4',
          pointBorderColor: '#083344',
          pointBorderWidth: 2,
          borderWidth: 2,
        }],
      }
      
    default:
      return { labels: [], datasets: [] }
  }
})

const chartOptions = computed(() => {
  const isProfit = props.type === 'profit'
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      mode: 'index',
      intersect: false,
    },
    plugins: {
      legend: {
        display: false,
      },
      tooltip: {
        backgroundColor: '#1e293b',
        titleColor: '#f1f5f9',
        bodyColor: '#cbd5e1',
        borderColor: '#334155',
        borderWidth: 1,
        padding: 12,
        displayColors: false,
        callbacks: {
          label: (context) => {
            const value = context.parsed.y
            if (props.type === 'profit') {
              const sign = value >= 0 ? '+' : ''
              return `${sign}$${value.toLocaleString('en-US', { minimumFractionDigits: 2 })}`
            } else if (props.type === 'balance') {
              return `$${value.toLocaleString('en-US', { minimumFractionDigits: 2 })}`
            } else {
              const sign = value >= 0 ? '+' : ''
              return `${sign}${value.toFixed(2)}%`
            }
          },
        },
      },
    },
    scales: {
      x: {
        display: true,
        grid: {
          display: false,
        },
        ticks: {
          color: '#64748b',
          maxRotation: 45,
          minRotation: 45,
          font: {
            size: 11,
          },
        },
      },
      y: {
        display: true,
        grid: {
          color: 'rgba(51, 65, 85, 0.5)',
        },
        ticks: {
          color: '#64748b',
          callback: (value) => {
            if (props.type === 'profit' || props.type === 'balance') {
              return '$' + value.toLocaleString()
            } else {
              const sign = value >= 0 ? '+' : ''
              return sign + value.toFixed(1) + '%'
            }
          },
          font: {
            size: 11,
          },
        },
      },
    },
  }
})

const chartTitle = computed(() => {
  switch (props.type) {
    case 'profit': return 'Daily Profit'
    case 'balance': return 'Balance'
    case 'growth': return 'Growth'
    default: return 'Chart'
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click.self="emit('close')">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-dark-950/90 backdrop-blur-sm"></div>
        
        <!-- Modal -->
        <div class="relative min-h-screen flex items-center justify-center p-4">
          <div class="relative w-full max-w-7xl card p-6 bg-dark-900" @click.stop>
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
              <div class="flex items-center gap-4">
                <h2 class="text-xl font-bold text-dark-100">{{ chartTitle }} - Monthly View</h2>
                <div class="flex items-center gap-3">
                  <CalendarIcon class="w-5 h-5 text-dark-400" />
                  <div class="flex items-center gap-2">
                    <select
                      v-model.number="selectedYear"
                      class="input text-sm bg-dark-800 border-dark-700 min-w-[100px]"
                    >
                      <option v-for="year in availableYears" :key="year" :value="year">
                        {{ year }}
                      </option>
                    </select>
                    <select
                      v-model.number="selectedMonth"
                      class="input text-sm bg-dark-800 border-dark-700 min-w-[140px]"
                    >
                      <option v-for="month in availableMonths" :key="month.value" :value="month.value">
                        {{ month.label }}
                      </option>
                    </select>
                  </div>
                </div>
              </div>
              <button
                @click="emit('close')"
                class="p-2 rounded-lg hover:bg-dark-800 text-dark-400 hover:text-dark-200 transition-colors"
              >
                <XMarkIcon class="w-6 h-6" />
              </button>
            </div>
            
            <!-- Chart -->
            <div v-if="loading" class="h-[600px] flex items-center justify-center">
              <div class="spinner" />
            </div>
            
            <div v-else-if="chartData.length === 0" class="h-[600px] flex items-center justify-center text-dark-500">
              <div class="text-center">
                <p class="text-lg mb-2">No data available</p>
                <p class="text-sm text-dark-500">No trading data for selected month</p>
              </div>
            </div>
            
            <div v-else class="h-[600px]">
              <Bar v-if="type === 'profit'" :data="computedChartData" :options="chartOptions" />
              <Line v-else :data="computedChartData" :options="chartOptions" />
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .card,
.modal-leave-active .card {
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.modal-enter-from .card,
.modal-leave-to .card {
  transform: scale(0.9);
  opacity: 0;
}
</style>

