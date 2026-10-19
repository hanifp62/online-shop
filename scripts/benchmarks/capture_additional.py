import asyncio
from playwright.async_api import async_playwright
import os

ARTIFACT_DIR = "/Users/hanifpriyambodo/.gemini/antigravity-ide/brain/e53c9b07-a96f-4daa-8c43-6d97e6dcc888"

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(viewport={"width": 1440, "height": 900})
        page = await context.new_page()

        # 1. Storefront Light Mode (ID)
        await page.goto("http://127.0.0.1:8000/", wait_until="networkidle")
        await page.evaluate("window.Zafira.setTheme('light'); window.Zafira.setLanguage('id');")
        await page.wait_for_timeout(500)
        await page.screenshot(path=os.path.join(ARTIFACT_DIR, "eval_storefront_light_v41.png"))
        print("Captured eval_storefront_light_v41.png")

        # 2. Storefront English Mode (EN)
        await page.evaluate("window.Zafira.setLanguage('en');")
        await page.wait_for_timeout(500)
        await page.screenshot(path=os.path.join(ARTIFACT_DIR, "eval_storefront_english_v41.png"))
        print("Captured eval_storefront_english_v41.png")

        # 3. Prototype Light Mode (ID)
        await page.goto("http://127.0.0.1:8000/prototype/", wait_until="networkidle")
        await page.evaluate("window.Zafira.setTheme('light'); window.Zafira.setLanguage('id');")
        await page.wait_for_timeout(500)
        await page.screenshot(path=os.path.join(ARTIFACT_DIR, "eval_prototype_light_v41.png"))
        print("Captured eval_prototype_light_v41.png")

        # 4. Prototype English Mode (EN)
        await page.evaluate("window.Zafira.setLanguage('en');")
        await page.wait_for_timeout(500)
        await page.screenshot(path=os.path.join(ARTIFACT_DIR, "eval_prototype_english_v41.png"))
        print("Captured eval_prototype_english_v41.png")

        # 5. Prototype Studio PDP View (Dark Mode, French)
        await page.evaluate("window.Zafira.setTheme('dark'); window.Zafira.setLanguage('fr'); openProductStudio(9);")
        await page.wait_for_timeout(600)
        await page.screenshot(path=os.path.join(ARTIFACT_DIR, "eval_prototype_studio_dark_v41.png"))
        print("Captured eval_prototype_studio_dark_v41.png")

        # 6. Prototype MSI Console View (Dark Mode, Italian)
        await page.evaluate("window.Zafira.setTheme('dark'); window.Zafira.setLanguage('it'); switchView('admin');")
        await page.wait_for_timeout(600)
        await page.screenshot(path=os.path.join(ARTIFACT_DIR, "eval_prototype_msi_dark_v41.png"))
        print("Captured eval_prototype_msi_dark_v41.png")

        await browser.close()
        print("All additional captures completed successfully!")

if __name__ == "__main__":
    asyncio.run(run())
