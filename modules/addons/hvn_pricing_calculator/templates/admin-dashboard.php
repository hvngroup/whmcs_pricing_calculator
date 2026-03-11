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
        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title hvn-card__title--blue">HVN Pricing Calculator</span>
                <span class="hvn-version">v<?php echo htmlspecialchars($version); ?></span>
            </div>
            <div class="hvn-card__body">
                <p class="hvn-desc">
                    Auto-calculate billing cycle pricing and currency conversion for Products, Configurable Options and Addons.
                </p>

                <p class="hvn-section-title"><span class="hvn-dot hvn-dot--blue"></span> Supported Pages</p>

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
                            ['Product Pricing', 'configproducts.php → Pricing tab', 'Cycles, Currencies, Setup Fee'],
                            ['Config Options', 'configproductoptions.php → Option pricing', 'Cycles, Currencies'],
                            ['Addon Pricing', 'configaddons.php → Pricing tab', 'Cycles, Currencies, Setup Fee'],
                            ['Inline Manager', 'configproducts.php → Config Options tab', 'Quick Create, Inline Edit'],
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

                <p class="hvn-footnote">
                    Access control is managed via <a href="configaddonmods.php">Setup → Addon Modules</a>.
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
                        <span class="hvn-stat__label">Default Currency</span>
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

    </div>

</div>