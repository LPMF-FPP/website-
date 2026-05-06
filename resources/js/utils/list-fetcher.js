// Small reusable list fetcher for pagination/filter + skeleton toggle
// Usage:
// import { createListFetcher } from './utils/list-fetcher';
// document.addEventListener('alpine:init', () => {
//   Alpine.data('sampleProcessesList', () => createListFetcher());
// });

export function isListNavigationUrl(url) {
  const hasPaginationParam = Array.from(url.searchParams.keys()).some((key) => key.endsWith('_page') || key === 'page');

  return hasPaginationParam
    || url.searchParams.has('sort')
    || url.searchParams.has('request_sort')
    || url.searchParams.has('search')
    || url.hash === '#page';
}

export function createListFetcher(targetRef = 'listContainer') {
  return {
    loading: false,
    responseMode: 'page',
    requestHeaders: {},
    getTargetContainer() {
      return this.$refs[targetRef]
        || this.$el.querySelector(`[x-ref="${targetRef}"]`)
        || this.$refs.listContainer
        || this.$el.querySelector('[x-ref="listContainer"]');
    },
    init() {
      // Delegate from root because list containers may be created later by x-if.
      const container = this.$el;
      container?.addEventListener(
        'click',
        (e) => {
          const anchor = e.target.closest('a');
          if (!anchor) {return;}
          const url = new URL(anchor.href, window.location.origin);
          const isListNavigation = isListNavigationUrl(url);
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
        const targetContainer = this.getTargetContainer();
        if (!targetContainer) {
          window.location.href = url;
          return;
        }
        if (this.responseMode === 'fragment') {
          targetContainer.innerHTML = html;
          if (opts.push) {history.pushState({}, '', url);}
          return;
        }
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const newContainer = doc.querySelector(`[x-ref="${targetRef}"]`) || doc.querySelector('[x-ref="listContainer"]') || doc.querySelector('table')?.closest('div');
        if (newContainer) {
          targetContainer.innerHTML = newContainer.innerHTML;
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
