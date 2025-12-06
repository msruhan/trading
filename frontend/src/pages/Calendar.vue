<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { dashboardAPI, newsAPI, journalAPI } from '@/services/api'
import dayjs from 'dayjs'
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'

const loading = ref(false)
const calendarData = ref({})
const selectedDate = ref(dayjs().format('YYYY-MM-DD'))
const currentMonth = ref(dayjs())

const selectedDateNews = ref([])
const selectedDateEntries = ref([])
const selectedDateLoading = ref(false)

// Calendar grid computation
const calendarDays = computed(() => {
  const start = currentMonth.value.startOf('month').startOf('week')
  const end = currentMonth.value.endOf('month').endOf('week')
  
  const days = []
  let day = start
  
  while (day.isBefore(end) || day.isSame(end, 'day')) {
    days.push({
      date: day.format('YYYY-MM-DD'),
      day: day.date(),
      isCurrentMonth: day.month() === currentMonth.value.month(),
      isToday: day.isSame(dayjs(), 'day'),
      isSelected: day.format('YYYY-MM-DD') === selectedDate.value,
      data: calendarData.value[day.format('YYYY-MM-DD')] || null,
    })
    day = day.add(1, 'day')
  }
  
  return days
})

const monthYear = computed(() => currentMonth.value.format('MMMM YYYY'))

onMounted(async () => {
  await fetchCalendarData()
  await fetchDateDetails(selectedDate.value)
})

const fetchCalendarData = async () => {
  loading.value = true
  try {
    const response = await dashboardAPI.getCalendar({
      year: currentMonth.value.year(),
      month: currentMonth.value.month() + 1,
    })
    calendarData.value = response.data
  } catch (error) {
    console.error('Failed to fetch calendar:', error)
  } finally {
    loading.value = false
  }
}

const fetchDateDetails = async (date) => {
  selectedDateLoading.value = true
  try {
    const [newsResponse, entriesResponse] = await Promise.all([
      newsAPI.getForDate(date),
      journalAPI.getForDate(date),
    ])
    selectedDateNews.value = newsResponse.data.news
    selectedDateEntries.value = entriesResponse.data.entries
  } catch (error) {
    console.error('Failed to fetch date details:', error)
  } finally {
    selectedDateLoading.value = false
  }
}

const prevMonth = async () => {
  currentMonth.value = currentMonth.value.subtract(1, 'month')
  await fetchCalendarData()
}

const nextMonth = async () => {
  currentMonth.value = currentMonth.value.add(1, 'month')
  await fetchCalendarData()
}

const selectDate = async (date) => {
  selectedDate.value = date
  await fetchDateDetails(date)
}

const getDayClass = (day) => {
  const classes = ['p-2 h-24 border border-dark-800 rounded-lg cursor-pointer transition-colors']
  
  if (!day.isCurrentMonth) {
    classes.push('opacity-30')
  }
  
  if (day.isToday) {
    classes.push('border-primary-500/50')
  }
  
  if (day.isSelected) {
    classes.push('bg-primary-500/10 border-primary-500')
  } else {
    classes.push('hover:bg-dark-800/50')
  }
  
  return classes.join(' ')
}

const getProfitClass = (profit) => {
  if (profit > 0) return 'text-green-400'
  if (profit < 0) return 'text-red-400'
  return 'text-dark-500'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-dark-100">Calendar & News</h1>
      <p class="text-dark-400">View trading activity and market events by date</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Calendar -->
      <div class="lg:col-span-2 card p-4">
        <!-- Month navigation -->
        <div class="flex items-center justify-between mb-4">
          <button
            class="p-2 rounded-lg hover:bg-dark-800 text-dark-400"
            @click="prevMonth"
          >
            <ChevronLeftIcon class="w-5 h-5" />
          </button>
          <h2 class="text-lg font-semibold text-dark-100">{{ monthYear }}</h2>
          <button
            class="p-2 rounded-lg hover:bg-dark-800 text-dark-400"
            @click="nextMonth"
          >
            <ChevronRightIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Day headers -->
        <div class="grid grid-cols-7 gap-1 mb-2">
          <div
            v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
            :key="day"
            class="text-center text-xs font-medium text-dark-500 py-2"
          >
            {{ day }}
          </div>
        </div>

        <!-- Calendar grid -->
        <div v-if="loading" class="flex justify-center py-12">
          <div class="spinner" />
        </div>
        <div v-else class="grid grid-cols-7 gap-1">
          <div
            v-for="day in calendarDays"
            :key="day.date"
            :class="getDayClass(day)"
            @click="selectDate(day.date)"
          >
            <div class="flex items-center justify-between">
              <span :class="['text-sm', day.isToday ? 'font-bold text-primary-400' : 'text-dark-300']">
                {{ day.day }}
              </span>
              <span v-if="day.data?.trades" class="text-xs text-dark-500">
                {{ day.data.trades }}
              </span>
            </div>
            <div v-if="day.data?.profit !== undefined" class="mt-1">
              <span :class="['text-xs font-mono', getProfitClass(day.data.profit)]">
                {{ day.data.profit >= 0 ? '+' : '' }}${{ day.data.profit }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Selected date details -->
      <div class="space-y-4">
        <!-- Date summary -->
        <div class="card p-4">
          <h3 class="text-sm font-medium text-dark-300 mb-3">
            {{ dayjs(selectedDate).format('dddd, MMMM D, YYYY') }}
          </h3>
          
          <div v-if="selectedDateLoading" class="flex justify-center py-4">
            <div class="spinner" />
          </div>
          
          <template v-else>
            <div v-if="calendarData[selectedDate]" class="space-y-2">
              <div class="flex justify-between">
                <span class="text-dark-400">Trades</span>
                <span class="text-dark-100">{{ calendarData[selectedDate].trades }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-dark-400">P/L</span>
                <span :class="getProfitClass(calendarData[selectedDate].profit)">
                  {{ calendarData[selectedDate].profit >= 0 ? '+' : '' }}${{ calendarData[selectedDate].profit }}
                </span>
              </div>
            </div>
            <div v-else class="text-center text-dark-500 py-4">
              No trades on this day
            </div>
          </template>
        </div>

        <!-- News -->
        <div class="card p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-dark-300">News & Events</h3>
          </div>
          
          <div v-if="selectedDateNews.length > 0" class="space-y-2">
            <div
              v-for="news in selectedDateNews"
              :key="news.id"
              class="p-2 rounded-lg bg-dark-800/50"
            >
              <div class="flex items-start gap-2">
                <span :class="[
                  'px-1.5 py-0.5 text-xs font-medium rounded',
                  news.impact === 'high' ? 'bg-red-500/20 text-red-400' :
                  news.impact === 'medium' ? 'bg-yellow-500/20 text-yellow-400' :
                  'bg-green-500/20 text-green-400'
                ]">
                  {{ news.currency }}
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-dark-200 truncate">{{ news.title }}</p>
                  <p v-if="news.time" class="text-xs text-dark-500">{{ news.time }}</p>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-center text-dark-500 py-4 text-sm">
            No news for this day
          </div>
        </div>

        <!-- Journal entries -->
        <div class="card p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-dark-300">Journal Entries</h3>
            <router-link 
              :to="`/journal?date=${selectedDate}`"
              class="p-1 rounded hover:bg-dark-800 text-dark-400"
            >
              <PlusIcon class="w-4 h-4" />
            </router-link>
          </div>
          
          <div v-if="selectedDateEntries.length > 0" class="space-y-2">
            <div
              v-for="entry in selectedDateEntries"
              :key="entry.id"
              class="p-2 rounded-lg bg-dark-800/50"
            >
              <p class="text-sm text-dark-200">{{ entry.title || entry.content.slice(0, 100) }}...</p>
              <p class="text-xs text-dark-500 mt-1">{{ entry.entry_type }}</p>
            </div>
          </div>
          <div v-else class="text-center text-dark-500 py-4 text-sm">
            No journal entries
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

