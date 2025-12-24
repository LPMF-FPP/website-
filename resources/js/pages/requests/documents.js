/**
 * RequestDocumentsClient - Module untuk tab Dokumen & Lampiran di detail permintaan
 * Menangani fetch, delete, preview dokumen tanpa reload halaman
 */

export class RequestDocumentsClient {
    constructor(requestId, config = {}) {
        this.requestId = requestId;
        this.csrf = config.csrf || document.querySelector('meta[name=csrf-token]')?.content || '';
        
        this.state = {
            documents: [],
            loading: true,
            error: '',
            deleting: {},
            selectedDocument: null,
            previewUrl: '',
        };
    }

    /**
     * Generic API fetch
     */
    async apiFetch(url, options = {}) {
        const { method = 'GET', body = null, headers = {} } = options;
        
        const requestHeaders = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...headers
        };

        const upper = method.toUpperCase();

        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(upper)) {
            requestHeaders['X-CSRF-TOKEN'] = this.csrf;
        }

        const fetchOptions = {
            method: upper,
            headers: requestHeaders,
            credentials: 'same-origin',
        };

        if (body !== null && !['GET', 'HEAD'].includes(upper)) {
            fetchOptions.body = JSON.stringify(body);
        }

        const response = await fetch(url, fetchOptions);
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message || `Request failed with status ${response.status}`);
        }

        return data;
    }

    /**
     * Fetch documents list
     */
    async fetchDocuments() {
        this.state.loading = true;
        this.state.error = '';

        try {
            const response = await fetch(`/api/requests/${this.requestId}/documents`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || 'Gagal memuat dokumen.');
            }

            const list = Array.isArray(payload?.documents)
                ? payload.documents
                : (Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []));

            this.state.documents = list;

            // Update selected document if it still exists
            if (this.state.selectedDocument) {
                const updated = this.state.documents.find((doc) => doc.id === this.state.selectedDocument.id);
                if (!updated) {
                    this.clearPreview();
                } else {
                    this.selectDocument(updated);
                }
            }
        } catch (error) {
            this.state.error = error.message || 'Gagal memuat dokumen.';
        } finally {
            this.state.loading = false;
        }
    }

    /**
     * Delete document
     */
    async deleteDocument(doc) {
        if (!doc?.id) return;
        if (!confirm('Yakin hapus dokumen ini?')) return;

        this.state.deleting[doc.id] = true;
        this.state.error = '';

        try {
            const response = await fetch(`/api/documents/${doc.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': this.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || 'Gagal menghapus dokumen.');
            }

            // Remove from list
            this.state.documents = this.state.documents.filter((item) => item.id !== doc.id);

            // Clear preview if deleted document was selected
            if (this.state.selectedDocument && this.state.selectedDocument.id === doc.id) {
                this.clearPreview();
            }
        } catch (error) {
            this.state.error = error.message || 'Gagal menghapus dokumen.';
        } finally {
            this.state.deleting[doc.id] = false;
        }
    }

    /**
     * Select document for preview
     */
    selectDocument(doc) {
        this.state.selectedDocument = doc;
        this.state.previewUrl = doc?.preview_url || doc?.url || doc?.download_url || '';
    }

    /**
     * Clear preview
     */
    clearPreview() {
        this.state.selectedDocument = null;
        this.state.previewUrl = '';
    }

    /**
     * Get document type label
     */
    documentType(doc) {
        return doc?.type_label || doc?.type || (doc?.is_generated ? 'generated' : 'upload');
    }

    /**
     * Check if document is PDF
     */
    documentIsPdf(doc) {
        if (!doc) return false;
        const mime = (doc.mime_type || doc.mime || doc.content_type || '').toLowerCase();
        const name = (doc.name || '').toLowerCase();
        const ext = (doc.extension || '').toLowerCase();
        return mime.includes('pdf') || name.endsWith('.pdf') || ext === 'pdf';
    }

    /**
     * Check if document is image
     */
    documentIsImage(doc) {
        if (!doc) return false;
        const mime = (doc.mime_type || doc.mime || doc.content_type || '').toLowerCase();
        const name = (doc.name || '').toLowerCase();
        const ext = (doc.extension || '').toLowerCase();
        return (
            mime.startsWith('image/') ||
            ['.png', '.jpg', '.jpeg', '.gif'].some((suffix) => name.endsWith(suffix)) ||
            ['png', 'jpg', 'jpeg', 'gif'].includes(ext)
        );
    }

    /**
     * Check if document is being deleted
     */
    isDeleting(id) {
        return !!this.state.deleting[id];
    }

    /**
     * Open document in new tab
     */
    openDocument(doc) {
        const target = doc?.preview_url || doc?.url || doc?.download_url;
        if (target) {
            window.open(target, '_blank');
        }
    }
}
