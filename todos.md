# Tasks

## Completed (04 Feb 2026)

- [x] **WhatsApp Whitelist System**
    - [x] Database migration & Model (`whatsapp_whitelists`)
    - [x] Service logic (`WhitelistService`)
    - [x] Command implementation (`/whitelist`)
    - [x] Security integration in `CommandDispatcher`
- [x] **Consolidated Report Fixes**
    - [x] Logic "Permintaan Selesai" (exclude ready_for_delivery)
    - [x] Logic "LHU Terbit" (count samples from ready requests)
    - [x] Label update "Sampel yang Telah Diuji"
- [x] **WhatsApp Fixes**
    - [x] Prevent duplicate "Ready for Pickup" notifications
    - [x] Fix group fetching (show all joined groups)
    - [x] Update webhook config for new server IP (192.168.0.209)
- [x] **Data Cleanup**
    - [x] Normalize "TRAMADOL" vs "Tramadol" in database
    - [x] Fix anomalies in specific request records (ID 148, 151)

## Next Steps

- [ ] **Dynamic Report Builder:** Allow custom date ranges and column selection for export.
- [ ] **Automated Backup verification:** Ensure backups are running correctly on new server.
