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

<div class="hvn-dashboard">

    <!-- Main Column -->
    <div class="hvn-dashboard__main" x-data="hvnPresetManager()" x-init="init()">

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
                    <div>
                        <template x-for="p in presets" :key="p.id">
                            <div class="hvn-preset-row">
                                <!-- Header: Name + Meta + Actions -->
                                <div class="hvn-preset-row__header">
                                    <div class="hvn-preset-row__name">
                                        <span x-text="p.name" style="font-weight:600;font-size:14px;"></span>
                                        <template x-if="p.is_default">
                                            <span class="hvn-tag hvn-tag--blue" style="margin-left:8px;">Default</span>
                                        </template>
                                    </div>
                                    <div class="hvn-preset-row__actions">
                                        <span class="hvn-preset-tag">
                                            Round: <strong x-text="p.rounding_method.charAt(0).toUpperCase() + p.rounding_method.slice(1)"></strong>
                                        </span>
                                        <span class="hvn-preset-tag">
                                            Precision: <strong x-text="parseFloat(p.rounding_precision)"></strong>
                                        </span>
                                        <span class="hvn-preset-row__btn-group">
                                            <button class="hvn-btn hvn-btn--default hvn-btn--xs" @click="edit(p)">✎ Edit</button>
                                            <button class="hvn-btn hvn-btn--danger hvn-btn--xs" @click="remove(p.id)">✕ Delete</button>
                                        </span>
                                    </div>
                                </div>

                                <!-- Body: Two discount rows -->
                                <div class="hvn-preset-row__body">
                                    <!-- Recurring -->
                                    <div class="hvn-preset-discount-group">
                                        <span class="hvn-preset-discount-label">Recurring</span>
                                        <div class="hvn-preset-discount-cells">
                                            <div class="hvn-preset-cell">
                                                <span class="hvn-preset-cell__cycle">Quarterly</span>
                                                <span class="hvn-preset-cell__val" x-text="parseFloat(p.discount_quarterly) + '%'"></span>
                                            </div>
                                            <div class="hvn-preset-cell">
                                                <span class="hvn-preset-cell__cycle">Semi-Ann.</span>
                                                <span class="hvn-preset-cell__val" x-text="parseFloat(p.discount_semiannually) + '%'"></span>
                                            </div>
                                            <div class="hvn-preset-cell">
                                                <span class="hvn-preset-cell__cycle">Annual</span>
                                                <span class="hvn-preset-cell__val" x-text="parseFloat(p.discount_annually) + '%'"></span>
                                            </div>
                                            <div class="hvn-preset-cell">
                                                <span class="hvn-preset-cell__cycle">Biennial</span>
                                                <span class="hvn-preset-cell__val" x-text="parseFloat(p.discount_biennially) + '%'"></span>
                                            </div>
                                            <div class="hvn-preset-cell">
                                                <span class="hvn-preset-cell__cycle">Triennial</span>
                                                <span class="hvn-preset-cell__val" x-text="parseFloat(p.discount_triennially) + '%'"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Setup Fee -->
                                    <div class="hvn-preset-discount-group">
                                        <span class="hvn-preset-discount-label">Setup Fee</span>
                                        <div class="hvn-preset-discount-cells">
                                            <div class="hvn-preset-cell">
                                                <span class="hvn-preset-cell__cycle">Quarterly</span>
                                                <span class="hvn-preset-cell__val" x-text="parseFloat(p.discount_setup_quarterly || 0) + '%'"></span>
                                            </div>
                                            <div class="hvn-preset-cell">
                                                <span class="hvn-preset-cell__cycle">Semi-Ann.</span>
                                                <span class="hvn-preset-cell__val" x-text="parseFloat(p.discount_setup_semiannually || 0) + '%'"></span>
                                            </div>
                                            <div class="hvn-preset-cell">
                                                <span class="hvn-preset-cell__cycle">Annual</span>
                                                <span class="hvn-preset-cell__val" x-text="parseFloat(p.discount_setup_annually || 0) + '%'"></span>
                                            </div>
                                            <div class="hvn-preset-cell">
                                                <span class="hvn-preset-cell__cycle">Biennial</span>
                                                <span class="hvn-preset-cell__val" x-text="parseFloat(p.discount_setup_biennially || 0) + '%'"></span>
                                            </div>
                                            <div class="hvn-preset-cell">
                                                <span class="hvn-preset-cell__cycle">Triennial</span>
                                                <span class="hvn-preset-cell__val" x-text="parseFloat(p.discount_setup_triennially || 0) + '%'"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="presets.length === 0">
                            <div class="hvn-text-muted" style="text-align:center;padding:40px;">
                                No presets found. Click "Add Preset" to create one.
                            </div>
                        </template>
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

    <!-- Sidebar -->
    <div class="hvn-dashboard__side">

        <!-- What Are Presets -->
        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--green hvn-card__title--sm">What Are Presets?</span>
            </div>
            <div class="hvn-card__body">
                <p class="hvn-guide-text">
                    A preset is a saved set of discount percentages for each billing cycle.
                    When you select a preset in the toolbar, all discount fields are populated instantly.
                </p>
                <p class="hvn-guide-text" style="margin-bottom:0;">
                    The <strong>default preset</strong> is automatically loaded when the toolbar appears on any pricing page.
                    You can have only one default at a time.
                </p>
            </div>
        </div>

        <!-- Example Calculation -->
        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--blue hvn-card__title--sm">Example Calculation</span>
            </div>
            <div class="hvn-card__body">
                <p class="hvn-guide-text" style="margin-bottom:8px;">
                    Given <strong>Monthly = $10.00</strong> with the "Standard" preset:
                </p>
                <table class="hvn-example-table">
                    <thead>
                        <tr>
                            <th>Cycle</th>
                            <th>Discount</th>
                            <th>Formula</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Quarterly</td>
                            <td>0%</td>
                            <td><code>10 × 3 × 1.00</code></td>
                            <td><strong>$30.00</strong></td>
                        </tr>
                        <tr>
                            <td>Semi-Annual</td>
                            <td>5%</td>
                            <td><code>10 × 6 × 0.95</code></td>
                            <td><strong>$57.00</strong></td>
                        </tr>
                        <tr>
                            <td>Annual</td>
                            <td>10%</td>
                            <td><code>10 × 12 × 0.90</code></td>
                            <td><strong>$108.00</strong></td>
                        </tr>
                        <tr>
                            <td>Biennial</td>
                            <td>15%</td>
                            <td><code>10 × 24 × 0.85</code></td>
                            <td><strong>$204.00</strong></td>
                        </tr>
                        <tr>
                            <td>Triennial</td>
                            <td>20%</td>
                            <td><code>10 × 36 × 0.80</code></td>
                            <td><strong>$288.00</strong></td>
                        </tr>
                    </tbody>
                </table>
                <p class="hvn-guide-text hvn-mt-8" style="margin-bottom:0;">
                    Higher discounts incentivize longer commitments. Customers save more, you get upfront revenue.
                </p>
            </div>
        </div>

        <!-- Rounding Reference -->
        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--gold hvn-card__title--sm">Rounding Methods</span>
            </div>
            <div class="hvn-card__body">
                <table class="hvn-example-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Behavior</th>
                            <th style="white-space:nowrap;">245,455 → (×1000)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>None</strong></td>
                            <td>No rounding</td>
                            <td>245,455</td>
                        </tr>
                        <tr>
                            <td><strong>Round</strong></td>
                            <td>Nearest</td>
                            <td>245,000</td>
                        </tr>
                        <tr>
                            <td><strong>Ceil</strong></td>
                            <td>Always up</td>
                            <td>246,000</td>
                        </tr>
                        <tr>
                            <td><strong>Floor</strong></td>
                            <td>Always down</td>
                            <td>245,000</td>
                        </tr>
                    </tbody>
                </table>
                <div class="hvn-tips hvn-mt-12">
                    <div class="hvn-tip">
                        <span class="hvn-tip__icon">💡</span>
                        <span class="hvn-tip__text"><strong>Precision</strong> controls the rounding unit. Use <code>0.01</code> for USD, <code>1000</code> or <code>10000</code> for VND.</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

</div>

<script>
if (!window.HvnConfig) window.HvnConfig = { ajaxUrl: '<?php echo $ajaxUrl; ?>' };
</script>