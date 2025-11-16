import { chromium } from 'playwright'

const BASE_URL = process.env.UI_BASE_URL || 'http://localhost:8080'
const EMAIL = process.env.UI_EMAIL || 'nelson.talemwa@royaldentalservices.com'
const PASSWORD = process.env.UI_PASSWORD || 'Nelson@RDSClinic'

const result = {
  login: { success: false, message: '' },
  appointmentsPage: { url: '', headingVisible: false },
  sidebar: { labelFound: false, keyVisibleCount: 0, sampleText: '' },
  console: { errors: [], warnings: [] },
}

const browser = await chromium.launch({ headless: true })
const page = await browser.newPage()

const consoleMessages = []
page.on('console', (msg) => {
  consoleMessages.push({ type: msg.type(), text: msg.text() })
})

try {
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' })
  await page.waitForSelector('input[type="email"]', { timeout: 15000 })
  await page.fill('input[type="email"]', EMAIL)
  await page.fill('input[type="password"]', PASSWORD)
  await page.click('button[type="submit"]')
  await page.waitForTimeout(1500)

  await page.goto(`${BASE_URL}/admin/appointments`, { waitUntil: 'networkidle' })
  result.appointmentsPage.url = page.url()
  const redirectedToLogin = result.appointmentsPage.url.includes('/login')
  result.login.success = !redirectedToLogin
  result.login.message = result.login.success
    ? 'Authenticated and reached appointments page'
    : 'Redirected back to login after submitting credentials'

  result.appointmentsPage.headingVisible = await page
    .locator('main')
    .locator('text=Appointments')
    .first()
    .isVisible()
    .catch(() => false)

  const navLocator = page.locator('nav').locator('a:has-text("Appointments")').first()
  if (await navLocator.count()) {
    result.sidebar.labelFound = true
    result.sidebar.sampleText = (await navLocator.textContent())?.trim()
  } else {
    const fallbackNav = page.locator('nav').locator('text=/Appointments/i').first()
    if (await fallbackNav.count()) {
      result.sidebar.labelFound = true
      result.sidebar.sampleText = (await fallbackNav.textContent())?.trim()
    }
  }
  result.sidebar.keyVisibleCount = await page.locator('text="navigation.appointments"').count()

  result.console.errors = consoleMessages
    .filter((m) => m.type === 'error')
    .map((m) => m.text)
  result.console.warnings = consoleMessages
    .filter((m) => m.type === 'warning')
    .map((m) => m.text)
} catch (error) {
  result.login.success = false
  result.login.message = `Script error: ${error.message}`
} finally {
  await browser.close()
}

console.log(JSON.stringify(result, null, 2))

if (
  !result.login.success ||
  !result.appointmentsPage.headingVisible ||
  !result.sidebar.labelFound ||
  result.sidebar.keyVisibleCount > 0 ||
  result.console.errors.length ||
  result.console.warnings.length
) {
  process.exitCode = 1
}
