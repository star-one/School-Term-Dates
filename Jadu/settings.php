<!--
  School Term Dates - Jadu Homepage widget (admin settings)

  All school years are held as JSON in the hidden #yearsjson field at the bottom of this
  file; the CMS substitutes it for %YEARSJSON% in front-end.php. Only term dates are stored
  - a school year's own start and end are derived (first day of Autumn Term A to last day of
  Summer Term B), so there are no "year start" / "year end" fields.

  Stored shape (one entry per school year):
    { "id": "y1700000000000",
      "terms": { "autumn": { "a": {"start":"2026-09-02","end":"2026-10-23"},
                             "b": {"start":"2026-11-02","end":"2026-12-18"} },
                 "spring": { ... }, "summer": { ... } } }
  Each term has two parts, Term A and Term B, with the half-term break between them.

  ADAPTING THIS FOR ANOTHER JURISDICTION (different term pattern)
  Change this file and front-end.php together - they must agree on the JSON shape above.
   1. Terms: edit the TERMS array in the script below. Four terms (e.g. Australia, New
      Zealand), semesters, trimesters or quarters are just a different list. The first term
      supplies the year start and the last term the year end (see yStart()/yEnd()), and
      validate() requires terms to run in order. Change $termKeys in front-end.php to match.
   2. Parts of a term: the HALVES array gives each term two parts. For a term with no
      mid-term break, use one part only; for several breaks, use more parts. front-end.php
      (validation, break calculation, rendering, calendar events) must be changed to match.
   3. Labels and help text ("Term A", "Term B") are plain strings in the script; localise them.
   4. Dates: <input type="date"> returns ISO yyyy-mm-dd on every browser that supports it,
      and that is what is stored, so no time zone or locale handling is needed here. The
      display format (MONTHS/fmt) only affects the list of existing years in this form.
   5. Layout: .td-admin__columns uses three equal columns from 48em; change
      grid-template-columns if there are not three terms.
   6. Do not add a <form> element: the widget builder wraps this file in its own form.
-->
<style>
#lb_widget_content { --td-gap: 1rem; --td-space: 0.75rem; --td-border: #c5cdd6; --td-muted: #5d6b7a; --td-error: #b3261e; max-width: 100%; }
#lb_widget_content .data_cell { width: 100%; }
#lb_widget_content .wrapper { padding: 0.5em; }
#lb_widget_content .td-admin__columns { display: grid; grid-template-columns: 1fr; gap: var(--td-gap); margin: var(--td-gap) 0; }
#lb_widget_content .td-admin__card { border: 0.125rem solid var(--td-border); border-radius: 0.5rem; padding: var(--td-space); margin: 0; min-width: 0; }
#lb_widget_content .td-admin__card legend { font-weight: 700; padding: 0 0.25rem; }
#lb_widget_content .td-admin__field { display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: var(--td-space); }
#lb_widget_content .td-admin__field input[type="date"] { width: 100%; font-size: 1rem; padding: 0.5rem; box-sizing: border-box; }
#lb_widget_content .td-admin__hint { font-size: 0.8125rem; color: var(--td-muted); }
#lb_widget_content .td-admin__req { color: var(--td-error); font-weight: 700; }
#lb_widget_content .td-admin__error { font-size: 0.8125rem; color: var(--td-error); font-weight: 600; }
#lb_widget_content .td-admin__field.has-error input { border: 2px solid var(--td-error); }
#lb_widget_content .td-admin__summary { color: var(--td-error); font-weight: 600; margin: 0 0 var(--td-space); }
#lb_widget_content .td-admin__success { margin: var(--td-space) 0 0; padding: 0.75rem 1rem; border-left: 0.375rem solid #0f7a52; background: #e6f4ee; color: #0b4d34; font-weight: 600; }
#lb_widget_content .td-admin__success:empty { display: none; }
#lb_widget_content .td-admin__actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
#lb_widget_content table.td-admin__list { width: 100%; border-collapse: collapse; margin-top: 0.5rem; }
#lb_widget_content .td-admin__list th, #lb_widget_content .td-admin__list td { border: 1px solid var(--td-border); padding: 0.375rem 0.5rem; text-align: left; vertical-align: top; }
#lb_widget_content button:focus-visible, #lb_widget_content input:focus-visible { outline: 0.1875rem solid #1f5fbf; outline-offset: 0.125rem; }
@media (min-width: 48em) { #lb_widget_content .td-admin__columns { grid-template-columns: 1fr 1fr 1fr; } }
@media (max-width: 48em) { #lb_widget_content .td-admin__list { display: block; overflow-x: auto; } }
</style>
<div class="wrapper">
<table class="form_table" id="lb_widget_content">
    <tbody>
    <tr>
        <td class="data_cell">
            <div role="group" aria-labelledby="td-form-title" id="td-form">
                <h3 id="td-form-title" style="margin-top:0">Add new school year</h3>
                <p id="td-form-summary" class="td-admin__summary" role="alert" hidden></p>
                <p class="td-admin__hint">Fields marked <span class="td-admin__req" aria-hidden="true">*</span> are required. The school year runs from the start of Autumn Term A to the end of Summer Term B.</p>

                <div class="td-admin__columns" id="td-terms"><!-- Autumn, Spring and Summer cards are built by the script below --></div>

                <div class="td-admin__actions">
                    <button type="button" id="td-submit" class="btn btn--primary">Save</button>
                    <button type="button" id="td-cancel" hidden>Cancel edit</button>
                </div>
                <p id="td-form-success" class="td-admin__success" role="status" aria-live="polite" tabindex="-1"></p>
            </div>
        </td>
    </tr>
    <tr>
        <td class="data_cell">
            <p id="td-list-empty">No school years have been added yet.</p>
            <table class="td-admin__list" id="td-list" hidden>
                <caption style="text-align:left;font-weight:600">Existing school years</caption>
                <thead><tr><th scope="col">School year</th><th scope="col">Dates</th><th scope="col">Actions</th></tr></thead>
                <tbody id="td-list-body"></tbody>
            </table>
            <!-- All years are stored as JSON in this one field; the CMS substitutes it for %YEARSJSON% in front-end.php -->
            <input id="yearsjson" value="" type="hidden">
        </td>
    </tr>
    </tbody>
</table>
</div>

<script>
(function () {
    var TERMS = [['autumn', 'Autumn'], ['spring', 'Spring'], ['summer', 'Summer']];
    var HALVES = [['a', 'Term A'], ['b', 'Term B']];
    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var FIRST = TERMS[0][0], LAST = TERMS[TERMS.length - 1][0];
    var years = [], editingId = null, loaded = false, dirty = false;
    // Acknowledgement messages shown after Save / Delete. "Continue" is Jadu's own button, which saves the widget and returns to the page editor. Edit the wording here (e.g. localise it).
    var MSG = {
        added: function (label) { return 'School year ' + label + ' has been added. Select Continue to save the widget and publish your changes.'; },
        updated: function (label) { return 'School year ' + label + ' has been updated. Select Continue to save the widget and publish your changes.'; },
        deleted: function (label) { return 'School year ' + label + ' has been deleted. Select Continue to save the widget and publish your changes.'; }
    };

    function $(id) { return document.getElementById(id); }

    /* ---------- build the term cards (labels, help text and error slots for every field) ---------- */
    function fieldHtml(key, label, help) {
        return '<div class="td-admin__field" id="td-field-' + key + '">' +
            '<label for="td-' + key + '">' + label + ' <span class="td-admin__req" aria-hidden="true">*</span></label>' +
            '<input type="date" id="td-' + key + '" required aria-required="true" aria-describedby="td-help-' + key + ' td-error-' + key + '">' +
            '<span class="td-admin__hint" id="td-help-' + key + '">' + help + '</span>' +
            '<span class="td-admin__error" id="td-error-' + key + '"></span></div>';
    }
    var html = '';
    TERMS.forEach(function (t) {
        html += '<fieldset class="td-admin__card"><legend>' + t[1] + '</legend>';
        HALVES.forEach(function (h) {
            html += fieldHtml(t[0] + '-' + h[0] + '-start', h[1] + ' start', 'First day of ' + h[1] + ' in the ' + t[1] + ' term.');
            html += fieldHtml(t[0] + '-' + h[0] + '-end', h[1] + ' end', 'Last day of ' + h[1] + ' in the ' + t[1] + ' term.');
        });
        html += '</fieldset>';
    });
    $('td-terms').innerHTML = html;

    /* ---------- helpers ---------- */
    function isDate(s) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) { return false; }
        var p = s.split('-'), d = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
        return d.getUTCFullYear() === +p[0] && d.getUTCMonth() === +p[1] - 1 && d.getUTCDate() === +p[2];
    }
    function fmt(s) { var p = s.split('-'); return +p[2] + ' ' + MONTHS[+p[1] - 1] + ' ' + p[0]; }
    // The school year's own dates are derived, never entered or stored.
    function yStart(y) { return y.terms[FIRST].a.start; }
    function yEnd(y) { return y.terms[LAST].b.end; }
    function yearLabel(y) {
        var a = yStart(y).slice(0, 4), b = yEnd(y).slice(0, 4);
        return a === b ? a : a + '/' + b.slice(2);
    }
    function allKeys() {
        var k = [];
        TERMS.forEach(function (t) { HALVES.forEach(function (h) { k.push(t[0] + '-' + h[0] + '-start', t[0] + '-' + h[0] + '-end'); }); });
        return k;
    }
    function showSuccess(msg) {
        var el = $('td-form-success');
        el.textContent = '';                       // clear first so the same message is announced again
        setTimeout(function () {
            el.textContent = msg;
            if (el.scrollIntoView) { el.scrollIntoView({ block: 'nearest' }); }
        }, 50);
    }
    function clearSuccess() { $('td-form-success').textContent = ''; }
    function persist() {
        $('yearsjson').value = JSON.stringify(years);
        dirty = true;
        var ev = document.createEvent('Event'); ev.initEvent('change', true, true); $('yearsjson').dispatchEvent(ev);
    }
    // Keep only complete years; drop the old stored "start"/"end" keys (now derived).
    function usable(y) {
        if (!y || !y.id || !y.terms) { return false; }
        for (var i = 0; i < TERMS.length; i++) {
            var t = y.terms[TERMS[i][0]];
            if (!t) { return false; }
            for (var j = 0; j < HALVES.length; j++) {
                var h = t[HALVES[j][0]];
                if (!h || !isDate(h.start || '') || !isDate(h.end || '')) { return false; }
            }
        }
        return true;
    }
    function normalise(y) { return { id: String(y.id), terms: y.terms }; }

    /* ---------- validation (client side; front-end.php validates again before display) ---------- */
    function clearErrors() {
        allKeys().forEach(function (k) {
            $('td-error-' + k).textContent = '';
            $('td-field-' + k).className = 'td-admin__field';
            $('td-' + k).removeAttribute('aria-invalid');
        });
        $('td-form-summary').hidden = true; $('td-form-summary').textContent = '';
    }
    function readForm() {
        var v = {}; allKeys().forEach(function (k) { v[k] = $('td-' + k).value; }); return v;
    }
    function validate(v) {
        var e = {};
        allKeys().forEach(function (k) {
            if (!v[k]) { e[k] = 'This field is required.'; }
            else if (!isDate(v[k])) { e[k] = 'Enter a valid date.'; }
        });
        var prevEnd = null;
        TERMS.forEach(function (t) {
            var as = t[0] + '-a-start', ae = t[0] + '-a-end', bs = t[0] + '-b-start', be = t[0] + '-b-end';
            if (!e[ae] && !e[as] && v[ae] < v[as]) { e[ae] = 'Must not be before Term A start.'; }
            if (!e[bs] && !e[ae] && v[bs] <= v[ae]) { e[bs] = 'Term B must start after Term A ends.'; }
            if (!e[be] && !e[bs] && v[be] < v[bs]) { e[be] = 'Must not be before Term B start.'; }
            if (prevEnd && !e[as] && v[as] <= prevEnd) { e[as] = 'Must be after the end of the previous term.'; }
            if (!e[be]) { prevEnd = v[be]; }
        });
        // The school year runs from the first term's start to the last term's end; it must not overlap another year.
        var fs = FIRST + '-a-start', le = LAST + '-b-end';
        if (!e[fs] && !e[le]) {
            years.forEach(function (y) {
                if (y.id !== editingId && v[fs] <= yEnd(y) && v[le] >= yStart(y) && !e[fs]) {
                    e[fs] = 'These dates overlap the existing school year ' + yearLabel(y) + '.';
                }
            });
        }
        return e;
    }
    function showErrors(e) {
        var first = null, n = 0;
        allKeys().forEach(function (k) {
            if (e[k]) {
                n++; if (!first) { first = k; }
                $('td-error-' + k).textContent = e[k];
                $('td-field-' + k).className = 'td-admin__field has-error';
                $('td-' + k).setAttribute('aria-invalid', 'true');
            }
        });
        $('td-form-summary').textContent = 'Please correct ' + n + (n === 1 ? ' error' : ' errors') + ' below.';
        $('td-form-summary').hidden = false;
        if (first) { $('td-' + first).focus(); }
    }

    /* ---------- list of existing years ---------- */
    function renderList() {
        years.sort(function (a, b) { return yStart(a) < yStart(b) ? -1 : 1; });
        var body = $('td-list-body'); body.innerHTML = '';
        years.forEach(function (y) {
            var tr = document.createElement('tr');
            var th = document.createElement('th'); th.scope = 'row'; th.textContent = yearLabel(y);
            var td1 = document.createElement('td'); td1.textContent = fmt(yStart(y)) + ' to ' + fmt(yEnd(y));
            var td2 = document.createElement('td');
            var edit = document.createElement('button'); edit.type = 'button'; edit.textContent = 'Edit';
            edit.setAttribute('aria-label', 'Edit school year ' + yearLabel(y));
            edit.setAttribute('class', 'btn btn--primary');
            edit.addEventListener('click', function () { startEdit(y.id); });
            var del = document.createElement('button'); del.type = 'button'; del.textContent = 'Delete';
            del.setAttribute('aria-label', 'Delete school year ' + yearLabel(y));
            del.setAttribute('class', 'btn btn--danger');
            del.addEventListener('click', function () { removeYear(y.id); });
            td2.appendChild(edit); td2.appendChild(document.createTextNode(' ')); td2.appendChild(del);
            tr.appendChild(th); tr.appendChild(td1); tr.appendChild(td2); body.appendChild(tr);
        });
        $('td-list').hidden = !years.length;
        $('td-list-empty').hidden = !!years.length;
    }

    function resetForm() {
        editingId = null; clearErrors();
        allKeys().forEach(function (k) { $('td-' + k).value = ''; });
        $('td-form-title').textContent = 'Add new school year';
        $('td-cancel').hidden = true;
    }
    function startEdit(id) {
        var y = years.filter(function (x) { return x.id === id; })[0]; if (!y) { return; }
        clearErrors(); clearSuccess(); editingId = id;
        TERMS.forEach(function (t) { HALVES.forEach(function (h) {
            $('td-' + t[0] + '-' + h[0] + '-start').value = y.terms[t[0]][h[0]].start;
            $('td-' + t[0] + '-' + h[0] + '-end').value = y.terms[t[0]][h[0]].end;
        }); });
        $('td-form-title').textContent = 'Edit school year ' + yearLabel(y);
        $('td-cancel').hidden = false;
        $('td-' + allKeys()[0]).focus();
    }
    function removeYear(id) {
        var y = years.filter(function (x) { return x.id === id; })[0]; if (!y) { return; }
        if (!window.confirm('Delete school year ' + yearLabel(y) + '? This cannot be undone.')) { return; }
        years = years.filter(function (x) { return x.id !== id; });
        if (editingId === id) { resetForm(); }
        persist(); renderList();
        showSuccess(MSG.deleted(yearLabel(y)));
    }

    $('td-submit').addEventListener('click', function () {
        clearErrors(); clearSuccess();
        var v = readForm(), e = validate(v);
        if (Object.keys(e).length) { showErrors(e); return; }
        var y = { id: editingId || ('y' + Date.now()), terms: {} };
        TERMS.forEach(function (t) {
            y.terms[t[0]] = {
                a: { start: v[t[0] + '-a-start'], end: v[t[0] + '-a-end'] },
                b: { start: v[t[0] + '-b-start'], end: v[t[0] + '-b-end'] }
            };
        });
        var wasEditing = !!editingId;
        if (editingId) { years = years.map(function (x) { return x.id === editingId ? y : x; }); }
        else { years.push(y); }
        resetForm(); persist(); renderList();
        showSuccess(wasEditing ? MSG.updated(yearLabel(y)) : MSG.added(yearLabel(y)));
    });
    $('td-cancel').addEventListener('click', resetForm);

    /* ---------- load previously saved data (the CMS may fill the hidden field after this script runs) ---------- */
    function load() {
        if (loaded || dirty) { return; }
        var raw = $('yearsjson').value;
        if (!raw) { return; }
        try {
            var data = JSON.parse(raw);
            if (Array.isArray(data)) { years = data.filter(usable).map(normalise); loaded = true; renderList(); }
        } catch (err) { /* leave the list empty */ }
    }
    load(); renderList();
    [250, 750, 1500, 3000].forEach(function (ms) { setTimeout(load, ms); });
})();
</script>
