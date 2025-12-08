<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import { chartAnalysisAPI } from '@/services/api'
import dayjs from 'dayjs'
import {
  ChartBarSquareIcon,
  ArrowsPointingOutIcon,
  ArrowsPointingInIcon,
  MagnifyingGlassPlusIcon,
  MagnifyingGlassMinusIcon,
  Cog6ToothIcon,
  BookmarkIcon,
  DocumentTextIcon,
  XMarkIcon,
  TrashIcon,
  ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline'

// Symbol presets
const symbolCategories = {
  forex: [
    { symbol: 'FX:EURUSD', name: 'EUR/USD' },
    { symbol: 'FX:GBPUSD', name: 'GBP/USD' },
    { symbol: 'FX:USDJPY', name: 'USD/JPY' },
    { symbol: 'FX:USDCHF', name: 'USD/CHF' },
    { symbol: 'FX:AUDUSD', name: 'AUD/USD' },
    { symbol: 'FX:USDCAD', name: 'USD/CAD' },
    { symbol: 'FX:NZDUSD', name: 'NZD/USD' },
    { symbol: 'FX:EURGBP', name: 'EUR/GBP' },
    { symbol: 'FX:EURJPY', name: 'EUR/JPY' },
    { symbol: 'FX:GBPJPY', name: 'GBP/JPY' },
  ],
  crypto: [
    { symbol: 'BINANCE:BTCUSDT', name: 'BTC/USDT' },
    { symbol: 'BINANCE:ETHUSDT', name: 'ETH/USDT' },
    { symbol: 'BINANCE:BNBUSDT', name: 'BNB/USDT' },
    { symbol: 'BINANCE:XRPUSDT', name: 'XRP/USDT' },
    { symbol: 'BINANCE:SOLUSDT', name: 'SOL/USDT' },
    { symbol: 'BINANCE:ADAUSDT', name: 'ADA/USDT' },
    { symbol: 'BINANCE:DOGEUSDT', name: 'DOGE/USDT' },
    { symbol: 'BINANCE:DOTUSDT', name: 'DOT/USDT' },
    { symbol: 'BINANCE:MATICUSDT', name: 'MATIC/USDT' },
    { symbol: 'BINANCE:LINKUSDT', name: 'LINK/USDT' },
  ],
  commodities: [
    { symbol: 'TVC:GOLD', name: 'Gold' },
    { symbol: 'TVC:SILVER', name: 'Silver' },
    { symbol: 'NYMEX:CL1!', name: 'Crude Oil' },
    { symbol: 'NYMEX:NG1!', name: 'Natural Gas' },
    { symbol: 'COMEX:HG1!', name: 'Copper' },
  ],
  indices: [
    { symbol: 'FOREXCOM:SPXUSD', name: 'S&P 500' },
    { symbol: 'FOREXCOM:NSXUSD', name: 'NASDAQ' },
    { symbol: 'FOREXCOM:DJI', name: 'Dow Jones' },
    { symbol: 'TVC:UKX', name: 'FTSE 100' },
    { symbol: 'TVC:DEU40', name: 'DAX' },
    { symbol: 'TVC:NI225', name: 'Nikkei 225' },
  ],
}

const selectedCategory = ref('forex')
const selectedSymbol = ref('FX:EURUSD')
const selectedInterval = ref('D')
const isFullscreen = ref(false)
const chartContainerRef = ref(null)
const widgetRef = ref(null)

const intervals = [
  { value: '1', label: '1m' },
  { value: '5', label: '5m' },
  { value: '15', label: '15m' },
  { value: '30', label: '30m' },
  { value: '60', label: '1H' },
  { value: '240', label: '4H' },
  { value: 'D', label: '1D' },
  { value: 'W', label: '1W' },
  { value: 'M', label: '1M' },
]

const themes = [
  { value: 'dark', label: 'Dark' },
  { value: 'light', label: 'Light' },
]
const selectedTheme = ref('dark')

// Chart Analysis state
const showSaveModal = ref(false)
const showAnalysisViewModal = ref(false)
const showAnalysesPanel = ref(false)
const savedAnalyses = ref([])
const currentAnalysis = ref(null)
const analysisTitle = ref('')
const analysisNotes = ref('')
const loadingAnalyses = ref(false)
const savingAnalysis = ref(false)

// Get current symbol name for display
const currentSymbolName = computed(() => {
  const allSymbols = Object.values(symbolCategories).flat()
  const found = allSymbols.find(s => s.symbol === selectedSymbol.value)
  return found ? found.name : selectedSymbol.value
})

// Load saved analyses for current symbol/interval
const loadAnalyses = async () => {
  loadingAnalyses.value = true
  try {
    const response = await chartAnalysisAPI.getBySymbol(selectedSymbol.value, selectedInterval.value)
    savedAnalyses.value = response.data.analyses || []
  } catch (error) {
    console.error('Failed to load analyses:', error)
    savedAnalyses.value = []
  } finally {
    loadingAnalyses.value = false
  }
}

// Save current analysis
const saveAnalysis = async () => {
  if (!analysisTitle.value.trim()) {
    alert('Please enter a title for this analysis')
    return
  }

  savingAnalysis.value = true
  try {
    const data = {
      symbol: selectedSymbol.value,
      interval: selectedInterval.value,
      title: analysisTitle.value.trim(),
      notes: analysisNotes.value.trim(),
      drawings: null, // TradingView drawings are in iframe, can't access
      tradingview_state: null, // Can't access TradingView state from iframe
    }

    if (currentAnalysis.value) {
      // Update existing
      await chartAnalysisAPI.update(currentAnalysis.value.id, data)
    } else {
      // Create new
      await chartAnalysisAPI.create(data)
    }

    showSaveModal.value = false
    analysisTitle.value = ''
    analysisNotes.value = ''
    currentAnalysis.value = null
    await loadAnalyses()
  } catch (error) {
    console.error('Failed to save analysis:', error)
    alert('Failed to save analysis. Please try again.')
  } finally {
    savingAnalysis.value = false
  }
}

// Load an analysis for evaluation
const loadAnalysis = async (analysis) => {
  try {
    console.log('Loading analysis:', analysis)
    
    // Close panel first
    showAnalysesPanel.value = false
    
    // Set current analysis FIRST (before changing symbol/interval)
    currentAnalysis.value = analysis
    analysisTitle.value = analysis.title || ''
    analysisNotes.value = analysis.notes || ''
    
    // Check if symbol/interval needs to change
    const needsReload = selectedSymbol.value !== analysis.symbol || selectedInterval.value !== analysis.interval
    
    console.log('Current symbol:', selectedSymbol.value, 'Analysis symbol:', analysis.symbol)
    console.log('Current interval:', selectedInterval.value, 'Analysis interval:', analysis.interval)
    console.log('Needs reload:', needsReload)
    
    // Update symbol and interval
    selectedSymbol.value = analysis.symbol
    selectedInterval.value = analysis.interval
    
    // Always reload widget to ensure it shows the correct symbol/interval
    // Wait a bit for symbol/interval reactive updates to complete
    await new Promise(resolve => setTimeout(resolve, 200))
    
    console.log('About to reload widget with:', selectedSymbol.value, selectedInterval.value)
    
    // Force reload widget with new symbol/interval
    // This ensures chart displays the analysis data even if modal is closed
    loadTradingViewWidget()
    
    // Wait for widget to initialize before showing modal
    // TradingView widget needs time to load the new symbol/interval
    await new Promise(resolve => setTimeout(resolve, 1000))
    
    console.log('Widget reloaded. Chart should now show:', selectedSymbol.value, selectedInterval.value)
    
    // Show view modal to display the analysis notes
    showAnalysisViewModal.value = true
    console.log('Analysis view modal shown')
  } catch (error) {
    console.error('Failed to load analysis:', error)
    alert('Failed to load analysis: ' + (error.message || 'Please try again.'))
  }
}

// Delete an analysis
const deleteAnalysis = async (id) => {
  if (!confirm('Are you sure you want to delete this analysis?')) {
    return
  }

  try {
    await chartAnalysisAPI.delete(id)
    await loadAnalyses()
    if (currentAnalysis.value?.id === id) {
      currentAnalysis.value = null
      analysisTitle.value = ''
      analysisNotes.value = ''
    }
  } catch (error) {
    console.error('Failed to delete analysis:', error)
    alert('Failed to delete analysis. Please try again.')
  }
}

// Open save modal
const openSaveModal = () => {
  currentAnalysis.value = null
  analysisTitle.value = ''
  analysisNotes.value = ''
  showSaveModal.value = true
}

// Watch for symbol/interval changes to load analyses
watch([selectedSymbol, selectedInterval], () => {
  if (showAnalysesPanel.value) {
    loadAnalyses()
  }
})

const loadTradingViewWidget = () => {
  // Remove existing widget
  if (chartContainerRef.value) {
    chartContainerRef.value.innerHTML = ''
  }
  
  // Reset widget ref
  widgetRef.value = null
  
  // Create widget container
  const container = document.createElement('div')
  container.id = 'tradingview_chart'
  container.style.width = '100%'
  container.style.height = '100%'
  chartContainerRef.value.appendChild(container)
  
  // Load TradingView Advanced Chart Widget
  const script = document.createElement('script')
  script.src = 'https://s3.tradingview.com/tv.js'
  script.async = true
  script.onload = () => {
    if (window.TradingView) {
      try {
        // Capture current values to ensure consistency
        const symbolToLoad = selectedSymbol.value
        const intervalToLoad = selectedInterval.value
        
        console.log('Creating TradingView widget with:', symbolToLoad, intervalToLoad)
        
        widgetRef.value = new window.TradingView.widget({
          container_id: 'tradingview_chart',
          autosize: true,
          symbol: symbolToLoad,
          interval: intervalToLoad,
        timezone: 'Etc/UTC',
        theme: selectedTheme.value,
        style: '1', // Candlestick
        locale: 'en',
        toolbar_bg: selectedTheme.value === 'dark' ? '#1e222d' : '#f1f3f6',
        enable_publishing: false,
        allow_symbol_change: true,
        save_image: true,
        hide_side_toolbar: false,
        studies: [
          // Default indicators
          'Volume@tv-basicstudies',
        ],
        // Enable all drawing tools
        drawings_access: {
          type: 'all',
          tools: [
            { name: 'Regression Trend' },
            { name: 'Trend Line', grayed: false },
            { name: 'Fib Retracement', grayed: false },
            { name: 'Fib Extension', grayed: false },
          ],
        },
        // Enable studies/indicators access
        studies_access: {
          type: 'all',
        },
        // Overrides for dark theme
        overrides: selectedTheme.value === 'dark' ? {
          'paneProperties.background': '#0d1117',
          'paneProperties.backgroundType': 'solid',
          'scalesProperties.backgroundColor': '#0d1117',
          'mainSeriesProperties.candleStyle.upColor': '#22c55e',
          'mainSeriesProperties.candleStyle.downColor': '#ef4444',
          'mainSeriesProperties.candleStyle.wickUpColor': '#22c55e',
          'mainSeriesProperties.candleStyle.wickDownColor': '#ef4444',
          'mainSeriesProperties.candleStyle.borderUpColor': '#22c55e',
          'mainSeriesProperties.candleStyle.borderDownColor': '#ef4444',
        } : {},
        // Loading screen
        loading_screen: {
          backgroundColor: selectedTheme.value === 'dark' ? '#0d1117' : '#ffffff',
          foregroundColor: selectedTheme.value === 'dark' ? '#6366f1' : '#3b82f6',
        },
        // Additional features
        withdateranges: true,
        hide_legend: false,
        details: true,
        hotlist: true,
        calendar: true,
        news: ['headlines'],
        show_popup_button: true,
        popup_width: '1200',
        popup_height: '800',
        })
        console.log('TradingView widget loaded:', selectedSymbol.value, selectedInterval.value)
      } catch (error) {
        console.error('Failed to create TradingView widget:', error)
      }
    }
  }
  
  // Only append script if not already loaded
  if (!document.querySelector('script[src="https://s3.tradingview.com/tv.js"]')) {
    document.head.appendChild(script)
  } else if (window.TradingView) {
    // Script already loaded, create widget directly
    // Use setTimeout to ensure DOM is ready
    setTimeout(() => {
      script.onload()
    }, 100)
  }
}

const changeSymbol = (symbol) => {
  // Only clear analysis if user manually changes symbol (not from loadAnalysis)
  if (!showAnalysisViewModal.value && !showSaveModal.value) {
    currentAnalysis.value = null
  }
  selectedSymbol.value = symbol
  loadTradingViewWidget()
}

const changeInterval = (interval) => {
  // Only clear analysis if user manually changes interval (not from loadAnalysis)
  if (!showAnalysisViewModal.value && !showSaveModal.value) {
    currentAnalysis.value = null
  }
  selectedInterval.value = interval
  loadTradingViewWidget()
}

const toggleFullscreen = () => {
  isFullscreen.value = !isFullscreen.value
}

const toggleTheme = () => {
  selectedTheme.value = selectedTheme.value === 'dark' ? 'light' : 'dark'
  loadTradingViewWidget()
}

onMounted(() => {
  loadTradingViewWidget()
})

onUnmounted(() => {
  // Cleanup
  if (chartContainerRef.value) {
    chartContainerRef.value.innerHTML = ''
  }
})

// Watch for symbol changes
watch(selectedSymbol, () => {
  loadTradingViewWidget()
})
</script>

<template>
  <div :class="['min-h-screen', isFullscreen ? 'fixed inset-0 z-50 bg-dark-950' : '']">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-4 p-4" v-if="!isFullscreen">
      <div>
        <h1 class="text-2xl font-bold text-dark-100 flex items-center gap-2">
          <ChartBarSquareIcon class="w-7 h-7 text-primary-500" />
          Trading Chart
        </h1>
        <p class="text-dark-400 mt-1">Advanced charting with TradingView - Includes drawing tools, indicators & more</p>
      </div>
    </div>

    <!-- Controls Bar -->
    <div :class="[
      'bg-dark-900 border border-dark-800 rounded-lg p-3 mb-4 flex flex-wrap items-center gap-4',
      isFullscreen ? 'mx-4 mt-4' : ''
    ]">
      <!-- Category Tabs -->
      <div class="flex items-center gap-1 bg-dark-800 rounded-lg p-1">
        <button
          v-for="(symbols, category) in symbolCategories"
          :key="category"
          @click="selectedCategory = category"
          :class="[
            'px-3 py-1.5 text-sm font-medium rounded-md transition-colors capitalize',
            selectedCategory === category
              ? 'bg-primary-500 text-white'
              : 'text-dark-400 hover:text-dark-200 hover:bg-dark-700'
          ]"
        >
          {{ category }}
        </button>
      </div>

      <!-- Symbol Dropdown -->
      <select
        v-model="selectedSymbol"
        class="input text-sm bg-dark-800 border-dark-700 min-w-[140px]"
      >
        <option v-for="item in symbolCategories[selectedCategory]" :key="item.symbol" :value="item.symbol">
          {{ item.name }}
        </option>
      </select>

      <!-- Interval Buttons -->
      <div class="flex items-center gap-1 bg-dark-800 rounded-lg p-1">
        <button
          v-for="interval in intervals"
          :key="interval.value"
          @click="changeInterval(interval.value)"
          :class="[
            'px-2 py-1 text-xs font-medium rounded transition-colors',
            selectedInterval === interval.value
              ? 'bg-primary-500 text-white'
              : 'text-dark-400 hover:text-dark-200 hover:bg-dark-700'
          ]"
        >
          {{ interval.label }}
        </button>
      </div>

      <!-- Spacer -->
      <div class="flex-1"></div>

      <!-- Save Analysis Button -->
      <button
        @click="openSaveModal"
        class="px-3 py-1.5 rounded-lg bg-primary-500 text-white hover:bg-primary-600 transition-colors flex items-center gap-2 text-sm font-medium"
      >
        <BookmarkIcon class="w-4 h-4" />
        Save Analysis
      </button>

      <!-- Load Analyses Button -->
      <button
        @click="showAnalysesPanel = !showAnalysesPanel; if (showAnalysesPanel) loadAnalyses()"
        class="px-3 py-1.5 rounded-lg bg-accent-500 text-white hover:bg-accent-600 transition-colors flex items-center gap-2 text-sm font-medium"
      >
        <DocumentTextIcon class="w-4 h-4" />
        Saved Analyses
      </button>

      <!-- Theme Toggle -->
      <button
        @click="toggleTheme"
        class="p-2 rounded-lg bg-dark-800 text-dark-400 hover:text-dark-200 hover:bg-dark-700 transition-colors"
        :title="selectedTheme === 'dark' ? 'Switch to Light Theme' : 'Switch to Dark Theme'"
      >
        <Cog6ToothIcon class="w-5 h-5" />
      </button>

      <!-- Fullscreen Toggle -->
      <button
        @click="toggleFullscreen"
        class="p-2 rounded-lg bg-dark-800 text-dark-400 hover:text-dark-200 hover:bg-dark-700 transition-colors"
        :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'"
      >
        <ArrowsPointingInIcon v-if="isFullscreen" class="w-5 h-5" />
        <ArrowsPointingOutIcon v-else class="w-5 h-5" />
      </button>
    </div>

    <!-- Current Analysis Indicator (if analysis is loaded) -->
    <div
      v-if="currentAnalysis && !showAnalysisViewModal && !showSaveModal"
      class="bg-primary-500/10 border border-primary-500/30 rounded-lg p-3 mb-4"
      :class="isFullscreen ? 'mx-4' : ''"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <BookmarkIcon class="w-5 h-5 text-primary-400" />
          <div>
            <p class="text-sm font-medium text-dark-200">
              Viewing Analysis: {{ currentAnalysis.title }}
            </p>
            <p class="text-xs text-dark-400">
              {{ currentSymbolName }} - {{ intervals.find(i => i.value === selectedInterval)?.label }} • 
              Saved {{ dayjs(currentAnalysis.created_at).format('MMM D, YYYY') }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="showAnalysisViewModal = true"
            class="px-3 py-1.5 text-xs rounded-lg bg-primary-500/20 text-primary-400 hover:bg-primary-500/30 transition-colors"
          >
            View Notes
          </button>
          <button
            @click="currentAnalysis = null; analysisTitle = ''; analysisNotes = ''"
            class="p-1.5 rounded-lg hover:bg-dark-800 text-dark-400 hover:text-dark-200 transition-colors"
            title="Clear analysis view"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Chart Container with Analyses Panel -->
    <div class="flex gap-4" :class="isFullscreen ? 'mx-4 mb-4' : ''">
      <!-- Chart -->
      <div :class="[
        'bg-dark-900 border border-dark-800 rounded-lg overflow-hidden flex-1',
      ]" :style="{ height: isFullscreen ? 'calc(100vh - 120px)' : '75vh' }">
        <div ref="chartContainerRef" class="w-full h-full"></div>
      </div>

      <!-- Saved Analyses Panel -->
      <div
        v-if="showAnalysesPanel"
        class="bg-dark-900 border border-dark-800 rounded-lg p-4 w-80 overflow-y-auto"
        :style="{ height: isFullscreen ? 'calc(100vh - 120px)' : '75vh' }"
      >
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-dark-100">Saved Analyses</h3>
          <button
            @click="showAnalysesPanel = false"
            class="p-1 rounded-lg hover:bg-dark-800 text-dark-400"
          >
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <div class="mb-3 text-sm text-dark-400">
          {{ currentSymbolName }} - {{ intervals.find(i => i.value === selectedInterval)?.label }}
        </div>

        <div v-if="loadingAnalyses" class="flex items-center justify-center py-8">
          <div class="spinner"></div>
        </div>

        <div v-else-if="savedAnalyses.length === 0" class="text-center py-8 text-dark-400 text-sm">
          No saved analyses for this symbol/timeframe
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="analysis in savedAnalyses"
            :key="analysis.id"
            class="bg-dark-800 rounded-lg p-3 border border-dark-700 hover:border-primary-500/50 transition-colors"
          >
            <div class="flex items-start justify-between mb-2">
              <h4 class="font-medium text-dark-200 text-sm">{{ analysis.title }}</h4>
              <button
                @click="deleteAnalysis(analysis.id)"
                class="p-1 rounded hover:bg-red-500/10 text-red-400 hover:text-red-300"
                title="Delete"
              >
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
            <p v-if="analysis.notes" class="text-xs text-dark-400 mb-2 line-clamp-2">
              {{ analysis.notes }}
            </p>
            <div class="text-xs text-dark-500 mb-2">
              {{ dayjs(analysis.created_at).format('MMM D, YYYY HH:mm') }}
            </div>
            <button
              @click="loadAnalysis(analysis)"
              class="w-full px-3 py-1.5 text-xs rounded-lg bg-primary-500/20 text-primary-400 hover:bg-primary-500/30 transition-colors flex items-center justify-center gap-1"
            >
              <ArrowDownTrayIcon class="w-3 h-3" />
              Load for Evaluation
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Save Analysis Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showSaveModal"
          class="fixed inset-0 z-50 overflow-y-auto"
          @click.self="showSaveModal = false"
        >
          <div class="fixed inset-0 bg-dark-950/90 backdrop-blur-sm"></div>
          <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="relative w-full max-w-2xl card p-6 bg-dark-900" @click.stop>
              <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-dark-100">
                  {{ currentAnalysis ? 'Update Analysis' : 'Save Analysis' }}
                </h2>
                <button
                  @click="showSaveModal = false"
                  class="p-2 rounded-lg hover:bg-dark-800 text-dark-400 hover:text-dark-200 transition-colors"
                >
                  <XMarkIcon class="w-6 h-6" />
                </button>
              </div>

              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-dark-300 mb-2">
                    Symbol & Timeframe
                  </label>
                  <div class="text-sm text-dark-400 bg-dark-800 rounded-lg p-3">
                    {{ currentSymbolName }} - {{ intervals.find(i => i.value === selectedInterval)?.label }}
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-dark-300 mb-2">
                    Title <span class="text-red-400">*</span>
                  </label>
                  <input
                    v-model="analysisTitle"
                    type="text"
                    placeholder="e.g., EUR/USD Support Level Analysis"
                    class="input w-full"
                    maxlength="255"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-dark-300 mb-2">
                    Notes & Analysis
                  </label>
                  <textarea
                    v-model="analysisNotes"
                    rows="6"
                    placeholder="Add your analysis notes, observations, price targets, support/resistance levels, etc..."
                    class="input w-full resize-none"
                  ></textarea>
                  <p class="text-xs text-dark-500 mt-1">
                    Describe your analysis, key levels, and trading plan. This will help you evaluate your analysis later.
                  </p>
                </div>

                <div class="flex items-center gap-3 pt-4">
                  <button
                    @click="saveAnalysis"
                    :disabled="savingAnalysis || !analysisTitle.trim()"
                    class="btn btn-primary flex-1"
                  >
                    {{ savingAnalysis ? 'Saving...' : (currentAnalysis ? 'Update' : 'Save') }}
                  </button>
                  <button
                    @click="showSaveModal = false"
                    class="btn btn-secondary"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- View Analysis Modal (for evaluation) -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showAnalysisViewModal"
          class="fixed inset-0 z-50 overflow-y-auto"
          @click.self="showAnalysisViewModal = false"
        >
          <div class="fixed inset-0 bg-dark-950/90 backdrop-blur-sm"></div>
          <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="relative w-full max-w-2xl card p-6 bg-dark-900" @click.stop>
              <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-dark-100 flex items-center gap-2">
                  <DocumentTextIcon class="w-6 h-6 text-primary-500" />
                  Analysis Evaluation
                </h2>
                <button
                  @click="showAnalysisViewModal = false"
                  class="p-2 rounded-lg hover:bg-dark-800 text-dark-400 hover:text-dark-200 transition-colors"
                >
                  <XMarkIcon class="w-6 h-6" />
                </button>
              </div>

              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-dark-300 mb-2">
                    Symbol & Timeframe
                  </label>
                  <div class="text-sm text-dark-400 bg-dark-800 rounded-lg p-3">
                    {{ currentSymbolName }} - {{ intervals.find(i => i.value === selectedInterval)?.label }}
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-dark-300 mb-2">
                    Title
                  </label>
                  <div class="text-dark-200 bg-dark-800 rounded-lg p-3">
                    {{ currentAnalysis?.title || 'Untitled Analysis' }}
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-dark-300 mb-2">
                    Saved Date
                  </label>
                  <div class="text-sm text-dark-400 bg-dark-800 rounded-lg p-3">
                    {{ currentAnalysis ? dayjs(currentAnalysis.created_at).format('MMMM D, YYYY [at] HH:mm') : '' }}
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-dark-300 mb-2">
                    Analysis Notes
                  </label>
                  <div class="text-dark-200 bg-dark-800 rounded-lg p-4 min-h-[200px] whitespace-pre-wrap">
                    {{ currentAnalysis?.notes || 'No notes available.' }}
                  </div>
                </div>

                <div class="flex items-center gap-3 pt-4">
                  <button
                    @click="showSaveModal = true; showAnalysisViewModal = false"
                    class="btn btn-primary flex-1"
                  >
                    Edit Analysis
                  </button>
                  <button
                    @click="showAnalysisViewModal = false"
                    class="btn btn-secondary"
                  >
                    Close
                  </button>
                </div>
                
                <div class="mt-4 p-3 bg-primary-500/10 border border-primary-500/30 rounded-lg">
                  <p class="text-xs text-primary-400">
                    💡 <strong>Tip:</strong> Chart telah dimuat dengan <strong>{{ currentSymbolName }}</strong> pada timeframe <strong>{{ intervals.find(i => i.value === selectedInterval)?.label }}</strong> sesuai analisis ini. 
                    Chart akan tetap menampilkan data ini meskipun modal ditutup. Gunakan drawing tools di TradingView untuk membandingkan prediksi dengan pergerakan harga saat ini.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Current Analysis Indicator (if analysis is loaded) -->
    <div
      v-if="currentAnalysis && !showAnalysisViewModal && !showSaveModal"
      class="bg-primary-500/10 border border-primary-500/30 rounded-lg p-3 mb-4"
      :class="isFullscreen ? 'mx-4' : ''"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <BookmarkIcon class="w-5 h-5 text-primary-400" />
          <div>
            <p class="text-sm font-medium text-dark-200">
              Viewing Analysis: {{ currentAnalysis.title }}
            </p>
            <p class="text-xs text-dark-400">
              {{ currentSymbolName }} - {{ intervals.find(i => i.value === selectedInterval)?.label }} • 
              Saved {{ dayjs(currentAnalysis.created_at).format('MMM D, YYYY') }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="showAnalysisViewModal = true"
            class="px-3 py-1.5 text-xs rounded-lg bg-primary-500/20 text-primary-400 hover:bg-primary-500/30 transition-colors"
          >
            View Notes
          </button>
          <button
            @click="currentAnalysis = null; analysisTitle = ''; analysisNotes = ''"
            class="p-1.5 rounded-lg hover:bg-dark-800 text-dark-400 hover:text-dark-200 transition-colors"
            title="Clear analysis view"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Quick Symbol Buttons (below chart) -->
    <div class="bg-dark-900 border border-dark-800 rounded-lg p-4 mt-4" v-if="!isFullscreen">
      <h3 class="text-sm font-medium text-dark-400 mb-3">Quick Access</h3>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="item in [...symbolCategories.forex.slice(0, 5), ...symbolCategories.crypto.slice(0, 5)]"
          :key="item.symbol"
          @click="changeSymbol(item.symbol)"
          :class="[
            'px-3 py-1.5 text-sm rounded-lg border transition-colors',
            selectedSymbol === item.symbol
              ? 'bg-primary-500/20 border-primary-500 text-primary-400'
              : 'bg-dark-800 border-dark-700 text-dark-300 hover:border-dark-600 hover:text-dark-200'
          ]"
        >
          {{ item.name }}
        </button>
      </div>
    </div>

    <!-- Info Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4" v-if="!isFullscreen">
      <!-- Drawing Tools Info -->
      <div class="card p-4">
        <h3 class="text-sm font-semibold text-dark-200 mb-2">📐 Drawing Tools</h3>
        <p class="text-xs text-dark-400">
          Fibonacci retracement, trend lines, channels, pitchfork, Gann tools, Elliott waves, and more.
          Access from the left toolbar.
        </p>
      </div>

      <!-- Indicators Info -->
      <div class="card p-4">
        <h3 class="text-sm font-semibold text-dark-200 mb-2">📊 Indicators</h3>
        <p class="text-xs text-dark-400">
          RSI, MACD, Volume, Moving Averages, Bollinger Bands, Stochastic, and 100+ more indicators.
          Click "Indicators" button on chart.
        </p>
      </div>

      <!-- Shortcuts Info -->
      <div class="card p-4">
        <h3 class="text-sm font-semibold text-dark-200 mb-2">⌨️ Keyboard Shortcuts</h3>
        <p class="text-xs text-dark-400">
          <span class="text-dark-300">Alt+T</span> - Trend Line, 
          <span class="text-dark-300">Alt+F</span> - Fibonacci, 
          <span class="text-dark-300">Alt+H</span> - Horizontal Line,
          <span class="text-dark-300">Alt+I</span> - Invert Chart
        </p>
      </div>
    </div>

    <!-- Exit Fullscreen Button (fixed position) -->
    <button
      v-if="isFullscreen"
      @click="toggleFullscreen"
      class="fixed top-4 right-4 z-50 p-2 rounded-lg bg-dark-800 text-dark-400 hover:text-dark-200 hover:bg-dark-700 transition-colors border border-dark-700"
    >
      <ArrowsPointingInIcon class="w-5 h-5" />
    </button>
  </div>
</template>

<style scoped>
/* Ensure TradingView widget fills container */
:deep(#tradingview_chart) {
  width: 100% !important;
  height: 100% !important;
}

:deep(.tradingview-widget-container) {
  width: 100% !important;
  height: 100% !important;
}

:deep(iframe) {
  width: 100% !important;
  height: 100% !important;
}

/* Modal transition */
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
  transform: scale(0.95);
  opacity: 0;
}
</style>

