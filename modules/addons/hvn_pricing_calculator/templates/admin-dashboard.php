<?php
/**
 * HVN Pricing Calculator — Admin Dashboard
 * Variables: $moduleLink, $version, $presetCount, $currencyCount, $defaultCur
 */
defined("WHMCS") or die("Access Denied");
?>

<div class="hvn-dashboard">

    <!-- Main -->
    <div class="hvn-dashboard__main">

        <!-- Header Card -->
        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--blue">HVN Pricing Calculator</span>
                <span class="hvn-version">v<?php echo htmlspecialchars($version); ?></span>
            </div>
            <div class="hvn-card__body">
                <p class="hvn-desc">
                    Auto-calculate billing cycle pricing and currency conversion for Products, Configurable Options and Addons.
                    Enter the Monthly price in the default currency — the module computes all remaining cycles with custom discounts
                    and converts to every other currency using WHMCS exchange rates.
                </p>
            </div>
        </div>

        <!-- How to Use -->
        <div class="hvn-card hvn-mt-16">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--green hvn-card__title--sm">How to Use</span>
            </div>
            <div class="hvn-card__body">

                <p class="hvn-section-title"><span class="hvn-dot hvn-dot--blue"></span> Step 1 — Choose a Preset (optional)</p>
                <p class="hvn-guide-text">
                    The toolbar appears automatically on supported pricing pages. Select a preset from the
                    <strong>Preset</strong> dropdown — this loads discount percentages, rounding settings, and
                    <strong>cycle toggles</strong> (which cycles to calculate). Presets follow the naming pattern:
                </p>
                <div class="hvn-guide-buttons">
                    <span class="hvn-guide-btn hvn-guide-btn--blue">Standard — All Cycles</span>
                    <span class="hvn-guide-arrow">→</span>
                    <span class="hvn-guide-desc">Calculates Q through Tri with standard discounts</span>
                </div>
                <div class="hvn-guide-buttons">
                    <span class="hvn-guide-btn hvn-guide-btn--blue">Standard — Flexible</span>
                    <span class="hvn-guide-arrow">→</span>
                    <span class="hvn-guide-desc">Only Q, Semi-Annual, Annual (skips Bi &amp; Tri)</span>
                </div>
                <div class="hvn-guide-buttons">
                    <span class="hvn-guide-btn hvn-guide-btn--blue">Standard — Annual Only</span>
                    <span class="hvn-guide-arrow">→</span>
                    <span class="hvn-guide-desc">Only Biennial &amp; Triennial (base = Annually)</span>
                </div>

                <p class="hvn-section-title"><span class="hvn-dot hvn-dot--blue"></span> Step 2 — Enter the Base Price</p>
                <p class="hvn-guide-text">
                    Enter the <strong>Monthly</strong> price (or <strong>Annually</strong> if base is set to Annually) in the
                    <strong>default currency</strong> (<?php echo htmlspecialchars($defaultCur->code ?? 'N/A'); ?>).
                    Cells with <code>-1.00</code> (disabled) are automatically skipped.
                </p>

                <p class="hvn-section-title"><span class="hvn-dot hvn-dot--blue"></span> Step 3 — Click Calc All</p>
                <p class="hvn-guide-text">
                    Click <strong>⚡ Calc All</strong> to compute enabled cycles with the selected discounts,
                    then convert to all other currencies. Disabled cycles (toggled off in the preset) are skipped entirely.
                    You can also use each button individually:
                </p>
                <div class="hvn-guide-buttons">
                    <span class="hvn-guide-btn hvn-guide-btn--blue">📊 Calc Cycles</span>
                    <span class="hvn-guide-arrow">→</span>
                    <span class="hvn-guide-desc">Calculate cycles from base price (default currency only)</span>
                </div>
                <div class="hvn-guide-buttons">
                    <span class="hvn-guide-btn hvn-guide-btn--blue">💱 Calc Currencies</span>
                    <span class="hvn-guide-arrow">→</span>
                    <span class="hvn-guide-desc">Convert default currency → all other currencies</span>
                </div>
                <div class="hvn-guide-buttons">
                    <span class="hvn-guide-btn hvn-guide-btn--green">⚡ Calc All</span>
                    <span class="hvn-guide-arrow">→</span>
                    <span class="hvn-guide-desc">Run both sequentially</span>
                </div>
                <div class="hvn-guide-buttons">
                    <span class="hvn-guide-btn hvn-guide-btn--default">↩ Undo</span>
                    <span class="hvn-guide-arrow">→</span>
                    <span class="hvn-guide-desc">Restore values before the last calculation</span>
                </div>
                <div class="hvn-guide-buttons">
                    <span class="hvn-guide-btn hvn-guide-btn--warning">🧹 Clear Disabled</span>
                    <span class="hvn-guide-arrow">→</span>
                    <span class="hvn-guide-desc">Set disabled cycles (toggled off) to 0.00 — useful when switching a product from All Cycles to Monthly-only</span>
                </div>

                <p class="hvn-section-title hvn-mt-16"><span class="hvn-dot hvn-dot--blue"></span> Step 4 — Save in WHMCS</p>
                <p class="hvn-guide-text">
                    After calculating, click the native WHMCS <strong>Save Changes</strong> button at the bottom of the page to persist pricing to the database.
                    The module only fills in the input fields — saving is handled by WHMCS itself.
                </p>
            </div>
        </div>

        <!-- Supported Pages -->
        <div class="hvn-card hvn-mt-16">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--gold hvn-card__title--sm">Supported Pages</span>
            </div>
            <div class="hvn-card__body">
                <table class="hvn-info-table">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th>Location</th>
                            <th>Features</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $pages = [
                            ['Product Pricing', 'configproducts.php → Pricing tab', 'Cycles, Currencies, Setup Fee calculation'],
                            ['Configurable Options', 'configproductoptions.php → Option pricing', 'Cycles, Currencies for each sub-option'],
                            ['Addon Pricing', 'configaddons.php → Pricing tab', 'Cycles, Currencies, Setup Fee calculation'],
                            ['Inline Config Manager', 'configproducts.php → Configurable Options tab', 'Quick Create group, inline pricing, multi-currency tabs'],
                        ];
                        foreach ($pages as $p):
                        ?>
                        <tr>
                            <td><?php echo $p[0]; ?></td>
                            <td><code class="hvn-code"><?php echo $p[1]; ?></code></td>
                            <td><?php echo $p[2]; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Formulas -->
        <div class="hvn-card hvn-mt-16">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--green hvn-card__title--sm">Calculation Formulas</span>
            </div>
            <div class="hvn-card__body">
                <p class="hvn-section-title"><span class="hvn-dot hvn-dot--blue"></span> Cycle Calculation (base = Monthly)</p>
                <div class="hvn-formula-block">
                    <code>Quarterly &nbsp;&nbsp;&nbsp;&nbsp; = Monthly × 3 &nbsp;× (1 − discount%)</code><br>
                    <code>Semi-Annually = Monthly × 6 &nbsp;× (1 − discount%)</code><br>
                    <code>Annually &nbsp;&nbsp;&nbsp;&nbsp; = Monthly × 12 × (1 − discount%)</code><br>
                    <code>Biennially &nbsp;&nbsp; = Monthly × 24 × (1 − discount%)</code><br>
                    <code>Triennially &nbsp; = Monthly × 36 × (1 − discount%)</code>
                </div>

                <p class="hvn-section-title"><span class="hvn-dot hvn-dot--blue"></span> Cycle Calculation (base = Annually)</p>
                <div class="hvn-formula-block">
                    <code>Biennially &nbsp;&nbsp; = Annually × 2 × (1 − discount%)</code><br>
                    <code>Triennially &nbsp; = Annually × 3 × (1 − discount%)</code>
                </div>


                <p class="hvn-section-title hvn-mt-16"><span class="hvn-dot hvn-dot--blue"></span> Currency Conversion</p>
                <div class="hvn-formula-block">
                    <code>Target Price = Source Price × (target_rate / source_rate)</code>
                </div>
                <p class="hvn-guide-text hvn-mt-8">
                    Exchange rates are read from <code>tblcurrencies.rate</code> in WHMCS.
                    Setup fees are only converted between currencies — they are never multiplied by cycle.
                </p>
            </div>
        </div>

    </div>

    <!-- Side -->
    <div class="hvn-dashboard__side">

        <!-- Stats -->
        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--green hvn-card__title--sm">Statistics</span>
            </div>
            <div class="hvn-card__body">
                <div class="hvn-stats-grid">
                    <div class="hvn-stat">
                        <span class="hvn-stat__label">Currencies</span>
                        <span class="hvn-stat__value"><?php echo (int) $currencyCount; ?></span>
                    </div>
                    <div class="hvn-stat">
                        <span class="hvn-stat__label">Presets</span>
                        <span class="hvn-stat__value"><?php echo (int) $presetCount; ?></span>
                    </div>
                    <div class="hvn-stat">
                        <span class="hvn-stat__label">Default</span>
                        <span class="hvn-stat__value">
                            <span class="hvn-tag hvn-tag--blue"><?php echo htmlspecialchars($defaultCur->code ?? 'N/A'); ?></span>
                        </span>
                    </div>
                </div>

                <div class="hvn-mt-16">
                    <a href="<?php echo $moduleLink; ?>&action=presets" class="hvn-btn hvn-btn--primary hvn-btn--block">
                        Manage Presets
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--gold hvn-card__title--sm">Quick Links</span>
            </div>
            <div class="hvn-quick-links">
                <?php
                $links = [
                    ['Products/Services', 'configproducts.php'],
                    ['Configurable Options', 'configproductoptions.php'],
                    ['Product Addons', 'configaddons.php'],
                    ['Currencies', 'configcurrencies.php'],
                    ['Access Control (Addon Modules)', 'configaddonmods.php'],
                ];
                foreach ($links as $link):
                ?>
                <a href="<?php echo $link[1]; ?>" class="hvn-quick-link">
                    <span class="hvn-quick-link__arrow">→</span>
                    <?php echo $link[0]; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--blue hvn-card__title--sm">Tips</span>
            </div>
            <div class="hvn-card__body">
                <div class="hvn-tips">
                    <div class="hvn-tip">
                        <span class="hvn-tip__icon">💡</span>
                        <span class="hvn-tip__text">Enable <strong>Overwrite existing</strong> to replace non-zero values. Disable it to only fill empty cells.</span>
                    </div>
                    <div class="hvn-tip">
                        <span class="hvn-tip__icon">💡</span>
                        <span class="hvn-tip__text">For VND or other large-unit currencies, set <strong>Round to: 1,000</strong> or <strong>10,000</strong> for cleaner prices.</span>
                    </div>
                    <div class="hvn-tip">
                        <span class="hvn-tip__icon">💡</span>
                        <span class="hvn-tip__text">Set a cell to <code>-1.00</code> to disable that cycle entirely. The module will skip it during all calculations, including Clear Disabled.</span>
                    </div>
                    <div class="hvn-tip">
                        <span class="hvn-tip__icon">🧹</span>
                        <span class="hvn-tip__text">Use <strong>Clear Disabled</strong> when editing a product that previously had Annual/Biennial/Triennial pricing but you now only want to sell Monthly or Quarterly. Toggle off the unwanted cycles, then click Clear Disabled before saving.</span>
                    </div>
                    <div class="hvn-tip">
                        <span class="hvn-tip__icon">💡</span>
                        <span class="hvn-tip__text">Use <strong>Flexible</strong> presets for monthly-based products (Q/SA/A only). Use <strong>Annual Only</strong> for products priced annually (Bi/Tri only).</span>
                    </div>
                    <div class="hvn-tip">
                        <span class="hvn-tip__icon">💡</span>
                        <span class="hvn-tip__text">Cycle toggles in the toolbar can be adjusted per-product without changing the preset. Changes are not saved back to the preset.</span>
                    </div>
                    <div class="hvn-tip">
                        <span class="hvn-tip__icon">💡</span>
                        <span class="hvn-tip__text">Always verify exchange rates at <a href="configcurrencies.php">Currencies</a> before running Calc Currencies.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>