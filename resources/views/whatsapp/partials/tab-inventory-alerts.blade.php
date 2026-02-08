<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">Inventory Alerts</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Preview kondisi stok rendah dan lot mendekati kadaluarsa.
                        Threshold expiry: <span class="font-mono" x-text="inventoryAlertsData?.expiry_days ?? '-' "></span> hari.
                    </p>
                </div>
                <button
                    type="button"
                    @click="loadTabData('inventory_alerts')"
                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-650 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 transition-colors shadow-sm"
                >
                    Refresh
                </button>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Low Stock -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border-b border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Stok Rendah</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/30">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Item</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Stok</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Min</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <template x-if="!inventoryAlertsData?.low_stock || inventoryAlertsData.low_stock.length === 0">
                                    <tr>
                                        <td colspan="3" class="px-4 py-4 text-sm text-gray-500 text-center">Tidak ada item stok rendah.</td>
                                    </tr>
                                </template>
                                <template x-for="item in inventoryAlertsData?.low_stock || []" :key="item.id">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                        <td class="px-4 py-2">
                                            <a :href="item.edit_url" class="text-sm font-medium text-primary-600 hover:underline" x-text="item.name"></a>
                                            <div class="text-xs text-gray-500" x-text="item.uom"></div>
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm font-mono text-amber-700 dark:text-amber-400" x-text="item.total_on_hand"></td>
                                        <td class="px-4 py-2 text-right text-sm font-mono text-gray-600 dark:text-gray-300" x-text="item.min_stock"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Expiring Lots -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-red-50 dark:bg-red-900/20 border-b border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Mendekati Kadaluarsa</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/30">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Item</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Lot</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Expiry</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <template x-if="!inventoryAlertsData?.expiring || inventoryAlertsData.expiring.length === 0">
                                    <tr>
                                        <td colspan="3" class="px-4 py-4 text-sm text-gray-500 text-center">Tidak ada lot mendekati kadaluarsa.</td>
                                    </tr>
                                </template>
                                <template x-for="lot in inventoryAlertsData?.expiring || []" :key="lot.id">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                        <td class="px-4 py-2">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="lot.item_name"></div>
                                            <div class="text-xs text-gray-500" x-text="lot.uom"></div>
                                        </td>
                                        <td class="px-4 py-2 text-sm font-mono text-gray-700 dark:text-gray-200" x-text="lot.lot_no"></td>
                                        <td class="px-4 py-2 text-right text-sm font-mono text-red-700 dark:text-red-400" x-text="lot.expiry_date"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- History -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">History</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Riwayat alert inventory yang pernah dikirim.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/30">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Waktu</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Tipe</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Target</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Sent</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Failed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <template x-if="!inventoryAlertsData?.history?.data || inventoryAlertsData.history.data.length === 0">
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-sm text-gray-500 text-center">Belum ada history alert.</td>
                        </tr>
                    </template>
                    <template x-for="row in inventoryAlertsData?.history?.data || []" :key="row.id">
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300" x-text="row.created_at_human"></td>
                            <td class="px-4 py-3">
                                <span
                                    class="px-2 py-0.5 text-xs rounded-full"
                                    :class="row.alert_type === 'LOW_STOCK' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800'"
                                    x-text="row.alert_type"
                                ></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-100" x-text="row.target_label"></td>
                            <td class="px-4 py-3 text-right text-sm font-mono text-gray-700 dark:text-gray-200" x-text="row.sent_count"></td>
                            <td class="px-4 py-3 text-right text-sm font-mono text-gray-700 dark:text-gray-200" x-text="row.failed_count"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
