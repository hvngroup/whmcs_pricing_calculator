<?php
/**
 * HVN Pricing Calculator — Action Hooks
 *
 * Strategy: Inject toolbar HTML directly from PHP (AdminAreaFooterOutput),
 * NOT via JS DOM manipulation. This ensures Alpine.js sees the x-data
 * elements when it initializes.
 */

defined("WHMCS") or die("Access Denied");

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Detect if we're on the module's own settings page.
 */
function hvn_pricing_isSettingsPage(): bool
{
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $module = $_REQUEST['module'] ?? '';
    return $script === 'addonmodules.php' && $module === 'hvn_pricing_calculator';
}

/**
 * Detect current admin page context for toolbar injection.
 */
function hvn_pricing_detectPage(): ?array
{
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $action = $_REQUEST['action'] ?? '';

    if ($script === 'configproducts.php' && $action === 'edit') {
        return [
            'type'       => 'product_edit',
            'product_id' => (int) ($_REQUEST['id'] ?? 0),
            'inject'     => ['pricing_calculator', 'config_options_manager'],
        ];
    }

    if ($script === 'configproductoptions.php') {
        // Only inject on the individual option pricing page:
        //   configproductoptions.php?manageoptions=true&cid=X
        // NOT on the group list or group manage page.
        if (!empty($_REQUEST['manageoptions']) && !empty($_REQUEST['cid'])) {
            return [
                'type'      => 'config_options',
                'option_id' => (int) $_REQUEST['cid'],
                'inject'    => ['pricing_calculator'],
            ];
        }
        return null;
    }

    if ($script === 'configaddons.php' && $action === 'manage') {
        return [
            'type'     => 'addon_edit',
            'addon_id' => (int) ($_REQUEST['id'] ?? 0),
            'inject'   => ['pricing_calculator'],
        ];
    }

    return null;
}

/**
 * Read module settings from DB.
 */
function hvn_pricing_getSettings(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = [];
    try {
        $rows = Capsule::table('tbladdonmodules')
            ->where('module', 'hvn_pricing_calculator')
            ->get(['setting', 'value']);
        foreach ($rows as $row) {
            $cache[$row->setting] = $row->value;
        }
    } catch (\Exception $e) {
        // Module may not be fully activated yet
    }
    return $cache;
}

/**
 * Build JS config object.
 */
function hvn_pricing_buildJsConfig(?array $page): array
{
    $settings = hvn_pricing_getSettings();

    $currencies = Capsule::table('tblcurrencies')
        ->orderBy('default', 'desc')
        ->orderBy('code')
        ->get()
        ->toArray();

    $presets = [];
    try {
        if (Capsule::schema()->hasTable('tbl_hvn_pricing_presets')) {
            $presets = Capsule::table('tbl_hvn_pricing_presets')
                ->orderBy('sort_order')
                ->get()
                ->toArray();
        }
    } catch (\Exception $e) {
        // ignore
    }

    $token = '';
    if (function_exists('generate_token')) {
        $raw = generate_token('link');
        if (preg_match('/[?&]token=([^&]+)/', $raw, $m)) {
            $token = $m[1];
        } else {
            $token = ltrim($raw, '&'); // fallback strip
        }
    } elseif (!empty($_SESSION['token'])) {
        $token = $_SESSION['token'];
    }

    return [
        'page'            => $page,
        'isSettings'      => hvn_pricing_isSettingsPage(),
        'currencies'      => $currencies,
        'presets'         => $presets,
        'defaultPreset'   => $settings['defaultPreset'] ?? 'Standard',
        'showRates'       => ($settings['showCurrencyRates'] ?? 'on') === 'on',
        'confirmApply'    => ($settings['confirmBeforeApply'] ?? '') === 'on',
        'defaultRounding' => $settings['defaultRounding'] ?? 'round',
        'defaultRoundTo'  => (float) ($settings['defaultRoundTo'] ?? 1),
        'autoInject'      => ($settings['autoInjectToolbar'] ?? 'on') === 'on',
        'ajaxUrl'         => 'addonmodules.php?module=hvn_pricing_calculator',
        'token'           => $token,
    ];
}

/**
 * Inject CSS on target admin pages + module settings page.
 * Uses AdminAreaHeaderOutput for normal pages.
 */
add_hook('AdminAreaHeaderOutput', 1, function (array $vars) {
    $page = hvn_pricing_detectPage();
    $isSettings = hvn_pricing_isSettingsPage();
    if ($page === null && !$isSettings) return '';

    $base = '../modules/addons/hvn_pricing_calculator/assets';
    return '<link rel="stylesheet" href="' . $base . '/css/hvn-pricing.css?' . time() . '">';
});

/**
 * Inject everything for normal pages (with full admin layout).
 */
add_hook('AdminAreaFooterOutput', 1, function (array $vars) {
    $page = hvn_pricing_detectPage();
    $isSettings = hvn_pricing_isSettingsPage();

    // Skip config_options — handled by AdminAreaHeadOutput for popup
    if ($page !== null && $page['type'] === 'config_options') return '';

    if ($page === null && !$isSettings) return '';

    return hvn_pricing_buildInjectionHtml($page);
});

/**
 * Inject into popup pages (configproductoptions.php?manageoptions=true).
 * AdminAreaHeadOutput runs inside <head> on ALL admin pages including popups.
 * We inject CSS here + a deferred script block that adds JS to the body.
 */
add_hook('AdminAreaHeadOutput', 1, function (array $vars) {
    $page = hvn_pricing_detectPage();

    // Only handle config_options popup
    if ($page === null || $page['type'] !== 'config_options') return '';

    $base = '../modules/addons/hvn_pricing_calculator/assets';

    $html = '';
    // CSS
    $html .= '<link rel="stylesheet" href="' . $base . '/css/hvn-pricing.css?' . time() . '">';

    // We inject JS via a DOMContentLoaded script since we're in <head>
    $jsConfig = hvn_pricing_buildJsConfig($page);
    $configJson = json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

    $html .= '<script>';
    $html .= 'window.HvnConfig=' . $configJson . ';';
    $html .= '</script>';
    $html .= '<script src="' . $base . '/js/hvn-pricing.js?' . time() . '"></script>';
    $html .= '<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>';

    return $html;
});

/**
 * Build the full injection HTML (config data + JS + toolbar + relocator).
 * Used by AdminAreaFooterOutput for normal pages.
 */
function hvn_pricing_buildInjectionHtml(?array $page): string
{
    $jsConfig = hvn_pricing_buildJsConfig($page);
    $base = '../modules/addons/hvn_pricing_calculator/assets';

    $html = '';

    // 1) Config data
    $html .= '<script>window.HvnConfig=' . json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';

    // 2) App JS (sync)
    $html .= '<script src="' . $base . '/js/hvn-pricing.js?' . time() . '"></script>';

    // 3) Alpine.js (defer)
    $html .= '<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>';

    // 4) Toolbar HTML (for pricing pages)
    if ($page !== null) {
        $html .= hvn_pricing_renderToolbarHtml($page);
    }

    // 5) Relocator script
    if ($page !== null) {
        $html .= hvn_pricing_renderRelocatorScript($page);
    }

    // 6) Config manager styles
    if ($page && $page['type'] === 'product_edit') {
        $html .= '<style>.hvn-config-input{width:72px;height:24px;text-align:right;font-size:12px;padding:2px 4px;border:1px solid #ddd;border-radius:2px;background:#fff;}.hvn-config-input:focus{border-color:#5b9bd5;outline:none;box-shadow:0 0 3px rgba(91,155,213,.3);}</style>';
    }

    return $html;
}

/**
 * Render the pricing calculator toolbar HTML.
 * This is injected at the bottom of the page, then relocated by JS.
 */
function hvn_pricing_renderToolbarHtml(array $page): string
{
    // The toolbar is rendered as a hidden div. The relocator script
    // will move it to the correct position on the page.
    $html = <<<'TOOLBAR'

<!-- HVN Pricing Calculator Toolbar -->
<div id="hvn-toolbar-mount" x-data="hvnToolbar()" style="display:none;">
<div class="hvn-toolbar">

    <div class="hvn-toolbar-header">
        <span class="hvn-toolbar-title">⚡ HVN Pricing Calculator</span>
        <div class="hvn-group">
            <label>Preset:</label>
            <select class="hvn-select" x-model="presetId" @change="loadPreset()"
                    x-effect="$nextTick(() => { $el.value = presetId })">
                <template x-for="p in presets" :key="p.id">
                    <option :value="String(p.id)" x-text="p.name"></option>
                </template>
            </select>
        </div>
    </div>

    <!-- Hint -->
    <div class="hvn-toolbar-hint">
        Enter the <strong>base price</strong> (Monthly or Annually) in the default currency, then click
        <strong>Calc All</strong> to auto-fill all cycles and currencies. Cells with <strong>-1.00</strong> (disabled) are skipped.
    </div>

    <!-- Row 1: Base, Round, Overwrite -->
    <div class="hvn-toolbar-row">
        <div class="hvn-group">
            <label>Base:</label>
            <select class="hvn-select" x-model="baseCycle">
                <option value="monthly">Monthly</option>
                <option value="annually">Annually</option>
            </select>
        </div>
        <div class="hvn-group">
            <label>Round:</label>
            <select class="hvn-select" x-model="rounding">
                <option value="none">None</option>
                <option value="ceil">Ceil</option>
                <option value="floor">Floor</option>
                <option value="round">Nearest</option>
            </select>
        </div>
        <div class="hvn-group">
            <label>To:</label>
            <select class="hvn-select" x-model.number="roundTo">
                <option value="0.01">0.01</option>
                <option value="1">1</option>
                <option value="100">100</option>
                <option value="1000">1,000</option>
                <option value="10000">10,000</option>
            </select>
        </div>
        <label class="hvn-toggle">
            <input type="checkbox" x-model="overwrite"> Overwrite existing
        </label>
    </div>

    <!-- Row 2: Recurring Discounts — full width -->
    <div class="hvn-toolbar-row">
        <div class="hvn-discounts">
            <label class="hvn-discounts__label">Discounts:</label>
            <div class="hvn-discounts__fields">
                <div class="hvn-discount"><label>Quarterly</label><input type="number" x-model.number="dQ" min="0" max="100" step="0.5"><span class="hvn-pct">%</span></div>
                <div class="hvn-discount"><label>Semi-Annual</label><input type="number" x-model.number="dSA" min="0" max="100" step="0.5"><span class="hvn-pct">%</span></div>
                <div class="hvn-discount"><label>Annual</label><input type="number" x-model.number="dA" min="0" max="100" step="0.5"><span class="hvn-pct">%</span></div>
                <div class="hvn-discount"><label>Biennial</label><input type="number" x-model.number="dBi" min="0" max="100" step="0.5"><span class="hvn-pct">%</span></div>
                <div class="hvn-discount"><label>Triennial</label><input type="number" x-model.number="dTri" min="0" max="100" step="0.5"><span class="hvn-pct">%</span></div>
            </div>
        </div>
    </div>

    <!-- Row 3: Setup Fee Discounts — full width -->
    <div class="hvn-toolbar-row">
        <div class="hvn-discounts">
            <label class="hvn-discounts__label">Setup Fee:</label>
            <div class="hvn-discounts__fields">
                <div class="hvn-discount"><label>Quarterly</label><input type="number" x-model.number="sdQ" min="0" max="100" step="0.5"><span class="hvn-pct">%</span></div>
                <div class="hvn-discount"><label>Semi-Annual</label><input type="number" x-model.number="sdSA" min="0" max="100" step="0.5"><span class="hvn-pct">%</span></div>
                <div class="hvn-discount"><label>Annual</label><input type="number" x-model.number="sdA" min="0" max="100" step="0.5"><span class="hvn-pct">%</span></div>
                <div class="hvn-discount"><label>Biennial</label><input type="number" x-model.number="sdBi" min="0" max="100" step="0.5"><span class="hvn-pct">%</span></div>
                <div class="hvn-discount"><label>Triennial</label><input type="number" x-model.number="sdTri" min="0" max="100" step="0.5"><span class="hvn-pct">%</span></div>
            </div>
        </div>
    </div>

    <!-- Row 4: Action Buttons -->
    <div class="hvn-toolbar-row">
        <div class="hvn-actions">
            <button type="button" class="hvn-btn hvn-btn--primary hvn-btn--sm" @click="calcCycles()">📊 Calc Cycles</button>
            <button type="button" class="hvn-btn hvn-btn--primary hvn-btn--sm" @click="calcCurrencies()">💱 Calc Currencies</button>
            <button type="button" class="hvn-btn hvn-btn--success hvn-btn--sm" @click="calcAll()">⚡ Calc All</button>
            <button type="button" class="hvn-btn hvn-btn--default hvn-btn--sm" @click="undo()">↩ Undo</button>
        </div>
    </div>

    <!-- Currency Rate Info -->
    <template x-if="showRates">
        <div class="hvn-currency-info">
            ℹ
            <template x-for="c in currencies" :key="c.id">
                <span class="hvn-rate" :class="{'hvn-rate--default': c.default==1}">
                    <span x-text="c.code"></span>
                    (rate: <span x-text="parseFloat(c.rate).toFixed(7)"></span>)
                    <template x-if="c.default==1"> <strong>(default)</strong></template>
                </span>
            </template>
        </div>
    </template>

</div>
</div>

TOOLBAR;

    return $html;
}

/**
 * Render a small script that relocates the toolbar to the correct position.
 * This runs immediately (not deferred), moving the already-rendered toolbar
 * from the footer to before the pricing table.
 */
function hvn_pricing_renderRelocatorScript(array $page): string
{
    $type = $page['type'] ?? '';

    // Build the selector strategy based on page type
    $script = '<script>';
    $script .= '(function(){';
    $script .= 'function relocate(){';
    $script .= 'var tb=document.getElementById("hvn-toolbar-mount");';
    $script .= 'if(!tb)return;';

    // WHMCS 8.x input naming patterns:
    //   Product/Addon: currency[ID][cycle]  or  pricing[ID][cycle]
    //   Config Options: price[currencyId][subId][index]
    $inputSelector = 'input[name*="currency["],input[name*="pricing["],input[name^="price["]';

    switch ($type) {
        case 'product_edit':
            $script .= 'var target=document.querySelector(\'' . $inputSelector . '\');';
            $script .= 'if(target){';
            $script .= '  var container=target.closest("table")||target.closest(".tab-pane")||target.closest("form");';
            $script .= '  if(container){container.parentNode.insertBefore(tb,container);tb.style.display="";}';
            $script .= '}else{';
            // Tab may not be active yet — observe DOM for when inputs appear
            $script .= '  var obs=new MutationObserver(function(m,o){';
            $script .= '    var t2=document.querySelector(\'' . $inputSelector . '\');';
            $script .= '    if(t2){var c2=t2.closest("table")||t2.closest("form");if(c2){c2.parentNode.insertBefore(tb,c2);tb.style.display="";}o.disconnect();}';
            $script .= '  });';
            $script .= '  obs.observe(document.body,{childList:true,subtree:true});';
            $script .= '}';
            break;

        case 'config_options':
            $script .= 'var target=document.querySelector(\'' . $inputSelector . '\');';
            $script .= 'if(target){';
            $script .= '  var container=target.closest("table")||target.closest("form");';
            $script .= '  if(container){container.parentNode.insertBefore(tb,container);tb.style.display="";}';
            $script .= '}else{';
            $script .= '  var form=document.querySelector(\'form[method="post"]\')||document.querySelector("form");';
            $script .= '  if(form){var ft=form.querySelector("table");if(ft)ft.parentNode.insertBefore(tb,ft);else form.insertBefore(tb,form.firstChild);tb.style.display="";}';
            $script .= '}';
            break;

        case 'addon_edit':
            $script .= 'var target=document.querySelector(\'' . $inputSelector . '\');';
            $script .= 'if(target){';
            $script .= '  var container=target.closest("table")||target.closest("form");';
            $script .= '  if(container){container.parentNode.insertBefore(tb,container);tb.style.display="";}';
            $script .= '}';
            break;
    }

    $script .= '}'; // end relocate()

    // Run on DOMContentLoaded and also after a short delay (for WHMCS AJAX tabs)
    $script .= 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",relocate);}';
    $script .= 'else{relocate();}';
    $script .= 'setTimeout(relocate,500);';
    $script .= 'setTimeout(relocate,1500);';

    // Listen for hash changes (WHMCS tab switching)
    $script .= 'window.addEventListener("hashchange",function(){setTimeout(relocate,300);});';

    $script .= '})();';
    $script .= '</script>';

    return $script;
}