<?php
/**
 * HVN Pricing Calculator — Presets Management
 * Variables: $moduleLink, $presets
 */
defined("WHMCS") or die("Access Denied");
$ajaxUrl = 'addonmodules.php?module=hvn_pricing_calculator';
?>

<div class="hvn-preset-page">

<p class="hvn-mb-16">
    <a href="<?php echo $moduleLink; ?>" class="hvn-btn hvn-btn--default hvn-btn--sm">← Back to Dashboard</a>
</p>

<div x-data="hvnPresetManager()" x-init="init()">

    <!-- Presets Card -->
    <div class="hvn-card">
        <div class="hvn-card__header">
            <span class="hvn-card__title hvn-card__title--blue">Discount Presets</span>
            <button class="hvn-btn hvn-btn--primary hvn-btn--sm" @click="add()">+ Add Preset</button>
        </div>
        <div class="hvn-card__body" style="padding:0;">

            <template x-if="loading">
                <div class="hvn-card__body" style="text-align:center;padding:40px;">
                    <span class="hvn-spinner"></span>
                    <p class="hvn-text-muted hvn-mt-12">Loading presets...</p>
                </div>
            </template>

            <template x-if="!loading">
                <div style="overflow-x:auto;">
                    <table class="hvn-preset-table" style="border:none;border-radius:0;">
                        <thead>
                            <tr>
                                <th style="text-align:left;padding-left:20px;">Name</th>
                                <th>Quarterly</th>
                                <th>Semi-Ann.</th>
                                <th>Annual</th>
                                <th>Biennial</th>
                                <th>Triennial</th>
                                <th class="hvn-col-sep">S/Quarterly</th>
                                <th>S/Semi-Ann.</th>
                                <th>S/Annual</th>
                                <th>S/Biennial</th>
                                <th>S/Triennial</th>
                                <th class="hvn-col-sep">Round</th>
                                <th>Precision</th>
                                <th>Default</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="p in presets" :key="p.id">
                                <tr>
                                    <td style="text-align:left;padding-left:20px;" x-text="p.name"></td>
                                    <td x-text="parseFloat(p.discount_quarterly) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_semiannually) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_annually) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_biennially) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_triennially) + '%'"></td>
                                    <td class="hvn-col-sep" x-text="parseFloat(p.discount_setup_quarterly || 0) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_setup_semiannually || 0) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_setup_annually || 0) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_setup_biennially || 0) + '%'"></td>
                                    <td x-text="parseFloat(p.discount_setup_triennially || 0) + '%'"></td>
                                    <td class="hvn-col-sep" x-text="p.rounding_method"></td>
                                    <td x-text="parseFloat(p.rounding_precision)"></td>
                                    <td>
                                        <template x-if="p.is_default">
                                            <span class="hvn-tag hvn-tag--blue">Default</span>
                                        </template>
                                    </td>
                                    <td>
                                        <button class="hvn-btn hvn-btn--text hvn-btn--xs" @click="edit(p)">Edit</button>
                                        <button class="hvn-btn hvn-btn--text hvn-btn--xs hvn-text-danger" @click="remove(p.id)">Delete</button>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="presets.length === 0">
                                <tr>
                                    <td colspan="15" class="hvn-text-muted" style="text-align:center;padding:40px;">
                                        No presets found. Click "Add Preset" to create one.
                                    </td>
                                </tr>
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
                <span class="hvn-card__title hvn-card__title--green hvn-card__title--sm">
                    <span x-text="editing === 'new' ? 'New Preset' : 'Edit Preset'"></span>
                </span>
            </div>
            <div class="hvn-card__body">

                <!-- Basic Info -->
                <div class="hvn-form-row">
                    <div class="hvn-form-field hvn-form-field--grow">
                        <label class="hvn-form-label">Preset Name</label>
                        <input type="text" x-model="form.name" class="hvn-form-input">
                    </div>
                    <div class="hvn-form-field hvn-form-field--md">
                        <label class="hvn-form-label">Rounding</label>
                        <select x-model="form.rounding_method" class="hvn-form-input">
                            <option value="none">None</option>
                            <option value="ceil">Ceil</option>
                            <option value="floor">Floor</option>
                            <option value="round">Round</option>
                        </select>
                    </div>
                    <div class="hvn-form-field hvn-form-field--md">
                        <label class="hvn-form-label">Precision</label>
                        <select x-model.number="form.rounding_precision" class="hvn-form-input">
                            <option value="0.01">0.01</option>
                            <option value="1">1</option>
                            <option value="100">100</option>
                            <option value="1000">1,000</option>
                            <option value="10000">10,000</option>
                        </select>
                    </div>
                    <div class="hvn-form-field hvn-form-field--align-end">
                        <label class="hvn-toggle">
                            <input type="checkbox" x-model="form.is_default">
                            Set as Default
                        </label>
                    </div>
                </div>

                <!-- Recurring Discounts -->
                <p class="hvn-section-title"><span class="hvn-dot hvn-dot--blue"></span> Recurring Discounts</p>
                <div class="hvn-form-discounts hvn-mb-16">
                    <?php
                    $rFields = [
                        'discount_quarterly' => 'Quarterly',
                        'discount_semiannually' => 'Semi-Annual',
                        'discount_annually' => 'Annual',
                        'discount_biennially' => 'Biennial',
                        'discount_triennially' => 'Triennial',
                    ];
                    foreach ($rFields as $key => $label): ?>
                    <div class="hvn-form-discount">
                        <label><?php echo $label; ?></label>
                        <input type="number" x-model.number="form.<?php echo $key; ?>" min="0" max="100" step="0.5">
                        <span class="hvn-pct">%</span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Setup Fee Discounts -->
                <p class="hvn-section-title"><span class="hvn-dot hvn-dot--green"></span> Setup Fee Discounts</p>
                <div class="hvn-form-discounts hvn-mb-16">
                    <?php
                    $sFields = [
                        'discount_setup_quarterly' => 'Quarterly',
                        'discount_setup_semiannually' => 'Semi-Annual',
                        'discount_setup_annually' => 'Annual',
                        'discount_setup_biennially' => 'Biennial',
                        'discount_setup_triennially' => 'Triennial',
                    ];
                    foreach ($sFields as $key => $label): ?>
                    <div class="hvn-form-discount">
                        <label><?php echo $label; ?></label>
                        <input type="number" x-model.number="form.<?php echo $key; ?>" min="0" max="100" step="0.5">
                        <span class="hvn-pct">%</span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Actions -->
                <div class="hvn-form-actions">
                    <button class="hvn-btn hvn-btn--primary" @click="save()" :disabled="saving">
                        <template x-if="saving"><span class="hvn-spinner"></span></template>
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