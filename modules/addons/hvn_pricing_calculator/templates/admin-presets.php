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
                                <!-- Header -->
                                <div class="hvn-preset-row__header">
                                    <div class="hvn-preset-row__name">
                                        <span x-text="p.name" style="font-weight:600;font-size:14px;"></span>
                                        <template x-if="p.is_default">
                                            <span class="hvn-tag hvn-tag--blue" style="margin-left:8px;">Default</span>
                                        </template>
                                    </div>
                                    <div class="hvn-preset-row__actions">
                                        <span class="hvn-preset-tag">
                                            Base: <strong x-text="(p.base_cycle||'monthly').charAt(0).toUpperCase() + (p.base_cycle||'monthly').slice(1)"></strong>
                                        </span>
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

                                <!-- Body -->
                                <div class="hvn-preset-row__body">
                                    <!-- Recurring -->
                                    <div class="hvn-preset-discount-group">
                                        <span class="hvn-preset-discount-label">Recurring</span>
                                        <div class="hvn-preset-discount-cells">
                                            <template x-for="c in [{key:'quarterly',label:'Quarterly'},{key:'semiannually',label:'Semi-Ann.'},{key:'annually',label:'Annual'},{key:'biennially',label:'Biennial'},{key:'triennially',label:'Triennial'}]" :key="c.key">
                                                <div class="hvn-preset-cell" :class="{'hvn-preset-cell--disabled': p['cycle_'+c.key] != 1}">
                                                    <span class="hvn-preset-cell__cycle" x-text="c.label"></span>
                                                    <template x-if="p['cycle_'+c.key] == 1">
                                                        <span class="hvn-preset-cell__val" x-text="parseFloat(p['discount_'+c.key]) + '%'"></span>
                                                    </template>
                                                    <template x-if="p['cycle_'+c.key] != 1">
                                                        <span class="hvn-preset-cell__val hvn-preset-cell__val--skip">Skip</span>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Setup Fee -->
                                    <div class="hvn-preset-discount-group">
                                        <span class="hvn-preset-discount-label">Setup Fee</span>
                                        <div class="hvn-preset-discount-cells">
                                            <template x-for="c in [{key:'quarterly',label:'Quarterly'},{key:'semiannually',label:'Semi-Ann.'},{key:'annually',label:'Annual'},{key:'biennially',label:'Biennial'},{key:'triennially',label:'Triennial'}]" :key="'s'+c.key">
                                                <div class="hvn-preset-cell" :class="{'hvn-preset-cell--disabled': p['cycle_'+c.key] != 1}">
                                                    <span class="hvn-preset-cell__cycle" x-text="c.label"></span>
                                                    <template x-if="p['cycle_'+c.key] == 1">
                                                        <span class="hvn-preset-cell__val" x-text="parseFloat(p['discount_setup_'+c.key] || 0) + '%'"></span>
                                                    </template>
                                                    <template x-if="p['cycle_'+c.key] != 1">
                                                        <span class="hvn-preset-cell__val hvn-preset-cell__val--skip">Skip</span>
                                                    </template>
                                                </div>
                                            </template>
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
                            <label class="hvn-form-label">Base Cycle</label>
                            <select x-model="form.base_cycle" class="hvn-form-input">
                                <option value="monthly">Monthly</option>
                                <option value="annually">Annually</option>
                            </select>
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

                    <!-- Enabled Cycles -->
                    <p class="hvn-section-title"><span class="hvn-dot hvn-dot--blue"></span> Enabled Cycles</p>
                    <p class="hvn-guide-text" style="margin-bottom:12px;">
                        Disabled cycles will be skipped during calculation. Their pricing fields remain untouched.
                    </p>
                    <div class="hvn-cycle-toggles hvn-mb-16">
                        <template x-for="c in [{key:'cycle_quarterly',label:'Quarterly'},{key:'cycle_semiannually',label:'Semi-Annual'},{key:'cycle_annually',label:'Annual'},{key:'cycle_biennially',label:'Biennial'},{key:'cycle_triennially',label:'Triennial'}]" :key="c.key">
                            <label class="hvn-cycle-toggle" :class="{'hvn-cycle-toggle--active': form[c.key]}">
                                <input type="checkbox" x-model="form[c.key]" style="display:none;">
                                <span class="hvn-cycle-toggle__check" x-text="form[c.key] ? '✓' : '✕'"></span>
                                <span x-text="c.label"></span>
                            </label>
                        </template>
                    </div>

                    <!-- Recurring Discounts -->
                    <p class="hvn-section-title"><span class="hvn-dot hvn-dot--blue"></span> Recurring Discounts</p>
                    <div class="hvn-form-discounts hvn-mb-16">
                        <?php
                        $rFields = [
                            'discount_quarterly' => ['Quarterly', 'cycle_quarterly'],
                            'discount_semiannually' => ['Semi-Annual', 'cycle_semiannually'],
                            'discount_annually' => ['Annual', 'cycle_annually'],
                            'discount_biennially' => ['Biennial', 'cycle_biennially'],
                            'discount_triennially' => ['Triennial', 'cycle_triennially'],
                        ];
                        foreach ($rFields as $key => [$label, $cycleKey]): ?>
                        <div class="hvn-form-discount" :class="{'hvn-form-discount--disabled': !form.<?php echo $cycleKey; ?>}">
                            <label><?php echo $label; ?></label>
                            <input type="number" x-model.number="form.<?php echo $key; ?>" min="0" max="100" step="0.5" :disabled="!form.<?php echo $cycleKey; ?>">
                            <span class="hvn-pct">%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Setup Fee Discounts -->
                    <p class="hvn-section-title"><span class="hvn-dot hvn-dot--green"></span> Setup Fee Discounts</p>
                    <div class="hvn-form-discounts hvn-mb-16">
                        <?php
                        $sFields = [
                            'discount_setup_quarterly' => ['Quarterly', 'cycle_quarterly'],
                            'discount_setup_semiannually' => ['Semi-Annual', 'cycle_semiannually'],
                            'discount_setup_annually' => ['Annual', 'cycle_annually'],
                            'discount_setup_biennially' => ['Biennial', 'cycle_biennially'],
                            'discount_setup_triennially' => ['Triennial', 'cycle_triennially'],
                        ];
                        foreach ($sFields as $key => [$label, $cycleKey]): ?>
                        <div class="hvn-form-discount" :class="{'hvn-form-discount--disabled': !form.<?php echo $cycleKey; ?>}">
                            <label><?php echo $label; ?></label>
                            <input type="number" x-model.number="form.<?php echo $key; ?>" min="0" max="100" step="0.5" :disabled="!form.<?php echo $cycleKey; ?>">
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
                    A preset is a saved set of discount percentages and cycle configuration.
                    When you select a preset in the toolbar, all settings are populated instantly.
                </p>
                <p class="hvn-guide-text" style="margin-bottom:0;">
                    The <strong>default preset</strong> is automatically loaded when the toolbar appears.
                    You can have only one default at a time.
                </p>
            </div>
        </div>

        <!-- Cycle Profiles -->
        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--blue hvn-card__title--sm">Cycle Configuration</span>
            </div>
            <div class="hvn-card__body">
                <p class="hvn-guide-text" style="margin-bottom:8px;">
                    Each preset controls which cycles are calculated. Disabled cycles are skipped — their pricing fields remain untouched.
                </p>
                <p class="hvn-section-title"><span class="hvn-dot hvn-dot--blue"></span> Common Patterns</p>
                <div class="hvn-tips">
                    <div class="hvn-tip">
                        <span class="hvn-tip__icon">📦</span>
                        <span class="hvn-tip__text"><strong>All Cycles</strong> — Enable all. Standard for most products.</span>
                    </div>
                    <div class="hvn-tip">
                        <span class="hvn-tip__icon">⚡</span>
                        <span class="hvn-tip__text"><strong>Flexible</strong> — Enable Q, SA, A only. For monthly-based products that don't offer multi-year plans.</span>
                    </div>
                    <div class="hvn-tip">
                        <span class="hvn-tip__icon">📅</span>
                        <span class="hvn-tip__text"><strong>Annual Only</strong> — Enable Bi, Tri only. For products priced annually where you calculate multi-year from the annual base.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Example Calculation -->
        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--gold hvn-card__title--sm">Example Calculation</span>
            </div>
            <div class="hvn-card__body">
                <p class="hvn-guide-text" style="margin-bottom:8px;">
                    Given <strong>Monthly = $10.00</strong> with "Standard" preset:
                </p>
                <table class="hvn-example-table">
                    <thead>
                        <tr><th>Cycle</th><th>Discount</th><th>Formula</th><th>Result</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Quarterly</td><td>0%</td><td><code>10 × 3 × 1.00</code></td><td><strong>$30.00</strong></td></tr>
                        <tr><td>Semi-Annual</td><td>5%</td><td><code>10 × 6 × 0.95</code></td><td><strong>$57.00</strong></td></tr>
                        <tr><td>Annual</td><td>10%</td><td><code>10 × 12 × 0.90</code></td><td><strong>$108.00</strong></td></tr>
                        <tr><td>Biennial</td><td>15%</td><td><code>10 × 24 × 0.85</code></td><td><strong>$204.00</strong></td></tr>
                        <tr><td>Triennial</td><td>20%</td><td><code>10 × 36 × 0.80</code></td><td><strong>$288.00</strong></td></tr>
                    </tbody>
                </table>
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
                        <tr><th>Method</th><th>Behavior</th><th style="white-space:nowrap;">245,455 → (×1000)</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>None</strong></td><td>No rounding</td><td>245,455</td></tr>
                        <tr><td><strong>Round</strong></td><td>Nearest</td><td>245,000</td></tr>
                        <tr><td><strong>Ceil</strong></td><td>Always up</td><td>246,000</td></tr>
                        <tr><td><strong>Floor</strong></td><td>Always down</td><td>245,000</td></tr>
                    </tbody>
                </table>
                <div class="hvn-tips hvn-mt-12">
                    <div class="hvn-tip">
                        <span class="hvn-tip__icon">💡</span>
                        <span class="hvn-tip__text">Use <code>0.01</code> for USD, <code>1000</code> or <code>10000</code> for VND.</span>
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