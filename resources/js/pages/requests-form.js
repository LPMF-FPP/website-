/**
 * requests-form.js
 * Handles investigator type toggle and dynamic suspect rows
 */

document.addEventListener("DOMContentLoaded", () => {
    initInvestigatorToggle();
    initSuspectRows();
    initExpertWitnessRequestToggle();
});

/**
 * Toggle between Investigator (Polri) and External (non-Polri) form sections
 */
function initInvestigatorToggle() {
    const radios = document.querySelectorAll('input[name="is_investigator"]');
    const investigatorBlock = document.querySelector(".block-investigator");
    const externalBlock = document.querySelector(".block-external");

    if (!investigatorBlock || !externalBlock || radios.length === 0) {
        return;
    }

    function updateVisibility() {
        const isInvestigator =
            document.querySelector('input[name="is_investigator"]:checked')
                ?.value === "1";

        if (isInvestigator) {
            investigatorBlock.style.display = "";
            externalBlock.style.display = "none";
            // Disable external fields to prevent validation
            externalBlock
                .querySelectorAll("input, select, textarea")
                .forEach((el) => {
                    el.disabled = true;
                });
            // Enable investigator fields
            investigatorBlock
                .querySelectorAll("input, select, textarea")
                .forEach((el) => {
                    el.disabled = false;
                });
        } else {
            investigatorBlock.style.display = "none";
            externalBlock.style.display = "";
            // Disable investigator fields to prevent validation
            investigatorBlock
                .querySelectorAll("input, select, textarea")
                .forEach((el) => {
                    el.disabled = true;
                });
            // Enable external fields
            externalBlock
                .querySelectorAll("input, select, textarea")
                .forEach((el) => {
                    el.disabled = false;
                });
        }
    }

    radios.forEach((radio) => {
        radio.addEventListener("change", updateVisibility);
    });

    // Initial state
    updateVisibility();
}

/**
 * Handle dynamic suspect rows
 */
function initSuspectRows() {
    const container = document.getElementById("suspects-container");
    const addBtn = document.getElementById("add-suspect");

    if (!container || !addBtn) {
        return;
    }

    addBtn.addEventListener("click", () => {
        addSuspectRow();
    });

    container.addEventListener("click", (e) => {
        if (
            e.target.classList.contains("remove-suspect") ||
            e.target.closest(".remove-suspect")
        ) {
            const row = e.target.closest(".suspect-row");
            if (row && document.querySelectorAll(".suspect-row").length > 1) {
                row.remove();
                reindexSuspects();
            } else {
                alert("Minimal harus ada satu tersangka");
            }
        }
    });
}

function addSuspectRow() {
    const container = document.getElementById("suspects-container");
    const rows = container.querySelectorAll(".suspect-row");
    const newIndex = rows.length;

    const template = `
        <div class="suspect-row bg-white p-4 rounded-lg border border-gray-200 shadow-sm" data-index="${newIndex}">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-semibold text-gray-800 flex items-center">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-orange-600 text-white text-sm font-bold mr-2">${newIndex + 1}</span>
                    Tersangka ${newIndex + 1}
                </h4>
                <button type="button" class="remove-suspect inline-flex items-center px-2 py-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded text-sm font-medium transition">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                    Hapus
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="suspects[${newIndex}][name]"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                           placeholder="Nama lengkap tersangka">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Jenis Kelamin
                    </label>
                    <select name="suspects[${newIndex}][gender]"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                        <option value="">Pilih</option>
                        <option value="male">Laki-laki</option>
                        <option value="female">Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Umur (tahun)
                    </label>
                    <input type="number"
                           name="suspects[${newIndex}][age]"
                           min="0"
                           max="120"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                           placeholder="Umur">
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML("beforeend", template);
    updateRemoveButtons();
}

function reindexSuspects() {
    const container = document.getElementById("suspects-container");
    const rows = container.querySelectorAll(".suspect-row");

    rows.forEach((row, idx) => {
        row.dataset.index = idx;

        // Update header text
        const header = row.querySelector("h4, h5");
        if (header) {
            // Check if it has the badge span
            const badge = header.querySelector("span");
            if (badge) {
                badge.textContent = idx + 1;
                // Update rest of header text
                header.childNodes.forEach((node) => {
                    if (node.nodeType === Node.TEXT_NODE) {
                        node.textContent = ` Tersangka ${idx + 1}`;
                    }
                });
            } else {
                header.textContent = `Tersangka #${idx + 1}`;
            }
        }

        // Update input names
        row.querySelectorAll("input, select").forEach((input) => {
            if (input.name) {
                input.name = input.name.replace(
                    /suspects\[\d+\]/,
                    `suspects[${idx}]`,
                );
            }
        });
    });

    updateRemoveButtons();
}

function updateRemoveButtons() {
    const rows = document.querySelectorAll(".suspect-row");
    const removeButtons = document.querySelectorAll(".remove-suspect");

    removeButtons.forEach((btn) => {
        btn.style.display = rows.length > 1 ? "" : "none";
    });
}

function initExpertWitnessRequestToggle() {
    const radios = document.querySelectorAll(
        'input[name="has_expert_witness_request"]',
    );
    const uploadBlock = document.getElementById("expert_witness_request_upload");
    const fileInput = document.getElementById("expert_witness_request_file");
    const letterNumberInput = document.getElementById(
        "expert_witness_letter_number",
    );
    const letterDateInput = document.getElementById("expert_witness_letter_date");
    const filenameDisplay = document.getElementById(
        "expert_witness_request_filename",
    );

    if (!uploadBlock || !fileInput || radios.length === 0) {
        return;
    }

    function updateVisibility() {
        const shouldUpload =
            document.querySelector(
                'input[name="has_expert_witness_request"]:checked',
            )?.value === "1";

        uploadBlock.classList.toggle("hidden", !shouldUpload);
        fileInput.required = shouldUpload && !fileInput.dataset.hasExistingDocument;

        [letterNumberInput, letterDateInput].forEach((input) => {
            if (input) {
                input.required = shouldUpload;
                input.disabled = !shouldUpload;
            }
        });

        if (!shouldUpload) {
            fileInput.value = "";
            if (letterNumberInput) {
                letterNumberInput.value = "";
            }
            if (letterDateInput) {
                letterDateInput.value = "";
            }
            if (filenameDisplay) {
                filenameDisplay.textContent = "PDF hingga 10MB";
            }
        }
    }

    radios.forEach((radio) => {
        radio.addEventListener("change", updateVisibility);
    });

    fileInput.addEventListener("change", () => {
        if (!filenameDisplay || !fileInput.files?.[0]) {
            return;
        }

        const file = fileInput.files[0];
        const fileSize = (file.size / (1024 * 1024)).toFixed(2);
        filenameDisplay.textContent = `${file.name} (${fileSize} MB)`;
    });

    updateVisibility();
}

// Export for use in inline scripts if needed
window.RequestsForm = {
    addSuspectRow,
    reindexSuspects,
    updateRemoveButtons,
    initExpertWitnessRequestToggle,
};
