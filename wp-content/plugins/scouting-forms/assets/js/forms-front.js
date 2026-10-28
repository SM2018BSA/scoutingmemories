/**
 * Scouting Forms: front-end behaviour for forms drawn by the plugin.
 *
 * - Field logic: shows/hides fields with the same rules as the server (Forms\Logic\FieldLogic).
 * - Dependent Dynamic fields: when a parent changes (State), loads the child's choices
 *   (Councils) or shown value (Council slug) from admin-ajax.php?action=sm_forms_dynamic.
 * - Searchable dropdowns for fields with Formidable's "autocomplete" setting.
 * - Collapsible sections, repeating sections (add/remove rows) and multi-page forms.
 *
 * No dependencies. Rules come from the JSON in each form's .sm-form-config element.
 */
(function () {
    'use strict';

    // ---------------------------------------------------------------- values

    function containerOf(scope, fieldId) {
        var found = scope.querySelectorAll('[data-sm-field="' + fieldId + '"]');
        for (var i = 0; i < found.length; i++) {
            // In the form scope, skip fields that belong to a repeater row
            if (scope.matches('[data-sm-row]') || !found[i].closest('[data-sm-row]')) {
                return found[i];
            }
        }
        return null;
    }

    function fieldValue(container) {
        if (!container) {
            return '';
        }
        var select = container.querySelector('select');
        if (select) {
            if (select.multiple) {
                return Array.prototype.filter.call(select.options, function (o) { return o.selected && o.value !== ''; })
                    .map(function (o) { return o.value; });
            }
            return select.value;
        }
        var boxes = container.querySelectorAll('input[type=checkbox], input[type=radio]');
        if (boxes.length) {
            var picked = Array.prototype.filter.call(boxes, function (b) { return b.checked; }).map(function (b) { return b.value; });
            return boxes[0].type === 'radio' ? (picked[0] || '') : picked;
        }
        var input = container.querySelector('input:not([type=button]):not([type=submit]), textarea');
        return input ? input.value : '';
    }

    function isBlank(value) {
        if (Array.isArray(value)) {
            return !value.some(function (v) { return String(v).trim() !== ''; });
        }
        return String(value === undefined || value === null ? '' : value).trim() === '';
    }

    // ---------------------------------------------------------------- logic (mirrors FieldLogic::meets)

    function meets(observed, cond, expected) {
        if (Array.isArray(observed)) {
            var list = observed.map(function (v) { return String(v).trim(); }).filter(function (v) { return v !== ''; });
            var exp = Array.isArray(expected) ? expected.map(String) : String(expected).trim();
            switch (cond) {
                case '==': return Array.isArray(exp) ? exp.some(function (e) { return list.indexOf(e) !== -1; }) : list.indexOf(exp) !== -1;
                case '!=': return list.indexOf(Array.isArray(exp) ? exp.join(',') : exp) === -1;
                case '>': return list.length > 0 && Math.min.apply(null, list.map(Number)) > Number(exp);
                case '<': return list.length > 0 && Math.max.apply(null, list.map(Number)) < Number(exp);
                case 'LIKE':
                case 'not LIKE':
                    var found = list.some(function (v) { return v.indexOf(exp) !== -1; });
                    return cond === 'LIKE' ? found : !found;
                case '%LIKE': return list.some(function (v) { return v.slice(-exp.length) === exp; });
                case 'LIKE%': return list.some(function (v) { return v.indexOf(exp) === 0; });
            }
            return false;
        }
        var o = String(observed === undefined || observed === null ? '' : observed).trim();
        var e = Array.isArray(expected) ? expected.join(',') : String(expected).trim();
        var numeric = o !== '' && e !== '' && !isNaN(o) && !isNaN(e);
        switch (cond) {
            case '==': return numeric ? Number(o) === Number(e) : o === e;
            case '!=': return numeric ? Number(o) !== Number(e) : o !== e;
            case '>': return numeric ? Number(o) > Number(e) : o > e;
            case '>=': return numeric ? Number(o) >= Number(e) : o >= e;
            case '<': return numeric ? Number(o) < Number(e) : o < e;
            case '<=': return numeric ? Number(o) <= Number(e) : o <= e;
            case 'LIKE': return e === '' || o.toLowerCase().indexOf(e.toLowerCase()) !== -1;
            case 'not LIKE': return !(e === '' || o.toLowerCase().indexOf(e.toLowerCase()) !== -1);
            case '%LIKE': return e === '' || o.toLowerCase().slice(-e.length) === e.toLowerCase();
            case 'LIKE%': return o.toLowerCase().indexOf(e.toLowerCase()) === 0;
        }
        return false;
    }

    function SmForm(form) {
        this.form = form;
        this.ajax = form.getAttribute('data-sm-ajax');
        var configEl = form.querySelector('.sm-form-config');
        try {
            this.config = configEl ? JSON.parse(configEl.textContent) : { fields: {}, text: {} };
        } catch (err) {
            this.config = { fields: {}, text: {} };
        }
        this.requests = {};
        this.init();
    }

    SmForm.prototype.text = function (key, fallback) {
        return (this.config.text && this.config.text[key]) || fallback;
    };

    SmForm.prototype.rule = function (fieldId) {
        return this.config.fields[String(fieldId)] || null;
    };

    // Value of a field seen from a scope: a repeater row looks in its own row first
    SmForm.prototype.valueIn = function (scope, fieldId) {
        var c = containerOf(scope, fieldId);
        if (!c && scope !== this.form) {
            c = containerOf(this.form, fieldId);
        }
        return fieldValue(c);
    };

    SmForm.prototype.isDynamic = function (fieldId) {
        var r = this.rule(fieldId);
        return !!(r && r.dynamic);
    };

    SmForm.prototype.shouldShow = function (scope, fieldId, container) {
        var config = this.rule(fieldId);
        if (!config || !config.rules || !config.rules.length) {
            return true;
        }
        var self = this;
        var outcomes = config.rules.map(function (rule) {
            var observed = self.valueIn(scope, rule.field);
            var expected = rule.value;
            if (rule.anything && config.dynamic && self.isDynamic(rule.field)) {
                expected = isBlank(observed) && rule.cond === '==' ? 'anything' : observed;
            }
            return meets(observed, rule.cond, expected);
        });
        var visible = config.showHide !== 'hide';
        if (config.anyAll === 'all' ? outcomes.indexOf(false) !== -1 : outcomes.indexOf(true) === -1) {
            visible = !visible;
        }
        // A dependent Dynamic dropdown with no choices stays hidden (as on the server)
        if (visible && config.dynamic && config.dynamic.dependent && !config.dynamic.display && container) {
            var select = container.querySelector('select');
            var hasChoices = select && Array.prototype.some.call(select.options, function (o) { return o.value !== ''; });
            var boxes = container.querySelectorAll('input[type=checkbox], input[type=radio]').length;
            if (!hasChoices && !boxes) {
                visible = false;
            }
        }
        return visible;
    };

    SmForm.prototype.applyLogic = function (scope) {
        scope = scope || this.form;
        var self = this;
        var rows = scope === this.form ? this.form.querySelectorAll('[data-sm-row]') : [];
        var containers = scope.querySelectorAll('[data-sm-field]');
        Array.prototype.forEach.call(containers, function (container) {
            var row = container.closest('[data-sm-row]');
            if (scope === self.form && row) {
                return; // rows are done below, each with its own values
            }
            var show = self.shouldShow(row || self.form, container.getAttribute('data-sm-field'), container);
            container.hidden = !show;
        });
        Array.prototype.forEach.call(rows, function (row) { self.applyLogic(row); });
    };

    // ---------------------------------------------------------------- dependent Dynamic fields

    // Fields whose choices come from fieldId (in the same scope)
    SmForm.prototype.childrenOf = function (fieldId) {
        var out = [];
        var fields = this.config.fields;
        Object.keys(fields).forEach(function (id) {
            var f = fields[id];
            if (!f.dynamic || !f.dynamic.dependent) {
                return;
            }
            var drives = f.rules.some(function (r) { return r.anything && String(r.field) === String(fieldId); });
            if (drives) {
                out.push(id);
            }
        });
        return out;
    };

    SmForm.prototype.parentChanged = function (scope, parentId) {
        var self = this;
        var value = this.valueIn(scope, parentId);
        this.childrenOf(parentId).forEach(function (childId) {
            var targetScope = containerOf(scope, childId) ? scope : self.form;
            var container = containerOf(targetScope, childId);
            if (!container) {
                return;
            }
            self.loadChild(targetScope, container, childId, parentId, value);
        });
    };

    SmForm.prototype.loadChild = function (scope, container, childId, parentId, parentValue) {
        var self = this;
        var config = this.rule(childId);
        var values = (Array.isArray(parentValue) ? parentValue : [parentValue]).filter(function (v) { return String(v) !== ''; });
        var key = childId + ':' + (container.id || '');
        if (this.requests[key]) {
            this.requests[key].abort();
        }

        if (!values.length) {
            this.fillChild(container, config, null);
            return;
        }

        var select = container.querySelector('select');
        if (select && !config.dynamic.display) {
            select.innerHTML = '<option value="">' + escapeHtml(this.text('loading', 'Loading…')) + '</option>';
            select.disabled = true;
            refreshCombo(select);
        }

        var params = new URLSearchParams();
        params.append('action', 'sm_forms_dynamic');
        params.append('field', childId);
        params.append('parent', parentId);
        values.forEach(function (v) { params.append('value[]', v); });

        var xhr = new XMLHttpRequest();
        this.requests[key] = xhr;
        xhr.open('GET', this.ajax + '?' + params.toString());
        xhr.onload = function () {
            delete self.requests[key];
            var data = null;
            try {
                var json = JSON.parse(xhr.responseText);
                data = json && json.success ? json.data : null;
            } catch (err) {
                data = null;
            }
            self.fillChild(container, config, data);
        };
        xhr.onerror = function () {
            delete self.requests[key];
            self.fillChild(container, config, null);
        };
        xhr.send();
    };

    SmForm.prototype.fillChild = function (container, config, data) {
        if (config.dynamic.display) {
            var shown = container.querySelector('.frm_show_it');
            var hidden = container.querySelector('input[type=hidden]');
            var text = data && data.text ? data.text : '';
            if (shown) {
                shown.textContent = text;
            }
            if (hidden) {
                hidden.value = data && data.value ? data.value : '';
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }
            return;
        }
        var select = container.querySelector('select');
        if (!select) {
            return;
        }
        var previous = Array.prototype.filter.call(select.options, function (o) { return o.selected; }).map(function (o) { return o.value; });
        var html = select.multiple ? '' : '<option value=""></option>';
        (data && data.options ? data.options : []).forEach(function (pair) {
            var selected = previous.indexOf(String(pair[0])) !== -1 ? ' selected' : '';
            html += '<option value="' + escapeHtml(pair[0]) + '"' + selected + '>' + escapeHtml(pair[1]) + '</option>';
        });
        select.innerHTML = html;
        select.disabled = false;
        refreshCombo(select);
        select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    // ---------------------------------------------------------------- repeating sections

    SmForm.prototype.addRow = function (button) {
        var sectionId = button.getAttribute('data-parent');
        var rows = this.form.querySelectorAll('[data-sm-row="' + sectionId + '"]');
        if (!rows.length) {
            return;
        }
        var last = rows[rows.length - 1];
        var nextKey = 0;
        Array.prototype.forEach.call(rows, function (r) {
            var k = parseInt(r.getAttribute('data-sm-row-key'), 10);
            if (!isNaN(k) && k >= nextKey) {
                nextKey = k + 1;
            }
        });
        var oldKey = last.getAttribute('data-sm-row-key');
        var clone = last.cloneNode(true);
        renameRow(clone, sectionId, oldKey, String(nextKey));
        clearRow(clone);
        clone.classList.remove('frm_first_repeat');
        last.parentNode.insertBefore(clone, last.nextSibling);
        this.enhance(clone);
        this.applyLogic(clone);
        var first = clone.querySelector('select, input:not([type=hidden]), textarea');
        if (first) {
            first.focus();
        }
    };

    SmForm.prototype.removeRow = function (button) {
        var row = button.closest('[data-sm-row]');
        if (!row) {
            return;
        }
        var siblings = this.form.querySelectorAll('[data-sm-row="' + row.getAttribute('data-sm-row') + '"]');
        if (siblings.length <= 1) {
            clearRow(row);
            this.applyLogic(row);
            return;
        }
        var wasFirst = row.classList.contains('frm_first_repeat');
        var focusTarget = row.previousElementSibling || row.nextElementSibling;
        row.parentNode.removeChild(row);
        if (wasFirst) {
            var firstRow = this.form.querySelector('[data-sm-row="' + row.getAttribute('data-sm-row') + '"]');
            if (firstRow) {
                firstRow.classList.add('frm_first_repeat');
            }
        }
        if (focusTarget && focusTarget.querySelector) {
            var b = focusTarget.querySelector('.sm-add-row');
            if (b) {
                b.focus();
            }
        }
    };

    function renameRow(row, sectionId, oldKey, newKey) {
        var namePart = '[' + sectionId + '][' + oldKey + ']';
        var idSuffix = new RegExp('-' + oldKey + '(?=$|_|-)');
        var containerPart = '-' + sectionId + '-' + oldKey + '_container';
        row.setAttribute('data-sm-row-key', newKey);
        row.id = 'frm_section_' + sectionId + '-' + newKey;
        Array.prototype.forEach.call(row.querySelectorAll('*'), function (el) {
            if (el.name) {
                el.name = el.name.split(namePart).join('[' + sectionId + '][' + newKey + ']');
            }
            if (el.name === 'item_meta[' + sectionId + '][row_ids][]') {
                el.value = newKey;
            }
            ['id', 'for', 'aria-labelledby', 'aria-describedby', 'aria-controls'].forEach(function (attr) {
                var v = el.getAttribute(attr);
                if (!v) {
                    return;
                }
                if (v.indexOf(containerPart) !== -1) {
                    el.setAttribute(attr, v.split(containerPart).join('-' + sectionId + '-' + newKey + '_container'));
                } else {
                    el.setAttribute(attr, v.split(' ').map(function (part) { return part.replace(idSuffix, '-' + newKey); }).join(' '));
                }
            });
            if (el.hasAttribute('data-key')) {
                el.setAttribute('data-key', newKey);
            }
        });
    }

    function clearRow(row) {
        Array.prototype.forEach.call(row.querySelectorAll('.sm-ac'), function (widget) { widget.parentNode.removeChild(widget); });
        Array.prototype.forEach.call(row.querySelectorAll('select'), function (s) {
            s.classList.remove('sm-ac-native');
            delete s.smCombo;
            var container = s.closest('[data-sm-field]');
            var dependent = container && container.getAttribute('data-sm-dependent') === '1';
            if (dependent) {
                s.innerHTML = s.multiple ? '' : '<option value=""></option>';
            } else {
                Array.prototype.forEach.call(s.options, function (o) { o.selected = false; });
                if (!s.multiple && s.options.length) {
                    s.selectedIndex = 0;
                }
            }
        });
        Array.prototype.forEach.call(row.querySelectorAll('input, textarea'), function (i) {
            if (i.name && /\[row_ids\]/.test(i.name)) {
                return;
            }
            if (i.type === 'checkbox' || i.type === 'radio') {
                i.checked = false;
            } else if (i.type !== 'button' && i.type !== 'submit') {
                i.value = '';
            }
        });
        Array.prototype.forEach.call(row.querySelectorAll('.frm_show_it'), function (p) { p.textContent = ''; });
        Array.prototype.forEach.call(row.querySelectorAll('.frm_error'), function (e) { e.parentNode.removeChild(e); });
        Array.prototype.forEach.call(row.querySelectorAll('.frm_blank_field'), function (c) { c.classList.remove('frm_blank_field'); });
        Array.prototype.forEach.call(row.querySelectorAll('.is-invalid'), function (c) { c.classList.remove('is-invalid'); });
    }

    // ---------------------------------------------------------------- pages

    SmForm.prototype.initPages = function () {
        this.pages = Array.prototype.slice.call(this.form.querySelectorAll('.sm-page'));
        if (this.pages.length < 2) {
            return;
        }
        this.finalSubmit = this.form.querySelector('[data-sm-final]');
        var start = parseInt(this.form.getAttribute('data-sm-start-page'), 10) || 1;
        this.showPage(Math.min(Math.max(start, 1), this.pages.length), false);
    };

    SmForm.prototype.showPage = function (n, focus) {
        this.page = n;
        this.pages.forEach(function (p, i) { p.hidden = i !== n - 1; });
        if (this.finalSubmit) {
            this.finalSubmit.hidden = n !== this.pages.length;
        }
        if (focus) {
            var top = this.form.getBoundingClientRect().top + window.pageYOffset - 20;
            window.scrollTo({ top: top, behavior: 'smooth' });
            var first = this.pages[n - 1].querySelector('[data-sm-field]:not([hidden]) select, [data-sm-field]:not([hidden]) input:not([type=hidden]), [data-sm-field]:not([hidden]) textarea');
            if (first) {
                first.focus({ preventScroll: true });
            }
        }
    };

    // Required fields on this page must be filled before moving on (the server checks again)
    SmForm.prototype.pageIsComplete = function (page) {
        var ok = true;
        var self = this;
        Array.prototype.forEach.call(page.querySelectorAll('[data-sm-field].frm_required_field'), function (container) {
            if (container.hidden || container.closest('[hidden]')) {
                return;
            }
            var blank = isBlank(fieldValue(container));
            var existing = container.querySelector('.sm-page-error');
            if (blank && !existing) {
                var msg = document.createElement('div');
                msg.className = 'frm_error invalid-feedback d-block sm-page-error';
                var label = container.querySelector('.frm_primary_label');
                var name = label ? label.childNodes[0].textContent.trim() : '';
                msg.textContent = (name ? name + ' ' : 'This field ') + self.text('blank', 'cannot be blank.');
                container.appendChild(msg);
            } else if (!blank && existing) {
                existing.parentNode.removeChild(existing);
            }
            if (blank) {
                if (ok) {
                    var input = container.querySelector('select, input:not([type=hidden]), textarea, .sm-ac-input');
                    if (input) {
                        input.focus();
                    }
                }
                ok = false;
            }
        });
        return ok;
    };

    // ---------------------------------------------------------------- sections

    function toggleSection(heading, open) {
        var container = heading.parentNode.querySelector('.sm-toggle-container');
        if (!container) {
            return;
        }
        var show = open === undefined ? container.hidden : open;
        container.hidden = !show;
        heading.setAttribute('aria-expanded', show ? 'true' : 'false');
        heading.classList.toggle('active', show);
    }

    // ---------------------------------------------------------------- searchable dropdowns

    function refreshCombo(select) {
        if (select.smCombo) {
            select.smCombo.sync();
        }
    }

    function Combo(select, form) {
        var self = this;
        this.select = select;
        this.form = form;
        this.multiple = select.multiple;
        this.active = -1;
        var uid = select.id || ('sm-ac-' + Math.random().toString(36).slice(2));

        this.wrap = document.createElement('div');
        this.wrap.className = 'sm-ac position-relative';
        this.chips = document.createElement('div');
        this.chips.className = 'sm-ac-chips d-flex flex-wrap gap-1 mb-1';
        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = 'form-control sm-ac-input';
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-controls', uid + '-list');
        this.input.setAttribute('autocomplete', 'off');
        this.input.placeholder = form.text('search', 'Type to search');
        var label = select.id ? document.querySelector('label[for="' + select.id + '"]') : null;
        if (label) {
            if (!label.id) {
                label.id = select.id + '_label';
            }
            this.input.setAttribute('aria-labelledby', label.id);
            label.setAttribute('for', uid + '-search');
        }
        this.input.id = uid + '-search';
        this.list = document.createElement('ul');
        this.list.className = 'sm-ac-list list-group position-absolute w-100 shadow-sm';
        this.list.id = uid + '-list';
        this.list.setAttribute('role', 'listbox');
        if (this.multiple) {
            this.list.setAttribute('aria-multiselectable', 'true');
        }
        this.list.hidden = true;

        if (this.multiple) {
            this.wrap.appendChild(this.chips);
        }
        this.wrap.appendChild(this.input);
        this.wrap.appendChild(this.list);
        select.classList.add('sm-ac-native');
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');
        select.parentNode.insertBefore(this.wrap, select.nextSibling);
        select.smCombo = this;

        this.input.addEventListener('input', function () { self.open(); });
        this.input.addEventListener('focus', function () { self.open(); });
        this.input.addEventListener('keydown', function (e) { self.key(e); });
        this.input.addEventListener('blur', function () {
            setTimeout(function () { self.close(); self.showSelectedText(); }, 150);
        });
        // Keep in step when the dropdown is changed some other way (autofill, another script)
        select.addEventListener('change', function () {
            self.renderChips();
            if (document.activeElement !== self.input) {
                self.showSelectedText();
            }
        });
        this.list.addEventListener('mousedown', function (e) { e.preventDefault(); });
        this.list.addEventListener('click', function (e) {
            var li = e.target.closest('[data-value]');
            if (li) {
                self.choose(li.getAttribute('data-value'));
            }
        });
        this.sync();
    }

    Combo.prototype.options = function () {
        return Array.prototype.filter.call(this.select.options, function (o) { return o.value !== ''; });
    };

    Combo.prototype.sync = function () {
        this.input.disabled = this.select.disabled;
        this.renderChips();
        this.showSelectedText();
        if (!this.list.hidden) {
            this.renderList();
        }
    };

    Combo.prototype.showSelectedText = function () {
        if (this.multiple) {
            this.input.value = '';
            return;
        }
        var selected = this.options().filter(function (o) { return o.selected; })[0];
        this.input.value = selected ? selected.text : '';
    };

    Combo.prototype.renderChips = function () {
        if (!this.multiple) {
            return;
        }
        var self = this;
        this.chips.innerHTML = '';
        this.options().filter(function (o) { return o.selected; }).forEach(function (o) {
            var chip = document.createElement('span');
            chip.className = 'badge text-bg-light border sm-ac-chip d-inline-flex align-items-center';
            chip.textContent = o.text;
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-close btn-close-sm ms-1';
            remove.setAttribute('aria-label', self.form.text('remove', 'Remove') + ' ' + o.text);
            remove.addEventListener('click', function () {
                o.selected = false;
                self.changed();
                self.input.focus();
            });
            chip.appendChild(remove);
            self.chips.appendChild(chip);
        });
    };

    Combo.prototype.renderList = function () {
        var term = this.input.value.trim().toLowerCase();
        var selectedText = !this.multiple && this.options().filter(function (o) { return o.selected; })[0];
        if (selectedText && selectedText.text.toLowerCase() === term) {
            term = '';
        }
        var matches = this.options().filter(function (o) { return term === '' || o.text.toLowerCase().indexOf(term) !== -1; });
        this.matches = matches.slice(0, 200);
        var html = '';
        var listId = this.list.id;
        this.matches.forEach(function (o, i) {
            html += '<li class="list-group-item list-group-item-action sm-ac-option' + (o.selected ? ' active' : '') + '" role="option" id="' + listId + '-' + i + '"'
                + ' aria-selected="' + (o.selected ? 'true' : 'false') + '" data-value="' + escapeHtml(o.value) + '">' + escapeHtml(o.text) + '</li>';
        });
        if (!this.matches.length) {
            html = '<li class="list-group-item text-muted" role="option" aria-disabled="true">' + escapeHtml(this.form.text('noResults', 'No matches')) + '</li>';
        }
        this.list.innerHTML = html;
        this.active = -1;
    };

    Combo.prototype.open = function () {
        if (this.select.disabled) {
            return;
        }
        this.renderList();
        this.list.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
    };

    Combo.prototype.close = function () {
        this.list.hidden = true;
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
    };

    Combo.prototype.key = function (e) {
        var items = this.list.querySelectorAll('[data-value]');
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (this.list.hidden) {
                this.open();
                items = this.list.querySelectorAll('[data-value]');
            }
            if (!items.length) {
                return;
            }
            this.active = e.key === 'ArrowDown' ? Math.min(this.active + 1, items.length - 1) : Math.max(this.active - 1, 0);
            Array.prototype.forEach.call(items, function (li, i) { li.classList.toggle('sm-ac-focus', i === this.active); }, this);
            this.input.setAttribute('aria-activedescendant', items[this.active].id);
            items[this.active].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            if (!this.list.hidden && this.active >= 0 && items[this.active]) {
                e.preventDefault();
                this.choose(items[this.active].getAttribute('data-value'));
            }
        } else if (e.key === 'Escape') {
            this.close();
            this.showSelectedText();
        } else if (e.key === 'Backspace' && this.multiple && this.input.value === '') {
            var chosen = this.options().filter(function (o) { return o.selected; });
            if (chosen.length) {
                chosen[chosen.length - 1].selected = false;
                this.changed();
            }
        }
    };

    Combo.prototype.choose = function (value) {
        var self = this;
        this.options().forEach(function (o) {
            if (o.value === value) {
                o.selected = self.multiple ? !o.selected : true;
            } else if (!self.multiple) {
                o.selected = false;
            }
        });
        if (this.multiple) {
            this.input.value = '';
            this.renderList();
        } else {
            this.close();
        }
        this.changed();
    };

    Combo.prototype.changed = function () {
        this.renderChips();
        this.showSelectedText();
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    // ---------------------------------------------------------------- set-up

    SmForm.prototype.enhance = function (scope) {
        var self = this;
        Array.prototype.forEach.call(scope.querySelectorAll('select[data-sm-autocomplete]'), function (select) {
            if (!select.smCombo) {
                new Combo(select, self);
            }
        });
        // Mark dependent fields so a cloned row empties them
        Array.prototype.forEach.call(scope.querySelectorAll('[data-sm-field]'), function (c) {
            var r = self.rule(c.getAttribute('data-sm-field'));
            if (r && r.dynamic && r.dynamic.dependent) {
                c.setAttribute('data-sm-dependent', '1');
            }
        });
    };

    SmForm.prototype.init = function () {
        var self = this;
        var form = this.form;
        this.enhance(form);
        this.applyLogic();
        this.initPages();

        // Sections with an error start open
        Array.prototype.forEach.call(form.querySelectorAll('.sm-trigger'), function (h) {
            var container = h.parentNode.querySelector('.sm-toggle-container');
            if (container && container.querySelector('.frm_blank_field, .is-invalid')) {
                toggleSection(h, true);
            }
        });

        form.addEventListener('change', function (e) {
            var container = e.target.closest('[data-sm-field]');
            if (!container) {
                return;
            }
            var scope = container.closest('[data-sm-row]') || form;
            var fieldId = container.getAttribute('data-sm-field');
            if (self.childrenOf(fieldId).length) {
                self.parentChanged(scope, fieldId);
            }
            self.applyLogic();
        });
        form.addEventListener('input', function (e) {
            if (e.target.matches('input[type=text], input[type=number], input[type=email], textarea')) {
                self.applyLogic();
            }
        });
        form.addEventListener('click', function (e) {
            var add = e.target.closest('.sm-add-row');
            var remove = e.target.closest('.sm-remove-row');
            var trigger = e.target.closest('.sm-trigger');
            var next = e.target.closest('[data-sm-next]');
            var prev = e.target.closest('[data-sm-prev]');
            if (add) {
                e.preventDefault();
                self.addRow(add);
            } else if (remove) {
                e.preventDefault();
                self.removeRow(remove);
            } else if (trigger && !e.target.closest('.sm-toggle-container')) {
                toggleSection(trigger);
            } else if (next) {
                if (self.pageIsComplete(self.pages[self.page - 1])) {
                    self.showPage(self.page + 1, true);
                }
            } else if (prev) {
                self.showPage(self.page - 1, true);
            }
        });
        form.addEventListener('keydown', function (e) {
            var trigger = e.target.closest('.sm-trigger');
            if (trigger && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                toggleSection(trigger);
            }
        });
        form.addEventListener('submit', function (e) {
            // Enter in a text box on an earlier page moves on instead of submitting
            if (self.pages && self.pages.length > 1 && self.page < self.pages.length) {
                e.preventDefault();
                if (self.pageIsComplete(self.pages[self.page - 1])) {
                    self.showPage(self.page + 1, true);
                }
                return;
            }
            // One submission per click: a double click must not save the entry twice
            if (form.getAttribute('data-sm-sending') === '1') {
                e.preventDefault();
                return;
            }
            form.setAttribute('data-sm-sending', '1');
            Array.prototype.forEach.call(form.querySelectorAll('button[type=submit], input[type=submit]'), function (b) {
                b.setAttribute('aria-disabled', 'true');
                b.classList.add('disabled');
            });
        });
        // Coming back to the page from the browser history re-enables the form
        window.addEventListener('pageshow', function () {
            form.removeAttribute('data-sm-sending');
            Array.prototype.forEach.call(form.querySelectorAll('button[type=submit], input[type=submit]'), function (b) {
                b.removeAttribute('aria-disabled');
                b.classList.remove('disabled');
            });
        });
    };

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function start() {
        Array.prototype.forEach.call(document.querySelectorAll('form[data-sm-form]'), function (form) {
            if (!form.smForm) {
                form.smForm = new SmForm(form);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
