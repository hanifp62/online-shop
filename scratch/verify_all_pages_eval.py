import os
import sys
import time
from playwright.sync_api import sync_playwright

OUTPUT_DIR = "/Users/hanifpriyambodo/Documents/online-shop/docs/06-prototype"
os.makedirs(OUTPUT_DIR, exist_ok=True)

def run_evaluation():
    results = {}
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        
        # 1. Desktop Context (1440x900)
        context_desktop = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context_desktop.new_page()
        
        print("--> 1. Evaluating Storefront Desktop (http://127.0.0.1:8000/)...")
        page.goto("http://127.0.0.1:8000/", wait_until="networkidle")
        time.sleep(1)
        
        # Verify 0px overflow
        has_no_overflow = page.evaluate("() => document.documentElement.scrollWidth <= window.innerWidth")
        results["storefront_desktop_no_overflow"] = has_no_overflow
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_storefront_desktop.png"))
        print(f"Storefront desktop overflow test: {'PASS' if has_no_overflow else 'FAIL'}")
        
        # Test Opening Welcome Profile Drawer
        print("--> Testing VIP Profile Drawer on Storefront...")
        page.click("#btn-header-profile")
        time.sleep(0.6)
        profile_drawer_open = page.evaluate("() => document.getElementById('welcome-profile-drawer').classList.contains('open')")
        results["profile_drawer_opened"] = profile_drawer_open
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_profile_drawer.png"))
        print(f"Profile drawer open test: {'PASS' if profile_drawer_open else 'FAIL'}")
        
        # Close Drawer via Escape
        page.keyboard.press("Escape")
        time.sleep(0.4)
        
        # Test Cart Drawer
        print("--> Testing Cart Drawer on Storefront...")
        page.click("[data-open-cart]")
        time.sleep(0.6)
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_cart_drawer.png"))
        page.keyboard.press("Escape")
        time.sleep(0.4)
        
        # Test Shortcuts Modal (?)
        print("--> Testing Shortcuts Modal on Storefront...")
        page.keyboard.press("?")
        time.sleep(0.5)
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_shortcuts_modal.png"))
        page.keyboard.press("Escape")
        time.sleep(0.4)
        
        # 2. Product Detail Page (PDP) (/produk/kebaya-lumiere)
        print("--> 2. Evaluating Product Detail Page (http://127.0.0.1:8000/produk/kebaya-lumiere)...")
        page.goto("http://127.0.0.1:8000/produk/kebaya-lumiere", wait_until="networkidle")
        time.sleep(1)
        pdp_overflow = page.evaluate("() => document.documentElement.scrollWidth <= window.innerWidth")
        results["pdp_desktop_no_overflow"] = pdp_overflow
        
        # Test PDP Monogram studio input and size selection
        try:
            page.fill("#pdp-monogram-input", "VIC")
            time.sleep(0.3)
            # Click size pill FR 40
            page.click(".size-pill:nth-child(3)")
            time.sleep(0.3)
        except Exception as e:
            print(f"PDP interaction note: {e}")
            
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_pdp_desktop.png"))
        print(f"PDP desktop overflow test: {'PASS' if pdp_overflow else 'FAIL'}")
        
        # 3. Prototype Single Page Application (http://127.0.0.1:8000/prototype/)
        print("--> 3. Evaluating Prototype Single Page Application...")
        page.goto("http://127.0.0.1:8000/prototype/", wait_until="networkidle")
        time.sleep(1)
        
        # Studio PDP View
        print("--> Opening Prototype Studio PDP...")
        page.evaluate("() => openProductStudio(9)")
        time.sleep(0.8)
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_prototype_studio_view.png"))
        
        # VIP Client Account Portal (Light Mode)
        print("--> Opening Prototype VIP Client Account Portal (Light Mode)...")
        page.evaluate("() => { if (window.zafiraCore) window.zafiraCore.setTheme('light'); switchView('account'); }")
        time.sleep(0.8)
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_account_light.png"))
        
        # VIP Client Account Portal (Dark Mode)
        print("--> Testing Dark Mode in VIP Client Account Portal...")
        page.evaluate("() => { if (window.zafiraCore) window.zafiraCore.setTheme('dark'); }")
        time.sleep(0.6)
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_account_dark.png"))
        
        # Atelier MSI Command Center
        print("--> Opening Prototype Atelier MSI Command Center...")
        page.evaluate("() => switchView('admin')")
        time.sleep(0.8)
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_admin_dashboard.png"))
        
        # Modals in Prototype: Work Order & Authenticity Certificate
        print("--> Testing Work Order Fiche de Travail Modal...")
        page.evaluate("() => openWorkOrderModal('ord-1')")
        time.sleep(0.6)
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_work_order_modal.png"))
        page.evaluate("() => closeAllModals()")
        time.sleep(0.4)
        
        print("--> Testing Certificate of Authenticity Modal...")
        page.evaluate("() => openAuthenticityModal(store.concierge[0].id)")
        time.sleep(0.6)
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_certificate_modal.png"))
        page.evaluate("() => closeAllModals()")
        time.sleep(0.4)
        
        # 4. Mobile Context (390x844 iPhone 14/15)
        print("--> 4. Evaluating Storefront Mobile (390x844)...")
        context_mobile = browser.new_context(viewport={"width": 390, "height": 844}, is_mobile=True)
        mobile_page = context_mobile.new_page()
        mobile_page.goto("http://127.0.0.1:8000/", wait_until="networkidle")
        time.sleep(1)
        
        mobile_no_overflow = mobile_page.evaluate("() => document.documentElement.scrollWidth <= window.innerWidth")
        results["storefront_mobile_no_overflow"] = mobile_no_overflow
        mobile_page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_storefront_mobile.png"))
        print(f"Storefront mobile overflow test: {'PASS' if mobile_no_overflow else 'FAIL'}")
        
        # 5. Auth / Login Page View
        print("--> 5. Evaluating Login Page (http://127.0.0.1:8000/login)...")
        page.goto("http://127.0.0.1:8000/login", wait_until="networkidle")
        time.sleep(0.8)
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_login_view.png"))
        
        # 6. Admin Login View
        print("--> 6. Evaluating Admin Login Page (http://127.0.0.1:8000/admin/login)...")
        page.goto("http://127.0.0.1:8000/admin/login", wait_until="networkidle")
        time.sleep(0.8)
        page.screenshot(path=os.path.join(OUTPUT_DIR, "eval_v44_admin_login_view.png"))
        
        browser.close()
        
    print("\n==========================================")
    print("ALL EVALUATIONS AND SCREENSHOTS COMPLETED!")
    print("Results:", results)
    print("==========================================")

if __name__ == "__main__":
    run_evaluation()
