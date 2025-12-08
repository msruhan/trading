<!--
  ChartAdvanced.vue
  Full-featured chart berbasis Lightweight Charts + Konva overlay
  Fitur:
   - Candlestick chart (lightweight-charts)
   - Volume (histogram)
   - SMA, EMA, RSI, MACD (technicalindicators)
   - Drawing tools: Trend line, Horizontal, Fibonacci, Rectangle (Konva)
   - Toolbar mirip TradingView
   - Multi-symbol: crypto via Binance API; forex via TwelveData API
   - Responsive & auto-resize
-->
<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick, computed } from 'vue'
import axios from 'axios'
import Konva from 'konva'
import { createChart, CrosshairMode, CandlestickSeries, LineSeries, HistogramSeries } from 'lightweight-charts'
import { SMA, EMA, RSI as RSIcalc, MACD as MACDcalc, BollingerBands } from 'technicalindicators'
import {
  ChartBarSquareIcon,
  ArrowsPointingOutIcon,
  ArrowsPointingInIcon,
  ArrowUturnLeftIcon,
  TrashIcon,
  CursorArrowRaysIcon,
  MinusIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'

// TwelveData API Key
const twelveDataApiKey = '73667996be8143edabd59eb7c863c3fa'

// Refs
const chartContainer = ref(null)
const chartWrap = ref(null)
const overlayContainer = ref(null)
const rsiPane = ref(null)
const macdPane = ref(null)

// Symbol categories
const symbolCategories = {
  crypto: [
    { label: 'BTC/USDT', value: 'BTCUSDT', type: 'crypto' },
    { label: 'ETH/USDT', value: 'ETHUSDT', type: 'crypto' },
    { label: 'BNB/USDT', value: 'BNBUSDT', type: 'crypto' },
    { label: 'XRP/USDT', value: 'XRPUSDT', type: 'crypto' },
    { label: 'SOL/USDT', value: 'SOLUSDT', type: 'crypto' },
    { label: 'ADA/USDT', value: 'ADAUSDT', type: 'crypto' },
    { label: 'DOGE/USDT', value: 'DOGEUSDT', type: 'crypto' },
    { label: 'DOT/USDT', value: 'DOTUSDT', type: 'crypto' },
  ],
  forex: [
    { label: 'EUR/USD', value: 'EUR/USD', type: 'forex' },
    { label: 'GBP/USD', value: 'GBP/USD', type: 'forex' },
    { label: 'USD/JPY', value: 'USD/JPY', type: 'forex' },
    { label: 'USD/CHF', value: 'USD/CHF', type: 'forex' },
    { label: 'AUD/USD', value: 'AUD/USD', type: 'forex' },
    { label: 'USD/CAD', value: 'USD/CAD', type: 'forex' },
    { label: 'NZD/USD', value: 'NZD/USD', type: 'forex' },
    { label: 'EUR/GBP', value: 'EUR/GBP', type: 'forex' },
  ],
  commodities: [
    { label: 'XAU/USD (Gold)', value: 'XAU/USD', type: 'forex' },
    { label: 'XAG/USD (Silver)', value: 'XAG/USD', type: 'forex' },
  ],
}

// UI State
const selectedCategory = ref('crypto')
const symbolInput = ref('BTCUSDT')
const customSymbol = ref('')
const timeframe = ref('1h')
const isFullscreen = ref(false)
const loading = ref(false)
const errorMsg = ref('')

// Current symbol info
const currentSymbolInfo = computed(() => {
  const allSymbols = [...symbolCategories.crypto, ...symbolCategories.forex, ...symbolCategories.commodities]
  return allSymbols.find(s => s.value === symbolInput.value) || { label: symbolInput.value, type: 'crypto' }
})

// Chart objects
let chart = null
let candleSeries = null
let volumeSeries = null
let smaSeries = null
let emaSeries = null
let bbUpperSeries = null
let bbLowerSeries = null
let rsiChart = null
let rsiSeries = null
let macdChart = null
let macdHist = null
let macdLine = null
let macdSignal = null

// Raw candle data for indicators
let rawCandleData = []

// Konva drawing
let stage = null
let layer = null
const currentTool = ref('cursor')
let isDrawing = false
let currentShape = null
const drawings = []
let startPoint = null

// Indicator toggles
const showRSI = ref(false)
const showMACD = ref(false)
const showSMA = ref(true)
const showEMA = ref(false)
const showBB = ref(false)
const showVolume = ref(true)

// SMA/EMA periods
const smaPeriod = ref(20)
const emaPeriod = ref(9)

// Timeframes
const timeframes = [
  { value: '1m', label: '1m' },
  { value: '5m', label: '5m' },
  { value: '15m', label: '15m' },
  { value: '30m', label: '30m' },
  { value: '1h', label: '1H' },
  { value: '4h', label: '4H' },
  { value: '1d', label: '1D' },
  { value: '1w', label: '1W' },
]

// Drawing tools
const tools = [
  { id: 'cursor', label: 'Cursor', icon: 'cursor' },
  { id: 'trend', label: 'Trend Line', icon: 'trend' },
  { id: 'hline', label: 'H-Line', icon: 'hline' },
  { id: 'vline', label: 'V-Line', icon: 'vline' },
  { id: 'fibo', label: 'Fibonacci', icon: 'fibo' },
  { id: 'rect', label: 'Rectangle', icon: 'rect' },
]

// Helper functions
function btnClass(tool) {
  return currentTool.value === tool
    ? 'bg-primary-500 text-white border-primary-500'
    : 'bg-dark-800 text-dark-300 border-dark-700 hover:bg-dark-700 hover:text-dark-200'
}

// Initialization
onMounted(async () => {
  await nextTick()
  
  // Wait for DOM to be ready
  if (!chartContainer.value || !chartWrap.value) {
    console.error('Chart container not ready')
    return
  }
  
  try {
    createMainChart()
    if (overlayContainer.value) {
      createOverlay()
    }
    await loadCandles(symbolInput.value, timeframe.value)
  } catch (err) {
    console.error('Chart initialization error:', err)
    errorMsg.value = 'Failed to initialize chart: ' + err.message
  }
  
  window.addEventListener('resize', resize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', resize)
  if (stage) stage.destroy()
  if (chart) chart.remove()
  if (rsiChart) rsiChart.remove()
  if (macdChart) macdChart.remove()
})

watch(timeframe, () => reloadData())

function resize() {
  if (!chartWrap.value) return
  const w = chartWrap.value.clientWidth
  const h = chartWrap.value.clientHeight || 600
  if (chart) {
    chart.applyOptions({ width: w })
    chart.resize(w, h)
  }
  if (stage) {
    stage.width(w)
    stage.height(h)
  }
  // Resize indicator panes
  if (rsiChart && rsiPane.value) {
    rsiChart.applyOptions({ width: rsiPane.value.clientWidth })
  }
  if (macdChart && macdPane.value) {
    macdChart.applyOptions({ width: macdPane.value.clientWidth })
  }
}

function createMainChart() {
  if (!chartContainer.value) {
    console.error('chartContainer is null')
    return
  }
  
  const width = chartWrap.value?.clientWidth || 1000
  const height = chartWrap.value?.clientHeight || 600

  chart = createChart(chartContainer.value, {
    width,
    height,
    layout: {
      background: { type: 'solid', color: '#0d1117' },
      textColor: '#9ca3af',
    },
    grid: {
      vertLines: { color: '#1f2937' },
      horzLines: { color: '#1f2937' },
    },
    crosshair: {
      mode: CrosshairMode.Normal,
      vertLine: { color: '#6366f1', width: 1, style: 2 },
      horzLine: { color: '#6366f1', width: 1, style: 2 },
    },
    rightPriceScale: {
      borderColor: '#374151',
      scaleMargins: { top: 0.1, bottom: 0.2 },
    },
    timeScale: {
      borderColor: '#374151',
      timeVisible: true,
      secondsVisible: false,
    },
    handleScroll: { vertTouchDrag: false },
  })

  // Candlestick series (v5 API)
  candleSeries = chart.addSeries(CandlestickSeries, {
    upColor: '#22c55e',
    downColor: '#ef4444',
    borderUpColor: '#22c55e',
    borderDownColor: '#ef4444',
    wickUpColor: '#22c55e',
    wickDownColor: '#ef4444',
  })

  // Volume series (v5 API)
  volumeSeries = chart.addSeries(HistogramSeries, {
    priceFormat: { type: 'volume' },
    priceScaleId: 'volume',
  })

  chart.priceScale('volume').applyOptions({
    scaleMargins: { top: 0.85, bottom: 0 },
  })

  // SMA series (v5 API)
  smaSeries = chart.addSeries(LineSeries, {
    color: '#f59e0b',
    lineWidth: 2,
    title: 'SMA',
  })

  // EMA series (v5 API)
  emaSeries = chart.addSeries(LineSeries, {
    color: '#8b5cf6',
    lineWidth: 2,
    title: 'EMA',
  })

  // Bollinger Bands (v5 API)
  bbUpperSeries = chart.addSeries(LineSeries, {
    color: '#60a5fa',
    lineWidth: 1,
    lineStyle: 2,
    title: 'BB Upper',
  })

  bbLowerSeries = chart.addSeries(LineSeries, {
    color: '#60a5fa',
    lineWidth: 1,
    lineStyle: 2,
    title: 'BB Lower',
  })
}

function createOverlay() {
  if (!overlayContainer.value) {
    console.error('overlayContainer is null')
    return
  }
  
  const width = chartWrap.value?.clientWidth || 1000
  const height = chartWrap.value?.clientHeight || 600

  stage = new Konva.Stage({
    container: overlayContainer.value,
    width,
    height,
  })

  layer = new Konva.Layer()
  stage.add(layer)

  // Pointer events
  overlayContainer.value.addEventListener('pointerdown', onPointerDown)
  overlayContainer.value.addEventListener('pointermove', onPointerMove)
  overlayContainer.value.addEventListener('pointerup', onPointerUp)
}

function setTool(t) {
  currentTool.value = t
  // Change cursor style
  if (overlayContainer.value) {
    overlayContainer.value.style.cursor = t === 'cursor' ? 'default' : 'crosshair'
  }
}

function undoDrawing() {
  if (!layer) return
  const last = drawings.pop()
  if (last) {
    if (Array.isArray(last)) {
      last.forEach(item => item.destroy())
    } else {
      last.destroy()
    }
    layer.draw()
  }
}

function clearDrawings() {
  if (!layer) return
  drawings.forEach(d => {
    if (Array.isArray(d)) {
      d.forEach(item => item.destroy())
    } else {
      d.destroy()
    }
  })
  drawings.length = 0
  layer.draw()
}

function getPointerPos(evt) {
  const rect = overlayContainer.value.getBoundingClientRect()
  return {
    x: evt.clientX - rect.left,
    y: evt.clientY - rect.top,
  }
}

function onPointerDown(evt) {
  if (currentTool.value === 'cursor') return
  if (!layer || !stage) return
  isDrawing = true
  startPoint = getPointerPos(evt)

  if (currentTool.value === 'trend') {
    currentShape = new Konva.Line({
      points: [startPoint.x, startPoint.y],
      stroke: '#60a5fa',
      strokeWidth: 2,
    })
    layer.add(currentShape)
  } else if (currentTool.value === 'hline') {
    currentShape = new Konva.Line({
      points: [0, startPoint.y, stage.width(), startPoint.y],
      stroke: '#f87171',
      strokeWidth: 1,
      dash: [6, 4],
    })
    layer.add(currentShape)
    drawings.push(currentShape)
    // Add price label
    const price = yToPrice(startPoint.y)
    const label = new Konva.Text({
      x: stage.width() - 80,
      y: startPoint.y - 12,
      text: price ? price.toFixed(5) : '',
      fontSize: 11,
      fill: '#f87171',
      fontFamily: 'monospace',
    })
    layer.add(label)
    drawings.push(label)
    currentShape = null
    isDrawing = false
    layer.draw()
  } else if (currentTool.value === 'vline') {
    currentShape = new Konva.Line({
      points: [startPoint.x, 0, startPoint.x, stage.height()],
      stroke: '#a78bfa',
      strokeWidth: 1,
      dash: [6, 4],
    })
    layer.add(currentShape)
    drawings.push(currentShape)
    currentShape = null
    isDrawing = false
    layer.draw()
  } else if (currentTool.value === 'fibo') {
    currentShape = new Konva.Line({
      points: [startPoint.x, startPoint.y],
      stroke: '#fbbf24',
      strokeWidth: 2,
      dash: [4, 2],
    })
    layer.add(currentShape)
  } else if (currentTool.value === 'rect') {
    currentShape = new Konva.Rect({
      x: startPoint.x,
      y: startPoint.y,
      width: 0,
      height: 0,
      stroke: '#10b981',
      strokeWidth: 2,
      fill: 'rgba(16, 185, 129, 0.1)',
    })
    layer.add(currentShape)
  }
}

function onPointerMove(evt) {
  if (!isDrawing || !currentShape || !layer) return
  const pos = getPointerPos(evt)

  if (currentTool.value === 'trend' || currentTool.value === 'fibo') {
    const pts = [startPoint.x, startPoint.y, pos.x, pos.y]
    currentShape.points(pts)
    layer.batchDraw()
  } else if (currentTool.value === 'rect') {
    const width = pos.x - startPoint.x
    const height = pos.y - startPoint.y
    currentShape.width(width)
    currentShape.height(height)
    layer.batchDraw()
  }
}

function onPointerUp(evt) {
  if (!isDrawing) return
  isDrawing = false
  if (!currentShape || !layer) return

  const endPoint = getPointerPos(evt)

  if (currentTool.value === 'trend') {
    drawings.push(currentShape)
    currentShape = null
  } else if (currentTool.value === 'fibo') {
    // Draw Fibonacci levels
    const y1 = startPoint.y
    const y2 = endPoint.y
    drawFibo(y1, y2)
    currentShape.destroy()
    currentShape = null
  } else if (currentTool.value === 'rect') {
    drawings.push(currentShape)
    currentShape = null
  }

  layer.draw()
}

function yToPrice(y) {
  if (!rawCandleData || rawCandleData.length === 0 || !stage) return 0
  const prices = rawCandleData.flatMap(c => [c.high, c.low])
  const maxP = Math.max(...prices)
  const minP = Math.min(...prices)
  const chartHeight = stage.height()
  const ratio = y / chartHeight
  return maxP - ratio * (maxP - minP)
}

function priceToY(price) {
  if (!rawCandleData || rawCandleData.length === 0 || !stage) return 0
  const prices = rawCandleData.flatMap(c => [c.high, c.low])
  const maxP = Math.max(...prices)
  const minP = Math.min(...prices)
  const chartHeight = stage.height()
  const ratio = (maxP - price) / (maxP - minP)
  return ratio * chartHeight
}

function drawFibo(y1, y2) {
  if (!layer || !stage) return
  
  const price1 = yToPrice(y1)
  const price2 = yToPrice(y2)
  const high = Math.max(price1, price2)
  const low = Math.min(price1, price2)

  const levels = [
    { level: 0, color: '#ef4444' },
    { level: 0.236, color: '#f97316' },
    { level: 0.382, color: '#eab308' },
    { level: 0.5, color: '#84cc16' },
    { level: 0.618, color: '#22c55e' },
    { level: 0.786, color: '#14b8a6' },
    { level: 1, color: '#3b82f6' },
  ]

  const fiboGroup = []

  levels.forEach(({ level, color }) => {
    const price = high - (high - low) * level
    const y = priceToY(price)

    const line = new Konva.Line({
      points: [0, y, stage.width(), y],
      stroke: color,
      strokeWidth: 1,
      opacity: 0.7,
    })

    const label = new Konva.Text({
      x: 6,
      y: y - 14,
      text: `${(level * 100).toFixed(1)}% (${price.toFixed(currentSymbolInfo.value.type === 'crypto' ? 2 : 5)})`,
      fontSize: 11,
      fill: color,
      fontFamily: 'monospace',
    })

    layer.add(line)
    layer.add(label)
    fiboGroup.push(line, label)
  })

  drawings.push(fiboGroup)
  layer.draw()
}

// Data loading
async function loadCandles(symbol, tf) {
  loading.value = true
  errorMsg.value = ''

  try {
    const symbolInfo = currentSymbolInfo.value
    let klines = []

    if (symbolInfo.type === 'crypto') {
      // Binance API
      const intervalMap = {
        '1m': '1m', '5m': '5m', '15m': '15m', '30m': '30m',
        '1h': '1h', '4h': '4h', '1d': '1d', '1w': '1w',
      }
      const res = await axios.get('https://api.binance.com/api/v3/klines', {
        params: {
          symbol: symbol,
          interval: intervalMap[tf] || '1h',
          limit: 500,
        },
      })
      klines = res.data.map(d => ({
        time: Math.floor(d[0] / 1000),
        open: parseFloat(d[1]),
        high: parseFloat(d[2]),
        low: parseFloat(d[3]),
        close: parseFloat(d[4]),
        volume: parseFloat(d[5]),
      }))
    } else {
      // TwelveData API for Forex
      const intervalMap = {
        '1m': '1min', '5m': '5min', '15m': '15min', '30m': '30min',
        '1h': '1h', '4h': '4h', '1d': '1day', '1w': '1week',
      }
      const res = await axios.get('https://api.twelvedata.com/time_series', {
        params: {
          symbol: symbol,
          interval: intervalMap[tf] || '1h',
          outputsize: 500,
          apikey: twelveDataApiKey,
        },
      })

      if (res.data.status === 'error') {
        throw new Error(res.data.message || 'TwelveData API error')
      }

      klines = res.data.values
        .map(v => ({
          time: Math.floor(new Date(v.datetime).getTime() / 1000),
          open: parseFloat(v.open),
          high: parseFloat(v.high),
          low: parseFloat(v.low),
          close: parseFloat(v.close),
          volume: parseFloat(v.volume || 0),
        }))
        .reverse() // TwelveData returns newest first
    }

    // Store raw data
    rawCandleData = klines

    // Check if chart series exist
    if (!candleSeries || !volumeSeries) {
      console.error('Chart series not initialized')
      errorMsg.value = 'Chart not initialized properly'
      return
    }

    // Set candle data
    const candleData = klines.map(k => ({
      time: k.time,
      open: k.open,
      high: k.high,
      low: k.low,
      close: k.close,
    }))
    candleSeries.setData(candleData)

    // Set volume data
    if (showVolume.value) {
      const volumeData = klines.map(k => ({
        time: k.time,
        value: k.volume,
        color: k.close >= k.open ? 'rgba(34, 197, 94, 0.5)' : 'rgba(239, 68, 68, 0.5)',
      }))
      volumeSeries.setData(volumeData)
    } else {
      volumeSeries.setData([])
    }

    // Update indicators
    updateIndicators()

    // Fit content
    chart.timeScale().fitContent()

  } catch (err) {
    console.error('loadCandles error:', err)
    errorMsg.value = err.message || 'Failed to load data'
  } finally {
    loading.value = false
  }
}

function updateIndicators() {
  if (!rawCandleData || rawCandleData.length === 0) return
  if (!smaSeries || !emaSeries || !bbUpperSeries || !bbLowerSeries) return

  const closes = rawCandleData.map(c => c.close)

  // SMA
  if (showSMA.value && smaSeries) {
    try {
      const sma = SMA.calculate({ period: smaPeriod.value, values: closes })
      const smaData = sma.map((v, i) => ({
        time: rawCandleData[i + smaPeriod.value - 1].time,
        value: v,
      }))
      smaSeries.setData(smaData)
    } catch (e) {
      console.warn('SMA error:', e)
    }
  } else if (smaSeries) {
    smaSeries.setData([])
  }

  // EMA
  if (showEMA.value && emaSeries) {
    try {
      const ema = EMA.calculate({ period: emaPeriod.value, values: closes })
      const emaData = ema.map((v, i) => ({
        time: rawCandleData[i + emaPeriod.value - 1].time,
        value: v,
      }))
      emaSeries.setData(emaData)
    } catch (e) {
      console.warn('EMA error:', e)
    }
  } else if (emaSeries) {
    emaSeries.setData([])
  }

  // Bollinger Bands
  if (showBB.value && bbUpperSeries && bbLowerSeries) {
    try {
      const bb = BollingerBands.calculate({ period: 20, stdDev: 2, values: closes })
      const upperData = bb.map((v, i) => ({
        time: rawCandleData[i + 19].time,
        value: v.upper,
      }))
      const lowerData = bb.map((v, i) => ({
        time: rawCandleData[i + 19].time,
        value: v.lower,
      }))
      bbUpperSeries.setData(upperData)
      bbLowerSeries.setData(lowerData)
    } catch (e) {
      console.warn('BB error:', e)
    }
  } else if (bbUpperSeries && bbLowerSeries) {
    bbUpperSeries.setData([])
    bbLowerSeries.setData([])
  }

  // RSI
  if (showRSI.value) {
    computeRSI()
  } else if (rsiChart) {
    rsiChart.remove()
    rsiChart = null
    rsiSeries = null
  }

  // MACD
  if (showMACD.value) {
    computeMACD()
  } else if (macdChart) {
    macdChart.remove()
    macdChart = null
    macdHist = null
    macdLine = null
    macdSignal = null
  }
}

function computeRSI() {
  if (!rsiPane.value || !rawCandleData.length) return

  const closes = rawCandleData.map(c => c.close)
  const rsiValues = RSIcalc.calculate({ period: 14, values: closes })

  if (!rsiChart) {
    rsiChart = createChart(rsiPane.value, {
      width: rsiPane.value.clientWidth,
      height: 120,
      layout: {
        background: { type: 'solid', color: '#111827' },
        textColor: '#9ca3af',
      },
      grid: {
        vertLines: { visible: false },
        horzLines: { color: '#1f2937' },
      },
      rightPriceScale: { borderColor: '#374151' },
      timeScale: { visible: false },
    })
    rsiSeries = rsiChart.addSeries(LineSeries, { color: '#a78bfa', lineWidth: 2 })
  }

  const rsiData = rsiValues.map((v, i) => ({
    time: rawCandleData[i + 14].time,
    value: v,
  }))
  rsiSeries.setData(rsiData)
}

function computeMACD() {
  if (!macdPane.value || !rawCandleData.length) return

  const closes = rawCandleData.map(c => c.close)
  const macdValues = MACDcalc.calculate({
    values: closes,
    fastPeriod: 12,
    slowPeriod: 26,
    signalPeriod: 9,
    SimpleMAOscillator: false,
    SimpleMASignal: false,
  })

  if (!macdChart) {
    macdChart = createChart(macdPane.value, {
      width: macdPane.value.clientWidth,
      height: 140,
      layout: {
        background: { type: 'solid', color: '#111827' },
        textColor: '#9ca3af',
      },
      grid: {
        vertLines: { visible: false },
        horzLines: { color: '#1f2937' },
      },
      rightPriceScale: { borderColor: '#374151' },
      timeScale: { visible: false },
    })
    macdHist = macdChart.addSeries(HistogramSeries, { color: '#60a5fa' })
    macdLine = macdChart.addSeries(LineSeries, { color: '#ef4444', lineWidth: 1 })
    macdSignal = macdChart.addSeries(LineSeries, { color: '#10b981', lineWidth: 1 })
  }

  const startIndex = 26
  const histData = macdValues.map((v, i) => ({
    time: rawCandleData[i + startIndex]?.time,
    value: v.histogram,
    color: v.histogram >= 0 ? 'rgba(34, 197, 94, 0.7)' : 'rgba(239, 68, 68, 0.7)',
  })).filter(d => d.time)

  const lineData = macdValues.map((v, i) => ({
    time: rawCandleData[i + startIndex]?.time,
    value: v.MACD,
  })).filter(d => d.time)

  const signalData = macdValues.map((v, i) => ({
    time: rawCandleData[i + startIndex]?.time,
    value: v.signal,
  })).filter(d => d.time)

  macdHist.setData(histData)
  macdLine.setData(lineData)
  macdSignal.setData(signalData)
}

async function reloadData() {
  await loadCandles(symbolInput.value, timeframe.value)
}

function changeSymbol(symbol) {
  symbolInput.value = symbol
  loadCandles(symbol, timeframe.value)
  clearDrawings()
}

function applyCustomSymbol() {
  if (customSymbol.value.trim()) {
    symbolInput.value = customSymbol.value.trim().toUpperCase()
    loadCandles(symbolInput.value, timeframe.value)
    clearDrawings()
  }
}

function toggleFullscreen() {
  isFullscreen.value = !isFullscreen.value
  nextTick(() => resize())
}

// Zoom controls
function zoomIn() {
  if (chart) {
    const timeScale = chart.timeScale()
    const range = timeScale.getVisibleLogicalRange()
    if (range) {
      const newRange = {
        from: range.from + (range.to - range.from) * 0.1,
        to: range.to - (range.to - range.from) * 0.1,
      }
      timeScale.setVisibleLogicalRange(newRange)
    }
  }
}

function zoomOut() {
  if (chart) {
    const timeScale = chart.timeScale()
    const range = timeScale.getVisibleLogicalRange()
    if (range) {
      const newRange = {
        from: range.from - (range.to - range.from) * 0.1,
        to: range.to + (range.to - range.from) * 0.1,
      }
      timeScale.setVisibleLogicalRange(newRange)
    }
  }
}

// Watch indicator toggles
watch([showSMA, showEMA, showBB, showRSI, showMACD, showVolume, smaPeriod, emaPeriod], () => {
  updateIndicators()
  if (volumeSeries) {
    if (showVolume.value && rawCandleData.length) {
      const volumeData = rawCandleData.map(k => ({
        time: k.time,
        value: k.volume,
        color: k.close >= k.open ? 'rgba(34, 197, 94, 0.5)' : 'rgba(239, 68, 68, 0.5)',
      }))
      volumeSeries.setData(volumeData)
    } else {
      volumeSeries.setData([])
    }
  }
})
</script>

<template>
  <div :class="['min-h-screen', isFullscreen ? 'fixed inset-0 z-50 bg-dark-950 p-4' : '']">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-4" v-if="!isFullscreen">
      <div>
        <h1 class="text-2xl font-bold text-dark-100 flex items-center gap-2">
          <ChartBarSquareIcon class="w-7 h-7 text-accent-500" />
          Chart Advanced
        </h1>
        <p class="text-dark-400 mt-1">Lightweight Charts + Custom Drawing Tools + Technical Indicators</p>
      </div>
    </div>

    <!-- Main Toolbar -->
    <div :class="[
      'bg-dark-900 border border-dark-800 rounded-lg p-3 mb-4',
      isFullscreen ? '' : ''
    ]">
      <div class="flex flex-wrap items-center gap-3">
        <!-- Category Tabs -->
        <div class="flex items-center gap-1 bg-dark-800 rounded-lg p-1">
          <button
            v-for="(symbols, category) in symbolCategories"
            :key="category"
            @click="selectedCategory = category"
            :class="[
              'px-3 py-1.5 text-sm font-medium rounded-md transition-colors capitalize',
              selectedCategory === category
                ? 'bg-accent-500 text-white'
                : 'text-dark-400 hover:text-dark-200 hover:bg-dark-700'
            ]"
          >
            {{ category }}
          </button>
        </div>

        <!-- Symbol Dropdown -->
        <select
          v-model="symbolInput"
          @change="changeSymbol(symbolInput)"
          class="input text-sm bg-dark-800 border-dark-700 min-w-[160px]"
        >
          <option v-for="s in symbolCategories[selectedCategory]" :key="s.value" :value="s.value">
            {{ s.label }}
          </option>
        </select>

        <!-- Custom Symbol -->
        <input
          v-model="customSymbol"
          @keyup.enter="applyCustomSymbol"
          placeholder="Custom symbol..."
          class="input text-sm bg-dark-800 border-dark-700 w-32"
        />

        <!-- Timeframe -->
        <div class="flex items-center gap-1 bg-dark-800 rounded-lg p-1">
          <button
            v-for="tf in timeframes"
            :key="tf.value"
            @click="timeframe = tf.value"
            :class="[
              'px-2 py-1 text-xs font-medium rounded transition-colors',
              timeframe === tf.value
                ? 'bg-primary-500 text-white'
                : 'text-dark-400 hover:text-dark-200 hover:bg-dark-700'
            ]"
          >
            {{ tf.label }}
          </button>
        </div>

        <!-- Spacer -->
        <div class="flex-1"></div>

        <!-- Zoom Controls -->
        <div class="flex items-center gap-1">
          <button @click="zoomOut" class="p-2 rounded-lg bg-dark-800 text-dark-400 hover:text-dark-200 hover:bg-dark-700 transition-colors">
            <MinusIcon class="w-4 h-4" />
          </button>
          <button @click="zoomIn" class="p-2 rounded-lg bg-dark-800 text-dark-400 hover:text-dark-200 hover:bg-dark-700 transition-colors">
            <PlusIcon class="w-4 h-4" />
          </button>
        </div>

        <!-- Fullscreen -->
        <button
          @click="toggleFullscreen"
          class="p-2 rounded-lg bg-dark-800 text-dark-400 hover:text-dark-200 hover:bg-dark-700 transition-colors"
        >
          <ArrowsPointingInIcon v-if="isFullscreen" class="w-5 h-5" />
          <ArrowsPointingOutIcon v-else class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Drawing Tools & Indicators Toolbar -->
    <div class="bg-dark-900 border border-dark-800 rounded-lg p-3 mb-4">
      <div class="flex flex-wrap items-center gap-3">
        <!-- Drawing Tools -->
        <div class="flex items-center gap-2">
          <span class="text-xs text-dark-500 uppercase tracking-wide">Tools:</span>
          <button
            v-for="tool in tools"
            :key="tool.id"
            @click="setTool(tool.id)"
            :class="['px-3 py-1.5 text-sm rounded-lg border transition-colors', btnClass(tool.id)]"
            :title="tool.label"
          >
            {{ tool.label }}
          </button>
        </div>

        <!-- Undo / Clear -->
        <div class="flex items-center gap-2 border-l border-dark-700 pl-3">
          <button
            @click="undoDrawing"
            class="p-2 rounded-lg bg-dark-800 text-dark-400 hover:text-dark-200 hover:bg-dark-700 transition-colors"
            title="Undo"
          >
            <ArrowUturnLeftIcon class="w-4 h-4" />
          </button>
          <button
            @click="clearDrawings"
            class="p-2 rounded-lg bg-dark-800 text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors"
            title="Clear All"
          >
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>

        <!-- Spacer -->
        <div class="flex-1"></div>

        <!-- Indicators -->
        <div class="flex items-center gap-4">
          <span class="text-xs text-dark-500 uppercase tracking-wide">Indicators:</span>
          
          <label class="flex items-center gap-1.5 text-sm text-dark-300 cursor-pointer">
            <input type="checkbox" v-model="showVolume" class="accent-primary-500" />
            Volume
          </label>
          
          <label class="flex items-center gap-1.5 text-sm text-dark-300 cursor-pointer">
            <input type="checkbox" v-model="showSMA" class="accent-amber-500" />
            SMA
            <input v-if="showSMA" type="number" v-model.number="smaPeriod" min="5" max="200" class="w-12 px-1 py-0.5 text-xs bg-dark-800 border border-dark-700 rounded" />
          </label>
          
          <label class="flex items-center gap-1.5 text-sm text-dark-300 cursor-pointer">
            <input type="checkbox" v-model="showEMA" class="accent-violet-500" />
            EMA
            <input v-if="showEMA" type="number" v-model.number="emaPeriod" min="5" max="200" class="w-12 px-1 py-0.5 text-xs bg-dark-800 border border-dark-700 rounded" />
          </label>
          
          <label class="flex items-center gap-1.5 text-sm text-dark-300 cursor-pointer">
            <input type="checkbox" v-model="showBB" class="accent-blue-500" />
            BB
          </label>
          
          <label class="flex items-center gap-1.5 text-sm text-dark-300 cursor-pointer">
            <input type="checkbox" v-model="showRSI" class="accent-purple-500" />
            RSI
          </label>
          
          <label class="flex items-center gap-1.5 text-sm text-dark-300 cursor-pointer">
            <input type="checkbox" v-model="showMACD" class="accent-emerald-500" />
            MACD
          </label>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="errorMsg" class="bg-red-500/10 border border-red-500/30 rounded-lg p-3 mb-4 text-red-400 text-sm">
      {{ errorMsg }}
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-8">
      <div class="spinner"></div>
      <span class="ml-3 text-dark-400">Loading chart data...</span>
    </div>

    <!-- Chart Container -->
    <div
      ref="chartWrap"
      :class="[
        'relative bg-dark-900 border border-dark-800 rounded-lg overflow-hidden',
      ]"
      :style="{ height: isFullscreen ? 'calc(100vh - 280px)' : '500px' }"
    >
      <div ref="chartContainer" class="absolute inset-0"></div>
      <!-- Konva overlay -->
      <div ref="overlayContainer" class="absolute inset-0" style="pointer-events: auto;"></div>
    </div>

    <!-- Indicator Panes -->
    <div v-if="showRSI || showMACD" class="mt-4 grid grid-cols-1 gap-4">
      <div v-if="showRSI" class="bg-dark-900 border border-dark-800 rounded-lg p-3">
        <div class="flex items-center justify-between mb-2">
          <h4 class="text-sm font-medium text-dark-300">RSI (14)</h4>
          <div class="flex items-center gap-2 text-xs text-dark-500">
            <span class="px-2 py-0.5 bg-dark-800 rounded">Overbought: 70</span>
            <span class="px-2 py-0.5 bg-dark-800 rounded">Oversold: 30</span>
          </div>
        </div>
        <div ref="rsiPane" style="height: 120px;"></div>
      </div>

      <div v-if="showMACD" class="bg-dark-900 border border-dark-800 rounded-lg p-3">
        <div class="flex items-center justify-between mb-2">
          <h4 class="text-sm font-medium text-dark-300">MACD (12, 26, 9)</h4>
          <div class="flex items-center gap-2 text-xs">
            <span class="text-red-400">● MACD Line</span>
            <span class="text-emerald-400">● Signal</span>
            <span class="text-blue-400">● Histogram</span>
          </div>
        </div>
        <div ref="macdPane" style="height: 140px;"></div>
      </div>
    </div>

    <!-- Quick Symbols -->
    <div class="bg-dark-900 border border-dark-800 rounded-lg p-4 mt-4" v-if="!isFullscreen">
      <h3 class="text-sm font-medium text-dark-400 mb-3">Quick Access</h3>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="s in [...symbolCategories.crypto.slice(0, 4), ...symbolCategories.forex.slice(0, 4)]"
          :key="s.value"
          @click="changeSymbol(s.value)"
          :class="[
            'px-3 py-1.5 text-sm rounded-lg border transition-colors',
            symbolInput === s.value
              ? 'bg-accent-500/20 border-accent-500 text-accent-400'
              : 'bg-dark-800 border-dark-700 text-dark-300 hover:border-dark-600 hover:text-dark-200'
          ]"
        >
          {{ s.label }}
        </button>
      </div>
    </div>

    <!-- Info Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4" v-if="!isFullscreen">
      <div class="card p-4">
        <h3 class="text-sm font-semibold text-dark-200 mb-2">📐 Drawing Tools</h3>
        <p class="text-xs text-dark-400">
          Trend Line, Horizontal/Vertical Line, Fibonacci Retracement, Rectangle.
          Select a tool then click and drag on the chart.
        </p>
      </div>

      <div class="card p-4">
        <h3 class="text-sm font-semibold text-dark-200 mb-2">📊 Indicators</h3>
        <p class="text-xs text-dark-400">
          SMA, EMA, Bollinger Bands overlay on main chart.
          RSI and MACD shown in separate panes below.
        </p>
      </div>

      <div class="card p-4">
        <h3 class="text-sm font-semibold text-dark-200 mb-2">📡 Data Sources</h3>
        <p class="text-xs text-dark-400">
          <span class="text-emerald-400">Crypto:</span> Binance Public API (real-time)<br>
          <span class="text-blue-400">Forex:</span> TwelveData API (delayed)
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Ensure chart fills container */
:deep(.tv-lightweight-charts) {
  width: 100% !important;
  height: 100% !important;
}
</style>

