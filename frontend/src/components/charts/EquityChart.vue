<script setup>
import { computed, ref, watch } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
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
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
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

const chartData = computed(() => {
  // Ensure data is an array and has required fields
  const validData = Array.isArray(props.data) 
    ? props.data.filter(d => d && d.date && (d.equity !== undefined || d.balance !== undefined))
    : []
  
  return {
    labels: validData.map(d => d.date),
  datasets: [
    {
      label: 'Equity',
        data: validData.map(d => Number(d.equity) || 0),
      borderColor: '#22c55e',
      backgroundColor: 'rgba(34, 197, 94, 0.1)',
      fill: true,
      tension: 0.4,
      pointRadius: 0,
      pointHoverRadius: 6,
      pointHoverBackgroundColor: '#22c55e',
      pointHoverBorderColor: '#fff',
      pointHoverBorderWidth: 2,
    },
    {
      label: 'Balance',
        data: validData.map(d => Number(d.balance) || 0),
      borderColor: '#a855f7',
      backgroundColor: 'transparent',
      fill: false,
      tension: 0.4,
      pointRadius: 0,
      pointHoverRadius: 6,
      pointHoverBackgroundColor: '#a855f7',
      pointHoverBorderColor: '#fff',
      pointHoverBorderWidth: 2,
      borderDash: [5, 5],
    },
  ],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    mode: 'index',
    intersect: false,
  },
  plugins: {
    legend: {
      display: true,
      position: 'top',
      align: 'end',
      labels: {
        color: '#94a3b8',
        usePointStyle: true,
        pointStyle: 'circle',
        padding: 20,
        font: {
          size: 12,
        },
      },
    },
    tooltip: {
      backgroundColor: '#1e293b',
      titleColor: '#f1f5f9',
      bodyColor: '#cbd5e1',
      borderColor: '#334155',
      borderWidth: 1,
      padding: 12,
      displayColors: true,
      callbacks: {
        label: (context) => {
          const value = context.parsed.y
          return `${context.dataset.label}: $${value.toLocaleString('en-US', { minimumFractionDigits: 2 })}`
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
        maxRotation: 0,
        autoSkip: true,
        maxTicksLimit: 7,
      },
    },
    y: {
      display: true,
      grid: {
        color: 'rgba(51, 65, 85, 0.5)',
      },
      ticks: {
        color: '#64748b',
        callback: (value) => '$' + value.toLocaleString(),
      },
    },
  },
}
</script>

<template>
  <div class="card p-4">
    <h3 class="text-sm font-medium text-dark-300 mb-4">Equity Curve</h3>
    
    <div v-if="loading" class="h-64 flex items-center justify-center">
      <div class="spinner" />
    </div>
    
    <div v-else-if="data.length === 0" class="h-64 flex items-center justify-center text-dark-500">
      No data available
    </div>
    
    <div v-else class="h-64">
      <Line :data="chartData" :options="chartOptions" />
    </div>
  </div>
</template>

