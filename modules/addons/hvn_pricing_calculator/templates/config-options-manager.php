<?php
/**
 * HVN Pricing Calculator — Config Options Manager Template
 *
 * Injected into Product edit → Configurable Options tab (tab 5).
 * This template is loaded as an HTML string by hooks.php and injected via JS.
 *
 * Alpine.js component: hvnConfigManager
 */
defined("WHMCS") or die("Access Denied");
?>

<div id="hvn-config-mount"
     x-data="hvnConfigManager()"
     x-init="init()"
     class="hvn-config-mgr"
     style="display:none;"
     x-show="loaded || loading"
     x-transition>

    <!-- Header -->
    <div class="hvn-config-mgr__header">
        <span class="hvn-config-mgr__title">⚙ Configurable Options Manager</span>
        <a href="configproductoptions.php" target="_blank" class="hvn-btn hvn-btn--default hvn-btn--xs">
            ↗ Open in WHMCS
        </a>
    </div>

    <!-- Loading -->
    <template x-if="loading && !loaded">
        <div style="text-align:center;padding:20px;">
            <span class="hvn-spinner"></span> Loading configurable options...
        </div>
    </template>

    <!-- No Groups — Quick Create -->
    <template x-if="loaded && groups.length === 0">
        <div class="hvn-quick-create">
            <p>ℹ No configurable option groups assigned to this product.</p>

            <div class="hvn-quick-create__form">
                <label style="font-weight:600;font-size:13px;">Create new group:</label>
                <input type="text"
                       x-model="newGroupName"
                       class="hvn-input"
                       style="width:100%;height:32px;"
                       placeholder="Group Name">
                <input type="text"
                       x-model="newGroupDesc"
                       class="hvn-input"
                       style="width:100%;height:32px;"
                       placeholder="Description (optional)">
                <button type="button"
                        class="hvn-btn hvn-btn--primary"
                        @click="quickCreate()"
                        :disabled="saving">
                    <template x-if="saving"><span class="hvn-spinner"></span></template>
                    + Create &amp; Assign to This Product
                </button>

                <div class="hvn-divider"><span>OR assign existing group</span></div>

                <div style="display:flex;gap:8px;">
                    <select x-model="selectedExistingGroup"
                            class="hvn-select"
                            style="flex:1;height:32px;">
                        <option value="">Select existing group...</option>
                        <template x-for="g in existingGroups" :key="g.id">
                            <option :value="g.id" x-text="g.name"></option>
                        </template>
                    </select>
                    <button type="button"
                            class="hvn-btn hvn-btn--default"
                            @click="assignGroup(selectedExistingGroup)"
                            :disabled="!selectedExistingGroup || saving">
                        Assign
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- Has Groups — Show Manager -->
    <template x-if="loaded && groups.length > 0">
        <div>
            <!-- Currency Tabs -->
            <div class="hvn-tabs">
                <template x-for="(cur, idx) in currencies" :key="cur.id">
                    <div class="hvn-tab"
                         :class="{ 'hvn-tab--active': activeCurrency == cur.id }"
                         @click="setCurrency(cur.id)">
                        <span x-text="cur.code"></span>
                        <template x-if="cur.default == 1">
                            <small style="margin-left:3px;opacity:0.7;">Default</small>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Embedded Pricing Calculator -->
            <div x-data="hvnConfigToolbar()" class="hvn-toolbar hvn-mb-8">
                <div class="hvn-toolbar-header">
                    <span class="hvn-toolbar-title">⚡ Config Pricing Calculator</span>
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

                <div class="hvn-toolbar-row">
                    <div class="hvn-discounts">
                        <label style="font-weight:600;color:var(--hvn-text-secondary);font-size:12px;">Discounts:</label>
                        <template x-for="d in discountFields" :key="d.key">
                            <div class="hvn-discount">
                                <label x-text="d.label"></label>
                                <input type="number" x-model.number="discounts[d.key]" min="0" max="100" step="0.5"
                                       class="hvn-input hvn-input--num">
                                <span class="hvn-pct">%</span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="hvn-toolbar-row">
                    <div class="hvn-discounts">
                        <label style="font-weight:600;color:var(--hvn-text-secondary);font-size:12px;">Setup Fee:</label>
                        <template x-for="d in discountFields" :key="'s'+d.key">
                            <div class="hvn-discount">
                                <label x-text="d.label"></label>
                                <input type="number" x-model.number="setupDiscounts[d.key]" min="0" max="100" step="0.5"
                                       class="hvn-input hvn-input--num">
                                <span class="hvn-pct">%</span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="hvn-toolbar-row">
                    <div class="hvn-actions">
                        <button type="button" class="hvn-btn hvn-btn--primary hvn-btn--sm" @click="calcCycles()">
                            📊 Calc Cycles
                        </button>
                        <button type="button" class="hvn-btn hvn-btn--primary hvn-btn--sm" @click="calcCurrencies()">
                            💱 Calc Currencies
                        </button>
                        <button type="button" class="hvn-btn hvn-btn--success hvn-btn--sm" @click="calcAll()">
                            ⚡ Calc All
                        </button>
                        <button type="button" class="hvn-btn hvn-btn--default hvn-btn--sm" @click="undo()">
                            ↩ Undo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Option Groups -->
            <template x-for="group in groups" :key="group.id">
                <div class="hvn-optgroup">
                    <div class="hvn-optgroup__header"
                         @click="group._collapsed = !group._collapsed">
                        <span class="hvn-optgroup__title" x-text="group.name"></span>
                        <div class="hvn-optgroup__meta">
                            <template x-if="group.shared_count > 1">
                                <span class="hvn-badge hvn-badge--shared"
                                      x-text="'Shared with ' + (group.shared_count - 1) + ' product(s)'">
                                </span>
                            </template>
                            <span x-text="group._collapsed ? '▸' : '▾'" style="font-size:14px;"></span>
                        </div>
                    </div>

                    <div class="hvn-optgroup__body" x-show="!group._collapsed" x-transition>
                        <template x-for="option in group.options" :key="option.id">
                            <div style="margin-bottom:12px;">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                    <strong x-text="option.optionname" style="font-size:13px;"></strong>
                                    <span class="hvn-text-xs hvn-text-muted"
                                          x-text="'[' + optionTypeLabel(option.optiontype) + ']'"></span>
                                    <template x-if="option.optiontype == 4">
                                        <span class="hvn-text-xs hvn-text-muted"
                                              x-text="'Min:' + option.qtyminimum + ' / Max:' + option.qtymaximum">
                                        </span>
                                    </template>
                                    <label class="hvn-toggle hvn-text-xs" style="height:22px;padding:2px 6px;">
                                        <input type="checkbox"
                                               :checked="option.hidden == 1"
                                               @change="option.hidden = $event.target.checked ? 1 : 0">
                                        Hidden
                                    </label>
                                </div>

                                <!-- Pricing Table -->
                                <table class="hvn-ptable">
                                    <thead>
                                        <tr>
                                            <th style="min-width:140px;">Sub-option</th>
                                            <th>Hide</th>
                                            <th>Monthly</th>
                                            <th>Q</th>
                                            <th>SA</th>
                                            <th>A</th>
                                            <th>Bi</th>
                                            <th>Tri</th>
                                            <th>M Setup</th>
                                            <th>Q Setup</th>
                                            <th>SA Setup</th>
                                            <th>A Setup</th>
                                            <th>Bi Setup</th>
                                            <th>Tri Setup</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="sub in option.subs" :key="sub.id">
                                            <tr>
                                                <td :title="sub.name" x-text="sub.name"></td>
                                                <td>
                                                    <input type="checkbox"
                                                           :checked="sub.hidden == 1"
                                                           @change="sub.hidden = $event.target.checked ? 1 : 0">
                                                </td>
                                                <!-- Recurring cycles -->
                                                <template x-for="cycle in ['monthly','quarterly','semiannually','annually','biennially','triennially']"
                                                          :key="cycle">
                                                    <td :class="{ 'hvn-cell-disabled': getPricing(sub, activeCurrency, cycle) == '-1.00' }">
                                                        <input type="text"
                                                               :value="getPricing(sub, activeCurrency, cycle)"
                                                               @change="setPricing(sub, activeCurrency, cycle, $event.target.value)"
                                                               :data-sub-id="sub.id"
                                                               :data-currency="activeCurrency"
                                                               :data-cycle="cycle"
                                                               class="hvn-config-input">
                                                    </td>
                                                </template>
                                                <!-- Setup fees -->
                                                <template x-for="fee in ['msetupfee','qsetupfee','ssetupfee','asetupfee','bsetupfee','tsetupfee']"
                                                          :key="fee">
                                                    <td>
                                                        <input type="text"
                                                               :value="getPricing(sub, activeCurrency, fee)"
                                                               @change="setPricing(sub, activeCurrency, fee, $event.target.value)"
                                                               :data-sub-id="sub.id"
                                                               :data-currency="activeCurrency"
                                                               :data-cycle="fee"
                                                               class="hvn-config-input">
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <template x-if="group.options.length === 0">
                            <p class="hvn-text-muted hvn-text-sm" style="padding:10px;">
                                No options in this group yet.
                                <a :href="'configproductoptions.php?action=managegroup&id=' + group.id" target="_blank">
                                    Add options in WHMCS →
                                </a>
                            </p>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Legend + Save -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                <span class="hvn-text-xs hvn-text-muted">
                    [-1.00] = disabled &nbsp;&nbsp; [0.00] = free
                </span>
                <button type="button"
                        class="hvn-btn hvn-btn--success"
                        @click="savePricing()"
                        :disabled="saving">
                    <template x-if="saving"><span class="hvn-spinner"></span></template>
                    Save Changes
                </button>
            </div>
        </div>
    </template>
</div>