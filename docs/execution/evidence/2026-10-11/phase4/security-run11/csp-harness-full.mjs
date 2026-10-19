import { chromium } from '/Users/hanifpriyambodo/.npm/_npx/e41f203b7505f1fb/node_modules/playwright/index.mjs';
import fs from 'node:fs';

const base = 'http://127.0.0.1:8017';
const out = '/Users/hanifpriyambodo/Documents/online-shop/docs/execution/evidence/2026-10-11/phase4/security-run11';
const policy = "default-src 'self'; script-src 'self'; style-src 'self' https://fonts.bunny.net 'unsafe-inline'; font-src 'self' https://fonts.bunny.net data:; img-src 'self' data: https:; connect-src 'self'; media-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; report-uri /__qa_csp_report";

const browser = await chromium.launch({
  headless: true,
  executablePath: '/Users/hanifpriyambodo/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell'
});

const pages = [];

async function inspectPage(path, opts = {}) {
  const context = await browser.newContext();
  await context.route('**/*', async route => {
    const req = route.request();
    if (req.isNavigationRequest() && req.url().startsWith(base)) {
      const resp = await route.fetch();
      const headers = { ...resp.headers(), 'content-security-policy-report-only': policy };
      return route.fulfill({ response: resp, headers });
    }
    return route.continue();
  });

  const page = await context.newPage();
  await page.addInitScript(() => {
    window.__violations = [];
    document.addEventListener('securitypolicyviolation', e => {
      window.__violations.push({
        directive: e.effectiveDirective,
        blockedURI: e.blockedURI,
        disposition: e.disposition,
        sample: (e.sample || '').slice(0, 100)
      });
    });
  });

  // Pre-login if requested
  if (opts.auth) {
    await page.goto(base + opts.auth.loginUrl);
    const token = await page.locator('meta[name=csrf-token]').getAttribute('content');
    await page.locator('input[name=email]').fill(opts.auth.email);
    await page.locator('input[name=password]').fill(opts.auth.password);
    await page.locator('form').evaluate((f, t) => {
      f.querySelector('input[name=_token]').value = t;
      f.submit();
    }, token);
    await page.waitForTimeout(1000);
  }

  const response = await page.goto(base + path, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle').catch(() => {});

  const status = response.status();
  const cspHeader = response.headers()['content-security-policy-report-only'];

  const inventory = await page.evaluate(() => {
    const scripts = [...document.scripts];
    const inlineScripts = scripts.filter(s => !s.src && s.textContent.trim()).length;
    const styleAttrs = document.querySelectorAll('[style]').length;
    const styles = document.querySelectorAll('style').length;
    const handlers = [...document.querySelectorAll('*')].reduce(
      (n, e) => n + [...e.attributes].filter(a => /^on/i.test(a.name)).length, 0
    );
    const origins = {};
    for (const e of document.querySelectorAll('script[src],link[href],img[src],source[src],video[src],iframe[src]')) {
      const a = e.getAttribute('src') || e.getAttribute('href');
      try {
        const u = new URL(a, location.href);
        if (u.origin !== location.origin) {
          origins[u.origin] = (origins[u.origin] || 0) + 1;
        }
      } catch {}
    }
    return {
      inlineScripts,
      inlineStyleAttributes: styleAttrs,
      styleBlocks: styles,
      inlineEventHandlers: handlers,
      externalOrigins: origins
    };
  });

  // Perform interaction clicks if defined
  const clickedActions = [];
  if (opts.interactions) {
    for (const act of opts.interactions) {
      try {
        const el = page.locator(act.selector).first();
        if (await el.count()) {
          await el.scrollIntoViewIfNeeded().catch(() => {});
          await el.click({ timeout: 2000 });
          clickedActions.push({ name: act.name, selector: act.selector, success: true });
          if (act.cleanup) {
            await act.cleanup(page);
          }
          await page.waitForTimeout(200);
        } else {
          clickedActions.push({ name: act.name, selector: act.selector, success: false, reason: 'selector not found' });
        }
      } catch (err) {
        clickedActions.push({ name: act.name, selector: act.selector, success: false, reason: err.message.slice(0, 100) });
      }
    }
  }

  await page.waitForTimeout(400);
  const violations = await page.evaluate(() => window.__violations);

  const entry = {
    path,
    canonicalEquivalent: opts.canonicalEquivalent || path,
    status,
    reportOnlyHeader: cspHeader,
    inventory,
    clickedActions,
    violationsCount: violations.length,
    violations
  };

  pages.push(entry);
  await context.close();
  return entry;
}

// 1. Storefront Home `/`
await inspectPage('/', {
  interactions: [
    {
      name: 'cart',
      selector: '[data-open-cart]',
      cleanup: async (p) => { await p.keyboard.press('Escape'); }
    },
    {
      name: 'search',
      selector: '[data-open-search]',
      cleanup: async (p) => { await p.keyboard.press('Escape'); }
    },
    {
      name: 'menu',
      selector: 'button.mobile-menu-button',
      cleanup: async (p) => { await p.keyboard.press('Escape'); }
    },
    {
      name: 'lang',
      selector: '.lang-switcher [data-set-lang="en"]',
      cleanup: async () => {}
    },
    {
      name: 'drawer-profile',
      selector: '#btn-header-profile',
      cleanup: async (p) => { await p.keyboard.press('Escape'); }
    },
    {
      name: 'onclick-ribbon-gold',
      selector: '#btn-ribbon-gold',
      cleanup: async () => {}
    },
    {
      name: 'onclick-wax-seal',
      selector: '#welcome-wax-seal',
      cleanup: async () => {}
    }
  ]
});

// 2. Prototype `/prototype/`
await inspectPage('/prototype/', {
  interactions: [
    {
      name: 'cart-onclick',
      selector: 'button.action-btn[onclick*="openCartDrawer"]',
      cleanup: async (p) => {
        const closeBtn = p.locator('button.btn-close[onclick*="closeCartDrawer"]').first();
        if (await closeBtn.count()) await closeBtn.click();
        else await p.keyboard.press('Escape');
      }
    },
    {
      name: 'menu-drawer-onclick',
      selector: '#btn-mobile-nav-toggle',
      cleanup: async (p) => {
        const closeBtn = p.locator('button.btn-close[onclick*="closeMobileNavDrawer"]').first();
        if (await closeBtn.count()) await closeBtn.click();
        else await p.keyboard.press('Escape');
      }
    },
    {
      name: 'profile-drawer-onclick',
      selector: '#btn-header-profile',
      cleanup: async (p) => {
        const closeBtn = p.locator('button.btn-close[onclick*="closeProfileDrawer"]').first();
        if (await closeBtn.count()) await closeBtn.click();
        else await p.keyboard.press('Escape');
      }
    },
    {
      name: 'keyboard-shortcuts-onclick',
      selector: '#shortcuts-toggle-btn',
      cleanup: async (p) => { await p.keyboard.press('Escape'); }
    },
    {
      name: 'category-filter-onclick',
      selector: 'button.cat-pill[onclick*="filterCategory"]',
      cleanup: async () => {}
    },
    {
      name: 'rendezvous-onclick',
      selector: 'a.announcement-rv-btn[onclick*="openRendezvousModal"]',
      cleanup: async (p) => { await p.keyboard.press('Escape'); }
    }
  ]
});

// 3. PDP `/produk/celeste-trench`
await inspectPage('/produk/celeste-trench');

// 4. Admin Login `/admin/login`
await inspectPage('/admin/login');

// 5. Customer Login `/login`
await inspectPage('/login');

// 6. Customer Account requested as `/account`
await inspectPage('/account', { canonicalEquivalent: '/akun' });

// 7. Customer Account authenticated canonical `/akun`
await inspectPage('/akun', {
  auth: {
    loginUrl: '/login',
    email: 'security-qa@example.test',
    password: process.env.QA_PASSWORD
  }
});

// 8. Admin Dashboard requested as `/admin/dashboard`
await inspectPage('/admin/dashboard', { canonicalEquivalent: '/admin' });

// 9. Admin Dashboard authenticated canonical `/admin`
await inspectPage('/admin', {
  auth: {
    loginUrl: '/admin/login',
    email: 'security-qa@example.test',
    password: process.env.QA_PASSWORD
  }
});

await browser.close();

const finalReport = {
  testedAt: new Date().toISOString(),
  candidatePolicy: policy,
  status: 'inventory DONE, enforcement NOT DEPLOYED',
  pages
};

fs.writeFileSync(out + '/csp.json', JSON.stringify(finalReport, null, 2) + '\n');
console.log('CSP Inventory & Harness complete. Scanned ' + pages.length + ' pages.');
