@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Document Templates</h1>
        <p class="mt-2 text-sm text-gray-600">
            Manage document templates for PDF, HTML, and DOCX generation
        </p>
    </div>

    <!-- Templates List -->
    <div class="bg-white shadow-sm rounded-lg border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Active Templates</h2>
                <button 
                    onclick="showUploadModal()"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    Upload New Template
                </button>
            </div>
        </div>

        <div class="divide-y divide-gray-200" id="templatesContainer">
            <div class="px-6 py-4 text-center text-gray-500">
                Loading templates...
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Upload Template</h3>
                <form id="uploadForm" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>
                        <select name="type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select type...</option>
                            <option value="ba_penerimaan">Berita Acara Penerimaan</option>
                            <option value="ba_penyerahan">Berita Acara Penyerahan</option>
                            <option value="lhu">Laporan Hasil Uji</option>
                            <option value="form_preparation">Form Persiapan</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Format</label>
                        <select name="format" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="pdf">PDF</option>
                            <option value="html">HTML</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Template Name</label>
                        <input type="text" name="name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">File</label>
                        <input type="file" name="file" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Upload
                        </button>
                        <button type="button" onclick="hideUploadModal()" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadTemplates();
    
    document.getElementById('uploadForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        try {
            const response = await fetch('/api/settings/document-templates/upload', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: formData
            });
            
            if (response.ok) {
                hideUploadModal();
                loadTemplates();
                alert('Template uploaded successfully');
            } else {
                const error = await response.json();
                alert('Error: ' + (error.message || 'Upload failed'));
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    });
});

async function loadTemplates() {
    try {
        const response = await fetch('/api/settings/document-templates', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }
        });
        
        if (!response.ok) throw new Error('Failed to load templates');
        
        const data = await response.json();
        renderTemplates(data);
    } catch (error) {
        document.getElementById('templatesContainer').innerHTML = `
            <div class="px-6 py-4 text-center text-red-500">
                Error loading templates: ${error.message}
            </div>
        `;
    }
}

function renderTemplates(data) {
    const container = document.getElementById('templatesContainer');
    const templates = data.templates;
    
    if (!templates || Object.keys(templates).length === 0) {
        container.innerHTML = `
            <div class="px-6 py-4 text-center text-gray-500">
                No templates found. Upload your first template.
            </div>
        `;
        return;
    }
    
    let html = '';
    
    for (const [type, formatGroups] of Object.entries(templates)) {
        for (const [format, templateList] of Object.entries(formatGroups)) {
            templateList.forEach(template => {
                html += `
                    <div class="px-6 py-4 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-sm font-medium text-gray-900">${template.name}</h3>
                                    ${template.is_active ? '<span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">Active</span>' : ''}
                                </div>
                                <p class="mt-1 text-sm text-gray-500">
                                    Type: ${template.type} • Format: ${template.format} • Version: ${template.version}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button 
                                    onclick="previewTemplate('${template.type}', '${template.format}')"
                                    class="px-3 py-1 text-sm text-blue-600 hover:text-blue-800"
                                >
                                    Preview
                                </button>
                                ${!template.is_active ? `
                                    <button 
                                        onclick="activateTemplate(${template.id})"
                                        class="px-3 py-1 text-sm text-green-600 hover:text-green-800"
                                    >
                                        Activate
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
        }
    }
    
    container.innerHTML = html;
}

async function previewTemplate(type, format) {
    window.open(`/api/settings/document-templates/preview/${type}/${format}`, '_blank');
}

async function activateTemplate(templateId) {
    if (!confirm('Activate this template? It will replace the currently active template.')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/settings/document-templates/${templateId}/activate`, {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }
        });
        
        if (response.ok) {
            loadTemplates();
            alert('Template activated successfully');
        } else {
            throw new Error('Activation failed');
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

function showUploadModal() {
    document.getElementById('uploadModal').classList.remove('hidden');
}

function hideUploadModal() {
    document.getElementById('uploadModal').classList.add('hidden');
    document.getElementById('uploadForm').reset();
}
</script>
@endsection
