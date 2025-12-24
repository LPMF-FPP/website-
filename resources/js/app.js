import './bootstrap';

import Alpine from 'alpinejs';
import { createListFetcher } from './utils/list-fetcher';
import { initSearchPage } from './pages/search';
import { registerSettingsComponent } from './pages/settings/alpine-component';

// Theme manager
(function() {
	const STORAGE_KEY = 'ui.theme';
	const TRANSITION_CLASS = 'theme-transition';
	let transitionTimeout = null;
	function withTransition() {
		const root = document.documentElement;
		root.classList.add(TRANSITION_CLASS);
		if (transitionTimeout) clearTimeout(transitionTimeout);
		transitionTimeout = setTimeout(() => root.classList.remove(TRANSITION_CLASS), 400);
	}
	function applyTheme(theme) {
		withTransition();
		const root = document.documentElement;
		if (theme === 'dark') {
			root.classList.add('dark');
			root.setAttribute('data-theme', 'dark');
		} else {
			root.classList.remove('dark');
			root.removeAttribute('data-theme');
		}
	}
	function initTheme() {
		const stored = localStorage.getItem(STORAGE_KEY);
		if (stored === 'dark' || stored === 'light') {
			applyTheme(stored);
			return stored;
		}
		const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
		const initial = prefersDark ? 'dark' : 'light';
		applyTheme(initial);
		return initial;
	}
	window.__setTheme = function(theme) {
		localStorage.setItem(STORAGE_KEY, theme);
		applyTheme(theme);
		window.dispatchEvent(new CustomEvent('theme:change', { detail: { theme } }));
	};
	window.__toggleTheme = function() {
		const current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
		window.__setTheme(current === 'dark' ? 'light' : 'dark');
	};
	initTheme();
})();

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
	Alpine.data('listFetcher', () => createListFetcher());
	// For backward compat with earlier usage names, you can alias:
	Alpine.data('sampleProcessesList', () => createListFetcher());
	Alpine.data('deliveryList', () => createListFetcher());
	
	// Register settings page component
	registerSettingsComponent();

	// Dashboard stats with auto-refresh
	Alpine.data('dashboardStats', (initialStats) => ({
		stats: initialStats || {
			total_requests: 0,
			pending_samples: 0,
			completed_tests: 0,
			sla_performance: 0
		},
		interval: null,
		loading: false,
		error: null,

		init() {
			// Start polling after component is initialized
			this.startPolling();
		},

		startPolling() {
			// Update immediately on start
			this.fetchStats();
			// Then update every 30 seconds
			this.interval = setInterval(() => this.fetchStats(), 30000);
		},

		async fetchStats() {
			try {
				this.loading = true;
				this.error = null;

				const response = await fetch('/api/dashboard-stats', {
					credentials: 'same-origin',
				});

				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}

				const data = await response.json();
				this.stats = data;
			} catch (error) {
				console.error('Failed to fetch dashboard stats:', error);
				this.error = 'Gagal memuat data statistik';
			} finally {
				this.loading = false;
			}
		},

		destroy() {
			// Cleanup interval on component destroy
			if (this.interval) {
				clearInterval(this.interval);
			}
		}
	}));
});

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
	const searchRoot = document.querySelector('[data-search-page]');
	if (searchRoot) {
		initSearchPage(searchRoot);
	}
});
