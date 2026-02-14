import React, { useEffect, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import { DocxEditor } from "@eigenpal/docx-js-editor";
import "@eigenpal/docx-js-editor/styles.css";

const DOCX_MIME =
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document";

function readConfig() {
    const el = document.getElementById("qmh-docx-editor-config");
    if (!el) return null;

    try {
        return JSON.parse(el.textContent || "{}");
    } catch (error) {
        return null;
    }
}

async function extractMessage(response) {
    try {
        const payload = await response.json();
        return payload?.message || null;
    } catch (error) {
        return null;
    }
}

async function apiPost(url, csrfToken) {
    const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({}),
    });

    if (!response.ok) {
        const message = (await extractMessage(response)) || "Permintaan gagal.";
        return { ok: false, status: response.status, message };
    }

    return { ok: true, status: response.status, data: await response.json() };
}

async function apiPutBinary(url, csrfToken, buffer) {
    const response = await fetch(url, {
        method: "PUT",
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": DOCX_MIME,
            "X-CSRF-TOKEN": csrfToken,
        },
        body: buffer,
    });

    const message = (await extractMessage(response)) || "Permintaan gagal.";

    if (!response.ok) {
        return { ok: false, status: response.status, message };
    }

    return { ok: true, status: response.status, message, data: null };
}

function StatusPill({ label, tone }) {
    const className =
        tone === "good"
            ? "bg-emerald-50 text-emerald-700 border-emerald-200"
            : tone === "warn"
              ? "bg-amber-50 text-amber-700 border-amber-200"
              : tone === "bad"
                ? "bg-red-50 text-red-700 border-red-200"
                : "bg-gray-50 text-gray-700 border-gray-200";

    return (
        <span
            className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium ${className}`}
        >
            {label}
        </span>
    );
}

function QmhDocxEditorApp({ config }) {
    const editorRef = useRef(null);
    const heartbeatTimerRef = useRef(null);
    const [buffer, setBuffer] = useState(null);
    const [errorMessage, setErrorMessage] = useState("");
    const [status, setStatus] = useState("idle");
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [lastSavedAt, setLastSavedAt] = useState("");

    const lockUrl = config.lockUrl;
    const heartbeatUrl = config.heartbeatUrl;
    const unlockUrl = config.unlockUrl;
    const docxUrl = config.docxUrl;
    const saveDocxUrl = config.saveDocxUrl;
    const csrfToken = config.csrfToken;

    const stopHeartbeat = () => {
        if (heartbeatTimerRef.current) {
            window.clearInterval(heartbeatTimerRef.current);
            heartbeatTimerRef.current = null;
        }
    };

    const unlock = async () => {
        if (!unlockUrl) return;
        stopHeartbeat();

        try {
            await apiPost(unlockUrl, csrfToken);
        } catch (error) {
            // no-op
        }
    };

    const startHeartbeat = () => {
        if (!heartbeatUrl) return;
        stopHeartbeat();

        heartbeatTimerRef.current = window.setInterval(async () => {
            try {
                await apiPost(heartbeatUrl, csrfToken);
            } catch (error) {
                // no-op
            }
        }, 30000);
    };

    const acquireLock = async () => {
        if (!lockUrl) {
            setErrorMessage("Revisi dokumen tidak ditemukan.");
            return false;
        }

        setStatus("locking");
        const result = await apiPost(lockUrl, csrfToken);
        if (!result.ok) {
            setErrorMessage(
                result.message || "Dokumen sedang diedit oleh pengguna lain.",
            );
            setStatus("error");
            return false;
        }

        setStatus("locked");
        startHeartbeat();
        return true;
    };

    const loadDocx = async () => {
        if (!docxUrl) {
            setErrorMessage("Sumber DOCX belum tersedia.");
            setStatus("error");
            return;
        }

        setStatus("loading");
        setErrorMessage("");

        const response = await fetch(docxUrl, {
            method: "GET",
            credentials: "same-origin",
            headers: {
                Accept: DOCX_MIME,
            },
        });

        if (!response.ok) {
            const message =
                (await extractMessage(response)) ||
                "Gagal memuat file DOCX. Pastikan lock aktif.";
            setErrorMessage(message);
            setStatus("error");
            return;
        }

        const arr = await response.arrayBuffer();
        setBuffer(arr);
        setStatus("ready");
    };

    const saveNow = async () => {
        if (!editorRef.current || !saveDocxUrl) return;

        setSaving(true);
        setErrorMessage("");

        try {
            const nextBuffer = await editorRef.current.save();
            if (!nextBuffer) {
                setErrorMessage("Gagal menyimpan: dokumen kosong.");
                return;
            }

            const result = await apiPutBinary(
                saveDocxUrl,
                csrfToken,
                nextBuffer,
            );
            if (!result.ok) {
                setErrorMessage(result.message);
                return;
            }

            setDirty(false);
            setLastSavedAt(
                new Date().toLocaleString("id-ID", { hour12: false }),
            );
        } finally {
            setSaving(false);
        }
    };

    useEffect(() => {
        let active = true;

        const init = async () => {
            const locked = await acquireLock();
            if (!active || !locked) return;
            await loadDocx();
        };

        init();

        const beforeUnload = (event) => {
            if (dirty) {
                event.preventDefault();
                event.returnValue = "";
            }

            unlock();
        };

        window.addEventListener("beforeunload", beforeUnload);

        return () => {
            active = false;
            window.removeEventListener("beforeunload", beforeUnload);
            unlock();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const pill = (() => {
        if (status === "locking" || status === "loading") {
            return <StatusPill label="Menyiapkan..." tone="warn" />;
        }
        if (status === "ready") {
            if (saving) return <StatusPill label="Menyimpan..." tone="warn" />;
            if (dirty)
                return <StatusPill label="Belum tersimpan" tone="warn" />;
            return <StatusPill label="Siap" tone="good" />;
        }
        if (status === "error") {
            return <StatusPill label="Error" tone="bad" />;
        }
        return <StatusPill label="Menunggu" tone="neutral" />;
    })();

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-3">
                <div className="flex flex-wrap items-center gap-2">
                    {pill}
                    {lastSavedAt ? (
                        <span className="text-xs text-gray-500">
                            Tersimpan: {lastSavedAt}
                        </span>
                    ) : null}
                </div>

                <div className="flex items-center gap-2">
                    <a
                        href={config.showUrl}
                        className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Kembali
                    </a>
                    <button
                        type="button"
                        onClick={saveNow}
                        disabled={saving || status !== "ready"}
                        className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {saving ? "Menyimpan..." : "Simpan DOCX"}
                    </button>
                </div>
            </div>

            {errorMessage ? (
                <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {errorMessage}
                </div>
            ) : null}

            {buffer ? (
                <div className="rounded-lg border border-gray-200 overflow-hidden">
                    <DocxEditor
                        ref={editorRef}
                        documentBuffer={buffer}
                        onChange={() => {
                            setDirty(true);
                        }}
                        onError={(err) => {
                            setErrorMessage(
                                err?.message || "Gagal memuat editor DOCX.",
                            );
                            setStatus("error");
                        }}
                    />
                </div>
            ) : (
                <div className="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                    Memuat editor...
                </div>
            )}
        </div>
    );
}

const mount = document.getElementById("qmh-docx-editor");
if (mount) {
    const config = readConfig();
    if (!config) {
        mount.innerHTML =
            '<div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">Konfigurasi editor DOCX tidak valid.</div>';
    } else {
        createRoot(mount).render(<QmhDocxEditorApp config={config} />);
    }
}
