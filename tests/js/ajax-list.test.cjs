const { test } = require('node:test');
const assert = require('node:assert/strict');
let ajaxList;
const { before } = require('node:test');
before(async () => { ajaxList = (await import('../../resources/js/ajax-list.js')).default; });

function list(filters, path) {
    global.location = { origin: 'https://intranet.test', pathname: path, href: `https://intranet.test${path}` };
    global.FormData = class { constructor() { return new URLSearchParams(filters); } };
    const state = ajaxList();
    state.$refs = { filters: { action: `https://intranet.test${path}`, elements: [] }, results: { innerHTML: 'original' } };
    global.history = { pushState() {} };
    return state;
}

test('pagination keeps current search, client and project type despite stale links', () => {
    const state = list({ search: 'new search', client_id: '7', project_type: 'Website' }, '/jobs');
    let requested;
    let prevented = false;
    state.load = url => { requested = url; };
    state.paginate({
        button: 0,
        target: { closest: () => ({ href: 'https://intranet.test/jobs?search=old&project_type=Hosting&page=2' }) },
        preventDefault: () => { prevented = true; },
    });
    assert.equal(prevented, true);
    assert.equal(requested.searchParams.get('search'), 'new search');
    assert.equal(requested.searchParams.get('client_id'), '7');
    assert.equal(requested.searchParams.get('project_type'), 'Website');
    assert.equal(requested.searchParams.get('page'), '2');
});

test('cleared filters are removed and new searches start at the first page', () => {
    const state = list({ search: '', client_id: '7', project_type: '' }, '/passwords');
    assert.equal(state.filterUrl().search, '?client_id=7');
    assert.equal(state.filterUrl().searchParams.has('page'), false);
});


function click(href, overrides = {}) {
    return { button: 0, target: { closest: () => href ? { href } : null }, preventDefault() {}, ...overrides };
}

function deferred() {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}

const ok = (html = 'updated') => ({ ok: true, redirected: false, text: async () => html });

test('pagination leaves unrelated links and modified clicks to the browser', () => {
    const state = list({}, '/jobs');
    state.load = () => assert.fail('Unexpected Ajax navigation');
    state.paginate(click(null));
    for (const key of ['ctrlKey', 'metaKey', 'shiftKey', 'altKey']) {
        state.paginate(click('https://intranet.test/jobs?page=2', { [key]: true }));
    }
    state.paginate(click('https://intranet.test/jobs?page=2', { button: 1 }));
    state.paginate(click('https://external.test/jobs?page=2'));
    state.paginate(click('https://intranet.test/clients?page=2'));
    state.paginate(click('https://intranet.test/jobs/5'));
});

test('successful searches replace results, update history and send Ajax headers', async () => {
    const state = list({ search: 'alice' }, '/passwords');
    let historyUrl;
    global.history.pushState = (_, __, url) => { historyUrl = url; };
    global.fetch = async (url, options) => {
        assert.equal(state.loading, true);
        assert.equal(url.searchParams.get('search'), 'alice');
        assert.equal(options.headers['X-Requested-With'], 'XMLHttpRequest');
        assert.equal(options.cache, 'no-store');
        return ok();
    };
    await state.load();
    assert.equal(state.$refs.results.innerHTML, 'updated');
    assert.equal(historyUrl.href, 'https://intranet.test/passwords?search=alice');
    assert.equal(state.error, false);
    assert.equal(state.loading, false);
});

test('server failures, redirects and network errors preserve results and can be retried', async () => {
    for (const response of [{ ok: false, redirected: false }, { ok: true, redirected: true }, null]) {
        const state = list({}, '/clients');
        global.fetch = async () => { if (!response) throw new Error('offline'); return response; };
        await state.load();
        assert.equal(state.error, true);
        assert.equal(state.loading, false);
        assert.equal(state.$refs.results.innerHTML, 'original');
        global.fetch = async () => ok('retry succeeded');
        await state.load(state.pendingUrl);
        assert.equal(state.error, false);
        assert.equal(state.$refs.results.innerHTML, 'retry succeeded');
    }
});

test('history restores fields including cleared values without pushing another entry', async () => {
    const state = list({}, '/jobs');
    location.href = 'https://intranet.test/jobs?search=old&page=3';
    state.$refs.filters.elements = [{ name: 'search', value: '' }, { name: 'project_type', value: 'Website' }, { value: 'Search' }];
    let request;
    state.load = (url, update) => { request = [url, update]; };
    state.restore();
    assert.equal(state.$refs.filters.elements[0].value, 'old');
    assert.equal(state.$refs.filters.elements[1].value, '');
    assert.equal(state.$refs.filters.elements[2].value, 'Search');
    assert.deepEqual(request, [location.href, false]);
    global.fetch = async () => ok();
    global.history.pushState = () => assert.fail('History should not be pushed');
    await ajaxList().load.call({ ...state, load: undefined }, location.href, false);
});

test('new requests abort old requests and ignore late responses', async () => {
    const state = list({}, '/clients');
    const old = deferred();
    let oldSignal;
    global.fetch = (_, options) => { oldSignal = options.signal; return old.promise; };
    const first = state.load();
    global.fetch = async () => ok('newest');
    await state.load('https://intranet.test/clients?page=2');
    assert.equal(oldSignal.aborted, true);
    old.resolve(ok('stale'));
    await first;
    assert.equal(state.$refs.results.innerHTML, 'newest');
    assert.equal(state.loading, false);
});

test('aborted request does not show an error or clear a newer loading state', async () => {
    const state = list({}, '/clients');
    const old = deferred(), latest = deferred();
    global.fetch = () => old.promise;
    const first = state.load();
    global.fetch = () => latest.promise;
    const second = state.load();
    old.reject(Object.assign(new Error('aborted'), { name: 'AbortError' }));
    await first;
    assert.equal(state.error, false);
    assert.equal(state.loading, true);
    latest.resolve(ok());
    await second;
    assert.equal(state.loading, false);
});

test('typing debounces requests and disposal cancels timers and requests', () => {
    const originalSet = global.setTimeout, originalClear = global.clearTimeout;
    const timers = new Map();
    let id = 0;
    global.setTimeout = (callback, delay) => { assert.equal(delay, 300); timers.set(++id, callback); return id; };
    global.clearTimeout = timer => timers.delete(timer);
    try {
        const state = list({}, '/clients');
        let loads = 0;
        state.load = () => { loads++; };
        state.schedule();
        state.schedule();
        assert.equal(timers.size, 1);
        [...timers.values()][0]();
        assert.equal(loads, 1);
        state.controller = new AbortController();
        state.schedule();
        assert.equal(state.controller.signal.aborted, true);
        state.destroy();
        assert.equal(timers.size, 0);
        list({}, '/clients').destroy();
    } finally { global.setTimeout = originalSet; global.clearTimeout = originalClear; }
});
