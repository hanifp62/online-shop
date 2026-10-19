import asyncio
import os
import re
from playwright.async_api import async_playwright

ARTIFACT_DIR = "/Users/hanifpriyambodo/.gemini/antigravity-ide/brain/e53c9b07-a96f-4daa-8c43-6d97e6dcc888"

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(viewport={"width": 1440, "height": 900})
        page = await context.new_page()

        print("=======================================================")
        print("  1. AUDITING LARAVEL STOREFRONT (http://127.0.0.1:8000/)")
        print("=======================================================")
        await page.goto("http://127.0.0.1:8000/", wait_until="networkidle")
        title = await page.title()
        print(f"Title: {title}")
        assert "v3.9" in title, f"Expected v3.9 in title, got: {title}"

        # Check that page text contains zero raw arrows or raw times entities
        body_text = await page.inner_text("body")
        raw_glyph_matches = re.findall(r"[⟶→←↗×−]", body_text)
        print(f"Storefront Raw Glyph Matches: {raw_glyph_matches}")
        assert len(raw_glyph_matches) == 0, f"Found raw glyphs in storefront body: {raw_glyph_matches}"
        print("PASS: Storefront body is 100% free of raw arrows and unicode glyphs.")

        # Check packaging section on main storefront
        pkg_section = page.locator("#packaging-section")
        assert await pkg_section.is_visible(), "Packaging section (#packaging-section) must be visible on main storefront"
        print("PASS: L'Art d'Offrir signature unboxing section is present on main storefront.")

        # Test ribbon switcher on storefront
        ribbon_band = page.locator("#welcome-ribbon-band")
        await page.click("#btn-ribbon-sapphire")
        await page.wait_for_timeout(300)
        sapphire_bg = await ribbon_band.evaluate("el => window.getComputedStyle(el).backgroundColor")
        print(f"Ribbon background after sapphire click: {sapphire_bg}")
        assert "15, 28, 51" in sapphire_bg or "27, 46, 80" in sapphire_bg, f"Expected dark sapphire ribbon, got: {sapphire_bg}"

        # Test wax seal on storefront
        await page.click("#welcome-wax-seal")
        await page.wait_for_timeout(300)
        toast = page.locator(".toast")
        toast_text = await toast.inner_text()
        print(f"Storefront Wax Seal Toast: {toast_text}")
        assert "Segel lilin resmi" in toast_text, "Expected wax seal toast validation"

        # Test Monogram Input and live counter on storefront
        monogram_input = page.locator("#monogram-input")
        await monogram_input.fill("ZFL")
        await page.wait_for_timeout(200)
        counter = await page.locator("#welcome-monogram-counter").inner_text()
        preview = await page.locator("#monogram-preview-display").inner_text()
        print(f"Storefront Monogram filled: {preview}, Counter: {counter}")
        assert counter == "[ 3 / 3 ]", f"Expected [ 3 / 3 ], got {counter}"
        assert preview == "ZFL", f"Expected ZFL, got {preview}"

        # Test Floating dock in Storefront
        dock_badge = await page.locator(".floating-luxury-dock .dock-ver").inner_text()
        print(f"Storefront Dock Version: {dock_badge}")
        assert "v3.9" in dock_badge, f"Expected v3.9 in dock badge, got {dock_badge}"

        # Capture Storefront Screenshot
        shot1 = os.path.join(ARTIFACT_DIR, "eval_storefront_v39.png")
        await page.screenshot(path=shot1, full_page=False)
        print(f"Saved: {shot1}")

        # Capture Unboxing Screenshot
        await pkg_section.scroll_into_view_if_needed()
        await page.wait_for_timeout(400)
        shot2 = os.path.join(ARTIFACT_DIR, "eval_packaging_unboxing_v39.png")
        await page.screenshot(path=shot2, full_page=False)
        print(f"Saved: {shot2}")

        print("\n=======================================================")
        print("  2. AUDITING PROTOTYPE (/prototype/ & /prototype/?p=9)")
        print("=======================================================")
        await page.goto("http://127.0.0.1:8000/prototype/", wait_until="networkidle")
        proto_title = await page.title()
        print(f"Prototype Title: {proto_title}")
        assert "v3.9" in proto_title, f"Expected v3.9 in proto title, got: {proto_title}"

        # Check raw glyphs in prototype storefront
        proto_body_text = await page.inner_text("#storefront-view")
        proto_glyph_matches = re.findall(r"[⟶→←↗×−]", proto_body_text)
        print(f"Prototype Storefront Raw Glyph Matches: {proto_glyph_matches}")
        assert len(proto_glyph_matches) == 0, f"Found raw glyphs in prototype storefront: {proto_glyph_matches}"
        print("PASS: Prototype storefront is 100% free of raw arrows and glyphs.")

        # Check gradients on prototype buttons & surfaces
        btn_gold = page.locator(".btn-gold").first
        btn_bg = await btn_gold.evaluate("el => window.getComputedStyle(el).backgroundImage")
        print(f"btn-gold backgroundImage: '{btn_bg}'")
        assert btn_bg == "none", f"Expected no gradient background on .btn-gold, got: {btn_bg}"

        wax_seal = page.locator(".wax-seal-badge").first
        wax_bg = await wax_seal.evaluate("el => window.getComputedStyle(el).backgroundImage")
        print(f"wax-seal-badge backgroundImage: '{wax_bg}'")
        assert wax_bg == "none", f"Expected no gradient background on .wax-seal-badge, got: {wax_bg}"

        ribbon_sim = page.locator(".ribbon-simulation").first
        ribbon_bg = await ribbon_sim.evaluate("el => window.getComputedStyle(el).backgroundImage")
        print(f"ribbon-simulation backgroundImage: '{ribbon_bg}'")
        assert ribbon_bg == "none", f"Expected no gradient background on .ribbon-simulation, got: {ribbon_bg}"
        print("PASS: All key luxury primitives use 100% solid matte surfaces without AI gradients.")

        # Test Deep Link to Product Studio via URL query param: /prototype/?p=9
        await page.goto("http://127.0.0.1:8000/prototype/?p=9", wait_until="networkidle")
        await page.wait_for_timeout(400)
        studio_view = page.locator("#product-studio-view")
        assert await studio_view.is_visible(), "Product studio view should be active when navigating with ?p=9"
        prod_title = await page.locator(".studio-product-title").inner_text()
        print(f"Loaded Studio Product via Deep Link: {prod_title}")
        assert "Kebaya Lumière" in prod_title, f"Expected Kebaya Lumière, got: {prod_title}"

        # Check studio text for raw glyphs
        studio_text = await studio_view.inner_text()
        studio_glyphs = re.findall(r"[⟶→←↗×−]", studio_text)
        print(f"Studio Raw Glyph Matches: {studio_glyphs}")
        assert len(studio_glyphs) == 0, f"Found raw glyphs in studio: {studio_glyphs}"
        print("PASS: Studio PDP is 100% free of raw arrows and glyphs.")

        # Screenshot Studio
        shot3 = os.path.join(ARTIFACT_DIR, "eval_prototype_studio_v39.png")
        await page.screenshot(path=shot3, full_page=False)
        print(f"Saved: {shot3}")

        # Test Deep Link to MSI Console: /prototype/#msi-view
        await page.goto("http://127.0.0.1:8000/prototype/#msi-view", wait_until="networkidle")
        await page.wait_for_timeout(400)
        admin_view = page.locator("#admin-view")
        assert await admin_view.is_visible(), "Admin MSI view should be active with hash #msi-view"
        print("PASS: Atelier Management Console (MSI) activated cleanly.")

        # Screenshot MSI
        shot4 = os.path.join(ARTIFACT_DIR, "eval_prototype_msi_v39.png")
        await page.screenshot(path=shot4, full_page=False)
        print(f"Saved: {shot4}")

        # Test Mobile Viewport (390 x 844 iPhone 14)
        mobile_context = await browser.new_context(viewport={"width": 390, "height": 844})
        mobile_page = await mobile_context.new_page()
        await mobile_page.goto("http://127.0.0.1:8000/", wait_until="networkidle")
        await mobile_page.wait_for_timeout(300)
        shot5 = os.path.join(ARTIFACT_DIR, "eval_mobile_storefront_v39.png")
        await mobile_page.screenshot(path=shot5, full_page=False)
        print(f"Saved: {shot5}")

        print("\n=======================================================")
        print("  ALL V3.9 ANTI-SLOP & LUXURY PARITY AUDIT GATES PASSED! ")
        print("=======================================================")

        await context.close()
        await mobile_context.close()
        await browser.close()

if __name__ == "__main__":
    asyncio.run(main())
