/**
 * HVN Pricing Calculator v1.0.0
 *
 * Alpine.js components for pricing calculation.
 *
 * ARCHITECTURE:
 *   - Toolbar HTML is rendered by PHP (hooks.php) and relocated by a small script.
 *   - This file registers Alpine.data() components via the `alpine:init` event.
 *   - Alpine.js CDN (defer) loads AFTER this file, fires `alpine:init`,
 *     then processes the DOM and finds x-data attributes.
 *
 * LOAD ORDER (set by hooks.php):
 *   1. <script>window.HvnConfig={...}</script>     — inline, sync
 *   2. <script src="hvn-pricing.js"></script>       — sync (registers alpine:init)
 *   3. <script defer src="alpine.js"></script>      — defer (processes DOM last)
 *   4. Toolbar HTML (already in DOM from PHP)
 *   5. Relocator script (moves toolbar to correct position)
 */

(function () {
    'use strict';

    var CFG = window.HvnConfig || {};

    /* ================================================================
       UTILITIES
       ================================================================ */
    var Utils = {
        round: function (val, method, precision) {
            if (method === 'none' || !precision) return val;
            var f = 1 / precision;
            switch (method) {
                case 'ceil':  return Math.ceil(val * f) / f;
                case 'floor': return Math.floor(val * f) / f;
                case 'round': return Math.round(val * f) / f;
                default:      return val;
            }
        },

        fmt: function (v) {
            if (v === -1) return '-1.00';
            if (v === 0) return '0.00';
            return parseFloat(v).toFixed(2);
        },

        parse: function (s) {
            if (s === undefined || s === null || s === '') return null;
            var n = parseFloat(String(s).replace(/,/g, '').trim());
            return isNaN(n) ? null : n;
        },

        defaultCurrency: function () {
            if (!CFG.currencies) return null;
            for (var i = 0; i < CFG.currencies.length; i++) {
                if (CFG.currencies[i].default == 1) return CFG.currencies[i];
            }
            return CFG.currencies[0] || null;
        },

        toast: function (msg, type) {
            type = type || 'info';
            var container = document.getElementById('hvn-toasts');
            if (!container) {
                container = document.createElement('div');
                container.id = 'hvn-toasts';
                container.className = 'hvn-toast-container';
                document.body.appendChild(container);
            }
            var el = document.createElement('div');
            el.className = 'hvn-toast hvn-toast--' + type;
            el.textContent = msg;
            container.appendChild(el);
            requestAnimationFrame(function () { el.classList.add('hvn-toast--visible'); });
            setTimeout(function () {
                el.classList.remove('hvn-toast--visible');
                setTimeout(function () { el.remove(); }, 300);
            }, 3500);
        },

        highlight: function (el) {
            el.classList.add('hvn-changed');
            setTimeout(function () { el.classList.remove('hvn-changed'); }, 3000);
        },

        fetchJson: async function (url, opts) {
            opts = opts || {};
            opts.headers = Object.assign(
                { 'X-Requested-With': 'XMLHttpRequest' },
                opts.headers || {}
            );
            try {
                var resp = await fetch(url, opts);
                return await resp.json();
            } catch (e) {
                Utils.toast('Network error: ' + e.message, 'error');
                return { success: false, error: e.message };
            }
        }
    };

    /* ================================================================
       CALCULATOR ENGINE
       ================================================================ */
    var Calc = {
        MULTIPLIERS: {
            monthly: 1, quarterly: 3, semiannually: 6,
            annually: 12, biennially: 24, triennially: 36
        },
        SETUP_MAP: {
            monthly: 'msetupfee', quarterly: 'qsetupfee', semiannually: 'ssetupfee',
            annually: 'asetupfee', biennially: 'bsetupfee', triennially: 'tsetupfee'
        },

        cycles: function (baseVal, baseCycle, discounts, rounding, roundTo) {
            if (baseVal === null || baseVal === -1) return null;
            var baseMult = this.MULTIPLIERS[baseCycle] || 1;
            var monthlyEquiv = baseVal / baseMult;
            var result = {};
            var dMap = { quarterly:'q', semiannually:'sa', annually:'a', biennially:'bi', triennially:'tri' };

            for (var cycle in this.MULTIPLIERS) {
                if (cycle === baseCycle) { result[cycle] = baseVal; continue; }
                var mult = this.MULTIPLIERS[cycle];
                if (baseCycle === 'annually' && mult < baseMult) continue;
                var raw = monthlyEquiv * mult;
                var dk = dMap[cycle];
                if (dk && discounts[dk] && mult > baseMult) raw *= (1 - discounts[dk] / 100);
                result[cycle] = Utils.round(raw, rounding, roundTo);
            }
            return result;
        },

        setupFees: function (baseSetup, baseCycle, setupDiscounts, rounding, roundTo) {
            if (baseSetup === null || baseSetup === -1) return null;
            var baseFeeKey = this.SETUP_MAP[baseCycle];
            var result = {};
            var dMap = { qsetupfee:'q', ssetupfee:'sa', asetupfee:'a', bsetupfee:'bi', tsetupfee:'tri' };
            for (var cycle in this.SETUP_MAP) {
                var feeKey = this.SETUP_MAP[cycle];
                if (feeKey === baseFeeKey) { result[feeKey] = baseSetup; continue; }
                var dk = dMap[feeKey];
                var raw = baseSetup;
                if (dk && setupDiscounts[dk]) raw *= (1 - setupDiscounts[dk] / 100);
                result[feeKey] = Utils.round(raw, rounding, roundTo);
            }
            return result;
        },

        convert: function (val, srcRate, tgtRate) {
            if (val === null || val === -1 || val === 0) return val;
            if (!srcRate || !tgtRate) return val;
            return val * (tgtRate / srcRate);
        }
    };

    /* ================================================================
       PRICING TABLE DOM PARSER
       ================================================================ */
    var PricingDOM = {
        /**
         * WHMCS pricing input naming patterns:
         *
         * Product/Addon pricing (configproducts.php, configaddons.php):
         *   currency[currencyId][cycle]   e.g. currency[2][monthly]
         *   pricing[currencyId][cycle]    (some versions)
         *
         * Configurable Options pricing (configproductoptions.php):
         *   price[currencyId][subOptionId][cycleIndex]  e.g. price[2][869][1]
         *   Cycle index mapping:
         *     1=msetupfee, 2=qsetupfee, 3=ssetupfee, 4=asetupfee, 5=bsetupfee, 6=tsetupfee
         *     7=monthly,   8=quarterly, 9=semiannually, 10=annually, 11=biennially, 12=triennially
         *   (Actual indices may vary — we detect from table headers)
         */

        // Cycle index map for configproductoptions.php price[cur][sub][idx]
        // WHMCS 8.x layout:
        //   Setup:   1=msetupfee, 2=qsetupfee, 3=ssetupfee, 4=asetupfee, 5=bsetupfee, 11=tsetupfee
        //   Pricing: 6=monthly,   7=quarterly,  8=semiannually, 9=annually, 10=biennially, 12=triennially
        PRICE_IDX_MAP: {
            1: 'msetupfee', 2: 'qsetupfee', 3: 'ssetupfee',
            4: 'asetupfee', 5: 'bsetupfee', 11: 'tsetupfee',
            6: 'monthly',   7: 'quarterly', 8: 'semiannually',
            9: 'annually', 10: 'biennially', 12: 'triennially'
        },

        findInputs: function (container) {
            container = container || document;

            // Try standard pattern first: currency[id][cycle] or pricing[id][cycle]
            var stdInputs = container.querySelectorAll('input[name*="currency["], input[name*="pricing["]');
            if (stdInputs.length > 0) {
                return this._parseStandardInputs(stdInputs);
            }

            // Try configoptions pattern: price[currencyId][subId][index]
            var priceInputs = container.querySelectorAll('input[name^="price["]');
            if (priceInputs.length > 0) {
                return this._parsePriceInputs(priceInputs);
            }

            return {};
        },

        /** Parse currency[id][cycle] and pricing[id][cycle] patterns */
        _parseStandardInputs: function (inputs) {
            var groups = {};
            inputs.forEach(function (el) {
                var m = el.name.match(/(?:currency|pricing)\[(\d+)\]\[(\w+)\]/);
                if (!m) return;
                var curId = parseInt(m[1]);
                var field = m[2];
                if (!groups[curId]) groups[curId] = {};
                groups[curId][field] = el;
            });
            return groups;
        },

        /**
         * Parse price[currencyId][subOptionId][index] pattern.
         * Groups by currencyId. For each currency, maps index to cycle name.
         *
         * NOTE: On configproductoptions.php, each sub-option has its own set
         * of inputs. We group ALL sub-options under the same currency,
         * so Calc Currencies works across all of them.
         * For Calc Cycles, we process each sub-option row independently.
         */
        _parsePriceInputs: function (inputs) {
            var self = this;
            var groups = {};     // { currencyId: { cycle: [inputEl, ...], ... } }
            var bySubOption = {}; // { currencyId: { subId: { cycle: inputEl } } }

            inputs.forEach(function (el) {
                var m = el.name.match(/price\[(\d+)\]\[(\d+)\]\[(\d+)\]/);
                if (!m) return;
                var curId = parseInt(m[1]);
                var subId = parseInt(m[2]);
                var idx = parseInt(m[3]);
                var cycle = self.PRICE_IDX_MAP[idx];
                if (!cycle) return;

                if (!bySubOption[curId]) bySubOption[curId] = {};
                if (!bySubOption[curId][subId]) bySubOption[curId][subId] = {};
                bySubOption[curId][subId][cycle] = el;
            });

            // Store the structured data for sub-option-aware operations
            this._subOptionData = bySubOption;

            // Also create a flat groups structure for currency conversion
            // (takes the first sub-option as representative — currency calc
            // will iterate sub-options separately)
            for (var curId in bySubOption) {
                groups[curId] = {};
                for (var subId in bySubOption[curId]) {
                    for (var cycle in bySubOption[curId][subId]) {
                        if (!groups[curId][cycle]) {
                            groups[curId][cycle] = bySubOption[curId][subId][cycle];
                        }
                    }
                    break; // just first sub for structure detection
                }
            }

            return groups;
        },

        /** Get sub-option structured data (for configoptions page) */
        getSubOptionData: function () {
            return this._subOptionData || null;
        },

        _subOptionData: null,
        readValues: function (curInputs) {
            var v = {};
            for (var f in curInputs) v[f] = Utils.parse(curInputs[f].value);
            return v;
        },
        writeValues: function (curInputs, vals, overwrite) {
            var c = 0;
            for (var f in vals) {
                if (!curInputs[f] || vals[f] === null || vals[f] === undefined) continue;
                var cur = Utils.parse(curInputs[f].value);
                if (cur === -1) continue;
                if (!overwrite && cur !== null && cur !== 0) continue;
                curInputs[f].value = Utils.fmt(vals[f]);
                Utils.highlight(curInputs[f]);
                c++;
            }
            return c;
        }
    };

    /* ================================================================
       UNDO MANAGER
       ================================================================ */
    var Undo = {
        _snap: null,
        save: function () {
            var data = [];
            document.querySelectorAll('input[name*="currency["], input[name*="pricing["], input[name^="price["]').forEach(function (el) {
                data.push({ el: el, val: el.value });
            });
            this._snap = data;
        },
        restore: function () {
            if (!this._snap || !this._snap.length) { Utils.toast('Nothing to undo.', 'warning'); return; }
            this._snap.forEach(function (s) { s.el.value = s.val; Utils.highlight(s.el); });
            this._snap = null;
            Utils.toast('Undo successful.', 'info');
        },
        hasSnap: function () { return this._snap && this._snap.length > 0; }
    };

    /* ================================================================
       HELPER
       ================================================================ */
    function findDefaultPreset() {
        if (!CFG.presets || !CFG.presets.length) return null;
        for (var i = 0; i < CFG.presets.length; i++) {
            if (CFG.presets[i].is_default == 1) return CFG.presets[i];
            if (CFG.presets[i].name === CFG.defaultPreset) return CFG.presets[i];
        }
        return CFG.presets[0];
    }

    /* ================================================================
       ALPINE COMPONENT: Toolbar (for native WHMCS pricing pages)
       ================================================================ */
    function toolbarData() {
        var dp = findDefaultPreset();
        return {
            baseCycle: 'monthly',
            rounding: CFG.defaultRounding || 'round',
            roundTo: CFG.defaultRoundTo || 1,
            overwrite: true,
            presetId: dp ? dp.id : '',
            presets: CFG.presets || [],
            currencies: CFG.currencies || [],
            showRates: CFG.showRates,
            dQ: dp ? +dp.discount_quarterly : 0,
            dSA: dp ? +dp.discount_semiannually : 5,
            dA: dp ? +dp.discount_annually : 10,
            dBi: dp ? +dp.discount_biennially : 15,
            dTri: dp ? +dp.discount_triennially : 20,
            sdQ: dp ? +(dp.discount_setup_quarterly || 0) : 0,
            sdSA: dp ? +(dp.discount_setup_semiannually || 0) : 5,
            sdA: dp ? +(dp.discount_setup_annually || 0) : 10,
            sdBi: dp ? +(dp.discount_setup_biennially || 0) : 15,
            sdTri: dp ? +(dp.discount_setup_triennially || 0) : 20,

            loadPreset: function () {
                var s = this, p = this.presets.find(function (x) { return x.id == s.presetId; });
                if (!p) return;
                this.dQ=+p.discount_quarterly; this.dSA=+p.discount_semiannually;
                this.dA=+p.discount_annually; this.dBi=+p.discount_biennially; this.dTri=+p.discount_triennially;
                this.sdQ=+(p.discount_setup_quarterly||0); this.sdSA=+(p.discount_setup_semiannually||0);
                this.sdA=+(p.discount_setup_annually||0); this.sdBi=+(p.discount_setup_biennially||0); this.sdTri=+(p.discount_setup_triennially||0);
                this.rounding = p.rounding_method; this.roundTo = +p.rounding_precision;
                Utils.toast('Preset "' + p.name + '" loaded.', 'info');
            },

            _d: function () { return { q:this.dQ, sa:this.dSA, a:this.dA, bi:this.dBi, tri:this.dTri }; },
            _sd: function () { return { q:this.sdQ, sa:this.sdSA, a:this.sdA, bi:this.sdBi, tri:this.sdTri }; },

            calcCycles: function () {
                var dc = Utils.defaultCurrency();
                if (!dc) { Utils.toast('No default currency found.', 'error'); return; }
                var grps = PricingDOM.findInputs();
                Undo.save();

                var subData = PricingDOM.getSubOptionData();
                var cnt = 0;

                if (subData) {
                    // Config options page: iterate each sub-option independently
                    var curSubs = subData[dc.id];
                    if (!curSubs) { Utils.toast('No pricing inputs for default currency.', 'warning'); return; }
                    for (var subId in curSubs) {
                        var inp = curSubs[subId];
                        var bv = Utils.parse(inp[this.baseCycle] ? inp[this.baseCycle].value : null);
                        if (bv === null || bv === -1) continue;
                        var cc = Calc.cycles(bv, this.baseCycle, this._d(), this.rounding, this.roundTo);
                        if (cc) cnt += PricingDOM.writeValues(inp, cc, this.overwrite);
                        var bsk = Calc.SETUP_MAP[this.baseCycle];
                        var bs = Utils.parse(inp[bsk] ? inp[bsk].value : null);
                        if (bs !== null && bs !== -1 && bs !== 0) {
                            var sc = Calc.setupFees(bs, this.baseCycle, this._sd(), this.rounding, this.roundTo);
                            if (sc) cnt += PricingDOM.writeValues(inp, sc, this.overwrite);
                        }
                    }
                } else {
                    // Standard page: single pricing group per currency
                    var inp = grps[dc.id];
                    if (!inp) { Utils.toast('No pricing inputs for default currency.', 'warning'); return; }
                    var bv = Utils.parse(inp[this.baseCycle] ? inp[this.baseCycle].value : null);
                    if (bv === null || bv === -1) { Utils.toast('Enter a valid ' + this.baseCycle + ' price first.', 'warning'); return; }
                    var cc = Calc.cycles(bv, this.baseCycle, this._d(), this.rounding, this.roundTo);
                    cnt = cc ? PricingDOM.writeValues(inp, cc, this.overwrite) : 0;
                    var bsk = Calc.SETUP_MAP[this.baseCycle];
                    var bs = Utils.parse(inp[bsk] ? inp[bsk].value : null);
                    if (bs !== null && bs !== -1 && bs !== 0) {
                        var sc = Calc.setupFees(bs, this.baseCycle, this._sd(), this.rounding, this.roundTo);
                        if (sc) cnt += PricingDOM.writeValues(inp, sc, this.overwrite);
                    }
                }

                Utils.toast('Cycles calculated: ' + cnt + ' fields.', 'success');
            },

            calcCurrencies: function () {
                var dc = Utils.defaultCurrency();
                if (!dc) { Utils.toast('No default currency found.', 'error'); return; }
                PricingDOM.findInputs(); // refresh sub-option data
                if (!Undo.hasSnap()) Undo.save();

                var subData = PricingDOM.getSubOptionData();
                var cnt = 0;
                var self = this;
                var sr = +dc.rate;

                if (subData) {
                    // Config options: convert each sub-option across currencies
                    var srcSubs = subData[dc.id];
                    if (!srcSubs) { Utils.toast('No pricing inputs for default currency.', 'warning'); return; }

                    this.currencies.forEach(function (cur) {
                        if (cur.id == dc.id) return;
                        var tr = +cur.rate;
                        if (!tr) { Utils.toast(cur.code + ' rate=0, skipped.', 'warning'); return; }
                        var tgtSubs = subData[cur.id];
                        if (!tgtSubs) return;

                        for (var subId in srcSubs) {
                            if (!tgtSubs[subId]) continue;
                            var sv = PricingDOM.readValues(srcSubs[subId]);
                            var cv = {};
                            for (var f in sv) {
                                if (sv[f] === null || sv[f] === -1) continue;
                                cv[f] = Utils.round(Calc.convert(sv[f], sr, tr), self.rounding, self.roundTo);
                            }
                            cnt += PricingDOM.writeValues(tgtSubs[subId], cv, self.overwrite);
                        }
                    });
                } else {
                    // Standard page
                    var grps = PricingDOM.findInputs();
                    var si = grps[dc.id];
                    if (!si) { Utils.toast('No pricing inputs for default currency.', 'warning'); return; }
                    var sv = PricingDOM.readValues(si);
                    this.currencies.forEach(function (cur) {
                        if (cur.id == dc.id) return;
                        var tr = +cur.rate;
                        if (!tr) { Utils.toast(cur.code + ' rate=0, skipped.', 'warning'); return; }
                        var ti = grps[cur.id]; if (!ti) return;
                        var cv = {};
                        for (var f in sv) { if (sv[f]===null||sv[f]===-1) continue; cv[f]=Utils.round(Calc.convert(sv[f],sr,tr),self.rounding,self.roundTo); }
                        cnt += PricingDOM.writeValues(ti, cv, self.overwrite);
                    });
                }

                Utils.toast('Currency conversion: ' + cnt + ' fields.', 'success');
            },

            calcAll: function () {
                if (CFG.confirmApply && !confirm('Apply pricing calculation to all fields?')) return;
                this.calcCycles(); this.calcCurrencies();
            },

            undo: function () { Undo.restore(); }
        };
    }

    /* ================================================================
       ALPINE COMPONENT: Config Options Manager
       ================================================================ */
    function configManagerData() {
        return {
            loading: false, loaded: false,
            groups: [], currencies: CFG.currencies || [],
            activeCurrency: Utils.defaultCurrency() ? Utils.defaultCurrency().id : null,
            existingGroups: [], selectedExistingGroup: '',
            newGroupName: '', newGroupDesc: '', saving: false,

            init: function () {
                if (CFG.page && CFG.page.type === 'product_edit') this._observeTab();
            },

            _observeTab: function () {
                var self = this;
                var check = function () {
                    var h = window.location.hash;
                    if (h.indexOf('tab=5') !== -1 || h.indexOf('tab5') !== -1) {
                        if (!self.loaded && !self.loading) self._loadData();
                    }
                };
                check();
                window.addEventListener('hashchange', check);
                document.addEventListener('click', function (e) {
                    var a = e.target.closest ? e.target.closest('a') : null;
                    if (a && ((a.textContent||'').indexOf('Configurable')!==-1 || (a.getAttribute('href')||'').indexOf('tab=5')!==-1))
                        setTimeout(check, 300);
                });
            },

            _loadData: async function () {
                this.loading = true;
                var pid = CFG.page ? CFG.page.product_id : 0;
                var r = await Utils.fetchJson(CFG.ajaxUrl + '&action=get_config_options&product_id=' + pid);
                if (r.success) {
                    this.groups = (r.data.groups||[]).map(function(g){g._collapsed=false;return g;});
                    if (r.data.currencies) this.currencies = r.data.currencies;
                    this.loaded = true;
                    var gr = await Utils.fetchJson(CFG.ajaxUrl + '&action=get_existing_groups&product_id=' + pid);
                    if (gr.success) this.existingGroups = gr.data || [];
                } else Utils.toast('Load failed: '+(r.error||''),'error');
                this.loading = false;
            },

            setCurrency: function (id) { this.activeCurrency = id; },

            getPricing: function (sub, curId, field) {
                if (!sub.pricing||!sub.pricing[curId]) return '-1.00';
                var v=sub.pricing[curId][field]; return v!==undefined&&v!==null?v:'-1.00';
            },
            setPricing: function (sub, curId, field, val) {
                if (!sub.pricing) sub.pricing={};
                if (!sub.pricing[curId]) sub.pricing[curId]={};
                sub.pricing[curId][field]=val;
            },

            optionTypeLabel: function(t){return{'1':'DROPDOWN','2':'RADIO','3':'YESNO','4':'QUANTITY'}[String(t)]||'TYPE-'+t;},

            quickCreate: async function () {
                if (!this.newGroupName.trim()){Utils.toast('Name required.','warning');return;}
                this.saving=true;
                var r=await Utils.fetchJson(CFG.ajaxUrl+'&action=quick_create_group',{
                    method:'POST',body:new URLSearchParams({name:this.newGroupName.trim(),description:this.newGroupDesc.trim(),product_id:CFG.page?CFG.page.product_id:0}),
                    headers:{'Content-Type':'application/x-www-form-urlencoded'}});
                if(r.success){Utils.toast('Created!','success');this.loaded=false;this.newGroupName='';this.newGroupDesc='';await this._loadData();}
                else Utils.toast('Error: '+(r.error||''),'error');
                this.saving=false;
            },

            assignGroup: async function (gid) {
                if (!gid) return; this.saving=true;
                var r=await Utils.fetchJson(CFG.ajaxUrl+'&action=assign_group',{
                    method:'POST',body:new URLSearchParams({group_id:gid,product_id:CFG.page?CFG.page.product_id:0}),
                    headers:{'Content-Type':'application/x-www-form-urlencoded'}});
                if(r.success){Utils.toast('Assigned!','success');this.loaded=false;await this._loadData();}
                else Utils.toast('Error: '+(r.error||''),'error');
                this.saving=false;
            },

            savePricing: async function () {
                this.saving=true;
                var payload=[],ho=[],hs=[];
                this.groups.forEach(function(g){g.options.forEach(function(opt){
                    ho.push({id:opt.id,hidden:opt.hidden});
                    opt.subs.forEach(function(sub){
                        hs.push({id:sub.id,hidden:sub.hidden});
                        for(var cid in sub.pricing){if(!sub.pricing[cid])continue;
                            var rec={sub_id:sub.id,currency_id:parseInt(cid)},p=sub.pricing[cid];
                            ['monthly','quarterly','semiannually','annually','biennially','triennially',
                             'msetupfee','qsetupfee','ssetupfee','asetupfee','bsetupfee','tsetupfee'].forEach(function(f){if(p[f]!==undefined)rec[f]=p[f];});
                            payload.push(rec);
                        }
                    });
                });});
                var r=await Utils.fetchJson(CFG.ajaxUrl+'&action=save_config_options',{
                    method:'POST',body:new URLSearchParams({pricing:JSON.stringify(payload),hidden_options:JSON.stringify(ho),hidden_subs:JSON.stringify(hs)}),
                    headers:{'Content-Type':'application/x-www-form-urlencoded'}});
                if(r.success) Utils.toast('Saved! ('+(r.count||0)+' records)','success');
                else Utils.toast('Failed: '+(r.error||''),'error');
                this.saving=false;
            }
        };
    }

    /* ================================================================
       ALPINE COMPONENT: Config Embedded Toolbar
       ================================================================ */
    function configToolbarData() {
        var dp = findDefaultPreset();
        return {
            baseCycle:'monthly', rounding:CFG.defaultRounding||'round', roundTo:CFG.defaultRoundTo||1, overwrite:true,
            presetId: dp?dp.id:'', presets:CFG.presets||[],
            discountFields:[{key:'q',label:'Quarterly'},{key:'sa',label:'Semi-Annual'},{key:'a',label:'Annual'},{key:'bi',label:'Biennial'},{key:'tri',label:'Triennial'}],
            discounts:{q:dp?+dp.discount_quarterly:0,sa:dp?+dp.discount_semiannually:5,a:dp?+dp.discount_annually:10,bi:dp?+dp.discount_biennially:15,tri:dp?+dp.discount_triennially:20},
            setupDiscounts:{q:dp?+(dp.discount_setup_quarterly||0):0,sa:dp?+(dp.discount_setup_semiannually||0):5,a:dp?+(dp.discount_setup_annually||0):10,bi:dp?+(dp.discount_setup_biennially||0):15,tri:dp?+(dp.discount_setup_triennially||0):20},
            _undoSnap:null,

            loadPreset:function(){var s=this,p=this.presets.find(function(x){return x.id==s.presetId;});if(!p)return;
                this.discounts={q:+p.discount_quarterly,sa:+p.discount_semiannually,a:+p.discount_annually,bi:+p.discount_biennially,tri:+p.discount_triennially};
                this.setupDiscounts={q:+(p.discount_setup_quarterly||0),sa:+(p.discount_setup_semiannually||0),a:+(p.discount_setup_annually||0),bi:+(p.discount_setup_biennially||0),tri:+(p.discount_setup_triennially||0)};
                this.rounding=p.rounding_method;this.roundTo=+p.rounding_precision;Utils.toast('Preset "'+p.name+'" loaded.','info');},

            _mgr:function(){var el=document.getElementById('hvn-config-mount');try{return el&&window.Alpine?Alpine.$data(el):null;}catch(e){return null;}},
            _snap:function(){var m=this._mgr();if(m)this._undoSnap=JSON.parse(JSON.stringify(m.groups));},

            calcCycles:function(){var m=this._mgr();if(!m){Utils.toast('Manager not ready.','warning');return;}this._snap();
                var cid=m.activeCurrency,base=this.baseCycle,cnt=0,self=this;
                m.groups.forEach(function(g){g.options.forEach(function(opt){opt.subs.forEach(function(sub){
                    if(!sub.pricing||!sub.pricing[cid])return;var p=sub.pricing[cid];
                    var bv=Utils.parse(p[base]);if(bv!==null&&bv!==-1){var cc=Calc.cycles(bv,base,self.discounts,self.rounding,self.roundTo);
                        if(cc)for(var c in cc){if(c===base)continue;var cv=Utils.parse(p[c]);if(cv===-1)continue;if(!self.overwrite&&cv!==null&&cv!==0)continue;p[c]=Utils.fmt(cc[c]);cnt++;}}
                    var bsk=Calc.SETUP_MAP[base],bs=Utils.parse(p[bsk]);if(bs!==null&&bs!==-1&&bs!==0){var sc=Calc.setupFees(bs,base,self.setupDiscounts,self.rounding,self.roundTo);
                        if(sc)for(var sk in sc){if(sk===bsk)continue;var sv=Utils.parse(p[sk]);if(sv===-1)continue;if(!self.overwrite&&sv!==null&&sv!==0)continue;p[sk]=Utils.fmt(sc[sk]);cnt++;}}
                });});});
                m.groups=m.groups.slice();Utils.toast('Config cycles: '+cnt+' fields.','success');},

            calcCurrencies:function(){var m=this._mgr();if(!m){Utils.toast('Manager not ready.','warning');return;}if(!this._undoSnap)this._snap();
                var dc=Utils.defaultCurrency();if(!dc){Utils.toast('No default currency.','error');return;}
                var sid=dc.id,sr=+dc.rate,cnt=0,self=this,af=['monthly','quarterly','semiannually','annually','biennially','triennially','msetupfee','qsetupfee','ssetupfee','asetupfee','bsetupfee','tsetupfee'];
                m.groups.forEach(function(g){g.options.forEach(function(opt){opt.subs.forEach(function(sub){
                    if(!sub.pricing||!sub.pricing[sid])return;var sp=sub.pricing[sid];
                    m.currencies.forEach(function(cur){if(cur.id==sid)return;var tr=+cur.rate;if(!tr)return;
                        if(!sub.pricing[cur.id])sub.pricing[cur.id]={};var tp=sub.pricing[cur.id];
                        af.forEach(function(f){var sv=Utils.parse(sp[f]);if(sv===null||sv===-1)return;var tv=Utils.parse(tp[f]);if(tv===-1)return;
                            if(!self.overwrite&&tv!==null&&tv!==0)return;tp[f]=Utils.fmt(Utils.round(Calc.convert(sv,sr,tr),self.rounding,self.roundTo));cnt++;});});
                });});});
                m.groups=m.groups.slice();Utils.toast('Config currencies: '+cnt+' fields.','success');},

            calcAll:function(){if(CFG.confirmApply&&!confirm('Apply to all?'))return;this.calcCycles();this.calcCurrencies();},
            undo:function(){if(!this._undoSnap){Utils.toast('Nothing to undo.','warning');return;}var m=this._mgr();if(m){m.groups=this._undoSnap;this._undoSnap=null;}Utils.toast('Undone.','info');}
        };
    }

    /* ================================================================
       ALPINE COMPONENT: Preset Manager (settings page)
       ================================================================ */
    function presetManagerData() {
        return {
            presets:[],editing:null,form:{},loading:false,saving:false,

            init:async function(){this.form=this._empty();await this.load();},
            _empty:function(){return{id:0,name:'',discount_quarterly:0,discount_semiannually:0,discount_annually:0,discount_biennially:0,discount_triennially:0,discount_setup_quarterly:0,discount_setup_semiannually:0,discount_setup_annually:0,discount_setup_biennially:0,discount_setup_triennially:0,rounding_method:'round',rounding_precision:1,is_default:false};},

            load:async function(){this.loading=true;var r=await Utils.fetchJson(CFG.ajaxUrl+'&action=get_presets');if(r.success){this.presets=(r.data||[]).map(function(p){p.is_default=p.is_default==1;return p;});}this.loading=false;},
            add:function(){this.form=this._empty();this.editing='new';},
            edit:function(p){
                var f=JSON.parse(JSON.stringify(p));
                // Normalize is_default to boolean for checkbox binding
                f.is_default = (f.is_default == 1 || f.is_default === true || f.is_default === 'true');
                this.form=f;
                this.editing=p.id;
            },
            cancel:function(){this.editing=null;},

            save:async function(){if(!this.form.name||!this.form.name.trim()){Utils.toast('Name required.','warning');return;}
                this.saving=true;
                // Convert is_default boolean to "1"/"0" for PHP
                var formData = Object.assign({}, this.form);
                formData.is_default = formData.is_default ? '1' : '0';
                var r=await Utils.fetchJson(CFG.ajaxUrl+'&action=save_preset',{method:'POST',body:new URLSearchParams(formData),headers:{'Content-Type':'application/x-www-form-urlencoded'}});
                if(r.success){Utils.toast('Saved!','success');this.editing=null;await this.load();}else Utils.toast('Error: '+(r.error||''),'error');this.saving=false;},

            remove:async function(id){if(!confirm('Delete?'))return;var r=await Utils.fetchJson(CFG.ajaxUrl+'&action=delete_preset',{method:'POST',body:new URLSearchParams({id:id}),headers:{'Content-Type':'application/x-www-form-urlencoded'}});
                if(r.success){Utils.toast('Deleted.','info');await this.load();}}
        };
    }

    /* ================================================================
       REGISTER ALL ALPINE COMPONENTS
       This runs when Alpine fires its `alpine:init` event,
       BEFORE it scans the DOM for x-data attributes.
       ================================================================ */
    document.addEventListener('alpine:init', function () {
        Alpine.data('hvnToolbar', toolbarData);
        Alpine.data('hvnConfigManager', configManagerData);
        Alpine.data('hvnConfigToolbar', configToolbarData);
        Alpine.data('hvnPresetManager', presetManagerData);
    });

    /* ================================================================
       CONFIG MANAGER: inject HTML into product tab 5
       Since this is dynamic (loaded after tab click), we inject via JS
       but use Alpine.initTree() to make Alpine process the new element.
       ================================================================ */
    function initConfigManager() {
        if (!CFG.page || CFG.page.type !== 'product_edit' || !CFG.page.product_id) return;

        var injected = false;
        var attempt = function () {
            if (injected || document.getElementById('hvn-config-mount')) { injected = true; return; }
            var tab = findConfigTab();
            if (!tab) return;

            var mount = document.createElement('div');
            mount.id = 'hvn-config-mount';
            mount.setAttribute('x-data', 'hvnConfigManager()');
            mount.setAttribute('x-init', 'init()');
            mount.className = 'hvn-config-mgr hvn-mt-12';
            mount.innerHTML = buildConfigManagerHTML();
            tab.appendChild(mount);
            injected = true;

            // Tell Alpine to process the new tree
            if (window.Alpine && window.Alpine.initTree) {
                window.Alpine.initTree(mount);
            }
        };

        // Try on hash match
        var onNav = function () {
            var h = window.location.hash;
            if (h.indexOf('tab=5') !== -1 || h.indexOf('tab5') !== -1) setTimeout(attempt, 300);
        };
        onNav();
        window.addEventListener('hashchange', onNav);
        document.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('a') : null;
            if (a && ((a.textContent||'').indexOf('Configurable') !== -1 || (a.getAttribute('href')||'').indexOf('tab=5') !== -1))
                setTimeout(onNav, 200);
        });
    }

    function findConfigTab() {
        var sels = ['#tab5', '#tab5Content', 'div[id*="tab5"]', '#tabConfigurableOptions'];
        for (var i = 0; i < sels.length; i++) { var el = document.querySelector(sels[i]); if (el) return el; }
        var hs = document.querySelectorAll('h2, h3, .header-lined');
        for (var j = 0; j < hs.length; j++) { if ((hs[j].textContent||'').indexOf('Configurable Options') !== -1) return hs[j].parentElement; }
        return null;
    }

    function buildConfigManagerHTML() {
        var h='<div class="hvn-config-mgr__header"><span class="hvn-config-mgr__title">⚙ Configurable Options Manager</span>';
        h+='<a href="configproductoptions.php" target="_blank" class="hvn-btn hvn-btn--default hvn-btn--xs">↗ Open in WHMCS</a></div>';
        h+='<template x-if="loading&&!loaded"><div style="text-align:center;padding:20px"><span class="hvn-spinner"></span> Loading...</div></template>';

        // Quick create
        h+='<template x-if="loaded&&groups.length===0"><div class="hvn-quick-create"><p>ℹ No configurable option groups assigned.</p>';
        h+='<div class="hvn-quick-create__form"><label style="font-weight:600">Create new group:</label>';
        h+='<input type="text" x-model="newGroupName" class="hvn-input" style="width:100%;height:32px" placeholder="Group Name">';
        h+='<input type="text" x-model="newGroupDesc" class="hvn-input" style="width:100%;height:32px" placeholder="Description (optional)">';
        h+='<button type="button" class="hvn-btn hvn-btn--primary" @click="quickCreate()" :disabled="saving">+ Create & Assign</button>';
        h+='<div class="hvn-divider"><span>OR assign existing</span></div>';
        h+='<div style="display:flex;gap:8px"><select x-model="selectedExistingGroup" class="hvn-select" style="flex:1;height:32px">';
        h+='<option value="">Select group...</option><template x-for="g in existingGroups" :key="g.id"><option :value="g.id" x-text="g.name"></option></template></select>';
        h+='<button type="button" class="hvn-btn hvn-btn--default" @click="assignGroup(selectedExistingGroup)" :disabled="!selectedExistingGroup||saving">Assign</button></div>';
        h+='</div></div></template>';

        // Groups view
        h+='<template x-if="loaded&&groups.length>0"><div>';
        h+='<div class="hvn-tabs"><template x-for="cur in currencies" :key="cur.id"><div class="hvn-tab" :class="{\'hvn-tab--active\':activeCurrency==cur.id}" @click="setCurrency(cur.id)"><span x-text="cur.code"></span><template x-if="cur.default==1"><small style="margin-left:3px;opacity:.7">Default</small></template></div></template></div>';

        // Embedded toolbar
        h+='<div x-data="hvnConfigToolbar()" class="hvn-toolbar hvn-mb-8">';
        h+=buildEmbeddedToolbar();
        h+='</div>';

        // Groups loop
        h+='<template x-for="group in groups" :key="group.id"><div class="hvn-optgroup">';
        h+='<div class="hvn-optgroup__header" @click="group._collapsed=!group._collapsed"><span class="hvn-optgroup__title" x-text="group.name"></span><div class="hvn-optgroup__meta">';
        h+='<template x-if="group.shared_count>1"><span class="hvn-badge hvn-badge--shared" x-text="\'Shared with \'+(group.shared_count-1)+\' product(s)\'"></span></template>';
        h+='<span x-text="group._collapsed?\'▸\':\'▾\'" style="font-size:14px"></span></div></div>';
        h+='<div class="hvn-optgroup__body" x-show="!group._collapsed" x-transition>';

        h+='<template x-for="option in group.options" :key="option.id"><div style="margin-bottom:12px">';
        h+='<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px"><strong x-text="option.optionname" style="font-size:13px"></strong>';
        h+='<span class="hvn-text-xs hvn-text-muted" x-text="\'[\'+optionTypeLabel(option.optiontype)+\']\'"></span>';
        h+='<template x-if="option.optiontype==4"><span class="hvn-text-xs hvn-text-muted" x-text="\'Min:\'+option.qtyminimum+\'/Max:\'+option.qtymaximum"></span></template>';
        h+='<label class="hvn-toggle hvn-text-xs" style="height:22px;padding:2px 6px"><input type="checkbox" :checked="option.hidden==1" @change="option.hidden=$event.target.checked?1:0"> Hidden</label></div>';

        h+='<div style="overflow-x:auto"><table class="hvn-ptable"><thead><tr><th style="min-width:140px">Sub-option</th><th>Hide</th>';
        h+='<th>Monthly</th><th>Q</th><th>SA</th><th>A</th><th>Bi</th><th>Tri</th>';
        h+='<th>M Fee</th><th>Q Fee</th><th>SA Fee</th><th>A Fee</th><th>Bi Fee</th><th>Tri Fee</th></tr></thead><tbody>';

        h+='<template x-for="sub in option.subs" :key="sub.id"><tr><td :title="sub.name" x-text="sub.name"></td>';
        h+='<td><input type="checkbox" :checked="sub.hidden==1" @change="sub.hidden=$event.target.checked?1:0"></td>';
        ['monthly','quarterly','semiannually','annually','biennially','triennially'].forEach(function(c){
            h+='<td :class="{\'hvn-cell-disabled\':getPricing(sub,activeCurrency,\''+c+'\')==\'-1.00\'}"><input type="text" :value="getPricing(sub,activeCurrency,\''+c+'\')" @change="setPricing(sub,activeCurrency,\''+c+'\',$event.target.value)" class="hvn-config-input"></td>';});
        ['msetupfee','qsetupfee','ssetupfee','asetupfee','bsetupfee','tsetupfee'].forEach(function(f){
            h+='<td><input type="text" :value="getPricing(sub,activeCurrency,\''+f+'\')" @change="setPricing(sub,activeCurrency,\''+f+'\',$event.target.value)" class="hvn-config-input"></td>';});
        h+='</tr></template></tbody></table></div>';

        h+='</div></template>';
        h+='<template x-if="group.options.length===0"><p class="hvn-text-muted hvn-text-sm" style="padding:10px">No options. <a :href="\'configproductoptions.php?action=managegroup&id=\'+group.id" target="_blank">Add →</a></p></template>';
        h+='</div></div></template>';

        h+='<div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">';
        h+='<span class="hvn-text-xs hvn-text-muted">[-1.00]=disabled [0.00]=free</span>';
        h+='<button type="button" class="hvn-btn hvn-btn--success" @click="savePricing()" :disabled="saving"><template x-if="saving"><span class="hvn-spinner"></span></template> Save Changes</button>';
        h+='</div></div></template>';
        return h;
    }

    function buildEmbeddedToolbar() {
        var h='<div class="hvn-toolbar-header"><span class="hvn-toolbar-title">⚡ Config Pricing Calculator</span>';
        h+='<div class="hvn-group"><label>Preset:</label><select class="hvn-select" x-model="presetId" @change="loadPreset()"><template x-for="p in presets" :key="p.id"><option :value="p.id" x-text="p.name"></option></template></select></div></div>';
        h+='<div class="hvn-toolbar-row"><div class="hvn-group"><label>Base:</label><select class="hvn-select" x-model="baseCycle"><option value="monthly">Monthly</option><option value="annually">Annually</option></select></div>';
        h+='<div class="hvn-group"><label>Round:</label><select class="hvn-select" x-model="rounding"><option value="none">None</option><option value="ceil">Ceil</option><option value="floor">Floor</option><option value="round">Nearest</option></select></div>';
        h+='<div class="hvn-group"><label>To:</label><select class="hvn-select" x-model.number="roundTo"><option value="0.01">0.01</option><option value="1">1</option><option value="100">100</option><option value="1000">1,000</option><option value="10000">10,000</option></select></div>';
        h+='<label class="hvn-toggle"><input type="checkbox" x-model="overwrite"> Overwrite existing</label></div>';

        h+='<div class="hvn-toolbar-row"><div class="hvn-discounts"><label style="font-weight:600;color:var(--hvn-text-secondary);font-size:12px">Discounts:</label>';
        h+='<template x-for="d in discountFields" :key="d.key"><div class="hvn-discount"><label x-text="d.label"></label><input type="number" x-model.number="discounts[d.key]" min="0" max="100" step="0.5" class="hvn-input hvn-input--num"><span class="hvn-pct">%</span></div></template></div></div>';

        h+='<div class="hvn-toolbar-row"><div class="hvn-discounts"><label style="font-weight:600;color:var(--hvn-text-secondary);font-size:12px">Setup Fee:</label>';
        h+='<template x-for="d in discountFields" :key="\'s\'+d.key"><div class="hvn-discount"><label x-text="d.label"></label><input type="number" x-model.number="setupDiscounts[d.key]" min="0" max="100" step="0.5" class="hvn-input hvn-input--num"><span class="hvn-pct">%</span></div></template></div></div>';

        h+='<div class="hvn-toolbar-row"><div class="hvn-actions">';
        h+='<button type="button" class="hvn-btn hvn-btn--primary hvn-btn--sm" @click="calcCycles()">📊 Calc Cycles</button>';
        h+='<button type="button" class="hvn-btn hvn-btn--primary hvn-btn--sm" @click="calcCurrencies()">💱 Calc Currencies</button>';
        h+='<button type="button" class="hvn-btn hvn-btn--success hvn-btn--sm" @click="calcAll()">⚡ Calc All</button>';
        h+='<button type="button" class="hvn-btn hvn-btn--default hvn-btn--sm" @click="undo()">↩ Undo</button>';
        h+='</div></div>';
        return h;
    }

    /* ================================================================
       BOOT
       ================================================================ */
    function boot() {
        // Config manager for product edit tab 5
        if (CFG.page && CFG.page.type === 'product_edit') {
            initConfigManager();
        }

        // For popup pages (config_options) where toolbar HTML is NOT
        // injected by PHP (no AdminAreaFooterOutput), create toolbar via JS.
        // Alpine.initTree() will make Alpine process it.
        if (CFG.page && CFG.page.type === 'config_options') {
            injectToolbarForPopup();
        }
    }

    /**
     * Inject toolbar into popup pages where PHP cannot add to <body>.
     * Creates the toolbar element and uses Alpine.initTree() after Alpine loads.
     */
    function injectToolbarForPopup() {
        // Wait for Alpine to be ready, then inject + initialize
        var tryInject = function () {
            if (document.getElementById('hvn-toolbar-mount')) return;

            // Find pricing inputs
            var target = document.querySelector('input[name^="price["]');
            if (!target) return;

            var container = target.closest('table') || target.closest('form');
            if (!container) return;

            // Build toolbar HTML (same as PHP renders for normal pages)
            var div = document.createElement('div');
            div.id = 'hvn-toolbar-mount';
            div.setAttribute('x-data', 'hvnToolbar()');
            div.innerHTML = getToolbarInnerHTML();

            container.parentNode.insertBefore(div, container);

            // Tell Alpine to process this new tree
            if (window.Alpine && window.Alpine.initTree) {
                window.Alpine.initTree(div);
            }
        };

        // Try multiple times — Alpine may not be ready yet
        setTimeout(tryInject, 100);
        setTimeout(tryInject, 500);
        setTimeout(tryInject, 1500);

        // Also listen for Alpine ready
        document.addEventListener('alpine:initialized', tryInject);
    }

    /**
     * Get toolbar inner HTML (matches PHP-rendered toolbar structure).
     */
    function getToolbarInnerHTML() {
        var h = '<div class="hvn-toolbar">';

        h += '<div class="hvn-toolbar-header">';
        h += '<span class="hvn-toolbar-title">⚡ HVN Pricing Calculator</span>';
        h += '<div class="hvn-group"><label>Preset:</label>';
        h += '<select class="hvn-select" x-model="presetId" @change="loadPreset()">';
        h += '<template x-for="p in presets" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>';
        h += '</select></div></div>';

        h += '<div class="hvn-toolbar-hint">';
        h += 'Enter the <strong>base price</strong> (Monthly or Annually) in the default currency, then click ';
        h += '<strong>Calc All</strong> to auto-fill all cycles and currencies. Cells with <strong>-1.00</strong> (disabled) are skipped.';
        h += '</div>';

        h += '<div class="hvn-toolbar-row">';
        h += '<div class="hvn-group"><label>Base:</label><select class="hvn-select" x-model="baseCycle">';
        h += '<option value="monthly">Monthly</option><option value="annually">Annually</option></select></div>';
        h += '<div class="hvn-group"><label>Round:</label><select class="hvn-select" x-model="rounding">';
        h += '<option value="none">None</option><option value="ceil">Ceil</option><option value="floor">Floor</option><option value="round">Nearest</option></select></div>';
        h += '<div class="hvn-group"><label>To:</label><select class="hvn-select" x-model.number="roundTo">';
        h += '<option value="0.01">0.01</option><option value="1">1</option><option value="100">100</option>';
        h += '<option value="1000">1,000</option><option value="10000">10,000</option></select></div>';
        h += '<label class="hvn-toggle"><input type="checkbox" x-model="overwrite"> Overwrite existing</label>';
        h += '</div>';

        // Discounts
        h += '<div class="hvn-toolbar-row"><div class="hvn-discounts">';
        h += '<label class="hvn-discounts__label">Discounts:</label><div class="hvn-discounts__fields">';
        [['dQ','Quarterly'],['dSA','Semi-Annual'],['dA','Annual'],['dBi','Biennial'],['dTri','Triennial']].forEach(function(d){
            h+='<div class="hvn-discount"><label>'+d[1]+'</label><input type="number" x-model.number="'+d[0]+'" min="0" max="100" step="0.5"><span class="hvn-pct">%</span></div>';
        });
        h += '</div></div></div>';

        // Setup fee
        h += '<div class="hvn-toolbar-row"><div class="hvn-discounts">';
        h += '<label class="hvn-discounts__label">Setup Fee:</label><div class="hvn-discounts__fields">';
        [['sdQ','Quarterly'],['sdSA','Semi-Annual'],['sdA','Annual'],['sdBi','Biennial'],['sdTri','Triennial']].forEach(function(d){
            h+='<div class="hvn-discount"><label>'+d[1]+'</label><input type="number" x-model.number="'+d[0]+'" min="0" max="100" step="0.5"><span class="hvn-pct">%</span></div>';
        });
        h += '</div></div></div>';

        h += '<div class="hvn-toolbar-row"><div class="hvn-actions">';
        h += '<button type="button" class="hvn-btn hvn-btn--primary hvn-btn--sm" @click="calcCycles()">📊 Calc Cycles</button>';
        h += '<button type="button" class="hvn-btn hvn-btn--primary hvn-btn--sm" @click="calcCurrencies()">💱 Calc Currencies</button>';
        h += '<button type="button" class="hvn-btn hvn-btn--success hvn-btn--sm" @click="calcAll()">⚡ Calc All</button>';
        h += '<button type="button" class="hvn-btn hvn-btn--default hvn-btn--sm" @click="undo()">↩ Undo</button>';
        h += '</div></div>';

        h += '<template x-if="showRates"><div class="hvn-currency-info">ℹ ';
        h += '<template x-for="c in currencies" :key="c.id"><span class="hvn-rate" :class="{\'hvn-rate--default\':c.default==1}">';
        h += '<span x-text="c.code"></span> (rate: <span x-text="parseFloat(c.rate).toFixed(7)"></span>)';
        h += '<template x-if="c.default==1"> <strong>(default)</strong></template> </span></template>';
        h += '</div></template>';

        h += '</div>';
        return h;
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();

    // Debug
    window.HvnPricing = { Utils: Utils, Calc: Calc, PricingDOM: PricingDOM, Undo: Undo };
})();