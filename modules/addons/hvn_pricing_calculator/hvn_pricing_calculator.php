<?php
/**
 * HVN - Pricing Calculator — WHMCS Addon Module
 *
 * Auto-calculate billing cycle pricing and currency conversion
 * for Products, Configurable Options and Addons.
 *
 * @package    HVN_Pricing_Calculator
 * @author     HVN GROUP
 * @copyright  2026 HVN GROUP
 * @license    Proprietary
 * @version    1.0.0
 * @link       https://hvn.vn
 */

defined("WHMCS") or die("Access Denied");

use Illuminate\Database\Capsule\Manager as Capsule;

if (!defined('HVN_PRICING_DIR')) {
    define('HVN_PRICING_DIR', __DIR__);
}

/**
 * Module configuration metadata.
 */
function hvn_pricing_calculator_config(): array
{
    return [
        'name'        => 'HVN - Pricing Calculator',
        'description' => 'Auto-calculate billing cycle pricing and currency conversion for Products, Configurable Options and Addons.',
        'version'     => '1.0.0',
        'author'      => '<a href="https://hvn.vn" target="_blank">HVN GROUP</a>',
        'language'    => 'english',
        'fields'      => [
            'autoInjectToolbar' => [
                'FriendlyName' => 'Auto-inject Toolbar',
                'Type'         => 'yesno',
                'Default'      => 'on',
                'Description'  => 'Automatically show pricing calculator toolbar on pricing pages.',
            ],
            'defaultPreset' => [
                'FriendlyName' => 'Default Preset',
                'Type'         => 'text',
                'Size'         => '30',
                'Default'      => 'Standard',
                'Description'  => 'Name of the default discount preset.',
            ],
            'showCurrencyRates' => [
                'FriendlyName' => 'Show Currency Rates',
                'Type'         => 'yesno',
                'Default'      => 'on',
                'Description'  => 'Display exchange rate info in toolbar.',
            ],
            'confirmBeforeApply' => [
                'FriendlyName' => 'Confirm Before Apply',
                'Type'         => 'yesno',
                'Default'      => '',
                'Description'  => 'Show confirmation dialog before applying calculations.',
            ],
            'defaultRounding' => [
                'FriendlyName' => 'Default Rounding Method',
                'Type'         => 'dropdown',
                'Options'      => 'none,ceil,floor,round',
                'Default'      => 'round',
                'Description'  => 'Default rounding method for calculations.',
            ],
            'defaultRoundTo' => [
                'FriendlyName' => 'Default Rounding Precision',
                'Type'         => 'dropdown',
                'Options'      => '0.01,1,100,1000,10000',
                'Default'      => '1',
                'Description'  => 'Default rounding unit.',
            ],
        ],
    ];
}

/**
 * Module activation — create tables, seed default presets.
 */
function hvn_pricing_calculator_activate(): array
{
    try {
        if (!Capsule::schema()->hasTable('tbl_hvn_pricing_presets')) {
            Capsule::schema()->create('tbl_hvn_pricing_presets', function ($table) {
                /** @var \Illuminate\Database\Schema\Blueprint $table */
                $table->increments('id');
                $table->string('name', 100);
                $table->decimal('discount_quarterly', 5, 2)->default(0.00);
                $table->decimal('discount_semiannually', 5, 2)->default(0.00);
                $table->decimal('discount_annually', 5, 2)->default(0.00);
                $table->decimal('discount_biennially', 5, 2)->default(0.00);
                $table->decimal('discount_triennially', 5, 2)->default(0.00);
                $table->decimal('discount_setup_quarterly', 5, 2)->default(0.00);
                $table->decimal('discount_setup_semiannually', 5, 2)->default(0.00);
                $table->decimal('discount_setup_annually', 5, 2)->default(0.00);
                $table->decimal('discount_setup_biennially', 5, 2)->default(0.00);
                $table->decimal('discount_setup_triennially', 5, 2)->default(0.00);
                $table->enum('rounding_method', ['none', 'ceil', 'floor', 'round'])->default('round');
                $table->decimal('rounding_precision', 10, 2)->default(1.00);
                $table->tinyInteger('is_default')->default(0);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Seed default presets
        $now = date('Y-m-d H:i:s');
        $presets = [
            [
                'name' => 'No Discount',
                'discount_quarterly' => 0, 'discount_semiannually' => 0,
                'discount_annually' => 0, 'discount_biennially' => 0,
                'discount_triennially' => 0,
                'discount_setup_quarterly' => 0, 'discount_setup_semiannually' => 0,
                'discount_setup_annually' => 0, 'discount_setup_biennially' => 0,
                'discount_setup_triennially' => 0,
                'rounding_method' => 'round', 'rounding_precision' => 1.00,
                'is_default' => 0, 'sort_order' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Standard',
                'discount_quarterly' => 0, 'discount_semiannually' => 5,
                'discount_annually' => 10, 'discount_biennially' => 15,
                'discount_triennially' => 20,
                'discount_setup_quarterly' => 0, 'discount_setup_semiannually' => 5,
                'discount_setup_annually' => 10, 'discount_setup_biennially' => 15,
                'discount_setup_triennially' => 20,
                'rounding_method' => 'round', 'rounding_precision' => 1.00,
                'is_default' => 1, 'sort_order' => 2,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Aggressive',
                'discount_quarterly' => 5, 'discount_semiannually' => 10,
                'discount_annually' => 20, 'discount_biennially' => 25,
                'discount_triennially' => 30,
                'discount_setup_quarterly' => 5, 'discount_setup_semiannually' => 10,
                'discount_setup_annually' => 20, 'discount_setup_biennially' => 25,
                'discount_setup_triennially' => 30,
                'rounding_method' => 'round', 'rounding_precision' => 1.00,
                'is_default' => 0, 'sort_order' => 3,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ];

        foreach ($presets as $preset) {
            $exists = Capsule::table('tbl_hvn_pricing_presets')
                ->where('name', $preset['name'])
                ->exists();
            if (!$exists) {
                Capsule::table('tbl_hvn_pricing_presets')->insert($preset);
            }
        }

        logActivity('HVN - Pricing Calculator: Module activated successfully.');

        return [
            'status'      => 'success',
            'description' => 'HVN - Pricing Calculator activated. Default presets created.',
        ];
    } catch (\Exception $e) {
        logActivity('HVN - Pricing Calculator: Activation failed — ' . $e->getMessage());
        return [
            'status'      => 'error',
            'description' => 'Activation failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Module deactivation — drop custom tables.
 */
function hvn_pricing_calculator_deactivate(): array
{
    try {
        Capsule::schema()->dropIfExists('tbl_hvn_pricing_presets');

        logActivity('HVN - Pricing Calculator: Module deactivated, tables removed.');

        return [
            'status'      => 'success',
            'description' => 'Module deactivated. Custom tables removed.',
        ];
    } catch (\Exception $e) {
        return [
            'status'      => 'error',
            'description' => 'Deactivation failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Module upgrade handler.
 */
function hvn_pricing_calculator_upgrade(array $vars): void
{
    $currentVersion = $vars['version'];

    // v1.1.0: Add setup fee discount columns
    if (version_compare($currentVersion, '1.1.0', '<')) {
        try {
            $columns = [
                'discount_setup_quarterly', 'discount_setup_semiannually',
                'discount_setup_annually', 'discount_setup_biennially',
                'discount_setup_triennially',
            ];
            foreach ($columns as $col) {
                if (!Capsule::schema()->hasColumn('tbl_hvn_pricing_presets', $col)) {
                    Capsule::schema()->table('tbl_hvn_pricing_presets', function ($table) use ($col) {
                        $table->decimal($col, 5, 2)->default(0.00);
                    });
                }
            }
            logActivity('HVN - Pricing Calculator: Upgraded to v1.1.0.');
        } catch (\Exception $e) {
            logActivity('HVN - Pricing Calculator: Upgrade to v1.1.0 failed — ' . $e->getMessage());
        }
    }
}

/**
 * Admin output — settings page + AJAX handler.
 */
function hvn_pricing_calculator_output(array $vars): void
{
    $moduleLink = $vars['modulelink'];
    $version    = $vars['version'];
    $LANG       = $vars['_lang'];

    $action = $_REQUEST['action'] ?? 'index';

    // AJAX endpoints
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        header('Content-Type: application/json; charset=utf-8');
        hvn_pricing_calculator_handleAjax($action);
        return;
    }

    // Render page
    switch ($action) {
        case 'presets':
            hvn_pricing_calculator_renderPresets($vars);
            break;
        default:
            hvn_pricing_calculator_renderDashboard($vars);
            break;
    }
}

/**
 * AJAX request handler.
 */
function hvn_pricing_calculator_handleAjax(string $action): void
{
    try {
        switch ($action) {

            case 'get_presets':
                $presets = Capsule::table('tbl_hvn_pricing_presets')
                    ->orderBy('sort_order')
                    ->get()
                    ->toArray();
                echo json_encode(['success' => true, 'data' => $presets]);
                break;

            case 'save_preset':
                $id   = (int) ($_POST['id'] ?? 0);
                $data = [
                    'name'                         => trim($_POST['name'] ?? ''),
                    'discount_quarterly'            => max(0, min(100, (float) ($_POST['discount_quarterly'] ?? 0))),
                    'discount_semiannually'         => max(0, min(100, (float) ($_POST['discount_semiannually'] ?? 0))),
                    'discount_annually'             => max(0, min(100, (float) ($_POST['discount_annually'] ?? 0))),
                    'discount_biennially'           => max(0, min(100, (float) ($_POST['discount_biennially'] ?? 0))),
                    'discount_triennially'          => max(0, min(100, (float) ($_POST['discount_triennially'] ?? 0))),
                    'discount_setup_quarterly'      => max(0, min(100, (float) ($_POST['discount_setup_quarterly'] ?? 0))),
                    'discount_setup_semiannually'   => max(0, min(100, (float) ($_POST['discount_setup_semiannually'] ?? 0))),
                    'discount_setup_annually'       => max(0, min(100, (float) ($_POST['discount_setup_annually'] ?? 0))),
                    'discount_setup_biennially'     => max(0, min(100, (float) ($_POST['discount_setup_biennially'] ?? 0))),
                    'discount_setup_triennially'    => max(0, min(100, (float) ($_POST['discount_setup_triennially'] ?? 0))),
                    'rounding_method'               => in_array($_POST['rounding_method'] ?? '', ['none','ceil','floor','round']) ? $_POST['rounding_method'] : 'round',
                    'rounding_precision'            => (float) ($_POST['rounding_precision'] ?? 1),
                    'is_default'                    => in_array($_POST['is_default'] ?? '', ['1', 'true', 'on'], true) ? 1 : 0,
                    'updated_at'                    => date('Y-m-d H:i:s'),
                ];

                if (empty($data['name'])) {
                    echo json_encode(['success' => false, 'error' => 'Preset name is required.']);
                    return;
                }

                if ($data['is_default']) {
                    Capsule::table('tbl_hvn_pricing_presets')->update(['is_default' => 0]);
                }

                if ($id > 0) {
                    Capsule::table('tbl_hvn_pricing_presets')->where('id', $id)->update($data);
                } else {
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $data['sort_order'] = (int) Capsule::table('tbl_hvn_pricing_presets')->max('sort_order') + 1;
                    $id = Capsule::table('tbl_hvn_pricing_presets')->insertGetId($data);
                }

                logActivity("HVN - Pricing Calculator: Preset '{$data['name']}' saved (ID: {$id}).");
                echo json_encode(['success' => true, 'id' => $id]);
                break;

            case 'delete_preset':
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $name = Capsule::table('tbl_hvn_pricing_presets')->where('id', $id)->value('name');
                    Capsule::table('tbl_hvn_pricing_presets')->where('id', $id)->delete();
                    logActivity("HVN - Pricing Calculator: Preset '{$name}' deleted.");
                }
                echo json_encode(['success' => true]);
                break;

            case 'get_currencies':
                $currencies = Capsule::table('tblcurrencies')
                    ->orderBy('default', 'desc')
                    ->orderBy('code')
                    ->get()
                    ->toArray();
                echo json_encode(['success' => true, 'data' => $currencies]);
                break;

            case 'get_config_options':
                $productId = (int) ($_GET['product_id'] ?? 0);
                if ($productId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Invalid product ID.']);
                    return;
                }
                $data = hvn_pricing_calculator_getConfigOptionsData($productId);
                echo json_encode(['success' => true, 'data' => $data]);
                break;

            case 'save_config_options':
                $pricing = json_decode($_POST['pricing'] ?? '[]', true);
                if (!is_array($pricing) || empty($pricing)) {
                    echo json_encode(['success' => false, 'error' => 'No pricing data provided.']);
                    return;
                }
                $count = hvn_pricing_calculator_saveConfigOptionsPricing($pricing);

                // Save hidden states for options
                $hiddenOptions = json_decode($_POST['hidden_options'] ?? '[]', true);
                if (is_array($hiddenOptions)) {
                    foreach ($hiddenOptions as $item) {
                        $optId = (int) ($item['id'] ?? 0);
                        $hidden = (int) ($item['hidden'] ?? 0);
                        if ($optId > 0) {
                            Capsule::table('tblproductconfigoptions')
                                ->where('id', $optId)
                                ->update(['hidden' => $hidden]);
                        }
                    }
                }

                // Save hidden states for sub-options
                $hiddenSubs = json_decode($_POST['hidden_subs'] ?? '[]', true);
                if (is_array($hiddenSubs)) {
                    foreach ($hiddenSubs as $item) {
                        $subId = (int) ($item['id'] ?? 0);
                        $hidden = (int) ($item['hidden'] ?? 0);
                        if ($subId > 0) {
                            Capsule::table('tblproductconfigoptionssub')
                                ->where('id', $subId)
                                ->update(['hidden' => $hidden]);
                        }
                    }
                }

                logActivity("HVN - Pricing Calculator: Saved {$count} config option pricing records + hidden states.");
                echo json_encode(['success' => true, 'count' => $count]);
                break;

            case 'quick_create_group':
                $name      = trim($_POST['name'] ?? '');
                $desc      = trim($_POST['description'] ?? '');
                $productId = (int) ($_POST['product_id'] ?? 0);

                if (empty($name) || $productId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Group name and product ID are required.']);
                    return;
                }

                $groupId = Capsule::table('tblproductconfiggroups')->insertGetId([
                    'name'        => $name,
                    'description' => $desc,
                ]);

                Capsule::table('tblproductconfiglinks')->insert([
                    'gid' => $groupId,
                    'pid' => $productId,
                ]);

                logActivity("HVN - Pricing Calculator: Created config group '{$name}' (#{$groupId}), assigned to product #{$productId}.");
                echo json_encode(['success' => true, 'group_id' => $groupId]);
                break;

            case 'get_existing_groups':
                $productId = (int) ($_GET['product_id'] ?? 0);
                $assignedIds = Capsule::table('tblproductconfiglinks')
                    ->where('pid', $productId)
                    ->pluck('gid')
                    ->toArray();

                $groups = Capsule::table('tblproductconfiggroups')
                    ->whereNotIn('id', $assignedIds)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->toArray();

                echo json_encode(['success' => true, 'data' => $groups]);
                break;

            case 'assign_group':
                $groupId   = (int) ($_POST['group_id'] ?? 0);
                $productId = (int) ($_POST['product_id'] ?? 0);

                if ($groupId <= 0 || $productId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Invalid group or product ID.']);
                    return;
                }

                $exists = Capsule::table('tblproductconfiglinks')
                    ->where('gid', $groupId)
                    ->where('pid', $productId)
                    ->exists();

                if (!$exists) {
                    Capsule::table('tblproductconfiglinks')->insert([
                        'gid' => $groupId,
                        'pid' => $productId,
                    ]);
                }

                logActivity("HVN - Pricing Calculator: Assigned config group #{$groupId} to product #{$productId}.");
                echo json_encode(['success' => true]);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
        }
    } catch (\Exception $e) {
        logActivity('HVN - Pricing Calculator AJAX Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    exit;
}

/**
 * Fetch config options data for a product (groups, options, sub-options, pricing).
 */
function hvn_pricing_calculator_getConfigOptionsData(int $productId): array
{
    $groups = Capsule::table('tblproductconfiglinks as l')
        ->join('tblproductconfiggroups as g', 'g.id', '=', 'l.gid')
        ->where('l.pid', $productId)
        ->select('g.*')
        ->get()
        ->toArray();

    $currencies = Capsule::table('tblcurrencies')
        ->orderBy('default', 'desc')
        ->orderBy('code')
        ->get()
        ->toArray();

    $result = [];

    foreach ($groups as $group) {
        $options = Capsule::table('tblproductconfigoptions')
            ->where('gid', $group->id)
            ->orderBy('order')
            ->get()
            ->toArray();

        $linkedCount = Capsule::table('tblproductconfiglinks')
            ->where('gid', $group->id)
            ->count();

        $groupData = [
            'id'           => $group->id,
            'name'         => $group->name,
            'description'  => $group->description ?? '',
            'shared_count' => $linkedCount,
            'options'      => [],
        ];

        foreach ($options as $option) {
            $subOptions = Capsule::table('tblproductconfigoptionssub')
                ->where('configid', $option->id)
                ->orderBy('sortorder')
                ->get()
                ->toArray();

            $optionData = [
                'id'         => $option->id,
                'optionname' => $option->optionname,
                'optiontype' => $option->optiontype,
                'hidden'     => (int) $option->hidden,
                'qtyminimum' => $option->qtyminimum ?? 0,
                'qtymaximum' => $option->qtymaximum ?? 0,
                'subs'       => [],
            ];

            foreach ($subOptions as $sub) {
                $pricing = [];
                foreach ($currencies as $cur) {
                    $price = Capsule::table('tblpricing')
                        ->where('type', 'configoptions')
                        ->where('relid', $sub->id)
                        ->where('currency', $cur->id)
                        ->first();

                    $pricing[$cur->id] = $price ? (array) $price : null;
                }

                $optionData['subs'][] = [
                    'id'      => $sub->id,
                    'name'    => $sub->optionname,
                    'hidden'  => (int) $sub->hidden,
                    'pricing' => $pricing,
                ];
            }

            $groupData['options'][] = $optionData;
        }

        $result[] = $groupData;
    }

    return [
        'groups'     => $result,
        'currencies' => $currencies,
    ];
}

/**
 * Save config options pricing. Returns number of records saved.
 */
function hvn_pricing_calculator_saveConfigOptionsPricing(array $pricingData): int
{
    $count = 0;
    $cycles = ['monthly','quarterly','semiannually','annually','biennially','triennially'];
    $setupFees = ['msetupfee','qsetupfee','ssetupfee','asetupfee','bsetupfee','tsetupfee'];
    $allFields = array_merge($cycles, $setupFees);

    foreach ($pricingData as $item) {
        $subId      = (int) ($item['sub_id'] ?? 0);
        $currencyId = (int) ($item['currency_id'] ?? 0);
        if ($subId <= 0 || $currencyId <= 0) {
            continue;
        }

        $values = [];
        foreach ($allFields as $field) {
            if (isset($item[$field])) {
                $values[$field] = $item[$field];
            }
        }

        if (empty($values)) {
            continue;
        }

        $exists = Capsule::table('tblpricing')
            ->where('type', 'configoptions')
            ->where('relid', $subId)
            ->where('currency', $currencyId)
            ->exists();

        if ($exists) {
            Capsule::table('tblpricing')
                ->where('type', 'configoptions')
                ->where('relid', $subId)
                ->where('currency', $currencyId)
                ->update($values);
        } else {
            Capsule::table('tblpricing')->insert(array_merge($values, [
                'type'     => 'configoptions',
                'relid'    => $subId,
                'currency' => $currencyId,
            ]));
        }

        $count++;
    }

    return $count;
}

/**
 * Render admin dashboard page.
 */
function hvn_pricing_calculator_renderDashboard(array $vars): void
{
    $moduleLink = $vars['modulelink'];
    $version    = $vars['version'];

    $presetCount    = Capsule::table('tbl_hvn_pricing_presets')->count();
    $currencyCount  = Capsule::table('tblcurrencies')->count();
    $defaultCur     = Capsule::table('tblcurrencies')->where('default', 1)->first();

    include HVN_PRICING_DIR . '/templates/admin-dashboard.php';
}

/**
 * Render presets management page.
 */
function hvn_pricing_calculator_renderPresets(array $vars): void
{
    $moduleLink = $vars['modulelink'];
    $presets    = Capsule::table('tbl_hvn_pricing_presets')
        ->orderBy('sort_order')
        ->get()
        ->toArray();

    include HVN_PRICING_DIR . '/templates/admin-presets.php';
}