import asyncio
from playwright.async_api import async_playwright
import os

ARTIFACT_DIR = "/Users/hanifpriyambodo/.gemini/antigravity-ide/brain/e53c9b07-a96f-4daa-8c43-6d97e6dcc888"

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(viewport={"width": 1440, "height": 900})
        page = await context.new_page()

        # 1. Test Storefront Dark Mode
        print("Navigating to Laravel Storefront http://127.0.0.1:8000/ ...")
        await page.goto("http://127.0.0.1:8000/", wait_until="networkidle")
        await page.wait_for_timeout(1000)

        # Apply dark mode via UI button
        print("Switching Storefront to Dark Mode...")
        dark_btn = page.locator('button[data-set-theme="dark"]').first
        if await dark_btn.count() > 0:
            await dark_btn.click()
            await page.wait_for_timeout(500)
        else:
            await page.evaluate("window.Zafira.setTheme('dark')")
            await page.wait_for_timeout(500)

        theme_attr = await page.evaluate("document.documentElement.getAttribute('data-theme')")
        print(f"Storefront Theme Attribute: {theme_attr}")
        assert theme_attr == "dark", f"Expected theme 'dark', got '{theme_attr}'"

        path_sf_dark = os.path.join(ARTIFACT_DIR, "eval_storefront_dark_v41.png")
        await page.screenshot(path=path_sf_dark, full_page=False)
        print(f"Captured: {path_sf_dark}")

        # 2. Test Storefront French Mode
        print("Switching Storefront to French (FR)...")
        fr_btn = page.locator('button[data-set-lang="fr"]').first
        if await fr_btn.count() > 0:
            await fr_btn.click()
            await page.wait_for_timeout(500)
        else:
            await page.evaluate("window.Zafira.setLanguage('fr')")
            await page.wait_for_timeout(500)

        lang_attr = await page.evaluate("document.documentElement.getAttribute('lang')")
        print(f"Storefront Lang Attribute: {lang_attr}")
        assert lang_attr == "fr", f"Expected lang 'fr', got '{lang_attr}'"

        # Check translated text on Hero title or Rendezvous
        announcement_text = await page.locator('[data-i18n="announcement"]').first.inner_text()
        print(f"Storefront French Announcement: {announcement_text}")

        path_sf_fr = os.path.join(ARTIFACT_DIR, "eval_storefront_french_v41.png")
        await page.screenshot(path=path_sf_fr, full_page=False)
        print(f"Captured: {path_sf_fr}")

        # 3. Test Interactive Prototype Dark Mode
        print("Navigating to Prototype http://127.0.0.1:8000/prototype/ ...")
        await page.goto("http://127.0.0.1:8000/prototype/", wait_until="networkidle")
        await page.wait_for_timeout(1000)

        # Set theme dark
        print("Setting Prototype to Dark Mode...")
        proto_dark_btn = page.locator('button[data-set-theme="dark"]').first
        if await proto_dark_btn.count() > 0:
            await proto_dark_btn.click()
            await page.wait_for_timeout(500)
        else:
            await page.evaluate("window.Zafira.setTheme('dark')")
            await page.wait_for_timeout(500)

        proto_theme = await page.evaluate("document.documentElement.getAttribute('data-theme')")
        print(f"Prototype Theme Attribute: {proto_theme}")
        assert proto_theme == "dark", f"Expected proto theme 'dark', got '{proto_theme}'"

        path_proto_dark = os.path.join(ARTIFACT_DIR, "eval_prototype_dark_v41.png")
        await page.screenshot(path=path_proto_dark, full_page=False)
        print(f"Captured: {path_proto_dark}")

        # 4. Test Interactive Prototype Italian Mode
        print("Setting Prototype to Italian (IT)...")
        proto_it_btn = page.locator('button[data-set-lang="it"]').first
        if await proto_it_btn.count() > 0:
            await proto_it_btn.click()
            await page.wait_for_timeout(500)
        else:
            await page.evaluate("window.Zafira.setLanguage('it')")
            await page.wait_for_timeout(500)

        proto_lang = await page.evaluate("document.documentElement.getAttribute('lang')")
        print(f"Prototype Lang Attribute: {proto_lang}")
        assert proto_lang == "it", f"Expected proto lang 'it', got '{proto_lang}'"

        proto_announcement = await page.locator('[data-i18n="announcement"]').first.inner_text()
        print(f"Prototype Italian Announcement: {proto_announcement}")

        path_proto_it = os.path.join(ARTIFACT_DIR, "eval_prototype_italian_v41.png")
        await page.screenshot(path=path_proto_it, full_page=False)
        print(f"Captured: {path_proto_it}")

        # 5. Anti-Slop Audit: Check Gradients and Emojis
        print("Auditing Gradients and Emojis across all rendered elements...")
        audit_results = await page.evaluate(r'''() => {
            const allElements = document.querySelectorAll('*');
            let gradientCount = 0;
            let gradientElements = [];
            const emojiRegex = /[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}]/u;
            let emojiCount = 0;
            let emojiMatches = [];

            allElements.forEach(el => {
                const style = window.getComputedStyle(el);
                const bg = style.backgroundImage;
                if (bg && (bg.includes('linear-gradient') || bg.includes('radial-gradient'))) {
                    // Check if it's the subtle film grain or an actual gradient
                    if (!el.classList.contains('film-grain-overlay')) {
                        gradientCount++;
                        gradientElements.push({ tag: el.tagName, class: el.className, bg: bg.substring(0, 50) });
                    }
                }
                const text = el.innerText || '';
                if (emojiRegex.test(text)) {
                    emojiCount++;
                    emojiMatches.push({ tag: el.tagName, class: el.className, text: text.substring(0, 30) });
                }
            });

            return { gradientCount, gradientElements, emojiCount, emojiMatches };
        }''')

        print(f"Audit Results: Gradients = {audit_results['gradientCount']}, Emojis = {audit_results['emojiCount']}")
        if audit_results['gradientCount'] > 0:
            print("Gradients found:", audit_results['gradientElements'][:5])
        if audit_results['emojiCount'] > 0:
            print("Emojis found:", audit_results['emojiMatches'][:5])

        await browser.close()
        print("Playwright test completed successfully!")

if __name__ == "__main__":
    asyncio.run(run())
