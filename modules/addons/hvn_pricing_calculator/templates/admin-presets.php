<?php
/**
 * HVN Pricing Calculator — Presets Management
 * Ant Design–inspired layout with Alpine.js CRUD.
 */
defined("WHMCS") or die("Access Denied");
$ajaxUrl = 'addonmodules.php?module=hvn_pricing_calculator';
?>

<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">

<p style="margin-bottom:16px;">
    <a href="<?php echo $moduleLink; ?>" class="hvn-btn hvn-btn--default hvn-btn--sm" style="text-decoration:none;">← Back to Dashboard</a>
</p>

<div x-data="hvnPresetManager()" x-init="init()">

    <!-- Presets Card -->
    <div class="hvn-card">
        <div class="hvn-card__header">
            <span class="hvn-card__title">
                <span style="display:inline-block;width:4px;height:18px;background:#1677ff;border-radius:2px;"></span>
                Discount Presets
            </span>
            <button class="hvn-btn hvn-btn--primary hvn-btn--sm" @click="add()">+ Add Preset</button>
        </div>
        <div class="hvn-card__body" style="padding:0;">

            <template x-if="loading">
                <div style="text-align:center;padding:40px;">
                    <span class="hvn-spinner" style="width:24px;height:24px;border-width:3px;"></span>
                    <p style="margin-top:12px;color:rgba(0,0,0,0.45);font-size:14px;">Loading presets...</p>
                </div>
            </template>

            <template x-if="!loading">
                <div style="overflow-x:auto;">
                    <table class="hvn-preset-table" style="border:none;border-radius:0;">
                        <thead>
                            <tr>
                                <th style="text-align:left;padding-left:20px;">Name</th>
                                <th>Q</th><th>SA</th><th>A</th><th>Bi</th><th>Tri</th>
                                <th style="border-left:2px solid #f0f0f0;">S/Q</th><th>S/SA</th><th>S/A</th><th>S/Bi</th><th>S/Tri</th>
                                <th style="border-left:2px solid #f0f0f0;">Round</th><th>Prec.</th>
                                <th>Default</th>
                                <th style="width:140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="p in presets" :key="p.id">
                                <tr>
                                    <td style="text-align:left;padding-left:20px;font-weight:500;" x-text="p.name"></td>
                                    <td x-text="parseFloat(p.discount_quarterly) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_semiannually) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_annually) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_biennially) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_triennially) + '%'"></td>
                                    <td style="border-left:2px solid #f0f0f0;" x-text="parseFloat(p.discount_setup_quarterly || 0) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_setup_semiannually || 0) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_setup_annually || 0) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_setup_biennially || 0) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_setup_triennially || 0) + '%'"></td>
                                    <td style="border-left:2px solid #f0f0f0;" x-text="p.rounding_method"></td>
                                    <td x-text="parseFloat(p.rounding_precision)"></td>
                                    <td>
                                        <template x-if="p.is_default">
                                            <span class="hvn-tag hvn-tag--blue">Default</span>
                                        </template>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px;justify-content:center;">
                                            <button class="hvn-btn hvn-btn--text hvn-btn--xs" @click="edit(p)">Edit</button>
                                            <button class="hvn-btn hvn-btn--text hvn-btn--xs" style="color:#ff4d4f;" @click="remove(p.id)">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="presets.length === 0">
                                <tr><td colspan="15" style="text-align:center;color:rgba(0,0,0,0.25);padding:40px;font-size:14px;">
                                    No presets found. Click "Add Preset" to create one.
                                </td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </div>

    <!-- Edit/Add Form -->
    <template x-if="editing !== null">
        <div class="hvn-card hvn-mt-16">
            <div class="hvn-card__header">
                <span class="hvn-card__title" style="font-size:14px;">
                    <span style="display:inline-block;width:4px;height:14px;background:#52c41a;border-radius:2px;"></span>
                    <span x-text="editing === 'new' ? 'New Preset' : 'Edit Preset'"></span>
                </span>
            </div>
            <div class="hvn-card__body">

                <!-- Basic Info -->
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
                    <div style="flex:1;min-width:200px;">
                        <label style="display:block;font-weight:500;font-size:13px;color:rgba(0,0,0,0.88);margin-bottom:6px;">Preset Name</label>
                        <input type="text" x-model="form.name" class="hvn-input" style="width:100%;height:36px;">
                    </div>
                    <div style="min-width:140px;">
                        <label style="display:block;font-weight:500;font-size:13px;color:rgba(0,0,0,0.88);margin-bottom:6px;">Rounding</label>
                        <select x-model="form.rounding_method" class="hvn-select" style="width:100%;height:36px;">
                            <option value="none">None</option>
                            <option value="ceil">Ceil</option>
                            <option value="floor">Floor</option>
                            <option value="round">Round</option>
                        </select>
                    </div>
                    <div style="min-width:140px;">
                        <label style="display:block;font-weight:500;font-size:13px;color:rgba(0,0,0,0.88);margin-bottom:6px;">Precision</label>
                        <select x-model.number="form.rounding_precision" class="hvn-select" style="width:100%;height:36px;">
                            <option value="0.01">0.01</option>
                            <option value="1">1</option>
                            <option value="100">100</option>
                            <option value="1000">1,000</option>
                            <option value="10000">10,000</option>
                        </select>
                    </div>
                    <div style="display:flex;align-items:flex-end;">
                        <label class="hvn-toggle" style="height:36px;">
                            <input type="checkbox" x-model="form.is_default">
                            Set as Default
                        </label>
                    </div>
                </div>

                <!-- Recurring Discounts -->
                <div style="margin-bottom:20px;">
                    <p style="font-weight:600;font-size:13px;color:rgba(0,0,0,0.88);margin:0 0 10px;">
                        <span style="display:inline-block;width:8px;height:8px;background:#1677ff;border-radius:50%;margin-right:6px;"></span>
                        Recurring Discounts
                    </p>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php
                        $rFields = [
                            'discount_quarterly' => 'Quarterly',
                            'discount_semiannually' => 'Semi-Annual',
                            'discount_annually' => 'Annual',
                            'discount_biennially' => 'Biennial',
                            'discount_triennially' => 'Triennial',
                        ];
                        foreach ($rFields as $key => $label): ?>
                        <div class="hvn-discount" style="padding:6px 12px;">
                            <label style="min-width:auto;font-size:12px;color:rgba(0,0,0,0.65);"><?php echo $label; ?></label>
                            <input type="number" x-model.number="form.<?php echo $key; ?>" min="0" max="100" step="0.5"
                                   style="width:56px;height:28px;text-align:center;font-size:13px;border:1px solid #d9d9d9;border-radius:4px;background:#fff;">
                            <span class="hvn-pct">%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Setup Fee Discounts -->
                <div style="margin-bottom:24px;">
                    <p style="font-weight:600;font-size:13px;color:rgba(0,0,0,0.88);margin:0 0 10px;">
                        <span style="display:inline-block;width:8px;height:8px;background:#52c41a;border-radius:50%;margin-right:6px;"></span>
                        Setup Fee Discounts
                    </p>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php
                        $sFields = [
                            'discount_setup_quarterly' => 'Quarterly',
                            'discount_setup_semiannually' => 'Semi-Annual',
                            'discount_setup_annually' => 'Annual',
                            'discount_setup_biennially' => 'Biennial',
                            'discount_setup_triennially' => 'Triennial',
                        ];
                        foreach ($sFields as $key => $label): ?>
                        <div class="hvn-discount" style="padding:6px 12px;">
                            <label style="min-width:auto;font-size:12px;color:rgba(0,0,0,0.65);"><?php echo $label; ?></label>
                            <input type="number" x-model.number="form.<?php echo $key; ?>" min="0" max="100" step="0.5"
                                   style="width:56px;height:28px;text-align:center;font-size:13px;border:1px solid #d9d9d9;border-radius:4px;background:#fff;">
                            <span class="hvn-pct">%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Actions -->
                <div style="display:flex;gap:8px;padding-top:16px;border-top:1px solid #f0f0f0;">
                    <button class="hvn-btn hvn-btn--primary" @click="save()" :disabled="saving">
                        <template x-if="saving"><span class="hvn-spinner" style="width:14px;height:14px;"></span></template>
                        Save Preset
                    </button>
                    <button class="hvn-btn hvn-btn--default" @click="cancel()">Cancel</button>
                </div>
            </div>
        </div>
    </template>

</div>

</div>

<script>
if (!window.HvnConfig) window.HvnConfig = { ajaxUrl: '<?php echo $ajaxUrl; ?>' };
</script>