from playwright.sync_api import sync_playwright

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    page.goto("http://127.0.0.1:8000/prototype/", wait_until="networkidle")
    page.evaluate("() => document.documentElement.setAttribute('data-theme', 'dark')")
    
    # Scroll to gallery-grid
    page.evaluate("() => document.querySelector('.gallery-grid').scrollIntoView()")
    page.screenshot(path="/Users/hanifpriyambodo/Documents/online-shop/docs/06-prototype/eval_v44_dark_prices_catalog_grid.png")
    
    browser.close()
print("Catalog grid screenshot captured!")
