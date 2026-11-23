const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function list(filters, path) {
    let factory;
    const source = fs.readFileSync('resources/js/app.js', 'utf8');
    const context = {
        Alpine: { data: (_name, callback) => { factory = callback; } },
        URL, URLSearchParams, AbortController, setTimeout, clearTimeout,
        location: { origin: 'https://intranet.test', pathname: path },
        FormData: class { constructor() { return new URLSearchParams(filters); } },
    };
    vm.runInNewContext(source.slice(source.indexOf("Alpine.data('ajaxList'"), source.indexOf("Alpine.data('invoiceState'")), context);
    const state = factory();
    state.$refs = { filters: { action: `https://intranet.test${path}` } };
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
