import axios from 'axios'

// Create axios instance
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Request interceptor to add auth token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor for error handling
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

// Auth API
export const authAPI = {
  login: (credentials) => api.post('/auth/login', credentials),
  register: (data) => api.post('/auth/register', data),
  logout: () => api.post('/auth/logout'),
  getUser: () => api.get('/auth/user'),
  updateProfile: (data) => api.put('/auth/profile', data),
  updatePassword: (data) => api.put('/auth/password', data),
}

// Dashboard API
export const dashboardAPI = {
  getOverview: (params) => api.get('/dashboard', { params }),
  getMetrics: (params) => api.get('/dashboard/metrics', { params }),
  getEquityCurve: (params) => api.get('/dashboard/equity-curve', { params }),
  getDailyPnL: (params) => api.get('/dashboard/daily-pnl', { params }),
  getCalendar: (params) => api.get('/dashboard/calendar', { params }),
  getStatsByPair: (params) => api.get('/dashboard/stats/pair', { params }),
  getStatsByHour: (params) => api.get('/dashboard/stats/hour', { params }),
}

// Accounts API
export const accountsAPI = {
  getAll: () => api.get('/accounts'),
  getOne: (id) => api.get(`/accounts/${id}`),
  create: (data) => api.post('/accounts', data),
  update: (id, data) => api.put(`/accounts/${id}`, data),
  delete: (id) => api.delete(`/accounts/${id}`),
  sync: (id) => api.post(`/accounts/${id}/sync`),
  triggerSync: (id) => api.post(`/accounts/${id}/trigger-sync`),
  regenerateToken: (id) => api.post(`/accounts/${id}/regenerate-token`),
  clearData: (id) => api.post(`/accounts/${id}/clear-data`),
  getSyncLogs: (id, params) => api.get(`/accounts/${id}/sync-logs`, { params }),
}

// EA Commands API
export const eaCommandsAPI = {
  getAll: (accountId, params) => api.get(`/accounts/${accountId}/ea-commands`, { params }),
  getPending: (accountId) => api.get(`/accounts/${accountId}/ea-commands/pending`),
  create: (accountId, data) => api.post(`/accounts/${accountId}/ea-commands`, data),
  updateStatus: (commandId, data) => api.put(`/ea-commands/${commandId}/status`, data),
}

// Trades API
export const tradesAPI = {
  getAll: (params) => api.get('/trades', { params }),
  getOne: (id) => api.get(`/trades/${id}`),
  getOpen: () => api.get('/trades/open'),
  getSummary: (params) => api.get('/trades/summary', { params }),
  createManual: (data) => api.post('/trades/manual', data),
  update: (id, data) => api.put(`/trades/${id}`, data),
  delete: (id) => api.delete(`/trades/${id}`),
  uploadStatement: (formData) => api.post('/upload/statement', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
}

// Reports API
export const reportsAPI = {
  getMonthly: (params) => api.get('/reports/monthly', { params }),
  exportCSV: (params) => api.get('/reports/monthly/csv', { params, responseType: 'blob' }),
  exportPDF: (params) => api.get('/reports/monthly/pdf', { params, responseType: 'blob' }),
}

// News API
export const newsAPI = {
  getAll: (params) => api.get('/news', { params }),
  getToday: () => api.get('/news/today'),
  getUpcoming: (params) => api.get('/news/upcoming', { params }),
  getForDate: (date) => api.get(`/news/date/${date}`),
  create: (data) => api.post('/news', data),
  update: (id, data) => api.put(`/news/${id}`, data),
  delete: (id) => api.delete(`/news/${id}`),
}

// Journal API
export const journalAPI = {
  getAll: (params) => api.get('/journal', { params }),
  getOne: (id) => api.get(`/journal/${id}`),
  getForDate: (date) => api.get(`/journal/date/${date}`),
  getTags: () => api.get('/journal/tags'),
  create: (data) => api.post('/journal', data),
  update: (id, data) => api.put(`/journal/${id}`, data),
  delete: (id) => api.delete(`/journal/${id}`),
}

// Chart Analysis API
export const chartAnalysisAPI = {
  getAll: () => api.get('/chart-analyses'),
  getBySymbol: (symbol, interval) => api.get(`/chart-analyses/${encodeURIComponent(symbol)}/${interval}`),
  getOne: (id) => api.get(`/chart-analyses/${id}`),
  create: (data) => api.post('/chart-analyses', data),
  update: (id, data) => api.put(`/chart-analyses/${id}`, data),
  delete: (id) => api.delete(`/chart-analyses/${id}`),
}

export default api

