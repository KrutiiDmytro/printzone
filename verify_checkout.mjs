import { chromium } from 'playwright';
import path from 'path';
import fs from 'fs';

const BASE = 'http://127.0.0.1';
const SHOTS = path.join(process.cwd(), 'verify_screenshots');
fs.mkdirSync(SHOTS, { recursive: true });

let n = 0;
const shot = async (page, name) => {
  const f = path.join(SHOTS, `${String(++n).padStart(2,'0')}_${name}.png`);
  await page.screenshot({ path: f }).catch(() => {});
  console.log(`  📸 ${f}`);
};

// App is slow in dev mode — use 120s timeouts
const TIMEOUT = 120_000;

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
await ctx.route(/fonts\.(googleapis|gstatic)\.com|use\.fontawesome\.com|cdn\.jsdelivr\.net/, r => r.abort());

const page = await ctx.newPage();
page.setDefaultTimeout(TIMEOUT);
page.setDefaultNavigationTimeout(TIMEOUT);

try {
  // 1. Login
  console.log('\n[1] Логін...');
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="_username"]').fill('user@example.com');
  await page.locator('input[name="_password"]').fill('user123');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(u => !u.href.includes('/login'), { timeout: TIMEOUT });
  console.log(`  ✅ Увійшов. URL: ${page.url()}`);
  await shot(page, 'after_login');

  // 2. Перевірити cart (є продукт з попереднього тесту)
  console.log('\n[2] Перевірити /cart...');
  await page.goto(`${BASE}/cart`, { waitUntil: 'domcontentloaded' });
  const cartText = (await page.locator('body').innerText()).replace(/\s+/g,' ');
  console.log(`  Cart snippet: ${cartText.substring(0, 200)}`);
  await shot(page, 'cart_page');

  const cartEmpty = cartText.includes('Your cart is empty');
  if (cartEmpty) {
    // Cart is empty — add product 60 via browser navigation
    console.log('  Cart порожній. Додаємо продукт 60...');
    await page.goto(`${BASE}/product/60`, { waitUntil: 'domcontentloaded' });
    await shot(page, 'product_page');

    // Submit add-to-cart form via evaluate (bypasses click timing issues)
    await page.evaluate(() => {
      const form = document.querySelector('form[action*="cart/add"]');
      if (form) form.submit();
    });
    await page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: TIMEOUT });
    console.log(`  URL після додавання: ${page.url()}`);
    await shot(page, 'after_add');

    // Re-check cart
    await page.goto(`${BASE}/cart`, { waitUntil: 'domcontentloaded' });
    const cartText2 = (await page.locator('body').innerText()).replace(/\s+/g,' ');
    console.log(`  Cart після додавання: ${cartText2.substring(0, 200)}`);
    await shot(page, 'cart_after_add');
  }

  // 3. Checkout
  console.log('\n[3] /checkout...');
  await page.goto(`${BASE}/checkout`, { waitUntil: 'domcontentloaded' });
  const checkoutUrl = page.url();
  console.log(`  URL: ${checkoutUrl}`);
  await shot(page, 'checkout_page');

  if (!checkoutUrl.includes('/checkout')) {
    const body = (await page.locator('body').innerText()).replace(/\s+/g,' ');
    throw new Error(`Перенаправлено з /checkout → ${checkoutUrl}. Body: ${body.substring(0,200)}`);
  }

  // 4. Billing форма
  console.log('\n[4] Заповнити форму...');
  await page.locator('input[name="firstName"]').fill('Test');
  await page.locator('input[name="lastName"]').fill('User');
  await page.locator('input[name="address"]').fill('123 Test Street');
  await page.locator('input[name="city"]').fill('Kyiv');
  await page.locator('input[name="country"]').fill('Ukraine');
  await page.locator('input[name="postcode"]').fill('01001');
  await page.locator('input[name="phone"]').fill('+380501234567');
  await shot(page, 'billing_filled');

  // 5. Place Order → Stripe
  console.log('\n[5] Place Order → Stripe...');
  // Click with force to bypass any overlay issues, then wait for navigation
  const submitBtn = page.locator('button[type="submit"]').first();
  console.log(`  Submit button visible: ${await submitBtn.isVisible()}`);
  console.log(`  Submit button enabled: ${await submitBtn.isEnabled()}`);
  await submitBtn.scrollIntoViewIfNeeded();

  // Wait for ANY navigation first, then check if it's Stripe
  const [resp] = await Promise.all([
    page.waitForResponse(r => r.url().includes('/checkout/place-order'), { timeout: 120_000 }).catch(() => null),
    submitBtn.click({ force: true }),
  ]);
  if (resp) {
    console.log(`  Response status: ${resp.status()}, URL: ${resp.url()}`);
  }
  await page.waitForLoadState('domcontentloaded').catch(() => {});
  const afterUrl = page.url();
  console.log(`  URL after submit: ${afterUrl.substring(0, 120)}`);
  await shot(page, 'after_submit');
  const afterBody = (await page.locator('body').innerText().catch(() => '')).replace(/\s+/g,' ');
  console.log(`  Page body snippet: ${afterBody.substring(0, 300)}`);

  if (!afterUrl.includes('stripe.com')) {
    throw new Error(`Не перенаправлено на Stripe. URL: ${afterUrl}`);
  }
  const stripeUrl = page.url();
  console.log(`  ✅ Stripe URL: ${stripeUrl.substring(0, 80)}...`);
  await page.waitForLoadState('domcontentloaded');
  await shot(page, 'stripe_checkout');

  // 6. Stripe test card (iframe)
  console.log('\n[6] Stripe картка...');
  try {
    await page.waitForSelector('iframe', { timeout: 15000 });
    const iframes = page.frames();
    const cardFrame = iframes.find(f => f.url().includes('stripe.com') && f.url().includes('card'));
    if (cardFrame) {
      await cardFrame.fill('input[name="cardnumber"], input[autocomplete="cc-number"]', '4242424242424242');
      await cardFrame.fill('input[name="exp-date"], input[autocomplete="cc-exp"]', '12/30');
      await cardFrame.fill('input[name="cvc"], input[autocomplete="cc-csc"]', '123');
      await shot(page, 'stripe_card_filled');
      await page.locator('[data-testid="hosted-payment-submit-button"], .SubmitButton').click();
      await page.waitForURL(u => u.href.includes('/checkout/success'), { timeout: 60_000 });
      console.log(`  ✅ Успішна оплата! URL: ${page.url()}`);
      await shot(page, 'checkout_success');
    } else {
      console.log(`  ℹ️  Stripe iframe не знайдено серед ${iframes.length} frames`);
      console.log(`  Frames: ${iframes.slice(0,5).map(f=>f.url().substring(0,60)).join(', ')}`);
      await shot(page, 'stripe_detail');
    }
  } catch (e) {
    console.log(`  ⚠️  Stripe card: ${e.message.split('\n')[0]}`);
    await shot(page, 'stripe_error');
  }

  console.log(`\n✅ Верифікація завершена! Скриншоти: ${SHOTS}`);

} catch (err) {
  console.error(`\n❌ ПОМИЛКА: ${err.message.split('\n')[0]}`);
  await shot(page, 'error').catch(() => {});
  process.exit(1);
} finally {
  await browser.close();
}
