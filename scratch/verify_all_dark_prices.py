from playwright.sync_api import sync_playwright
import time

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        
        # 1. TEST PROTOTYPE
        page = browser.new_page(viewport={"width": 1440, "height": 900})
        page.goto("http://127.0.0.1:8000/prototype/", wait_until="networkidle")
        time.sleep(1)
        
        # Turn on dark mode
        page.evaluate("() => { if (window.Zafira) window.Zafira.setTheme('dark'); else document.documentElement.setAttribute('data-theme', 'dark'); }")
        time.sleep(0.5)
        
        # A. Editorial diptych metrics price
        diptych_price = page.locator(".diptych-metrics .metric-col strong").nth(2)
        diptych_color = diptych_price.evaluate("el => window.getComputedStyle(el).color")
        print(f"Prototype Editorial Diptych Price Color: {diptych_color}")
        assert diptych_color in ["rgb(241, 213, 155)", "rgb(245, 243, 238)"], f"Unexpected diptych color: {diptych_color}"
        
        # Capture Editorial Diptych screenshot
        diptych_section = page.locator(".diptych-row").first
        diptych_section.scroll_into_view_if_needed()
        time.sleep(0.5)
        page.screenshot(path="docs/06-prototype/eval_v45_dark_prices_editorial_diptych.png")
        
        # B. Gallery Grid Cards
        # Switch to museum gallery mode or scroll down to gallery-grid
        page.locator(".view-mode-btn").nth(1).click() # Click Museum Grid view mode
        time.sleep(0.5)
        
        card_price = page.locator(".gallery-grid .product-price").first
        card_color = card_price.evaluate("el => window.getComputedStyle(el).color")
        print(f"Prototype Catalog Card Price Color: {card_color}")
        assert card_color in ["rgb(245, 243, 238)", "rgb(241, 213, 155)"], f"Unexpected card price color: {card_color}"
        
        card_price.scroll_into_view_if_needed()
        time.sleep(0.5)
        page.screenshot(path="docs/06-prototype/eval_v45_dark_prices_gallery_grid.png")
        
        # C. Studio PDP Split View
        page.locator(".gallery-grid .btn-quick").first.click()
        time.sleep(0.5)
        studio_price = page.locator(".studio-price")
        studio_color = studio_price.evaluate("el => window.getComputedStyle(el).color")
        print(f"Prototype Studio PDP Price Color: {studio_color}")
        assert studio_color in ["rgb(241, 213, 155)", "rgb(245, 243, 238)"], f"Unexpected studio price color: {studio_color}"
        page.screenshot(path="docs/06-prototype/eval_v45_dark_prices_studio_pdp.png")
        
        # D. Cart Drawer
        page.evaluate("() => { if (typeof quickAddToCart === 'function') quickAddToCart(1); if (typeof openCartDrawer === 'function') openCartDrawer(); }")
        time.sleep(0.5)
        
        cart_item_price = page.locator(".cart-item-price").first
        cart_price_color = cart_item_price.evaluate("el => window.getComputedStyle(el).color")
        print(f"Prototype Cart Item Price Color: {cart_price_color}")
        assert cart_price_color in ["rgb(245, 243, 238)", "rgb(241, 213, 155)"], f"Unexpected cart item price color: {cart_price_color}"
        
        subtotal = page.locator("#cart-subtotal")
        subtotal_color = subtotal.evaluate("el => window.getComputedStyle(el).color")
        print(f"Prototype Cart Subtotal Color: {subtotal_color}")
        assert subtotal_color in ["rgb(241, 213, 155)", "rgb(245, 243, 238)"], f"Unexpected cart subtotal color: {subtotal_color}"
        
        page.screenshot(path="docs/06-prototype/eval_v45_dark_prices_cart_drawer.png")
        
        # 2. TEST STOREFRONT
        page_store = browser.new_page(viewport={"width": 1440, "height": 900})
        page_store.goto("http://127.0.0.1:8000/", wait_until="networkidle")
        time.sleep(1)
        page_store.evaluate("() => { document.documentElement.setAttribute('data-theme', 'dark'); localStorage.setItem('zafira_theme', 'dark'); }")
        time.sleep(0.5)
        
        sf_price = page_store.locator(".product-info p[translate='no']").first
        sf_color = sf_price.evaluate("el => window.getComputedStyle(el).color")
        print(f"Storefront Product Price Color: {sf_color}")
        assert sf_color in ["rgb(242, 238, 232)", "rgb(245, 243, 238)", "rgb(241, 213, 155)"], f"Unexpected storefront price color: {sf_color}"
        
        sf_price.scroll_into_view_if_needed()
        time.sleep(0.5)
        page_store.screenshot(path="docs/06-prototype/eval_v45_dark_prices_storefront.png")
        
        # 3. TEST PRODUCT DETAIL PAGE (PDP)
        page_pdp = browser.new_page(viewport={"width": 1440, "height": 900})
        page_pdp.goto("http://127.0.0.1:8000/produk/sac-seraphine", wait_until="networkidle")
        time.sleep(1)
        page_pdp.evaluate("() => { document.documentElement.setAttribute('data-theme', 'dark'); localStorage.setItem('zafira_theme', 'dark'); }")
        time.sleep(0.5)
        
        pdp_price = page_pdp.locator(".product-detail-price")
        pdp_color = pdp_price.evaluate("el => window.getComputedStyle(el).color")
        print(f"Storefront PDP Price Color: {pdp_color}")
        assert pdp_color in ["rgb(241, 213, 155)", "rgb(242, 238, 232)", "rgb(245, 243, 238)"], f"Unexpected pdp price color: {pdp_color}"
        
        pdp_price.scroll_into_view_if_needed()
        time.sleep(0.5)
        page_pdp.screenshot(path="docs/06-prototype/eval_v45_dark_prices_pdp.png")
        
        browser.close()
        print("ALL DARK PRICES VERIFIED AND SCREENSHOTS CAPTURED SUCCESSFULLY!")

if __name__ == "__main__":
    run()
