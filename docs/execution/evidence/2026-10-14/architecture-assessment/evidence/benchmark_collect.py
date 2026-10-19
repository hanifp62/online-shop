"""Fetch public official policy pages for the ten named global marketplaces.

Scope: public documentation only. No application HTTP, no credentials, no login
walls, no scraping of private data. Pages that block automated access or render
policy text through client-side JavaScript are recorded as UNKNOWN, never
invented or paraphrased from memory.
"""
import concurrent.futures, datetime, html, json, pathlib, re, urllib.request

# One primary source per user-named retailer, plus a secondary when the primary
# is known to block automated agents.
SOURCES = [
    ('Amazon', 'https://www.amazon.com/gp/help/customer/display.html?nodeId=GKM69DUUYKQWKWX7',
     'https://www.aboutamazon.com/news/retail'),
    ('Alibaba/Tmall', 'https://rulechannel.alibaba.com/icbu?type=detail&ruleId=2041&cId=1306',
     'https://helpcenter.alibaba.com/s/buyer?categoryId=1000088727&questionId2=20147614&pageId=128&language=en'),
    ('JD.com', 'https://help.jd.com/user/issue/109-188.html',
     'https://ir.jd.com/'),
    ('PDD/Temu', 'https://www.temu.com/return-and-refund-policy.html',
     'https://investor.pddholdings.com/'),
    ('Walmart', 'https://www.walmart.com/help/article/returns/87f4b5a3d5b6427b8f8b4a8c2d8e5d8f',
     'https://corporate.walmart.com/policies'),
    ('eBay', 'https://www.ebay.com/help/buying/returns-refunds/return-item-refund?id=4041',
     'https://www.ebay.com/help/policies/ebay-money-back-guarantee-policy/ebay-money-back-guarantee-policy?id=4210'),
    ('MercadoLibre', 'https://www.mercadolibre.com.mx/ayuda/compra-protegida_986',
     'https://www.mercadolibre.com.mx/compra-protegida'),
    ('Sea/Shopee', 'https://help.shopee.co.id/portal/article/77527',
     'https://help.shopee.co.id/portal/4/article/77527'),
    ('Rakuten', 'https://ichiba.faq.rakuten.net/detail/000002563',
     'https://www.rakuten.co.jp/'),
    ('Coupang', 'https://www.coupang.com/np/coupangglobal',
     'https://ir.aboutcoupang.com/'),
]

KEYWORDS = r'\b(return|refund|exchange|days|guarantee|pengembalian|dana|devolución|devolucion|返品|退货|退款|환불)\b'


def fetch(url):
    req = urllib.request.Request(url, headers={
        'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        'Accept-Language': 'en,id,es,ja,zh,ko;q=0.8',
    })
    with urllib.request.urlopen(req, timeout=25) as r:
        raw = r.read(2000000)
        charset = r.headers.get_content_charset()
        return r.status, r.url, raw, charset


def decode(raw, charset):
    if not charset:
        m = re.search(rb'charset=["\']?([\w-]+)', raw[:4000], re.I)
        charset = m.group(1).decode('ascii', 'replace') if m else 'utf-8'
    try:
        return raw.decode(charset, 'replace'), charset
    except LookupError:
        return raw.decode('utf-8', 'replace'), 'utf-8(fallback)'


def plain(text):
    text = re.sub(r'<(script|style|noscript)\b[^>]*>.*?</\1>', ' ', text, flags=re.S | re.I)
    text = re.sub(r'<[^>]+>', ' ', text)
    return re.sub(r'\s+', ' ', html.unescape(text)).strip()


def probe(item):
    name, primary, secondary = item
    attempts = []
    for role, url in (('primary', primary), ('secondary', secondary)):
        try:
            status, final, raw, charset = fetch(url)
        except Exception as exc:
            attempts.append({'role': role, 'url': url, 'reachable': False,
                             'evidence_status': 'UNKNOWN', 'error': str(exc)})
            continue
        text, used = decode(raw, charset)
        body = plain(text)
        hits = list(re.finditer(KEYWORDS, body, re.I))[:6]
        excerpts = [body[max(0, m.start() - 110):m.start() + 420] for m in hits]
        attempts.append({
            'role': role, 'url': url, 'final_url': final, 'http_status': status,
            'reachable': True, 'charset': used, 'plain_text_chars': len(body),
            # A shell page with almost no extractable prose is client-rendered;
            # its policy wording is not observable through this transport.
            'policy_text_observable': bool(excerpts) and len(body) > 800,
            'evidence_status': 'OBSERVED' if (excerpts and len(body) > 800) else 'UNKNOWN',
            'unknown_reason': None if (excerpts and len(body) > 800)
                              else 'HTTP reachable but policy prose not present in server HTML (client-rendered or gated)',
            'excerpts': excerpts,
        })
    observed = next((a for a in attempts if a.get('evidence_status') == 'OBSERVED'), None)
    return {
        'name': name,
        'overall_evidence_status': 'OBSERVED' if observed else 'UNKNOWN',
        'quoted_source': observed['final_url'] if observed else None,
        'comparative_score': 'NOT RUN',
        'attempts': attempts,
    }


with concurrent.futures.ThreadPoolExecutor(max_workers=10) as pool:
    results = list(pool.map(probe, SOURCES))

payload = {
    'collected_at': datetime.datetime.now().astimezone().isoformat(),
    'retailer_set': 'user-specified: Amazon, Alibaba/Tmall, JD.com, PDD/Temu, Walmart, eBay, MercadoLibre, Sea/Shopee, Rakuten, Coupang',
    'scope': 'Public official pages only. No application HTTP, no credentials.',
    'limits': [
        'HTTP status proves reachability only, never feature parity or service quality.',
        'Retailers whose policy prose is client-rendered or blocked stay UNKNOWN; no text is reconstructed from memory.',
        'No comparative capability scoring was produced; competitive position remains NOT RUN.',
    ],
    'observed_count': sum(1 for r in results if r['overall_evidence_status'] == 'OBSERVED'),
    'unknown_count': sum(1 for r in results if r['overall_evidence_status'] == 'UNKNOWN'),
    'sources': results,
}
pathlib.Path(__file__).with_name('public-benchmarks.json').write_text(json.dumps(payload, indent=2, ensure_ascii=False) + '\n')
for r in results:
    print(r['name'], r['overall_evidence_status'], r['quoted_source'])
print('observed', payload['observed_count'], 'unknown', payload['unknown_count'])
