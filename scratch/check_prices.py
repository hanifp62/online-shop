from playwright.sync_api import sync_playwright

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    
    # Capture dark mode catalog
    page.goto("http://127.0.0.1:8000/prototype/", wait_until="networkidle")
    page.evaluate("() => document.documentElement.setAttribute('data-theme', 'dark')")
    page.screenshot(path="/Users/hanifpriyambodo/Documents/online-shop/docs/06-prototype/eval_v44_dark_prices_catalog.png")
    
    # Capture dark mode PDP studio
    page.evaluate("() => openProductStudio(9)")
    page.screenshot(path="/Users/hanifpriyambodo/Documents/online-shop/docs/06-prototype/eval_v44_dark_prices_studio.png")
    
    # Capture dark mode account ledger
    page.evaluate("() => switchView('account')")
    page.screenshot(path="/Users/hanifpriyambodo/Documents/online-shop/docs/06-prototype/eval_v44_dark_prices_account.png")
    
    browser.close()
print("Screenshots captured successfully!")
