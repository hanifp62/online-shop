const fs = require('fs');
const assert = require('assert/strict');
const {chromium} = require('/Users/hanifpriyambodo/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const root = '/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-final-8018';
const base = 'http://127.0.0.1:8018';
const ev = __dirname;
const results = [];
(async () => {
  const identity = await fetch(base + '/manual/identity.txt');
  assert.equal(await identity.text(), fs.readFileSync(root + '/public/manual/identity.txt', 'utf8'));
  const creds = JSON.parse(fs.readFileSync(root + '/private-runtime/credentials.json', 'utf8'));
  const browser = await chromium.launch({headless:true, executablePath:'/Users/hanifpriyambodo/Library/Caches/ms-playwright/chromium-1228/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing'});
  const ctx = await browser.newContext({viewport:{width:1280,height:900}, reducedMotion:'reduce'});
  const page = await ctx.newPage();
  const errors=[]; page.on('pageerror', e=>errors.push(e.message));
  const shot = name => page.screenshot({path:ev+'/'+name+'.png', fullPage:true});
  const check = async (name, fn) => { try { results.push({name,status:'PASS',utc:new Date().toISOString(), evidence:await fn()}); } catch(e) { results.push({name,status:'FAIL',utc:new Date().toISOString(),error:e.message.split('\n')[0]}); await shot('failure-'+name).catch(()=>{}); } fs.writeFileSync(ev+'/browser-results.json',JSON.stringify({results,pageErrors:errors},null,2)); };
  const cart = async () => (await ctx.request.get(base+'/cart',{headers:{Accept:'application/json'}})).json();
  await check('customer-login',async()=>{
    await page.goto(base+'/login'); await page.locator('#email').fill(creds.customer.email); await page.locator('#password').fill(creds.customer.password);
    await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/login')),page.locator('button[type=submit]').click()]);
    assert.equal((await ctx.request.get(base+'/akun')).status(),200); return {authenticated:true};
  });
  await check('home-add-toast-count',async()=>{
    await page.goto(base+'/'); await page.locator('[data-product-card][data-product-id="1"] [data-card-add]').click();
    await page.waitForFunction(()=>document.querySelector('.cart-count')?.textContent==='1');
    const toast=await page.locator('.toast.show [data-toast-message]').innerText(); assert.ok(toast.includes('Synthetic Sapphire') && toast.includes('ditambahkan'),toast);
    assert.equal((await cart()).items[0].quantity,1); await shot('home-add'); return {count:1,toast:await page.locator('[data-toast-message]').innerText()};
  });
  await check('rapid-three-plus',async()=>{
    await page.locator('[data-open-cart]').click();
    await page.locator('[data-quantity="plus"]').evaluate(el=>{el.click();el.click();el.click();});
    await page.waitForFunction(()=>document.querySelector('.quantity-control span')?.textContent==='4');
    assert.equal((await cart()).items[0].quantity,4); await shot('drawer-quantity'); return {before:1,clicks:3,after:4};
  });
  await check('remove-undo',async()=>{
    await page.locator('[data-remove-cart]').click(); await page.locator('[data-toast-action]').waitFor({state:'visible'});
    assert.equal((await cart()).items.length,0); await page.locator('[data-toast-action]').click();
    await page.waitForFunction(()=>document.querySelector('.quantity-control span')?.textContent==='4');
    assert.equal((await cart()).items[0].quantity,4); await shot('drawer-undo'); return {removed:true,restoredQuantity:4};
  });
  await check('catalog-add-feedback',async()=>{
    await page.goto(base+'/koleksi'); const form=page.locator('form[action$="/cart/items"]').filter({has:page.locator('input[name="product_id"][value="1"]')}).first();
    await form.locator('button[type="submit"]').click(); await page.waitForFunction(()=>document.querySelector('#catalog-feedback')?.textContent.includes('Jumlah 1'));
    assert.equal((await cart()).items[0].quantity,1); await shot('catalog-add');
    return {quantity:1,feedback:await page.locator('#catalog-feedback').innerText()};
  });
  await check('catalog-toast-count',async()=>{
    assert.ok(await page.locator('.toast.show').count(),'Catalog has no shared toast');
    assert.equal(await page.locator('.cart-count').first().innerText(),'1'); return {toast:true,count:1};
  });
  await check('checkout-summary',async()=>{
    const data=await cart(); await page.goto(base+'/belanja'); await page.waitForFunction(()=>document.querySelector('#feedback')?.textContent==='Tas belanja siap.');
    const lines=[];
    for(const item of data.items){const row=page.locator('#cart-list .item').filter({hasText:item.product_title});const expected=`Rp${Number(item.unit_price).toLocaleString('id-ID')} × ${item.quantity} = Rp${Number(item.subtotal).toLocaleString('id-ID')}`;assert.ok((await row.innerText()).includes(expected));lines.push(expected);}
    assert.equal(await page.locator('#cart-total').innerText(),'Subtotal: Rp'+Number(data.subtotal).toLocaleString('id-ID'));
    await shot('checkout-summary'); return {lines,subtotal:data.subtotal};
  });
  await check('place-order-reference',async()=>{
    await page.locator('#checkout-button').click(); await page.waitForFunction(()=>/^Pesanan #\d+ dibuat:/.test(document.querySelector('#feedback')?.textContent||''));
    const confirmation=await page.locator('#feedback').innerText(); assert.match(confirmation,/pending \/ belum dibayar/); assert.equal((await cart()).items.length,0);
    await shot('order-confirmation'); return {confirmation};
  });
  for(const width of [390,320]) await check('catalog-mobile-'+width,async()=>{
    await page.setViewportSize({width,height:844}); await page.goto(base+'/koleksi'); await page.waitForTimeout(700);
    const dims=await page.evaluate(()=>({viewport:innerWidth,scroll:document.documentElement.scrollWidth})); assert.ok(dims.scroll<=width,JSON.stringify(dims));
    assert.equal(await page.locator('#catalog-filters').evaluate(e=>e.open),false); await page.locator('#catalog-filters summary').click(); assert.equal(await page.locator('#catalog-filters').evaluate(e=>e.open),true);
    const openDims=await page.evaluate(()=>({viewport:innerWidth,scroll:document.documentElement.scrollWidth})); assert.ok(openDims.scroll<=width,JSON.stringify(openDims)); await shot('catalog-'+width); return {closed:dims,open:openDims,disclosureOpened:true};
  });
  await check('admin-login-pdf-download',async()=>{
    await page.setViewportSize({width:1280,height:900}); await page.goto(base+'/admin/login'); await page.locator('#email').fill(creds.admin.email); await page.locator('#password').fill(creds.admin.password);
    await Promise.all([page.waitForURL(u=>u.pathname==='/admin'),page.locator('button[type=submit]').click()]); await page.goto(base+'/admin/orders');
    const link=page.locator('a[href*="reports/orders.pdf"]').first();
    const [download,response]=await Promise.all([page.waitForEvent('download'),page.waitForResponse(r=>r.url().includes('/admin/reports/orders.pdf')),link.click()]);
    assert.equal(response.status(),200); assert.match(response.headers()['content-type'],/^application\/pdf/);
    await download.saveAs(ev+'/admin-report.pdf'); const data=fs.readFileSync(ev+'/admin-report.pdf'); assert.equal(data.subarray(0,5).toString(),'%PDF-'); await shot('admin-orders'); return {status:response.status(),contentType:response.headers()['content-type'],bytes:data.length};
  });
  await browser.close(); fs.writeFileSync(ev+'/browser-results.json',JSON.stringify({results,pageErrors:errors},null,2)); console.log(JSON.stringify({results,pageErrors:errors},null,2));
})().catch(e=>{console.error(e.message);process.exitCode=1;});
