// Small reusable list fetcher for pagination/filter + skeleton toggle
// Usage:
// import { createListFetcher } from './utils/list-fetcher';
// document.addEventListener('alpine:init', () => {
//   Alpine.data('sampleProcessesList', () => createListFetcher());
// });

export function createListFetcher() {
  return {
    loading: false,
    responseMode: 'page',
    requestHeaders: {},
    init() {
      // Intercept pagination links inside the container
      const container = this.$refs.listContainer;
      container?.addEventListener(
        'click',
        (e) => {
          const anchor = e.target.closest('a');
          if (!anchor) {return;}
          const url = new URL(anchor.href, window.location.origin);
          const hasPaginationParam = Array.from(url.searchParams.keys()).some((key) => key.endsWith('_page') || key === 'page');
          const isListNavigation = hasPaginationParam || url.searchParams.has('sort') || url.searchParams.has('search') || url.hash === '#page';
          if (url.origin === window.location.origin && isListNavigation) {
            e.preventDefault();
            this.fetchList(url.toString());
          }
        },
        true,
      );
      // Support back/forward
      window.addEventListener('popstate', () => {
        this.fetchList(window.location.href, { push: false });
      });
    },
    handleFilterSubmit(ev) {
      const form = ev.target;
      const action = form.getAttribute('action') || window.location.pathname;
      const params = new URLSearchParams(new FormData(form));
      const url = `${action}?${params.toString()}`;
      this.fetchList(url);
    },
    async fetchList(url, opts = { push: true }) {
      try {
        this.loading = true;
        const res = await fetch(url, { 
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            ...this.requestHeaders,
          },
          credentials: 'same-origin'
        });
        const html = await res.text();
        if (this.responseMode === 'fragment') {
          this.$refs.listContainer.innerHTML = html;
          if (opts.push) {history.pushState({}, '', url);}
          return;
        }
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const newContainer = doc.querySelector('[x-ref="listContainer"]') || doc.querySelector('table')?.closest('div');
        if (newContainer) {
          this.$refs.listContainer.innerHTML = newContainer.innerHTML;
          if (opts.push) {history.pushState({}, '', url);}
        } else {
          window.location.href = url;
        }
      } catch (e) {
        console.error('List fetch failed', e);
        window.location.href = url;
      } finally {
        this.loading = false;
      }
    },
  };
}
