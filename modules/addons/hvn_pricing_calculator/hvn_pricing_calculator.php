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
 * @version    1.1.0
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
        'version'     => '1.1.0',
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
                $table->string('base_cycle', 20)->default('monthly');
                // Recurring discounts
                $table->decimal('discount_quarterly', 5, 2)->default(0.00);
                $table->decimal('discount_semiannually', 5, 2)->default(0.00);
                $table->decimal('discount_annually', 5, 2)->default(0.00);
                $table->decimal('discount_biennially', 5, 2)->default(0.00);
                $table->decimal('discount_triennially', 5, 2)->default(0.00);
                // Setup fee discounts
                $table->decimal('discount_setup_quarterly', 5, 2)->default(0.00);
                $table->decimal('discount_setup_semiannually', 5, 2)->default(0.00);
                $table->decimal('discount_setup_annually', 5, 2)->default(0.00);
                $table->decimal('discount_setup_biennially', 5, 2)->default(0.00);
                $table->decimal('discount_setup_triennially', 5, 2)->default(0.00);
                // Cycle enable/disable toggles (1 = enabled, 0 = skip)
                $table->tinyInteger('cycle_quarterly')->default(1);
                $table->tinyInteger('cycle_semiannually')->default(1);
                $table->tinyInteger('cycle_annually')->default(1);
                $table->tinyInteger('cycle_biennially')->default(1);
                $table->tinyInteger('cycle_triennially')->default(1);
                // Rounding
                $table->enum('rounding_method', ['none', 'ceil', 'floor', 'round'])->default('round');
                $table->decimal('rounding_precision', 10, 2)->default(1.00);
                $table->tinyInteger('is_default')->default(0);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Seed 9 default presets: 3 discount levels × 3 cycle profiles
        $now = date('Y-m-d H:i:s');
        $base = [
            'discount_setup_quarterly' => 0, 'discount_setup_semiannually' => 0,
            'discount_setup_annually' => 0, 'discount_setup_biennially' => 0,
            'discount_setup_triennially' => 0,
            'rounding_method' => 'round', 'rounding_precision' => 1.00,
            'is_default' => 0, 'created_at' => $now, 'updated_at' => $now,
        ];

        $discountLevels = [
            'No Discount' => ['q' => 0, 'sa' => 0, 'a' => 0, 'bi' => 0, 'tri' => 0],
            'Standard'    => ['q' => 0, 'sa' => 5, 'a' => 10, 'bi' => 15, 'tri' => 20],
            'Aggressive'  => ['q' => 5, 'sa' => 10, 'a' => 20, 'bi' => 25, 'tri' => 30],
        ];

        $cycleProfiles = [
            'All Cycles'  => ['base' => 'monthly',  'q' => 1, 'sa' => 1, 'a' => 1, 'bi' => 1, 'tri' => 1],
            'Flexible'    => ['base' => 'monthly',  'q' => 1, 'sa' => 1, 'a' => 1, 'bi' => 0, 'tri' => 0],
            'Annual Only' => ['base' => 'annually', 'q' => 0, 'sa' => 0, 'a' => 0, 'bi' => 1, 'tri' => 1],
        ];

        $order = 1;
        $presets = [];
        foreach ($cycleProfiles as $profileName => $profile) {
            foreach ($discountLevels as $levelName => $discounts) {
                $presets[] = array_merge($base, [
                    'name' => $levelName . ' — ' . $profileName,
                    'base_cycle' => $profile['base'],
                    'discount_quarterly'    => $discounts['q'],
                    'discount_semiannually' => $discounts['sa'],
                    'discount_annually'     => $discounts['a'],
                    'discount_biennially'   => $discounts['bi'],
                    'discount_triennially'  => $discounts['tri'],
                    'discount_setup_quarterly'    => $discounts['q'],
                    'discount_setup_semiannually' => $discounts['sa'],
                    'discount_setup_annually'     => $discounts['a'],
                    'discount_setup_biennially'   => $discounts['bi'],
                    'discount_setup_triennially'  => $discounts['tri'],
                    'cycle_quarterly'    => $profile['q'],
                    'cycle_semiannually' => $profile['sa'],
                    'cycle_annually'     => $profile['a'],
                    'cycle_biennially'   => $profile['bi'],
                    'cycle_triennially'  => $profile['tri'],
                    'is_default' => ($levelName === 'Standard' && $profileName === 'All Cycles') ? 1 : 0,
                    'sort_order' => $order++,
                ]);
            }
        }

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

    // v1.1.0: Add cycle enable/disable columns + setup fee discount columns
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

            // Add cycle toggle columns
            $cycleColumns = [
                'cycle_quarterly', 'cycle_semiannually',
                'cycle_annually', 'cycle_biennially', 'cycle_triennially',
            ];
            foreach ($cycleColumns as $col) {
                if (!Capsule::schema()->hasColumn('tbl_hvn_pricing_presets', $col)) {
                    Capsule::schema()->table('tbl_hvn_pricing_presets', function ($table) use ($col) {
                        $table->tinyInteger($col)->default(1);
                    });
                }
            }

            // Add base_cycle column
            if (!Capsule::schema()->hasColumn('tbl_hvn_pricing_presets', 'base_cycle')) {
                Capsule::schema()->table('tbl_hvn_pricing_presets', function ($table) {
                    $table->string('base_cycle', 20)->default('monthly')->after('name');
                });
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

    $ajaxActions = [
        'get_presets',
        'save_preset',
        'delete_preset',
        'get_currencies',
        'get_config_options',
        'save_config_options',
        'quick_create_group',
        'assign_config_group',
    ];

    $isXhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isXhr || in_array($action, $ajaxActions, true)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        hvn_pricing_calculator_handleAjax($action);
        exit;
    }

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
                    'base_cycle'                   => in_array($_POST['base_cycle'] ?? '', ['monthly','annually']) ? $_POST['base_cycle'] : 'monthly',
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
                    'cycle_quarterly'               => in_array($_POST['cycle_quarterly'] ?? '', ['1', 'true', 'on'], true) ? 1 : 0,
                    'cycle_semiannually'            => in_array($_POST['cycle_semiannually'] ?? '', ['1', 'true', 'on'], true) ? 1 : 0,
                    'cycle_annually'                => in_array($_POST['cycle_annually'] ?? '', ['1', 'true', 'on'], true) ? 1 : 0,
                    'cycle_biennially'              => in_array($_POST['cycle_biennially'] ?? '', ['1', 'true', 'on'], true) ? 1 : 0,
                    'cycle_triennially'             => in_array($_POST['cycle_triennially'] ?? '', ['1', 'true', 'on'], true) ? 1 : 0,
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
                $pricingRaw       = html_entity_decode($_POST['pricing']        ?? '[]', ENT_QUOTES, 'UTF-8');
                $hiddenOptionsRaw = html_entity_decode($_POST['hidden_options'] ?? '[]', ENT_QUOTES, 'UTF-8');
                $hiddenSubsRaw    = html_entity_decode($_POST['hidden_subs']    ?? '[]', ENT_QUOTES, 'UTF-8');

                $pricing = json_decode($pricingRaw, true);
                if (!is_array($pricing) || empty($pricing)) {
                    echo json_encode(['success' => false, 'error' => 'No pricing data provided.']);
                    return;
                }

                $count = hvn_pricing_calculator_saveConfigOptionsPricing($pricing);

                $hiddenOptions = json_decode($hiddenOptionsRaw, true);
                if (is_array($hiddenOptions)) {
                    foreach ($hiddenOptions as $item) {
                        $optId  = (int) ($item['id'] ?? 0);
                        $hidden = (int) ($item['hidden'] ?? 0);
                        if ($optId > 0) {
                            Capsule::table('tblproductconfigoptions')
                                ->where('id', $optId)
                                ->update(['hidden' => $hidden]);
                        }
                    }
                }

                $hiddenSubs = json_decode($hiddenSubsRaw, true);
                if (is_array($hiddenSubs)) {
                    foreach ($hiddenSubs as $item) {
                        $subId  = (int) ($item['id'] ?? 0);
                        $hidden = (int) ($item['hidden'] ?? 0);
                        if ($subId > 0) {
                            Capsule::table('tblproductconfigoptionssub')
                                ->where('id', $subId)
                                ->update(['hidden' => $hidden]);
                        }
                    }
                }
                
                logActivity("HVN - Pricing Calculator: Saved {$count} config option pricing records.");
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
 * Fetch config options data for a product.
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
 * Save config options pricing.
 */
function hvn_pricing_calculator_saveConfigOptionsPricing(array $pricingData): int
{
    $count = 0;
    $allFields = [
        'monthly','quarterly','semiannually','annually','biennially','triennially',
        'msetupfee','qsetupfee','ssetupfee','asetupfee','bsetupfee','tsetupfee',
    ];

    foreach ($pricingData as $item) {
        $subId      = (int) ($item['sub_id'] ?? 0);
        $currencyId = (int) ($item['currency_id'] ?? 0);
        if ($subId <= 0 || $currencyId <= 0) continue;

        $values = [];
        foreach ($allFields as $field) {
            if (isset($item[$field])) $values[$field] = $item[$field];
        }
        if (empty($values)) continue;

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