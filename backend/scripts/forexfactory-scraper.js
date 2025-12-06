#!/usr/bin/env node

import puppeteer from 'puppeteer-extra'
import StealthPlugin from 'puppeteer-extra-plugin-stealth'

puppeteer.use(StealthPlugin())

/**
 * CLI script to scrape ForexFactory calendar using Puppeteer.
 * 
 * Usage:
 *   node backend/scripts/forexfactory-scraper.js --mode=today
 *   node backend/scripts/forexfactory-scraper.js --mode=week
 *
 * Output:
 *   JSON array of events written to stdout
 */

function parseArgs() {
  const args = process.argv.slice(2)
  const result = {}

  for (const arg of args) {
    const [key, value] = arg.split('=')
    if (key && value) {
      result[key.replace(/^--/, '')] = value
    }
  }

  return result
}

/**
 * Convert 12-hour time to 24-hour format
 */
function convertTo24Hour(timeStr) {
  if (!timeStr || timeStr === '' || timeStr.toLowerCase().includes('all day') || 
      timeStr.toLowerCase().includes('tentative') || timeStr.toLowerCase() === 'day') {
    return null
  }

  const cleanTime = timeStr.toLowerCase().trim()
  
  const match = cleanTime.match(/^(\d{1,2}):(\d{2})\s*(am|pm)$/i)
  if (!match) {
    const match24 = cleanTime.match(/^(\d{1,2}):(\d{2})$/)
    if (match24) {
      const h = match24[1].padStart(2, '0')
      const m = match24[2]
      return `${h}:${m}:00`
    }
    return null
  }

  let hours = parseInt(match[1], 10)
  const minutes = match[2]
  const period = match[3].toLowerCase()

  if (period === 'am') {
    if (hours === 12) hours = 0
  } else {
    if (hours !== 12) hours += 12
  }

  return `${hours.toString().padStart(2, '0')}:${minutes}:00`
}

/**
 * Parse date text like "Mon Dec 2" or "Sat Nov 30" to ISO format
 */
function parseCalendarDate(dateText, referenceYear) {
  if (!dateText) return null
  
  const monthNames = {
    jan: '01', feb: '02', mar: '03', apr: '04', may: '05', jun: '06',
    jul: '07', aug: '08', sep: '09', oct: '10', nov: '11', dec: '12'
  }
  
  const dateMatch = dateText.match(/([A-Za-z]+)\s+([A-Za-z]+)\s+(\d+)/i)
  if (!dateMatch) return null
  
  const month = monthNames[dateMatch[2].toLowerCase()]
  const day = dateMatch[3].padStart(2, '0')
  
  if (!month) return null
  
  let year = referenceYear
  const currentMonth = new Date().getMonth() + 1
  const eventMonth = parseInt(month, 10)
  
  if (currentMonth >= 11 && eventMonth <= 2) {
    year = referenceYear + 1
  }
  if (currentMonth <= 2 && eventMonth >= 11) {
    year = referenceYear - 1
  }
  
  return `${year}-${month}-${day}`
}

async function autoScroll(page) {
  await page.evaluate(async () => {
    await new Promise((resolve) => {
      let totalHeight = 0
      const distance = 500
      const timer = setInterval(() => {
        const scrollHeight = document.body.scrollHeight
        window.scrollBy(0, distance)
        totalHeight += distance
        if (totalHeight >= scrollHeight) {
          clearInterval(timer)
          resolve()
        }
      }, 200)
    })
  })
}

async function scrape() {
  const args = parseArgs()
  const mode = args.mode || 'week'
  
  let url
  if (mode === 'today') {
    url = 'https://www.forexfactory.com/'
  } else {
    url = 'https://www.forexfactory.com/calendar?week=this'
  }

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  })

  try {
    const page = await browser.newPage()

    await page.setUserAgent(
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    )
    await page.setExtraHTTPHeaders({
      'Accept-Language': 'en-US,en;q=0.9',
      Referer: 'https://www.forexfactory.com/',
    })

    // Set a larger viewport to load more content
    await page.setViewport({ width: 1920, height: 1080 })

    await page.goto(url, {
      waitUntil: 'networkidle2',
      timeout: 90_000,
    })

    // Wait for Cloudflare challenge
    await new Promise((resolve) => setTimeout(resolve, 8000))

    // Wait for calendar table
    await page.waitForSelector('.calendar__table, #calendarTable', {
      timeout: 60_000,
    })

    // Scroll to load all content
    await autoScroll(page)
    
    // Wait for any lazy-loaded content
    await new Promise((resolve) => setTimeout(resolve, 3000))

    // Extract events
    const events = await page.evaluate(() => {
      const rows = document.querySelectorAll('.calendar__row')
      const data = []
      
      let currentDate = null
      let currentTime = null

      const clean = (el) => {
        if (!el) return ''
        return el.innerText?.trim() || el.textContent?.trim() || ''
      }

      const extractImpact = (row) => {
        const impactSpan = row.querySelector('.calendar__impact span, .impact span')
        if (impactSpan) {
          const classList = impactSpan.className || ''
          const title = impactSpan.getAttribute('title') || ''
          
          if (classList.includes('high') || classList.includes('red') || title.toLowerCase().includes('high')) {
            return 'high'
          }
          if (classList.includes('medium') || classList.includes('ora') || title.toLowerCase().includes('medium')) {
            return 'medium'
          }
          if (classList.includes('low') || classList.includes('yel') || title.toLowerCase().includes('low')) {
            return 'low'
          }
        }
        
        const impactCell = row.querySelector('.calendar__impact')
        if (impactCell) {
          const html = impactCell.innerHTML || ''
          if (html.includes('icon--ff-impact-red') || html.includes('high')) return 'high'
          if (html.includes('icon--ff-impact-ora') || html.includes('medium')) return 'medium'
          if (html.includes('icon--ff-impact-yel') || html.includes('low')) return 'low'
        }
        
        return 'medium'
      }

      rows.forEach((row) => {
        // Check for date in the row
        const dateCell = row.querySelector('.calendar__date span, .calendar__date')
        if (dateCell) {
          const dateText = clean(dateCell)
          if (dateText && dateText.length > 0 && !dateText.match(/^\s*$/)) {
            currentDate = dateText
          }
        }

        // Get time
        const timeCell = row.querySelector('.calendar__time')
        const timeText = clean(timeCell)
        if (timeText && timeText !== '' && timeText !== '\u00a0') {
          currentTime = timeText
        }

        // Get other fields
        const currency = clean(row.querySelector('.calendar__currency'))
        const eventCell = row.querySelector('.calendar__event')
        const eventTitle = eventCell ? clean(eventCell.querySelector('.calendar__event-title span, .calendar__event-title, span') || eventCell) : ''
        
        const actualCell = row.querySelector('.calendar__actual')
        const forecastCell = row.querySelector('.calendar__forecast')
        const previousCell = row.querySelector('.calendar__previous')
        
        const actual = actualCell ? clean(actualCell.querySelector('span') || actualCell) : ''
        const forecast = forecastCell ? clean(forecastCell.querySelector('span') || forecastCell) : ''
        const previous = previousCell ? clean(previousCell.querySelector('span') || previousCell) : ''
        
        const impact = extractImpact(row)
        const eventId = row.getAttribute('data-eventid') || null

        if (currency && eventTitle && currency !== 'ALL') {
          data.push({
            date_raw: currentDate,
            time_raw: currentTime,
            currency: currency.toUpperCase(),
            impact,
            title: eventTitle,
            actual: actual || null,
            forecast: forecast || null,
            previous: previous || null,
            event_id: eventId,
          })
        }
      })

      return data
    })

    // Post-process events
    const currentYear = new Date().getFullYear()
    const processedEvents = events.map(event => {
      const isoDate = parseCalendarDate(event.date_raw, currentYear)
      const time24 = convertTo24Hour(event.time_raw)
      
      return {
        date: isoDate,
        time: time24,
        time_raw: event.time_raw,
        currency: event.currency,
        impact: event.impact,
        title: event.title,
        actual: event.actual,
        forecast: event.forecast,
        previous: event.previous,
        event_id: event.event_id,
      }
    }).filter(e => e.date !== null)

    process.stdout.write(JSON.stringify(processedEvents ?? []))
  } finally {
    await browser.close()
  }
}

scrape().catch((err) => {
  console.error(JSON.stringify({ error: err.message || String(err) }))
  process.exit(1)
})
