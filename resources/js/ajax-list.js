export default function ajaxList() {
    return {
        loading: false, error: false, controller: null, timer: null, pendingUrl: null,
        schedule() {
            clearTimeout(this.timer);
            this.controller?.abort();
            this.timer = setTimeout(() => this.load(), 300);
        },
        restore() {
            const params = new URL(location.href).searchParams;
            for (const input of this.$refs.filters.elements) {
                if (input.name) input.value = params.get(input.name) ?? '';
            }
            this.load(location.href, false);
        },
        filterUrl() {
            const url = new URL(this.$refs.filters.action);
            url.search = new URLSearchParams(new FormData(this.$refs.filters)).toString();
            for (const [key, value] of [...url.searchParams]) if (!value) url.searchParams.delete(key);
            return url;
        },
        paginate(event) {
            const link = event.target.closest('a');
            if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            const url = new URL(link.href);
            if (url.origin !== location.origin || url.pathname !== location.pathname || !url.searchParams.has('page')) return;
            event.preventDefault();
            // Visible links can predate a pending search; use the current controls.
            const filteredUrl = this.filterUrl();
            filteredUrl.searchParams.set('page', url.searchParams.get('page'));
            this.load(filteredUrl);
        },
        async load(target = null, updateHistory = true) {
            clearTimeout(this.timer);
            this.controller?.abort();
            const controller = new AbortController();
            this.controller = controller;
            const url = target ? new URL(target) : this.filterUrl();
            this.pendingUrl = url.href;
            this.loading = true;
            this.error = false;
            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                    signal: controller.signal, cache: 'no-store',
                });
                if (!response.ok || response.redirected) throw new Error('Unable to load results');
                const html = await response.text();
                if (controller.signal.aborted) return;
                this.$refs.results.innerHTML = html;
                if (updateHistory) history.pushState({}, '', url);
            } catch (error) {
                if (error.name !== 'AbortError') this.error = true;
            } finally {
                if (this.controller === controller) this.loading = false;
            }
        },
        destroy() { clearTimeout(this.timer); this.controller?.abort(); },
    };
}
