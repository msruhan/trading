<script setup>
import { ref, onMounted, onUnmounted, watch, computed, nextTick } from 'vue'
import { chartAnalysisAPI, msiAPI } from '@/services/api'
import api from '@/services/api'
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
const chartLoading = ref(false)
const chartError = ref(null)

// Prediction 3: Technical Analysis
const technicalAnalysis = ref(null)
const loadingTechnical = ref(false)
const technicalError = ref(null)

// Prediction 4: Market Stability Index (MSI)
const msiData = ref(null)
const loadingMSI = ref(false)
const msiError = ref(null)

// Module info modal
const showModuleModal = ref(false)
const selectedModule = ref(null)

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

const loadTradingViewWidget = async () => {
  chartLoading.value = true
  chartError.value = null
  
  // Wait for DOM to be ready
  await nextTick()
  
  // Check if container exists
  if (!chartContainerRef.value) {
    console.error('Chart container ref is not available')
    chartError.value = 'Chart container tidak tersedia'
    chartLoading.value = false
    return
  }
  
  // Remove existing widget
  chartContainerRef.value.innerHTML = ''
  
  // Reset widget ref
  widgetRef.value = null
  
  // Create widget container
  const container = document.createElement('div')
  container.id = 'tradingview_chart'
  container.style.width = '100%'
  container.style.height = '100%'
  container.style.minHeight = '400px'
  chartContainerRef.value.appendChild(container)
  
  // Function to create widget
  const createWidget = () => {
    if (!window.TradingView) {
      console.error('TradingView library not loaded')
      chartError.value = 'TradingView library tidak dapat dimuat'
      chartLoading.value = false
      return
    }
    
    if (!document.getElementById('tradingview_chart')) {
      console.error('TradingView container not found in DOM')
      chartError.value = 'Container chart tidak ditemukan'
      chartLoading.value = false
      return
    }
    
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
      console.log('TradingView widget created successfully')
      chartLoading.value = false
    } catch (error) {
      console.error('Failed to create TradingView widget:', error)
      chartError.value = 'Gagal membuat widget TradingView: ' + error.message
      chartLoading.value = false
    }
  }
  
  // Check if TradingView script is already loaded
  const existingScript = document.querySelector('script[src="https://s3.tradingview.com/tv.js"]')
  
  if (existingScript && window.TradingView) {
    // Script already loaded, create widget directly
    await nextTick()
    setTimeout(() => {
      createWidget()
    }, 100)
  } else if (existingScript && !window.TradingView) {
    // Script tag exists but not loaded yet, wait for it
    existingScript.addEventListener('load', () => {
      setTimeout(() => {
        createWidget()
      }, 100)
    })
  } else {
    // Load TradingView script
    const script = document.createElement('script')
    script.src = 'https://s3.tradingview.com/tv.js'
    script.async = true
    script.onload = () => {
      setTimeout(() => {
        createWidget()
      }, 100)
    }
    script.onerror = () => {
      console.error('Failed to load TradingView script')
      chartError.value = 'Gagal memuat script TradingView. Periksa koneksi internet Anda.'
      chartLoading.value = false
    }
    document.head.appendChild(script)
  }
}

const changeSymbol = async (symbol) => {
  // Only clear analysis if user manually changes symbol (not from loadAnalysis)
  if (!showAnalysisViewModal.value && !showSaveModal.value) {
    currentAnalysis.value = null
  }
  selectedSymbol.value = symbol
  await nextTick()
  loadTradingViewWidget()
}

const changeInterval = async (interval) => {
  // Only clear analysis if user manually changes interval (not from loadAnalysis)
  if (!showAnalysisViewModal.value && !showSaveModal.value) {
    currentAnalysis.value = null
  }
  selectedInterval.value = interval
  await nextTick()
  loadTradingViewWidget()
}

const toggleFullscreen = () => {
  isFullscreen.value = !isFullscreen.value
}

const toggleTheme = async () => {
  selectedTheme.value = selectedTheme.value === 'dark' ? 'light' : 'dark'
  await nextTick()
  loadTradingViewWidget()
}

onMounted(async () => {
  // Wait for DOM to be fully ready
  await nextTick()
  // Small delay to ensure container is rendered
  setTimeout(() => {
    loadTradingViewWidget()
  }, 200)
  
  // Fetch predictions
  await fetchTechnicalAnalysis()
  await fetchMSI()
})

onUnmounted(() => {
  // Cleanup
  if (chartContainerRef.value) {
    chartContainerRef.value.innerHTML = ''
  }
})

// Watch for symbol changes
watch(selectedSymbol, async () => {
  await nextTick()
  loadTradingViewWidget()
})

// Helper functions for predictions
const getPredictionClass = (status) => {
  return status === 'danger' || status === 'DANGER' ? 'text-red-400' :
         status === 'caution' || status === 'CAUTION' ? 'text-yellow-400' :
         'text-green-400'
}

const getMSIStatusClass = (status) => {
  return status === 'DANGER' ? 'text-red-400' :
         status === 'CAUTION' ? 'text-yellow-400' :
         'text-green-400'
}

const getMSIStatusBg = (status) => {
  return status === 'DANGER' ? 'bg-red-500/10 border-red-500/30' :
         status === 'CAUTION' ? 'bg-yellow-500/10 border-yellow-500/30' :
         'bg-green-500/10 border-green-500/30'
}

// Generate sample OHLC data for technical analysis
const getOHLCFromTradingView = () => {
  const basePrice = 2650.0 // XAUUSD base price
  const now = Date.now()
  const oneHour = 60 * 60 * 1000
  const oneMinute = 60 * 1000
  
  // Generate M15 data (last 50 periods)
  const m15Data = []
  for (let i = 50; i >= 0; i--) {
    const time = now - (i * oneMinute * 15)
    const open = basePrice + (Math.random() - 0.5) * 8
    const high = open + Math.random() * 3
    const low = open - Math.random() * 3
    const close = open + (Math.random() - 0.5) * 5
    m15Data.push({
      open: parseFloat(open.toFixed(2)),
      high: parseFloat(high.toFixed(2)),
      low: parseFloat(low.toFixed(2)),
      close: parseFloat(close.toFixed(2)),
      time: new Date(time).toISOString()
    })
  }
  
  // Generate H1 data (last 50 hours)
  const h1Data = []
  for (let i = 50; i >= 0; i--) {
    const time = now - (i * oneHour)
    const open = basePrice + (Math.random() - 0.5) * 10
    const high = open + Math.random() * 5
    const low = open - Math.random() * 5
    const close = open + (Math.random() - 0.5) * 8
    h1Data.push({
      open: parseFloat(open.toFixed(2)),
      high: parseFloat(high.toFixed(2)),
      low: parseFloat(low.toFixed(2)),
      close: parseFloat(close.toFixed(2)),
      time: new Date(time).toISOString()
    })
  }
  
  // Generate H4 data (last 30 periods)
  const h4Data = []
  for (let i = 30; i >= 0; i--) {
    const time = now - (i * oneHour * 4)
    const open = basePrice + (Math.random() - 0.5) * 15
    const high = open + Math.random() * 8
    const low = open - Math.random() * 8
    const close = open + (Math.random() - 0.5) * 12
    h4Data.push({
      open: parseFloat(open.toFixed(2)),
      high: parseFloat(high.toFixed(2)),
      low: parseFloat(low.toFixed(2)),
      close: parseFloat(close.toFixed(2)),
      time: new Date(time).toISOString()
    })
  }
  
  return {
    '1m': [],
    '5m': [],
    '15m': m15Data,
    'H1': h1Data,
    'H4': h4Data,
  }
}

// Fetch Technical Analysis (Prediction 3)
const fetchTechnicalAnalysis = async (ohlcData = null) => {
  loadingTechnical.value = true
  technicalError.value = null
  
  try {
    // If no OHLC data provided, generate sample data
    if (!ohlcData) {
      ohlcData = getOHLCFromTradingView()
    }
    
    const response = await api.post('/news/technical-analysis', {
      ohlc: ohlcData,
      symbol: 'XAUUSD',
    })
    
    if (response.data && response.data.prediction_3_technical) {
      technicalAnalysis.value = response.data.prediction_3_technical
    } else {
      technicalError.value = 'Format response tidak valid dari server'
    }
  } catch (error) {
    console.error('Failed to fetch technical analysis:', error)
    technicalError.value = error.response?.data?.message || error.message || 'Gagal mengambil analisis teknikal'
  } finally {
    loadingTechnical.value = false
  }
}

// Fetch MSI (Prediction 4)
const fetchMSI = async () => {
  loadingMSI.value = true
  msiError.value = null
  
  try {
    const response = await msiAPI.getLive()
    msiData.value = response.data
  } catch (error) {
    console.error('Failed to fetch MSI:', error)
    msiError.value = error.response?.data?.message || error.message || 'Gagal mengambil Market Stability Index'
  } finally {
    loadingMSI.value = false
  }
}

// Get module information based on module type
const getModuleInfo = (moduleName, moduleData) => {
  const info = {
    name: '',
    description: '',
    function: '',
    currentStatus: moduleData?.status || 'unknown',
    currentStatusLabel: moduleData?.status_label || 'Unknown',
    currentConfidence: moduleData?.confidence ?? 0,
    category: '',
    safeRanges: [],
    dangerRanges: [],
    interpretation: '',
    priority: '',
    howItWorks: ''
  }

  // Normalize module name first
  let normalizedName = typeof moduleName === 'string' ? moduleName.toLowerCase().trim() : String(moduleName).toLowerCase().trim()
  
  // Map common variations to standard keys - handle display names like "Trend Strength Model (TSM)"
  // Priority: check for TSM/POB first (shorter), then full names
  // Handle "Trend Strength Model (TSM)" -> check for 'tsm' first, then 'trend' + 'strength'
  // Handle "Probability of Breakout (POB)" -> check for 'pob' first, then 'breakout'
  if (normalizedName.includes('tsm') || (normalizedName.includes('trend') && normalizedName.includes('strength'))) {
    normalizedName = 'trend_strength'
  } else if (normalizedName.includes('pob') || (normalizedName.includes('breakout') && normalizedName.includes('probability')) || normalizedName.includes('breakout')) {
    normalizedName = 'breakout_probability'
  } else if (normalizedName.includes('liquidity') && normalizedName.includes('map')) {
    normalizedName = 'liquidity_map'
  } else if (normalizedName.includes('pattern') && normalizedName.includes('recognition')) {
    normalizedName = 'patterns'
  } else if (normalizedName.includes('atr') && normalizedName.includes('shock')) {
    normalizedName = 'atr_shock'
  } else if (normalizedName.includes('order') && normalizedName.includes('flow')) {
    normalizedName = 'order_flow'
  } else if (normalizedName.includes('market') && normalizedName.includes('regime')) {
    normalizedName = 'market_regime'
  }
  
  console.log('getModuleInfo - Original:', moduleName, 'Normalized:', normalizedName)
  
  // Use normalized name in switch
  switch (normalizedName) {
    case 'trend_strength':
    case 'tsm':
      info.name = 'Trend Strength Model (TSM)'
      info.description = 'Mengukur kekuatan trend menggunakan beberapa pendekatan teknikal secara bersamaan. Biasanya berdasarkan: ADX (indikator utama), Slope MA, Price–MA distance, Pattern detection (HHHL untuk bullish trend, LLLH untuk bearish trend)'
      info.function = 'Menilai apakah pasar sedang Sideways (baik untuk grid), mulai Trending (berbahaya), atau Trending kuat (sangat berbahaya). EA Grid bekerja paling baik di kondisi sideways (choppy market).'
      info.priority = 'Pendukung'
      info.howItWorks = 'Hitung ADX → ADX < 20 = sideways. Hitung slope MA50 / MA100 → menandakan arah. Hitung jarak harga ke MA → trend exhaustion atau trend expansion. Pola swing high/low → mendukung konfirmasi. Hasilkan skor 0–100: 0 = tidak ada trend (sideways total), 100 = trend sangat kuat (bahaya).'
      info.category = moduleData?.status === 'strong_trend' ? '🔴 Strong Trend' : 
                      moduleData?.status === 'moderately_trending' ? '🟡 Moderate Trend' : 
                      moduleData?.status === 'normal' ? '🟢 Sideways/Normal' : '⚪ Unknown'
      info.safeRanges = [
        { range: 'ADX < 20, MA Slope datar, TSM 0-30', description: 'ADX rendah dengan slope MA datar menunjukkan market sideways total. Kondisi ideal untuk EA Grid.', score: '🟢 SAFE – sideways' },
        { range: 'ADX < 25', description: 'ADX rendah menunjukkan market sideways, kondisi ideal untuk EA Grid', score: '🟢 SAFE' }
      ]
      info.dangerRanges = [
        { range: 'ADX > 25, MA Slope tajam, TSM > 60', description: 'ADX tinggi dengan slope MA tajam menunjukkan trend sangat kuat. EA Grid berisiko tinggi karena market akan bergerak satu arah dengan kuat.', score: '🔴 DANGER – trend kuat' },
        { range: 'ADX 20-25, MA Slope kecil, TSM 30-60', description: 'ADX sedang dengan slope kecil menunjukkan awal trend. Perlu perhatian karena market mulai trending.', score: '🟡 CAUTION – awal trend' }
      ]
      info.interpretation = moduleData?.status === 'strong_trend' 
        ? 'Market sedang dalam trend kuat (TSM > 60, ADX > 25, slope tajam). EA Grid berisiko tinggi karena akan menghadapi pergerakan satu arah yang kuat.'
        : moduleData?.status === 'moderately_trending'
        ? 'Market menunjukkan awal trend (TSM 30-60, ADX 20-25, slope kecil). Monitor dengan hati-hati, pertimbangkan untuk mengurangi lot atau pause EA.'
        : 'Market cenderung sideways (TSM 0-30, ADX < 20, slope datar). Kondisi relatif aman untuk EA Grid.'
      break

    case 'breakout_probability':
    case 'pob':
      info.name = 'Probability of Breakout (POB)'
      info.description = 'Memprediksi kemungkinan harga break dari range saat ini. Indikator ini sangat berharga karena EA Grid ingin harga tetap di dalam range.'
      info.function = 'Menilai apakah harga sedang bersiap breakout atau tetap ranging. Memberikan early warning sebelum trend besar terjadi.'
      info.priority = 'Pendukung'
      info.howItWorks = 'Biasanya menggunakan kombinasi: Mengukur range compression (Bollinger Band squeeze atau ATR rendah). Mendeteksi penumpukan likuiditas di area tertentu (liquidity map / equal highs-lows). Evaluasi tekanan order flow (dari indikator Order Flow Proxy). Hitung probabilitas breakout: POB = f(compression, liquidity buildup, directional pressure). Output berupa %: 0% → tidak ada tekanan breakout, 100% → breakout hampir pasti terjadi.'
      info.category = moduleData?.status === 'high_breakout_risk' ? '🔴 High Breakout Risk' : 
                      moduleData?.status === 'moderate_breakout_risk' ? '🟡 Moderate Risk' : 
                      moduleData?.status === 'low_breakout_risk' ? '🟢 Low Risk' : '⚪ Unknown'
      info.safeRanges = [
        { range: 'Probabilitas < 30%', description: 'Range stabil, EA Grid aman. Tidak ada tekanan breakout yang signifikan.', score: '🟢 SAFE' }
      ]
      info.dangerRanges = [
        { range: 'Probabilitas > 60%', description: 'Breakout kemungkinan besar terjadi. EA Grid berisiko menghadapi pergerakan besar yang dapat menyebabkan loss besar.', score: '🔴 DANGER' },
        { range: 'Probabilitas 30-60%', description: 'Ada potensi breakout. Perlu perhatian dan monitoring ketat.', score: '🟡 CAUTION' }
      ]
      info.interpretation = moduleData?.status === 'high_breakout_risk'
        ? 'Probabilitas breakout tinggi (>60%). Breakout kemungkinan besar terjadi. EA Grid berisiko menghadapi pergerakan besar yang dapat menyebabkan loss besar.'
        : moduleData?.status === 'moderate_breakout_risk'
        ? 'Probabilitas breakout sedang (30-60%). Ada potensi breakout, monitor dengan hati-hati.'
        : 'Probabilitas breakout rendah (<30%). Range stabil, EA Grid aman karena harga cenderung tetap dalam range.'
      break

    case 'liquidity_map':
      info.name = 'Liquidity Map Model'
      info.description = 'Mengidentifikasi zona likuiditas (support/resistance) berdasarkan volume dan price action'
      info.function = 'Mendeteksi area dimana banyak order berada (support/resistance kuat). EA Grid perlu menghindari area ini karena dapat menyebabkan whipsaw dan loss.'
      info.priority = 'Pendukung'
      info.howItWorks = 'Menganalisis konsentrasi volume dan price action untuk mengidentifikasi zona support/resistance. Area dengan volume tinggi dan banyak rejection = zona likuiditas kuat.'
      info.category = moduleData?.status === 'high_liquidity_risk' ? '🔴 High Liquidity Risk' : 
                      moduleData?.status === 'moderate_liquidity_risk' ? '🟡 Moderate Risk' : 
                      moduleData?.status === 'low_liquidity_risk' ? '🟢 Low Risk' : '⚪ Unknown'
      info.safeRanges = [
        { range: 'Likuiditas rendah', description: 'Tidak ada zona likuiditas kuat yang terdeteksi, market bergerak bebas', score: 'Safe' },
        { range: 'Jarak dari support/resistance > 50 pips', description: 'Harga cukup jauh dari zona likuiditas, aman untuk trading', score: 'Safe' }
      ]
      info.dangerRanges = [
        { range: 'Likuiditas sangat tinggi', description: 'Banyak zona likuiditas kuat terdeteksi. EA Grid berisiko terkena whipsaw dan loss di area ini.', score: 'Danger' },
        { range: 'Harga mendekati support/resistance', description: 'Harga mendekati zona likuiditas, berpotensi rejection atau breakout', score: 'Caution' }
      ]
      info.interpretation = moduleData?.status === 'high_liquidity_risk'
        ? 'Banyak zona likuiditas kuat terdeteksi. EA Grid berisiko terkena whipsaw dan loss di area ini.'
        : moduleData?.status === 'moderate_liquidity_risk'
        ? 'Beberapa zona likuiditas terdeteksi. Monitor dengan hati-hati.'
        : 'Likuiditas rendah. Market bergerak bebas, relatif aman untuk EA Grid.'
      break

    case 'patterns':
      info.name = 'Pattern Recognition'
      info.description = 'Mengenali pola chart seperti Head & Shoulders, Double Top/Bottom, Triangle, Flag, dll'
      info.function = 'Mendeteksi pola chart yang dapat memprediksi pergerakan harga selanjutnya. Pola reversal berbahaya untuk EA Grid karena dapat menyebabkan perubahan trend mendadak.'
      info.priority = 'Prioritas Tinggi'
      info.howItWorks = 'Menganalisis formasi candle dan struktur harga untuk mengidentifikasi pola chart klasik. Pola reversal (H&S, Double Top) = bearish, pola continuation (Flag, Triangle) = bisa bullish/bearish.'
      info.category = moduleData?.status === 'reversal_pattern' ? '🔴 Reversal Pattern' : 
                      moduleData?.status === 'continuation_pattern' ? '🟡 Continuation Pattern' : 
                      moduleData?.status === 'no_pattern' ? '🟢 No Pattern' : '⚪ Unknown'
      info.safeRanges = [
        { range: 'Tidak ada pola terdeteksi', description: 'Tidak ada pola chart yang jelas, market bergerak normal', score: 'Safe' },
        { range: 'Pola continuation lemah', description: 'Pola continuation terdeteksi tapi lemah, tidak mengancam', score: 'Safe' }
      ]
      info.dangerRanges = [
        { range: 'Pola reversal kuat (H&S, Double Top/Bottom)', description: 'Pola reversal terdeteksi dengan confidence tinggi. EA Grid berisiko tinggi karena trend akan berubah arah.', score: 'Danger' },
        { range: 'Pola continuation kuat', description: 'Pola continuation terdeteksi, market akan melanjutkan trend', score: 'Caution' }
      ]
      info.interpretation = moduleData?.status === 'reversal_pattern'
        ? 'Pola reversal terdeteksi. EA Grid berisiko tinggi karena trend akan berubah arah secara mendadak.'
        : moduleData?.status === 'continuation_pattern'
        ? 'Pola continuation terdeteksi. Market akan melanjutkan trend, monitor dengan hati-hati.'
        : 'Tidak ada pola chart yang jelas. Market bergerak normal, relatif aman untuk EA Grid.'
      break

    case 'atr_shock':
      info.name = 'Dynamic ATR Shock Detector'
      info.description = 'Memonitor lonjakan volatilitas abnormal yang tidak terlihat dari ATR biasa. ATR normal mendeteksi volatilitas jangka panjang, Dynamic ATR Shock Detector mendeteksi perubahan mendadak'
      info.function = 'Menangkap pergerakan harga abnormal. Mengidentifikasi risiko breakout. Mengantisipasi spike spontan saat news atau manipulasi harga. Kondisi "shock" sangat berbahaya untuk EA Grid karena spike kecil saja bisa memicu floating besar.'
      info.priority = 'Prioritas Tinggi'
      info.howItWorks = 'Menghitung ATR(14) standard. Menghitung short-term ATR (ATR(2) atau ATR(3)). Membandingkan short-term ATR dengan long-term ATR. ShockRatio = ATR_short / ATR_long. Jika rasio besar → terjadi volatility shock. Deteksi: ShockRatio < 1.2 = Normal, 1.2-1.6 = Moderate Spike, > 1.6 = Volatility Shock.'
      info.category = moduleData?.status === 'extreme_shock' ? '🔴 Extreme Shock' : 
                      moduleData?.status === 'shock' ? '🔴 Shock' : 
                      moduleData?.status === 'normal' ? '🟢 Normal' : '⚪ Unknown'
      info.safeRanges = [
        { range: 'ShockRatio < 1.2, Skor 70-100', description: 'ATR dalam range normal, volatilitas stabil. Tidak ada shock terdeteksi, kondisi aman untuk EA Grid.', score: '🟢 SAFE' },
        { range: 'ATR < 1.5x rata-rata', description: 'ATR dalam range normal, volatilitas stabil', score: '🟢 SAFE' }
      ]
      info.dangerRanges = [
        { range: 'ShockRatio > 1.6, Skor 0-40', description: 'Volatility Shock terdeteksi. EA Grid berisiko tinggi terkena stop loss atau margin call. Sangat disarankan untuk pause EA.', score: '🔴 DANGER' },
        { range: 'ShockRatio 1.2-1.6, Skor 40-70', description: 'Moderate Spike terdeteksi. Volatilitas meningkat, EA Grid perlu perhatian khusus.', score: '🟡 CAUTION' },
        { range: 'ATR > 3x rata-rata', description: 'ATR sangat tinggi menunjukkan volatilitas ekstrem. EA Grid berisiko tinggi.', score: '🔴 DANGER' }
      ]
      info.interpretation = moduleData?.status === 'extreme_shock' || moduleData?.status === 'shock'
        ? 'Volatilitas ekstrem terdeteksi (ShockRatio > 1.6). EA Grid berisiko tinggi terkena stop loss atau margin call. Spike kecil saja bisa memicu floating besar. Sangat disarankan untuk pause EA.'
        : moduleData?.status === 'moderate_spike'
        ? 'Moderate spike terdeteksi (ShockRatio 1.2-1.6). Volatilitas meningkat, monitor dengan hati-hati.'
        : 'Volatilitas normal (ShockRatio < 1.2). Kondisi relatif aman untuk EA Grid.'
      break

    case 'order_flow':
      info.name = 'Order Flow Proxy Model'
      info.description = 'Indikator yang mencoba meniru perilaku order flow (arus beli vs arus jual) meskipun data order book asli tidak tersedia. Menggunakan proxy seperti: Candle body imbalance, Wick dominance, Volume delta (jika ada volume), Momentum imbalance, Sudent direction pressure'
      info.function = 'Mendeteksi apakah tekanan pasar lebih dominan ke arah BUY atau SELL. Bagi EA Grid, kondisi terbaik adalah: Order Flow Seimbang → market sideways (ideal), Order Flow tidak seimbang → potensi trend kuat → berbahaya.'
      info.priority = 'Pendukung'
      info.howItWorks = 'Mengukur body candle terakhir (besar-kecil, warna). Mendeteksi keberadaan imbalanced pressure (contoh: 3 candle bullish besar berturut-turut). Mengukur ratio wick–body (wick dominan = rejection = sideways). Menganalisis perubahan momentum (MACD/RSI derivative opsional). Menghasilkan nilai skor 0-100 yang menunjukkan dominasi order flow.'
      info.category = moduleData?.status === 'strong_order_flow' ? '🔴 Strong Order Flow' : 
                      moduleData?.status === 'moderate_order_flow' ? '🟡 Moderate Flow' : 
                      moduleData?.status === 'balanced' ? '🟢 Balanced' : '⚪ Unknown'
      info.safeRanges = [
        { range: 'Skor 40-60, Order Flow seimbang', description: 'Order flow seimbang menunjukkan market sideways. Kondisi ideal untuk EA Grid karena tidak ada dominasi buyer atau seller.', score: '🟢 SAFE' },
        { range: 'Order flow lemah', description: 'Order flow lemah, market bergerak lambat dan tidak ada tekanan kuat', score: '🟢 SAFE' }
      ]
      info.dangerRanges = [
        { range: 'Skor <25 atau >75, Tekanan kuat', description: 'Order flow sangat kuat ke satu arah menunjukkan trend kuat hampir pasti terbentuk. EA Grid berisiko menghadapi pergerakan besar ke satu arah.', score: '🔴 DANGER' },
        { range: 'Skor 25-40 atau 60-75, Tekanan lemah', description: 'Order flow sedang menunjukkan ada kecenderungan arah. Perlu perhatian karena mulai ada dominasi buyer atau seller.', score: '🟡 CAUTION' }
      ]
      info.interpretation = moduleData?.status === 'strong_order_flow'
        ? 'Order flow sangat kuat ke satu arah (skor <25 atau >75). Trend kuat hampir pasti terbentuk. EA Grid berisiko menghadapi pergerakan besar yang dapat menyebabkan loss.'
        : moduleData?.status === 'moderate_order_flow'
        ? 'Order flow sedang (skor 25-40 atau 60-75). Ada kecenderungan arah, monitor dengan hati-hati.'
        : 'Order flow seimbang (skor 40-60). Market sideways, kondisi ideal untuk EA Grid karena tidak ada dominasi buyer atau seller.'
      break

    case 'market_regime':
      info.name = 'Market Regime Classification'
      info.description = 'Mengklasifikasikan kondisi market menjadi Trending, Ranging, Volatile, atau Stable berdasarkan multiple indikator'
      info.function = 'Mengidentifikasi kondisi market secara keseluruhan. EA Grid bekerja paling baik di kondisi Ranging/Stable, berbahaya di Trending/Volatile.'
      info.priority = 'Prioritas Tinggi'
      info.howItWorks = 'Menggabungkan analisis dari multiple indikator (trend, volatilitas, volume) untuk mengklasifikasikan market. Trending = market bergerak kuat satu arah, Ranging = market bergerak dalam range, Volatile = volatilitas tinggi.'
      info.category = moduleData?.status === 'trending' ? '🔴 Trending' : 
                      moduleData?.status === 'volatile' ? '🔴 Volatile' : 
                      moduleData?.status === 'ranging' ? '🟢 Ranging' : 
                      moduleData?.status === 'stable' ? '🟢 Stable' : '⚪ Unknown'
      info.safeRanges = [
        { range: 'Ranging/Stable', description: 'Market bergerak dalam range atau stabil. Kondisi ideal untuk EA Grid.', score: 'Safe' },
        { range: 'Trending lemah', description: 'Market trending tapi lemah, masih bisa ditoleransi', score: 'Safe' }
      ]
      info.dangerRanges = [
        { range: 'Trending kuat', description: 'Market trending kuat ke satu arah. EA Grid berisiko tinggi karena akan menghadapi pergerakan besar satu arah.', score: 'Danger' },
        { range: 'Volatile', description: 'Market sangat volatile. EA Grid berisiko tinggi terkena stop loss atau margin call.', score: 'Danger' }
      ]
      info.interpretation = moduleData?.status === 'trending' || moduleData?.status === 'volatile'
        ? 'Market dalam kondisi trending atau volatile. EA Grid berisiko tinggi. Sangat disarankan untuk pause EA.'
        : moduleData?.status === 'ranging' || moduleData?.status === 'stable'
        ? 'Market dalam kondisi ranging atau stable. Kondisi ideal untuk EA Grid.'
        : 'Kondisi market tidak jelas. Monitor dengan hati-hati.'
      break
      
    default:
      // Default fallback for unknown modules
      info.name = moduleName || 'Unknown Module'
      info.description = 'Informasi modul tidak tersedia'
      info.function = 'Fungsi modul tidak diketahui'
      info.priority = 'Unknown'
      info.howItWorks = 'Cara kerja modul tidak diketahui'
      info.category = '⚪ Unknown'
      info.safeRanges = []
      info.dangerRanges = []
      info.interpretation = 'Tidak ada interpretasi tersedia untuk modul ini'
      break
  }

  return info
}

// Open module info modal
const openModuleInfo = (moduleName, moduleData, timeframe) => {
  console.log('Opening module info:', { moduleName, moduleData, timeframe })
  
  // Pass the module name directly to getModuleInfo - it will handle normalization internally
  const moduleInfo = getModuleInfo(moduleName, moduleData)
  console.log('Module info:', moduleInfo)
  
  if (!moduleInfo || !moduleInfo.name || moduleInfo.name === 'Unknown Module') {
    console.error('Failed to get module info for:', normalizedName, 'Original:', moduleName)
    // Try with original name as fallback
    const fallbackInfo = getModuleInfo(moduleName, moduleData)
    console.log('Fallback info:', fallbackInfo)
    if (fallbackInfo && fallbackInfo.name && fallbackInfo.name !== 'Unknown Module') {
      selectedModule.value = {
        ...fallbackInfo,
        timeframe: timeframe
      }
      showModuleModal.value = true
      console.log('Modal opened with fallback. Selected module:', selectedModule.value)
      return
    }
    alert('Informasi modul tidak ditemukan untuk: ' + moduleName + '\n\nSilakan cek console untuk detail lebih lanjut.')
    return
  }
  
  selectedModule.value = {
    ...moduleInfo,
    timeframe: timeframe
  }
  showModuleModal.value = true
  console.log('Modal should be shown:', showModuleModal.value, 'Selected module:', selectedModule.value)
}

// Computed: Technical Confidence & Override Logic
const technicalConfidence = computed(() => {
  if (!technicalAnalysis.value?.predictions) return null
  
  const allModules = []
  let insufficientDataCount = 0
  let dangerWithHighConfidence = false
  const dangerModules = []
  
  // Collect all modules from all timeframes
  Object.values(technicalAnalysis.value.predictions).forEach((timeframe, tfIndex) => {
    const timeframeName = Object.keys(technicalAnalysis.value.predictions)[tfIndex]
    if (timeframe?.modules) {
      Object.values(timeframe.modules).forEach((module, modIndex) => {
        const moduleKey = Object.keys(timeframe.modules)[modIndex]
        allModules.push({
          ...module,
          timeframe: timeframeName,
          moduleKey: moduleKey
        })
        if (module.status === 'insufficient_data') {
          insufficientDataCount++
        }
        // Check if any DANGER module has confidence >= 70%
        if ((module.status === 'danger' || module.status === 'extreme_shock' || 
             module.status === 'strong_trend' || module.status === 'shock') && 
            (module.confidence ?? 0) >= 70) {
          dangerWithHighConfidence = true
          dangerModules.push({
            module: module.module || moduleKey,
            timeframe: timeframeName,
            status: module.status,
            confidence: module.confidence ?? 0,
            status_label: module.status_label
          })
        }
      })
    }
  })
  
  if (allModules.length === 0) return null
  
  // Calculate average confidence (excluding insufficient_data)
  const validModules = allModules.filter(m => m.status !== 'insufficient_data')
  const avgConfidence = validModules.length > 0
    ? Math.round(validModules.reduce((sum, m) => sum + (m.confidence ?? 0), 0) / validModules.length)
    : 0
  
  // Determine final status with override rules
  let finalStatus = technicalAnalysis.value.combined_overall?.status || 'safe'
  let overrideReason = null
  let eaRecommendation = null
  const riskScore = technicalAnalysis.value.combined_overall?.risk_score || 0
  
  // Rule 1: If any DANGER module with confidence >= 70% → override to DANGER
  if (dangerWithHighConfidence) {
    finalStatus = 'danger'
    const dangerModulesList = dangerModules.map(m => `${m.module} (${m.timeframe})`).join(', ')
    overrideReason = `Modul DANGER dengan confidence ≥ 70% terdeteksi: ${dangerModulesList}`
    eaRecommendation = `EA DANGER | Risk Score: ${riskScore}/100 | Kondisi market berisiko tinggi untuk EA Grid. Disarankan nonaktifkan EA atau gunakan mode konservatif.`
  }
  // Rule 2: If all SAFE and no Insufficient Data → SAFE
  else if (allModules.every(m => m.status === 'safe' || m.status === 'low_risk') && insufficientDataCount === 0) {
    finalStatus = 'safe'
    eaRecommendation = `EA SAFE | Risk Score: ${riskScore}/100 | Kondisi market relatif aman untuk EA Grid.`
  }
  // Rule 3: If mixed & many Insufficient Data → CAUTION
  else if (insufficientDataCount > allModules.length * 0.5) {
    finalStatus = 'caution'
    overrideReason = 'Keputusan teknikal kurang kuat, sebaiknya ikuti Prediksi 1 & 2.'
    eaRecommendation = `EA CAUTION | Risk Score: ${riskScore}/100 | Data teknikal kurang lengkap. Gunakan mode konservatif atau nonaktifkan EA.`
  }
  // Default: Generate recommendation based on final status
  else {
    if (finalStatus === 'danger') {
      eaRecommendation = `EA DANGER | Risk Score: ${riskScore}/100 | Kondisi market berisiko tinggi untuk EA Grid. Disarankan nonaktifkan EA atau gunakan mode konservatif.`
    } else if (finalStatus === 'caution') {
      eaRecommendation = `EA CAUTION | Risk Score: ${riskScore}/100 | Kondisi market perlu perhatian. Gunakan mode konservatif.`
    } else {
      eaRecommendation = `EA SAFE | Risk Score: ${riskScore}/100 | Kondisi market relatif aman untuk EA Grid.`
    }
  }
  
  return {
    confidence: avgConfidence,
    finalStatus,
    overrideReason,
    insufficientDataCount,
    totalModules: allModules.length,
    eaRecommendation,
    dangerModules: dangerModules,
    originalStatus: technicalAnalysis.value.combined_overall?.status || 'safe',
    originalRiskScore: riskScore
  }
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
        'bg-dark-900 border border-dark-800 rounded-lg overflow-hidden flex-1 relative',
      ]" :style="{ height: isFullscreen ? 'calc(100vh - 120px)' : '75vh', minHeight: '400px' }">
        <!-- Loading Indicator -->
        <div v-if="chartLoading" class="absolute inset-0 flex items-center justify-center bg-dark-900/80 z-10">
          <div class="text-center">
            <div class="spinner mx-auto mb-3"></div>
            <p class="text-sm text-dark-400">Memuat chart...</p>
          </div>
        </div>
        
        <!-- Error Indicator -->
        <div v-if="chartError && !chartLoading" class="absolute inset-0 flex items-center justify-center bg-dark-900/80 z-10">
          <div class="text-center p-4">
            <div class="text-red-400 text-4xl mb-3">⚠️</div>
            <p class="text-sm text-red-400 mb-2">{{ chartError }}</p>
            <button
              @click="loadTradingViewWidget()"
              class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors text-sm"
            >
              Coba Lagi
            </button>
          </div>
        </div>
        
        <div ref="chartContainerRef" class="w-full h-full" style="min-height: 400px;"></div>
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

    <!-- Predictions Cards (Prediction 3 & 4) -->
    <div class="space-y-6 mt-4" v-if="!isFullscreen">
      <!-- PREDICTION 3: Technical Analysis Based -->
      <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-3">
            <span class="text-2xl">📊</span>
            <div>
              <h2 class="text-lg font-semibold">Prediksi 4: Berdasarkan Analisis Teknikal</h2>
              <p class="text-sm text-dark-400">7 modul analisis teknikal multi-timeframe</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button 
              @click="fetchTechnicalAnalysis()"
              :disabled="loadingTechnical"
              class="flex items-center gap-2 px-3 py-1.5 text-sm bg-orange-600/20 text-orange-400 border border-orange-500/30 rounded-lg hover:bg-orange-600/30 transition-colors disabled:opacity-50"
            >
              <svg :class="['w-4 h-4', loadingTechnical && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              {{ loadingTechnical ? 'Analyzing...' : 'Analyze Technical' }}
            </button>
            <span class="text-xs text-dark-500">(Sample data)</span>
          </div>
        </div>

        <!-- Error State -->
        <div v-if="technicalError" class="p-4 bg-red-500/10 border border-red-500/30 rounded-xl mb-4">
          <p class="text-sm text-red-400">{{ technicalError }}</p>
          <p class="text-xs text-red-400/70 mt-1">Pastikan TradingView chart terhubung dan mengirim data OHLC.</p>
        </div>

        <!-- Loading State -->
        <div v-else-if="loadingTechnical" class="text-center py-8 text-dark-400">
          <svg class="w-8 h-8 animate-spin mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
          <p>Menganalisis data teknikal...</p>
        </div>

        <!-- Day Prediction Summary (Card Besar) -->
        <div v-else-if="technicalAnalysis?.combined_overall" :class="[
          'p-6 rounded-xl mb-6 border-2',
          (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'danger' ? 'bg-red-500/10 border-red-500/40' :
          (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'caution' ? 'bg-yellow-500/10 border-yellow-500/40' :
          'bg-green-500/10 border-green-500/40'
        ]">
          <div class="flex items-center gap-4 mb-4">
            <span :class="[
              'text-4xl font-bold',
              (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'danger' ? 'text-red-400' :
              (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'caution' ? 'text-yellow-400' : 'text-green-400'
            ]">
              {{ (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'danger' ? '🔴 DANGER' : 
                 (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'caution' ? '🟡 CAUTION' : '🟢 SAFE' }}
            </span>
            <div class="flex-1">
              <h3 class="text-xl font-bold mb-1">Kesimpulan Analisis Teknikal</h3>
              <p class="text-sm text-dark-400">Berdasarkan analisis M15, H1, dan H4 dengan 7 modul</p>
              <p v-if="technicalConfidence?.overrideReason" class="text-xs text-yellow-400 mt-1 font-medium">
                ⚠️ {{ technicalConfidence.overrideReason }}
              </p>
            </div>
            <div class="text-right">
              <div class="text-3xl font-bold" :class="[
                (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'danger' ? 'text-red-400' :
                (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'caution' ? 'text-yellow-400' : 'text-green-400'
              ]">
                {{ technicalAnalysis.combined_overall.risk_score }}/100
              </div>
              <div class="text-xs text-dark-400">Risk Score</div>
              <div class="text-sm font-medium mt-1" :class="[
                (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'danger' ? 'text-red-400' :
                (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'caution' ? 'text-yellow-400' : 'text-green-400'
              ]">
                {{ technicalConfidence?.confidence || technicalAnalysis.combined_overall.confidence || 0 }}% Confidence
              </div>
            </div>
          </div>
          
          <!-- EA Recommendation (with override logic) -->
          <div class="mb-4">
            <p class="text-lg text-dark-200 font-medium">
              {{ technicalConfidence?.eaRecommendation || technicalAnalysis.combined_overall.recommendation }}
            </p>
            
            <!-- Override Explanation -->
            <div v-if="technicalConfidence?.overrideReason && technicalConfidence.finalStatus === 'danger'" class="mt-3 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
              <div class="flex items-start gap-3">
                <span class="text-2xl">⚠️</span>
                <div class="flex-1">
                  <h4 class="text-sm font-bold text-red-400 mb-2">Status Di-Override Menjadi DANGER</h4>
                  <p class="text-xs text-red-300/80 mb-3">
                    Meskipun overall risk score rendah ({{ technicalConfidence.originalRiskScore }}/100) dan semua timeframe menunjukkan <strong>Safe</strong>, 
                    sistem mendeteksi modul individual dengan status <strong>DANGER</strong> dan confidence ≥ 70%.
                  </p>
                  
                  <!-- List of Danger Modules -->
                  <div v-if="technicalConfidence.dangerModules && technicalConfidence.dangerModules.length > 0" class="mb-3">
                    <p class="text-xs font-semibold text-red-400 mb-2">Modul yang memicu override:</p>
                    <div class="space-y-2">
                      <div 
                        v-for="(dangerMod, idx) in technicalConfidence.dangerModules" 
                        :key="idx"
                        class="p-2 bg-red-500/20 rounded border border-red-500/30"
                      >
                        <div class="flex items-center justify-between">
                          <div>
                            <span class="text-xs font-medium text-red-300">{{ dangerMod.module }}</span>
                            <span class="text-xs text-red-400/70 ml-2">({{ dangerMod.timeframe }})</span>
                          </div>
                          <div class="text-right">
                            <span class="text-xs font-bold text-red-400">{{ dangerMod.confidence }}%</span>
                            <span class="text-xs text-red-400/70 ml-1">confidence</span>
                          </div>
                        </div>
                        <p class="text-xs text-red-300/70 mt-1">{{ dangerMod.status_label }}</p>
                      </div>
                    </div>
                  </div>
                  
                  <div class="mt-3 p-2 bg-dark-900/50 rounded border border-dark-700/50">
                    <p class="text-xs text-dark-300 font-medium mb-1">📊 Perbandingan:</p>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                      <div>
                        <span class="text-dark-400">Original Status:</span>
                        <span class="text-green-400 font-medium ml-1">{{ technicalConfidence.originalStatus?.toUpperCase() || 'SAFE' }}</span>
                      </div>
                      <div>
                        <span class="text-dark-400">Final Status:</span>
                        <span class="text-red-400 font-medium ml-1">DANGER</span>
                      </div>
                      <div>
                        <span class="text-dark-400">Risk Score:</span>
                        <span class="text-dark-300 font-medium ml-1">{{ technicalConfidence.originalRiskScore }}/100</span>
                      </div>
                      <div>
                        <span class="text-dark-400">Timeframes:</span>
                        <span class="text-green-400 font-medium ml-1">M15: Safe, H1: Safe, H4: Safe</span>
                      </div>
                    </div>
                  </div>
                  
                  <p class="text-xs text-red-300/70 mt-3 italic">
                    💡 <strong>Alasan Override:</strong> Meskipun mayoritas modul menunjukkan kondisi aman, 
                    modul dengan confidence tinggi (≥70%) yang menunjukkan risiko ekstrem memiliki prioritas lebih tinggi 
                    untuk melindungi EA Grid dari kondisi market yang berbahaya.
                  </p>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Technical Confidence Summary -->
          <div v-if="technicalConfidence" class="mt-4 p-4 bg-dark-800/50 rounded-lg border border-dark-700/50">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm font-semibold text-dark-300 mb-1">Technical Confidence</div>
                <div class="text-xs text-dark-500">
                  {{ technicalConfidence.totalModules }} modul dianalisis
                  <span v-if="technicalConfidence.insufficientDataCount > 0" class="text-yellow-400">
                    ({{ technicalConfidence.insufficientDataCount }} modul kurang data)
                  </span>
                </div>
              </div>
              <div class="text-right">
                <div class="text-2xl font-bold" :class="[
                  technicalConfidence.confidence >= 70 ? 'text-green-400' :
                  technicalConfidence.confidence >= 50 ? 'text-yellow-400' : 'text-gray-400'
                ]">
                  {{ technicalConfidence.confidence }}%
                </div>
                <div class="text-xs text-dark-500 mt-0.5">
                  <span v-if="technicalConfidence.confidence === 0" class="text-gray-400">
                    Data OHLC kurang
                  </span>
                  <span v-else-if="technicalConfidence.confidence < 50" class="text-yellow-400">
                    Confidence rendah
                  </span>
                  <span v-else class="text-green-400">
                    Confidence baik
                  </span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Stats Row -->
          <div class="flex flex-wrap gap-3 text-sm mb-4 mt-4">
            <span class="px-3 py-1.5 bg-dark-800 rounded-lg">
              📊 Timeframes: <span class="text-white font-medium">3 (M15, H1, H4)</span>
            </span>
            <span class="px-3 py-1.5 bg-red-500/20 text-red-400 rounded-lg border border-red-500/30">
              🔴 Danger: {{ technicalAnalysis.combined_overall.timeframe_summary?.danger || 0 }}
            </span>
            <span class="px-3 py-1.5 bg-yellow-500/20 text-yellow-400 rounded-lg border border-yellow-500/30">
              🟡 Caution: {{ technicalAnalysis.combined_overall.timeframe_summary?.caution || 0 }}
            </span>
            <span class="px-3 py-1.5 bg-green-500/20 text-green-400 rounded-lg border border-green-500/30">
              🟢 Safe: {{ technicalAnalysis.combined_overall.timeframe_summary?.safe || 0 }}
            </span>
          </div>

          <!-- Timeframe Breakdown -->
          <div v-if="technicalAnalysis?.predictions" class="grid md:grid-cols-3 gap-3 mt-4">
            <!-- M15 Summary -->
            <div v-if="technicalAnalysis.predictions.M15" :class="[
              'p-3 rounded-lg border',
              technicalAnalysis.predictions.M15.overall.status === 'danger' ? 'bg-red-500/5 border-red-500/20' :
              technicalAnalysis.predictions.M15.overall.status === 'caution' ? 'bg-yellow-500/5 border-yellow-500/20' :
              'bg-green-500/5 border-green-500/20'
            ]">
              <div class="flex items-center justify-between mb-2">
                <span class="font-bold text-sm">M15</span>
                <span :class="[
                  'px-2 py-0.5 rounded text-xs font-medium',
                  technicalAnalysis.predictions.M15.overall.status === 'danger' ? 'bg-red-500/30 text-red-300' :
                  technicalAnalysis.predictions.M15.overall.status === 'caution' ? 'bg-yellow-500/30 text-yellow-300' :
                  'bg-green-500/30 text-green-300'
                ]">
                  {{ technicalAnalysis.predictions.M15.overall.status_label }}
                </span>
              </div>
              <div class="text-xs text-dark-400">
                Risk: <span class="font-bold" :class="[
                  technicalAnalysis.predictions.M15.overall.status === 'danger' ? 'text-red-400' :
                  technicalAnalysis.predictions.M15.overall.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
                ]">{{ technicalAnalysis.predictions.M15.overall.risk_score }}/100</span>
              </div>
            </div>

            <!-- H1 Summary -->
            <div v-if="technicalAnalysis.predictions.H1" :class="[
              'p-3 rounded-lg border',
              technicalAnalysis.predictions.H1.overall.status === 'danger' ? 'bg-red-500/5 border-red-500/20' :
              technicalAnalysis.predictions.H1.overall.status === 'caution' ? 'bg-yellow-500/5 border-yellow-500/20' :
              'bg-green-500/5 border-green-500/20'
            ]">
              <div class="flex items-center justify-between mb-2">
                <span class="font-bold text-sm">H1</span>
                <span :class="[
                  'px-2 py-0.5 rounded text-xs font-medium',
                  technicalAnalysis.predictions.H1.overall.status === 'danger' ? 'bg-red-500/30 text-red-300' :
                  technicalAnalysis.predictions.H1.overall.status === 'caution' ? 'bg-yellow-500/30 text-yellow-300' :
                  'bg-green-500/30 text-green-300'
                ]">
                  {{ technicalAnalysis.predictions.H1.overall.status_label }}
                </span>
              </div>
              <div class="text-xs text-dark-400">
                Risk: <span class="font-bold" :class="[
                  technicalAnalysis.predictions.H1.overall.status === 'danger' ? 'text-red-400' :
                  technicalAnalysis.predictions.H1.overall.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
                ]">{{ technicalAnalysis.predictions.H1.overall.risk_score }}/100</span>
              </div>
            </div>

            <!-- H4 Summary -->
            <div v-if="technicalAnalysis.predictions.H4" :class="[
              'p-3 rounded-lg border',
              technicalAnalysis.predictions.H4.overall.status === 'danger' ? 'bg-red-500/5 border-red-500/20' :
              technicalAnalysis.predictions.H4.overall.status === 'caution' ? 'bg-yellow-500/5 border-yellow-500/20' :
              'bg-green-500/5 border-green-500/20'
            ]">
              <div class="flex items-center justify-between mb-2">
                <span class="font-bold text-sm">H4</span>
                <span :class="[
                  'px-2 py-0.5 rounded text-xs font-medium',
                  technicalAnalysis.predictions.H4.overall.status === 'danger' ? 'bg-red-500/30 text-red-300' :
                  technicalAnalysis.predictions.H4.overall.status === 'caution' ? 'bg-yellow-500/30 text-yellow-300' :
                  'bg-green-500/30 text-green-300'
                ]">
                  {{ technicalAnalysis.predictions.H4.overall.status_label }}
                </span>
              </div>
              <div class="text-xs text-dark-400">
                Risk: <span class="font-bold" :class="[
                  technicalAnalysis.predictions.H4.overall.status === 'danger' ? 'text-red-400' :
                  technicalAnalysis.predictions.H4.overall.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
                ]">{{ technicalAnalysis.predictions.H4.overall.risk_score }}/100</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Module Details per Timeframe -->
        <div v-if="technicalAnalysis?.predictions && !loadingTechnical && !technicalError" class="space-y-4">
          <!-- M15 Modules -->
          <details v-if="technicalAnalysis.predictions.M15?.modules" class="group">
            <summary class="cursor-pointer text-sm font-semibold text-dark-300 mb-4 flex items-center gap-2 hover:text-white">
              <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
              📊 Detail Modul M15 (7 modul)
            </summary>
            <div class="mt-4 space-y-3">
              <div 
                v-for="(module, key) in technicalAnalysis.predictions.M15.modules" 
                :key="key"
                @click.stop.prevent="openModuleInfo(module.module || key, module, 'M15')"
                :class="[
                  'p-4 rounded-lg border cursor-pointer transition-all hover:scale-[1.02] hover:shadow-lg',
                  module.status === 'danger' || module.status === 'extreme_shock' || module.status === 'strong_trend' || module.status === 'shock' ? 'bg-red-500/5 border-red-500/20 hover:border-red-500/40' :
                  module.status === 'caution' || module.status === 'moderately_trending' || module.status === 'high_risk' ? 'bg-yellow-500/5 border-yellow-500/20 hover:border-yellow-500/40' :
                  module.status === 'insufficient_data' ? 'bg-gray-500/5 border-gray-500/20 hover:border-gray-500/40' :
                  'bg-green-500/5 border-green-500/20 hover:border-green-500/40'
                ]"
                title="Klik untuk informasi detail modul"
              >
                <div class="flex items-center justify-between mb-2">
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <h4 class="font-medium text-sm">{{ module.module }}</h4>
                      <!-- Priority Badge for EA Grid -->
                      <span 
                        v-if="['Pattern Recognition', 'market_regime', 'atr_shock'].includes(module.module)"
                        class="px-1.5 py-0.5 text-xs font-bold bg-orange-500/20 text-orange-400 rounded border border-orange-500/30"
                        title="Prioritas tinggi untuk EA Grid"
                      >
                        ⭐
                      </span>
                      <span 
                        v-else-if="['TSM', 'Order Flow Proxy', 'breakout_probability'].includes(module.module)"
                        class="px-1.5 py-0.5 text-xs font-bold bg-blue-500/20 text-blue-400 rounded border border-blue-500/30"
                        title="Pendukung untuk EA Grid"
                      >
                        📊
                      </span>
                    </div>
                    <p class="text-xs text-dark-400 mt-0.5">{{ module.status_label }}</p>
                  </div>
                  <div class="text-right">
                    <div class="text-xs text-dark-500 mb-0.5">Confidence</div>
                    <div class="text-sm font-bold" :class="[
                      module.status === 'danger' || module.status === 'extreme_shock' ? 'text-red-400' :
                      module.status === 'caution' ? 'text-yellow-400' : 
                      module.status === 'insufficient_data' ? 'text-gray-400' : 'text-green-400'
                    ]">
                      {{ (module.confidence ?? 0) }}%
                    </div>
                  </div>
                </div>
                
                <!-- Reasons -->
                <div v-if="module.reasons && module.reasons.length > 0" class="mt-2">
                  <details class="text-xs">
                    <summary class="cursor-pointer text-dark-400 hover:text-dark-300 mb-1">
                      📋 Alasan ({{ module.reasons.length }})
                    </summary>
                    <div class="mt-2 space-y-1.5 pl-2 border-l-2 border-orange-500/30">
                      <div 
                        v-for="(reason, idx) in module.reasons" 
                        :key="idx"
                        class="text-dark-300"
                      >
                        • {{ reason }}
                      </div>
                    </div>
                  </details>
                </div>
              </div>
            </div>
          </details>

          <!-- H1 Modules -->
          <details v-if="technicalAnalysis.predictions.H1?.modules" class="group">
            <summary class="cursor-pointer text-sm font-semibold text-dark-300 mb-4 flex items-center gap-2 hover:text-white">
              <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
              📊 Detail Modul H1 (7 modul)
            </summary>
            <div class="mt-4 space-y-3">
              <div 
                v-for="(module, key) in technicalAnalysis.predictions.H1.modules" 
                :key="key"
                @click.stop.prevent="openModuleInfo(module.module || key, module, 'H1')"
                :class="[
                  'p-4 rounded-lg border cursor-pointer transition-all hover:scale-[1.02] hover:shadow-lg',
                  module.status === 'danger' || module.status === 'extreme_shock' || module.status === 'strong_trend' || module.status === 'shock' ? 'bg-red-500/5 border-red-500/20 hover:border-red-500/40' :
                  module.status === 'caution' || module.status === 'moderately_trending' || module.status === 'high_risk' ? 'bg-yellow-500/5 border-yellow-500/20 hover:border-yellow-500/40' :
                  module.status === 'insufficient_data' ? 'bg-gray-500/5 border-gray-500/20 hover:border-gray-500/40' :
                  'bg-green-500/5 border-green-500/20 hover:border-green-500/40'
                ]"
                title="Klik untuk informasi detail modul"
              >
                <div class="flex items-center justify-between mb-2">
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <h4 class="font-medium text-sm">{{ module.module }}</h4>
                      <span 
                        v-if="['Pattern Recognition', 'market_regime', 'atr_shock'].includes(module.module)"
                        class="px-1.5 py-0.5 text-xs font-bold bg-orange-500/20 text-orange-400 rounded border border-orange-500/30"
                        title="Prioritas tinggi untuk EA Grid"
                      >
                        ⭐
                      </span>
                      <span 
                        v-else-if="['TSM', 'Order Flow Proxy', 'breakout_probability'].includes(module.module)"
                        class="px-1.5 py-0.5 text-xs font-bold bg-blue-500/20 text-blue-400 rounded border border-blue-500/30"
                        title="Pendukung untuk EA Grid"
                      >
                        📊
                      </span>
                    </div>
                    <p class="text-xs text-dark-400 mt-0.5">{{ module.status_label }}</p>
                  </div>
                  <div class="text-right">
                    <div class="text-xs text-dark-500 mb-0.5">Confidence</div>
                    <div class="text-sm font-bold" :class="[
                      module.status === 'danger' || module.status === 'extreme_shock' ? 'text-red-400' :
                      module.status === 'caution' ? 'text-yellow-400' : 
                      module.status === 'insufficient_data' ? 'text-gray-400' : 'text-green-400'
                    ]">
                      {{ (module.confidence ?? 0) }}%
                    </div>
                  </div>
                </div>
                
                <!-- Reasons -->
                <div v-if="module.reasons && module.reasons.length > 0" class="mt-2">
                  <details class="text-xs">
                    <summary class="cursor-pointer text-dark-400 hover:text-dark-300 mb-1">
                      📋 Alasan ({{ module.reasons.length }})
                    </summary>
                    <div class="mt-2 space-y-1.5 pl-2 border-l-2 border-orange-500/30">
                      <div 
                        v-for="(reason, idx) in module.reasons" 
                        :key="idx"
                        class="text-dark-300"
                      >
                        • {{ reason }}
                      </div>
                    </div>
                  </details>
                </div>
              </div>
            </div>
          </details>

          <!-- H4 Modules -->
          <details v-if="technicalAnalysis.predictions.H4?.modules" class="group">
            <summary class="cursor-pointer text-sm font-semibold text-dark-300 mb-4 flex items-center gap-2 hover:text-white">
              <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
              📊 Detail Modul H4 (7 modul)
            </summary>
            <div class="mt-4 space-y-3">
              <div 
                v-for="(module, key) in technicalAnalysis.predictions.H4.modules" 
                :key="key"
                @click.stop.prevent="openModuleInfo(module.module || key, module, 'H4')"
                :class="[
                  'p-4 rounded-lg border cursor-pointer transition-all hover:scale-[1.02] hover:shadow-lg',
                  module.status === 'danger' || module.status === 'extreme_shock' || module.status === 'strong_trend' || module.status === 'shock' ? 'bg-red-500/5 border-red-500/20 hover:border-red-500/40' :
                  module.status === 'caution' || module.status === 'moderately_trending' || module.status === 'high_risk' ? 'bg-yellow-500/5 border-yellow-500/20 hover:border-yellow-500/40' :
                  module.status === 'insufficient_data' ? 'bg-gray-500/5 border-gray-500/20 hover:border-gray-500/40' :
                  'bg-green-500/5 border-green-500/20 hover:border-green-500/40'
                ]"
                title="Klik untuk informasi detail modul"
              >
                <div class="flex items-center justify-between mb-2">
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <h4 class="font-medium text-sm">{{ module.module }}</h4>
                      <span 
                        v-if="['Pattern Recognition', 'market_regime', 'atr_shock'].includes(module.module)"
                        class="px-1.5 py-0.5 text-xs font-bold bg-orange-500/20 text-orange-400 rounded border border-orange-500/30"
                        title="Prioritas tinggi untuk EA Grid"
                      >
                        ⭐
                      </span>
                      <span 
                        v-else-if="['TSM', 'Order Flow Proxy', 'breakout_probability'].includes(module.module)"
                        class="px-1.5 py-0.5 text-xs font-bold bg-blue-500/20 text-blue-400 rounded border border-blue-500/30"
                        title="Pendukung untuk EA Grid"
                      >
                        📊
                      </span>
                    </div>
                    <p class="text-xs text-dark-400 mt-0.5">{{ module.status_label }}</p>
                  </div>
                  <div class="text-right">
                    <div class="text-xs text-dark-500 mb-0.5">Confidence</div>
                    <div class="text-sm font-bold" :class="[
                      module.status === 'danger' || module.status === 'extreme_shock' ? 'text-red-400' :
                      module.status === 'caution' ? 'text-yellow-400' : 
                      module.status === 'insufficient_data' ? 'text-gray-400' : 'text-green-400'
                    ]">
                      {{ (module.confidence ?? 0) }}%
                    </div>
                  </div>
                </div>
                
                <!-- Reasons -->
                <div v-if="module.reasons && module.reasons.length > 0" class="mt-2">
                  <details class="text-xs">
                    <summary class="cursor-pointer text-dark-400 hover:text-dark-300 mb-1">
                      📋 Alasan ({{ module.reasons.length }})
                    </summary>
                    <div class="mt-2 space-y-1.5 pl-2 border-l-2 border-orange-500/30">
                      <div 
                        v-for="(reason, idx) in module.reasons" 
                        :key="idx"
                        class="text-dark-300"
                      >
                        • {{ reason }}
                      </div>
                    </div>
                  </details>
                </div>
              </div>
            </div>
          </details>
        </div>

        <!-- Empty State -->
        <div v-else-if="!loadingTechnical && !technicalAnalysis?.combined_overall && !technicalError" class="text-center py-8 text-dark-400">
          <span class="text-4xl mb-2 block">📊</span>
          <p>Tidak ada analisis teknikal</p>
          <p class="text-sm mt-1">Klik "Analyze Technical" untuk memulai analisis</p>
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

  <!-- Module Info Modal -->
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="showModuleModal && selectedModule"
        class="fixed inset-0 z-50 overflow-y-auto"
        @click.self="showModuleModal = false"
      >
        <div class="fixed inset-0 bg-dark-950/90 backdrop-blur-sm"></div>
        <div class="relative min-h-screen flex items-center justify-center p-4">
          <div class="relative w-full max-w-3xl card p-6 bg-dark-900" @click.stop>
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-xl font-bold text-dark-100">{{ selectedModule.name }}</h2>
              <button
                @click="showModuleModal = false"
                class="p-2 rounded-lg hover:bg-dark-800 text-dark-400 hover:text-dark-200 transition-colors"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <div class="space-y-4">
              <!-- Timeframe Badge -->
              <div class="flex items-center gap-2 mb-4">
                <span class="px-3 py-1 bg-primary-500/20 text-primary-400 rounded-lg text-sm font-medium">
                  Timeframe: {{ selectedModule.timeframe }}
                </span>
                <span v-if="selectedModule.priority === 'Prioritas Tinggi'" class="px-3 py-1 bg-orange-500/20 text-orange-400 rounded-lg text-sm font-medium">
                  ⭐ Prioritas Tinggi
                </span>
                <span v-else-if="selectedModule.priority === 'Pendukung'" class="px-3 py-1 bg-blue-500/20 text-blue-400 rounded-lg text-sm font-medium">
                  📊 Pendukung
                </span>
              </div>

              <!-- Description -->
              <div>
                <h3 class="text-sm font-semibold text-dark-300 mb-2">📖 Deskripsi</h3>
                <p class="text-sm text-dark-200">{{ selectedModule.description }}</p>
              </div>

              <!-- Function -->
              <div>
                <h3 class="text-sm font-semibold text-dark-300 mb-2">⚙️ Fungsi</h3>
                <p class="text-sm text-dark-200">{{ selectedModule.function }}</p>
              </div>

              <!-- How It Works -->
              <div>
                <h3 class="text-sm font-semibold text-dark-300 mb-2">🔧 Cara Kerja</h3>
                <p class="text-sm text-dark-200">{{ selectedModule.howItWorks }}</p>
              </div>

              <!-- Current Status & Confidence -->
              <div class="p-4 bg-dark-800/50 rounded-lg border border-dark-700/50">
                <div class="grid grid-cols-2 gap-4 mb-3">
                  <div>
                    <div class="text-xs text-dark-400 mb-1">Status Saat Ini</div>
                    <div class="text-lg font-bold text-dark-100">{{ selectedModule.currentStatusLabel }}</div>
                    <div class="text-xs text-dark-500 mt-1">{{ selectedModule.category }}</div>
                  </div>
                  <div>
                    <div class="text-xs text-dark-400 mb-1">Confidence</div>
                    <div class="text-lg font-bold" :class="[
                      selectedModule.currentConfidence >= 70 ? 'text-green-400' :
                      selectedModule.currentConfidence >= 50 ? 'text-yellow-400' : 
                      selectedModule.currentConfidence > 0 ? 'text-red-400' : 'text-gray-400'
                    ]">
                      {{ selectedModule.currentConfidence }}%
                    </div>
                    <div class="text-xs text-dark-500 mt-1">
                      <span v-if="selectedModule.currentConfidence === 0" class="text-gray-400">
                        Data tidak cukup
                      </span>
                      <span v-else-if="selectedModule.currentConfidence < 50" class="text-yellow-400">
                        Confidence rendah
                      </span>
                      <span v-else class="text-green-400">
                        Confidence baik
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Interpretation -->
              <div class="p-4 rounded-lg border" :class="[
                selectedModule.currentStatus === 'danger' || selectedModule.currentStatus === 'extreme_shock' || 
                selectedModule.currentStatus === 'strong_trend' || selectedModule.currentStatus === 'shock' || 
                selectedModule.currentStatus === 'trending' || selectedModule.currentStatus === 'volatile' ||
                selectedModule.currentStatus === 'reversal_pattern' || selectedModule.currentStatus === 'high_breakout_risk' ||
                selectedModule.currentStatus === 'high_liquidity_risk' || selectedModule.currentStatus === 'strong_order_flow'
                  ? 'bg-red-500/10 border-red-500/30' :
                selectedModule.currentStatus === 'caution' || selectedModule.currentStatus === 'moderately_trending' ||
                selectedModule.currentStatus === 'moderate_breakout_risk' || selectedModule.currentStatus === 'moderate_liquidity_risk' ||
                selectedModule.currentStatus === 'moderate_order_flow' || selectedModule.currentStatus === 'continuation_pattern'
                  ? 'bg-yellow-500/10 border-yellow-500/30' :
                'bg-green-500/10 border-green-500/30'
              ]">
                <div class="text-sm font-semibold mb-2" :class="[
                  selectedModule.currentStatus === 'danger' || selectedModule.currentStatus === 'extreme_shock' || 
                  selectedModule.currentStatus === 'strong_trend' || selectedModule.currentStatus === 'shock' || 
                  selectedModule.currentStatus === 'trending' || selectedModule.currentStatus === 'volatile' ||
                  selectedModule.currentStatus === 'reversal_pattern' || selectedModule.currentStatus === 'high_breakout_risk' ||
                  selectedModule.currentStatus === 'high_liquidity_risk' || selectedModule.currentStatus === 'strong_order_flow'
                    ? 'text-red-400' :
                  selectedModule.currentStatus === 'caution' || selectedModule.currentStatus === 'moderately_trending' ||
                  selectedModule.currentStatus === 'moderate_breakout_risk' || selectedModule.currentStatus === 'moderate_liquidity_risk' ||
                  selectedModule.currentStatus === 'moderate_order_flow' || selectedModule.currentStatus === 'continuation_pattern'
                    ? 'text-yellow-400' : 'text-green-400'
                ]">
                  💡 Interpretasi
                </div>
                <p class="text-sm text-dark-200">{{ selectedModule.interpretation }}</p>
              </div>

              <!-- Safe Ranges -->
              <div>
                <h3 class="text-sm font-semibold text-green-400 mb-3">🟢 Kondisi Aman</h3>
                <div class="space-y-2">
                  <div 
                    v-for="(range, idx) in selectedModule.safeRanges" 
                    :key="idx"
                    class="p-3 bg-green-500/5 border border-green-500/20 rounded-lg"
                  >
                    <div class="flex items-center justify-between mb-1">
                      <span class="text-sm font-medium text-green-300">{{ range.range }}</span>
                      <span class="text-xs text-green-400 px-2 py-0.5 bg-green-500/20 rounded">{{ range.score }}</span>
                    </div>
                    <p class="text-xs text-dark-300">{{ range.description }}</p>
                  </div>
                </div>
              </div>

              <!-- Danger Ranges -->
              <div>
                <h3 class="text-sm font-semibold text-red-400 mb-3">🔴 Kondisi Berbahaya</h3>
                <div class="space-y-2">
                  <div 
                    v-for="(range, idx) in selectedModule.dangerRanges" 
                    :key="idx"
                    class="p-3 bg-red-500/5 border border-red-500/20 rounded-lg"
                  >
                    <div class="flex items-center justify-between mb-1">
                      <span class="text-sm font-medium text-red-300">{{ range.range }}</span>
                      <span class="text-xs text-red-400 px-2 py-0.5 bg-red-500/20 rounded">{{ range.score }}</span>
                    </div>
                    <p class="text-xs text-dark-300">{{ range.description }}</p>
                  </div>
                </div>
              </div>

              <!-- Close Button -->
              <div class="flex justify-end pt-4">
                <button
                  @click="showModuleModal = false"
                  class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors"
                >
                  Tutup
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Ensure TradingView widget fills container */
:deep(#tradingview_chart) {
  width: 100% !important;
  height: 100% !important;
  min-height: 400px !important;
  display: block !important;
}

:deep(.tradingview-widget-container) {
  width: 100% !important;
  height: 100% !important;
  min-height: 400px !important;
  display: block !important;
}

:deep(iframe) {
  width: 100% !important;
  height: 100% !important;
  min-height: 400px !important;
  display: block !important;
  border: none !important;
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

