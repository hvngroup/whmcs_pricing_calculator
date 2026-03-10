<?php
/**
 * HVN Pricing Calculator — Admin Dashboard
 * Ant Design–inspired layout.
 *
 * Variables: $moduleLink, $version, $presetCount, $currencyCount, $defaultCur
 */
defined("WHMCS") or die("Access Denied");
?>

<div style="display:flex;gap:20px;flex-wrap:wrap;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">

    <!-- Main Card -->
    <div style="flex:2;min-width:420px;">
        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title">
                    <span style="display:inline-block;width:4px;height:18px;background:#1677ff;border-radius:2px;"></span>
                    HVN Pricing Calculator
                </span>
                <span style="font-size:12px;color:rgba(0,0,0,0.45);background:#f5f5f5;padding:2px 10px;border-radius:100px;">
                    v<?php echo htmlspecialchars($version); ?>
                </span>
            </div>
            <div class="hvn-card__body">
                <p style="color:rgba(0,0,0,0.65);margin:0 0 16px;font-size:14px;">
                    Auto-calculate billing cycle pricing and currency conversion for Products, Configurable Options and Addons.
                </p>

                <p style="font-weight:600;font-size:14px;color:rgba(0,0,0,0.88);margin:0 0 12px;">Supported Pages</p>

                <table style="width:100%;border-collapse:separate;border-spacing:0;border:1px solid #f0f0f0;border-radius:8px;overflow:hidden;font-size:13px;">
                    <thead>
                        <tr>
                            <th style="padding:10px 14px;text-align:left;background:#fafafa;border-bottom:1px solid #f0f0f0;font-weight:600;color:rgba(0,0,0,0.65);font-size:12px;text-transform:uppercase;letter-spacing:0.3px;">Page</th>
                            <th style="padding:10px 14px;text-align:left;background:#fafafa;border-bottom:1px solid #f0f0f0;font-weight:600;color:rgba(0,0,0,0.65);font-size:12px;text-transform:uppercase;letter-spacing:0.3px;">Location</th>
                            <th style="padding:10px 14px;text-align:left;background:#fafafa;border-bottom:1px solid #f0f0f0;font-weight:600;color:rgba(0,0,0,0.65);font-size:12px;text-transform:uppercase;letter-spacing:0.3px;">Features</th>
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
                        foreach ($pages as $i => $p):
                            $last = $i === count($pages) - 1;
                            $borderStyle = $last ? '' : 'border-bottom:1px solid #f0f0f0;';
                        ?>
                        <tr>
                            <td style="padding:10px 14px;<?php echo $borderStyle; ?>font-weight:500;"><?php echo $p[0]; ?></td>
                            <td style="padding:10px 14px;<?php echo $borderStyle; ?>color:rgba(0,0,0,0.65);"><code style="background:#f5f5f5;padding:2px 6px;border-radius:4px;font-size:12px;"><?php echo $p[1]; ?></code></td>
                            <td style="padding:10px 14px;<?php echo $borderStyle; ?>color:rgba(0,0,0,0.65);"><?php echo $p[2]; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top:16px;font-size:12px;color:rgba(0,0,0,0.45);">
                    Access control is managed via <a href="configaddonmods.php" style="color:#1677ff;text-decoration:none;">Setup → Addon Modules</a>.
                </p>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div style="flex:1;min-width:260px;display:flex;flex-direction:column;gap:16px;">

        <!-- Stats Card -->
        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title" style="font-size:14px;">
                    <span style="display:inline-block;width:4px;height:14px;background:#52c41a;border-radius:2px;"></span>
                    Statistics
                </span>
            </div>
            <div class="hvn-card__body" style="padding:12px 20px;">
                <div style="display:flex;gap:20px;flex-wrap:wrap;">
                    <div class="hvn-stat">
                        <span class="hvn-stat__label">Currencies</span>
                        <span class="hvn-stat__value"><?php echo (int) $currencyCount; ?></span>
                    </div>
                    <div class="hvn-stat">
                        <span class="hvn-stat__label">Presets</span>
                        <span class="hvn-stat__value"><?php echo (int) $presetCount; ?></span>
                    </div>
                </div>

                <div class="hvn-stat" style="border-top:1px solid #f0f0f0;padding-top:12px;margin-top:4px;">
                    <span class="hvn-stat__label">Default Currency</span>
                    <div>
                        <span class="hvn-tag hvn-tag--blue">
                            <?php echo htmlspecialchars($defaultCur->code ?? 'N/A'); ?>
                        </span>
                        <span style="font-size:12px;color:rgba(0,0,0,0.45);margin-left:6px;">
                            rate: <?php echo $defaultCur->rate ?? '1.0'; ?>
                        </span>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <a href="<?php echo $moduleLink; ?>&action=presets" class="hvn-btn hvn-btn--primary hvn-btn--sm" style="text-decoration:none;">
                        Manage Presets
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="hvn-card">
            <div class="hvn-card__header">
                <span class="hvn-card__title" style="font-size:14px;">
                    <span style="display:inline-block;width:4px;height:14px;background:#faad14;border-radius:2px;"></span>
                    Quick Links
                </span>
            </div>
            <div class="hvn-card__body" style="padding:8px 0;">
                <?php
                $links = [
                    ['Products/Services', 'configproducts.php'],
                    ['Configurable Options', 'configproductoptions.php'],
                    ['Product Addons', 'configaddons.php'],
                    ['Currencies', 'configcurrencies.php'],
                ];
                foreach ($links as $link):
                ?>
                <a href="<?php echo $link[1]; ?>" style="display:flex;align-items:center;padding:8px 20px;color:rgba(0,0,0,0.88);text-decoration:none;font-size:14px;transition:background 0.2s;">
                    <span style="color:#1677ff;margin-right:10px;font-size:12px;">→</span>
                    <?php echo $link[0]; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

</div>