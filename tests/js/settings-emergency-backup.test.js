import test from "node:test";
import assert from "node:assert/strict";

globalThis.MutationObserver = class {
    constructor() {}
    disconnect() {}
    observe() {}
    takeRecords() {
        return [];
    }
};

globalThis.Element = class {};

globalThis.window = {
    __SETTINGS_INITIAL_DATA__: {},
    Element: globalThis.Element,
};

globalThis.document = {
    querySelector: () => null,
    createElement: () => ({
        setAttribute() {},
        appendChild() {},
        remove() {},
    }),
    body: {
        appendChild() {},
        removeChild() {},
    },
};

const { default: Alpine } = await import("alpinejs");
const { registerSettingsComponent } = await import(
    "../../resources/js/pages/settings/alpine-component.js"
);

let factory = null;
const originalData = Alpine.data;
Alpine.data = (name, callback) => {
    if (name === "settingsPageAlpine") {
        factory = callback;
    }
};

registerSettingsComponent();

Alpine.data = originalData;

test("settings emergency backup exposes handlers", () => {
    assert.ok(factory, "settingsPageAlpine factory should be registered");
    const component = factory();
    assert.equal(typeof component.startEmergencyBackup, "function");
    assert.equal(typeof component.fetchEmergencyBackups, "function");
    assert.equal(typeof component.client.state.backupRunning, "boolean");
    assert.equal(typeof component.client.state.backupProgress, "string");
    assert.equal(typeof component.client.state.backupProgressPercent, "number");
});
