// Expected network failures are correlated with real requests, never ignored by text alone.
module.exports = function monitor(page, { stage, unexpected, expectedNetworkFailure } = {}) {
    const records = [];
    const pending = [];
    const reject = (event, reason, emittedStage = stage(), status = null) => unexpected({ event, reason, stage: emittedStage, status });
    const accept = (record, errorText, event, emittedStage) => expectedNetworkFailure?.({
        requestURL: record.request.url(), method: record.request.method(), status: record.status,
        errorText, event, stage: emittedStage,
    }) === true;
    const correlate = entry => {
        const record = [...entry.candidates].reverse().find(item => item.failed || item.status >= 400);
        if (!record) return false;
        if (!accept(record, entry.text, 'console', entry.stage)) reject('console', 'unapproved', entry.stage, record.status);
        return true;
    };
    const flush = () => {
        for (let i = pending.length - 1; i >= 0; i -= 1) {
            if (correlate(pending[i])) pending.splice(i, 1);
        }
    };
    page.on('pageerror', () => reject('pageerror', 'runtime'));
    page.on('request', request => {
        records.push({ request, stage: stage(), status: null, failed: false });
        if (records.length > 100) records.shift();
    });
    page.on('response', response => {
        const record = records.find(item => item.request === response.request());
        if (record) record.status = response.status();
        flush();
    });
    page.on('requestfailed', request => {
        const record = records.find(item => item.request === request);
        if (!record) { reject('requestfailed', 'missing-request'); return; }
        record.failed = true;
        if (record.stage !== stage() || !accept(record, request.failure()?.errorText ?? '', 'requestfailed', stage())) reject('requestfailed', 'unapproved');
        flush();
    });
    page.on('console', message => {
        if (message.type() !== 'error') return;
        const url = message.location().url;
        const entry = { text: message.text(), stage: stage(),
            candidates: records.filter(item => item.request.url() === url && item.stage === stage()) };
        if (!expectedNetworkFailure || !entry.candidates.length) { reject('console', 'uncorrelated'); return; }
        if (correlate(entry)) return;
        if (pending.length === 20) reject('console', 'pending-limit');
        else pending.push(entry);
    });
    return () => { flush(); while (pending.length) { const entry = pending.pop(); reject('console', 'unresolved', entry.stage); } };
};
