async page => {
 const out='docs/execution/evidence/2026-10-14/f-findings-retest/';
 const results=[];
 for(const route of ['/prototype/','/']) {
  for(const lang of ['en','id','fr','it']) {
   for(const width of [1024,1366,1440,1650]) {
    const height=width===1440?900:width===1650?927:768;
    await page.setViewportSize({width,height});
    await page.goto('http://127.0.0.1:8016'+route);
    await page.evaluate(lang=>{localStorage.setItem('zafira_lang',lang);window.Zafira.setLanguage(lang);},lang);
    await page.evaluate(async()=>{await document.fonts.ready;window.scrollTo(0,0);});
    await page.waitForTimeout(450);
    const data=await page.evaluate(()=>{
     const rect=e=>{const r=e.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom};};
     const visible=e=>!!e.getClientRects().length&&getComputedStyle(e).visibility!=='hidden'&&getComputedStyle(e).display!=='none';
     const describe=e=>({tag:e.tagName,key:e.getAttribute('data-i18n'),class:e.className,text:e.innerText,rect:rect(e),client:[e.clientWidth,e.clientHeight],scroll:[e.scrollWidth,e.scrollHeight],display:getComputedStyle(e).display,position:getComputedStyle(e).position,overflow:[getComputedStyle(e).overflowX,getComputedStyle(e).overflowY]});
     const all=s=>[...document.querySelectorAll(s)].filter(visible).map(describe);
     const intersects=(a,b)=>a.x<b.right&&a.right>b.x&&a.y<b.bottom&&a.bottom>b.y;
     const dock=document.querySelector('.floating-luxury-dock');
     const dr=dock&&rect(dock);
     const overlaps=dr?[...document.querySelectorAll('h1,h2,h3,p,button,a,input,img')].filter(e=>visible(e)&&!dock.contains(e)&&!e.contains(dock)&&intersects(rect(e),dr)&&rect(e).y<innerHeight&&rect(e).bottom>0).map(describe):[];
     const ann=document.querySelector('.announcement-col-center > span[data-i18n="announcement"]');
     let announcement=null;
     if(ann){const r=document.createRange();r.selectNodeContents(ann);announcement={element:describe(ann),textRects:[...r.getClientRects()].map(r=>({x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height})),ancestors:[]};for(let e=ann.parentElement;e&&e!==document.body;e=e.parentElement)announcement.ancestors.push(describe(e));}
     const h=document.querySelector('[data-i18n="maison_title"]');let maison=null;
     if(h){let boxes=[];const walker=document.createTreeWalker(h,NodeFilter.SHOW_TEXT);while(walker.nextNode()){const r=document.createRange();r.selectNodeContents(walker.currentNode);boxes.push(...[...r.getClientRects()].map(r=>({x:r.x,y:r.y,width:r.width,height:r.height})));}maison={element:describe(h),parent:describe(h.parentElement),boxes,lines:[...new Set(boxes.filter(b=>b.width>0).map(b=>Math.round(b.y)))].length};}
     const textNodes=[];const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);while(walker.nextNode()){const n=walker.currentNode,e=n.parentElement;if(!e||['SCRIPT','STYLE'].includes(e.tagName)||!visible(e)||!n.textContent.trim())continue;const r=document.createRange();r.selectNode(n);if([...r.getClientRects()].some(b=>b.width&&b.height))textNodes.push({text:n.textContent.trim(),parent:e.tagName,class:e.className,key:e.getAttribute('data-i18n')});}
     return {url:location.href,locale:document.documentElement.lang,viewport:[innerWidth,innerHeight],scrollY,documentWidth:document.documentElement.scrollWidth,header:all('.sf-header,.sf-nav a,.mobile-nav-toggle,.hero-title,.hero-btn-row button'),dock:dock?describe(dock):null,overlaps,announcement,maison,copy:all('[data-i18n="editorial_title"],[data-i18n^="service_"][data-i18n$="_title"]'),hero:all('.hero-content h1,.hero-content p,.hero-overlay'),textNodes};
    });
    const name=(route==='/'?'store':'prototype')+'-'+lang+'-'+width;
    await page.screenshot({path:out+name+'.png'});
    results.push({name,...data});
   }
  }
 }
 return results;
}
