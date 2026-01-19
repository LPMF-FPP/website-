import { describe, it, beforeEach, mock } from "node:test";
import assert from "node:assert/strict";
import { SettingsClient } from "../../resources/js/pages/settings/index.js";

const mockFetch = mock.fn();

class SettingsClientStub {
    constructor(config = {}) {
        this.api = {
            notificationsSecurity: "/api/settings/notifications-security",
            whatsappSettings: "/api/settings/notifications/whatsapp",
            ...config.api,
        };
        this.csrf = config.csrf || "";
        this.state = {
            form: {
                notifications: {
                    whatsapp: {
                        enabled: true,
                        base_url: "http://gowa.lpmf.local:3000",
                        device_id: "device-123",
                        basic_user: "lpmf",
                        basic_pass: "lpmfjaya1",
                        enabled_milestones: ["REQUEST_RECEIVED"],
                        templates: {
                            REQUEST_RECEIVED: "Permintaan diterima {resi}.",
                            READY_FOR_PICKUP: "Permintaan siap diambil {resi}.",
                        },
                    },
                    email: { enabled: false },
                },
                smtp: {},
            },
            loadingSections: {},
            sectionStatus: {},
            sectionErrors: {},
            roles: { manage: [], issue: [] },
        };
        this.fetchCalls = [];
    }

    async apiFetch(url, options = {}) {
        this.fetchCalls.push({ url, options });
        return { message: "OK" };
    }

    clone(obj) {
        return JSON.parse(JSON.stringify(obj));
    }

    sanitizePayload(payload) {
        return payload;
    }

    setSectionLoading(key, state) {
        this.state.loadingSections[key] = !!state;
    }

    setSectionStatus(key, message, intentClass) {
        this.state.sectionStatus[key] = { message, intentClass };
    }

    setSectionError(key, message) {
        this.state.sectionErrors[key] = message;
    }

    sectionEndpoint(key) {
        if (key === "notifications") {
            return {
                url: this.api.notificationsSecurity,
                method: "PUT",
                body: {
                    notifications: this.clone(this.state.form.notifications),
                    smtp: this.clone(this.state.form.smtp),
                    security: { roles: {} },
                },
            };
        }
        return null;
    }

    async saveWhatsAppSettings() {
        const wa = this.state.form.notifications?.whatsapp;
        if (!wa) return;

        const payload = {
            enabled: !!wa.enabled,
            base_url: wa.base_url || "http://localhost:3000",
            device_id: wa.device_id || null,
            basic_user: wa.basic_user || null,
            basic_pass: wa.basic_pass || null,
            enabled_milestones: Array.isArray(wa.enabled_milestones)
                ? wa.enabled_milestones
                : [],
            templates:
                wa.templates && typeof wa.templates === "object"
                    ? wa.templates
                    : {},
        };

        await this.apiFetch(this.api.whatsappSettings, {
            method: "PUT",
            body: payload,
        });
    }

    async fetchSettings() {}

    async saveSection(key) {
        const config = this.sectionEndpoint(key);
        if (!config) return false;

        this.setSectionError(key, "");
        this.setSectionStatus(key, "", "text-primary-600");
        this.setSectionLoading(key, true);

        try {
            await this.apiFetch(config.url, {
                method: config.method,
                body: this.sanitizePayload(config.body),
            });

            if (key === "notifications") {
                await this.saveWhatsAppSettings();
            }

            this.setSectionStatus(
                key,
                "Pengaturan tersimpan.",
                "text-emerald-600",
            );
            await this.fetchSettings();
            return true;
        } catch (error) {
            this.setSectionError(
                key,
                error.message || "Gagal menyimpan pengaturan.",
            );
            this.setSectionStatus(key, "Gagal menyimpan.", "text-red-600");
            return false;
        } finally {
            this.setSectionLoading(key, false);
        }
    }
}

describe("SettingsClient WhatsApp Save", () => {
    let client;

    beforeEach(() => {
        client = new SettingsClientStub({ csrf: "test-csrf" });
    });

    it("should call both endpoints when saving notifications", async () => {
        const result = await client.saveSection("notifications");

        assert.strictEqual(result, true, "saveSection should return true");
        assert.strictEqual(
            client.fetchCalls.length,
            2,
            "should make 2 API calls",
        );

        const firstCall = client.fetchCalls[0];
        assert.strictEqual(
            firstCall.url,
            "/api/settings/notifications-security",
            "first call should be to notifications-security",
        );

        const secondCall = client.fetchCalls[1];
        assert.strictEqual(
            secondCall.url,
            "/api/settings/notifications/whatsapp",
            "second call should be to whatsapp settings",
        );
    });

    it("should send correct WhatsApp payload", async () => {
        await client.saveSection("notifications");

        const waCall = client.fetchCalls[1];
        const payload = waCall.options.body;

        assert.strictEqual(payload.enabled, true);
        assert.strictEqual(payload.base_url, "http://gowa.lpmf.local:3000");
        assert.strictEqual(payload.device_id, "device-123");
        assert.strictEqual(payload.basic_user, "lpmf");
        assert.strictEqual(payload.basic_pass, "lpmfjaya1");
        assert.deepStrictEqual(payload.enabled_milestones, [
            "REQUEST_RECEIVED",
        ]);
        assert.deepStrictEqual(payload.templates, {
            REQUEST_RECEIVED: "Permintaan diterima {resi}.",
            READY_FOR_PICKUP: "Permintaan siap diambil {resi}.",
        });
    });

    it("should handle empty whatsapp settings gracefully", async () => {
        client.state.form.notifications.whatsapp = {};
        await client.saveSection("notifications");

        const waCall = client.fetchCalls[1];
        const payload = waCall.options.body;

        assert.strictEqual(payload.enabled, false);
        assert.strictEqual(payload.base_url, "http://localhost:3000");
        assert.strictEqual(payload.device_id, null);
        assert.strictEqual(payload.basic_user, null);
        assert.strictEqual(payload.basic_pass, null);
        assert.deepStrictEqual(payload.enabled_milestones, []);
        assert.deepStrictEqual(payload.templates, {});
    });

    it("should not call whatsapp endpoint for other sections", async () => {
        client.sectionEndpoint = (key) => {
            if (key === "branding") {
                return {
                    url: "/api/settings/branding",
                    method: "PUT",
                    body: {},
                };
            }
            return null;
        };

        await client.saveSection("branding");

        assert.strictEqual(client.fetchCalls.length, 1);
        assert.strictEqual(client.fetchCalls[0].url, "/api/settings/branding");
    });

    it("should retain whatsapp device_id when merging settings", () => {
        const realClient = new SettingsClient({ csrf: "test-token" });
        const merged = realClient.mergeNotifications({
            whatsapp: {
                enabled: true,
                device_id: "device-123",
                base_url: "http://gowa.example",
            },
        });

        assert.strictEqual(merged.whatsapp.device_id, "device-123");
    });
});

console.log("WhatsApp settings test completed");
