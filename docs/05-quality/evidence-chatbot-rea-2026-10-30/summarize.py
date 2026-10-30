import json, sys, re, collections, urllib.parse
VENDORS = {
 'intercom':r'intercom', 'zendesk':r'zendesk|zdassets|zopim', 'gorgias':r'gorgias', 'tidio':r'tidio',
 'freshchat/freshworks':r'freshchat|freshworks|freshdesk', 'livechat':r'livechatinc|livechat\.com', 'drift':r'drift\.com|driftt',
 'salesforce/sfdc':r'salesforce|force\.com|sfdc|liveagent|embeddedservice', 'genesys':r'genesys|purecloud|mypurecloud',
 'nuance/LivePerson':r'liveperson|lpsnmedia|nuance', 'kustomer':r'kustomer', 'gladly':r'gladly', 'helpshift':r'helpshift',
 'qiscus':r'qiscus', 'sleekflow':r'sleekflow', 'kata.ai':r'kata\.ai', 'crisp':r'crisp\.chat', 'tawk':r'tawk\.to',
 'hubspot':r'hubspot|hs-scripts|usemessages', 'ada':r'ada\.support|ada\.cx', 'whatsapp':r'whatsapp|wa\.me',
 'shopify-inbox/chat':r'shopify-chat|shopify_chat|inbox\.shopify|shopifychat', 'yellow.ai':r'yellow\.ai|yellowmessenger',
 'smooch/sunshine':r'smooch', 'decagon':r'decagon', 'sierra':r'sierra\.ai', 'reamaze':r'reamaze', 'richpanel':r'richpanel',
 'eGain':r'egain', 'oracle-service':r'rightnow|custhelp', 'medallia':r'medallia', 'verint':r'verint|nuancechat', 'glia':r'glia\.com|salemove',
 'chatgpt/openai':r'openai', 'algolia-ai':r'algolia', 'rep.ai/manifest':r'rep\.ai|hellorep', 'octane/ai':r'octaneai', 'zowie':r'zowie',
}
f = sys.argv[1]
d = json.load(open(f))
nr = d['normalized_result']
ev = nr['events']['items']
urls = []
ws = []
for e in ev:
    k = e.get('kind')
    u = (e.get('url') or {}).get('url') if isinstance(e.get('url'), dict) else None
    if k == 'request' and u: urls.append((u, e.get('resource_type')))
    if k and k.startswith('websocket') and u: ws.append((k,u))
    if k in ('frame-navigated','frame-attached') and u: urls.append((u,'frame'))
origins = collections.Counter(urllib.parse.urlsplit(u).netloc for u,_ in urls)
hits = collections.defaultdict(set)
for u,rt in urls + [(u,'ws') for _,u in ws]:
    for v,p in VENDORS.items():
        if re.search(p, u, re.I): hits[v].add(urllib.parse.urlsplit(u).netloc + urllib.parse.urlsplit(u).path[:60] + f' [{rt}]')
print('FILE', f.split('/')[-1], 'evidence', d['evidence_id'][:20], 'completeness', nr['completeness']['status'], 'events', nr['events']['retained'], 'dropped', nr['events']['dropped'])
print('final_url', nr['steps'][-1]['after_url']['url'])
print('requests', len(urls), 'distinct_origins', len(origins))
print('top_origins', ', '.join(f'{o}:{c}' for o,c in origins.most_common(25)))
print('websockets', sorted({u for _,u in ws})[:10])
for v,s in hits.items(): print('VENDOR', v, '->', sorted(s)[:6])
# accessibility text
acc = None
for st in nr['steps']:
    a = st['artifacts'].get('accessibility')
    if a and a.get('state')=='captured': acc = a['value']['text']
if acc:
    lines = [l.strip() for l in acc.splitlines() if re.search(r'chat|whats ?app|bantuan|help|customer (care|service)|hubungi|contact|concierge|advisor|ask|tanya|assistant|asisten|layanan pelanggan|client service|live', l, re.I)]
    print('a11y_bytes', len(acc), 'matching_lines', len(lines))
    for l in lines[:25]: print('  A11Y', l[:160])
else:
    print('a11y: not captured')
for st in nr['steps']:
    if st.get('error'): print('STEP_ERROR', st['step_id'], str(st['error'])[:200])
print('limitations', [l[:120] for l in nr.get('limitations',[])][:4])
