<script setup>
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
)

const props = defineProps({
  data: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const chartData = computed(() => ({
  labels: props.data.map(d => d.label),
  datasets: [
    {
      label: 'Monthly P/L',
      data: props.data.map(d => d.profit),
      backgroundColor: props.data.map(d => 
        d.profit >= 0 ? 'rgba(34, 197, 94, 0.8)' : 'rgba(239, 68, 68, 0.8)'
      ),
      borderColor: props.data.map(d => 
        d.profit >= 0 ? '#22c55e' : '#ef4444'
      ),
      borderWidth: 1,
      borderRadius: 4,
      borderSkipped: false,
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
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
      callbacks: {
        label: (context) => {
          const value = context.parsed.y
          const prefix = value >= 0 ? '+' : ''
          return `P/L: ${prefix}$${value.toLocaleString('en-US', { minimumFractionDigits: 2 })}`
        },
        afterLabel: (context) => {
          const item = props.data[context.dataIndex]
          return item?.trades ? `Trades: ${item.trades}` : ''
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
        autoSkip: false,
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
          const prefix = value >= 0 ? '+' : ''
          return prefix + '$' + value.toLocaleString()
        },
      },
    },
  },
}
</script>

<template>
  <div class="card p-4">
    <h3 class="text-sm font-medium text-dark-300 mb-4">Monthly P/L</h3>
    
    <div v-if="loading" class="h-64 flex items-center justify-center">
      <div class="spinner" />
    </div>
    
    <div v-else-if="data.length === 0" class="h-64 flex items-center justify-center text-dark-500">
      No data available
    </div>
    
    <div v-else class="h-64">
      <Bar :data="chartData" :options="chartOptions" />
    </div>
  </div>
</template>

