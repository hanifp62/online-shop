# CSP Report-Only harness result - final-regression-3

Status: LIMITED HARNESS-ONLY. Header injected by Playwright route interception on 8015 navigation responses. No source middleware. NOT DEPLOYED. NOT full CSP readiness.

Candidate strict policy:
```
default-src 'self'; script-src 'self'; style-src 'self' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net data:; img-src 'self' data: https://images.unsplash.com; connect-src 'self'; media-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; report-uri /__qa_csp_report
```

Functional assertions: PASS (0 failures). (Report-Only never blocks execution, so pass proves only that the harness did not break the pages).

## / - 95 violation events, 95 HTTP reports, page errors 0
- `style-src-attr` (blocked: `inline`): 92
- `script-src-elem` (blocked: `inline`): 2
- `style-src-elem` (blocked: `inline`): 1

## /prototype/ - 445 violation events, 110 HTTP reports, page errors 0
- `style-src-attr` (blocked: `inline`): 443
- `script-src-elem` (blocked: `inline`): 1
- `style-src-elem` (blocked: `inline`): 1

## /produk/celeste-trench - 16 violation events, 16 HTTP reports, page errors 0
- `style-src-attr` (blocked: `inline`): 16

## /admin/login - 1 violation events, 1 HTTP reports, page errors 0
- `style-src-attr` (blocked: `inline`): 1

