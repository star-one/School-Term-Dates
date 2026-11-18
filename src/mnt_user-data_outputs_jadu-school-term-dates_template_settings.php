<!--
 * JADU HOMEPAGE WIDGET - settings.php (admin side) - BARE-BONES TEMPLATE
 *
 * How the pair works (Jadu Central):
 *  - Every input/textarea inside #lb_widget_content is picked up by its id.
 *  - The id, upper-cased, becomes a %PLACEHOLDER% that the CMS substitutes into front-end.php.
 *      id="title"      ->  %TITLE%
 *      id="itemsjson"  ->  %ITEMSJSON%
 *  - Keep the markup as a table.form_table > tr > td.label_cell + td.data_cell.
 *  - Do not use a <form> element: the builder wraps this file in its own form.
 *  - Saved values are written back into the fields by id when the widget is re-edited.
 *    Fields filled by the CMS may arrive after this script runs, so retry loading (see bottom).
 *
 * Fields:
 *  1. Simple text field          (id="title")
 *  2. Textarea                   (id="content")
 *  3. Hidden JSON store          (id="itemsjson") - use when you need a variable-length list
 *     of records (add/edit/delete). Proven to survive the widget builder save/re-edit cycle.
-->
<table class="form_table" id="lb_widget_content">
    <tbody>
    <tr>
        <td class="label_cell"><label for="title">Heading</label></td>
        <td class="data_cell">
            <input id="title" value="" size="12" class="field" type="text">
        </td>
    </tr>
    <tr>
        <td class="label_cell"><label for="content">Content</label></td>
        <td class="data_cell">
            <textarea id="content" rows="6" cols="80"></textarea>
        </td>
    </tr>
    <!-- Remove this row if you do not need a list of records. -->
    <tr>
        <td class="label_cell">Items</td>
        <td class="data_cell">
            <ul id="items-list"></ul>
            <label for="item-name">New item</label>
            <input id="item-name" value="" size="12" class="field" type="text">
            <button type="button" id="item-add">Add</button>
            <input id="itemsjson" value="" type="hidden">
        </td>
    </tr>
    </tbody>
</table>

<script>
(function () {
    var items = [], loaded = false, dirty = false;
    function $(id) { return document.getElementById(id); }

    function persist() {
        $('itemsjson').value = JSON.stringify(items);
        dirty = true;
        var ev = document.createEvent('Event'); ev.initEvent('change', true, true);
        $('itemsjson').dispatchEvent(ev);
    }

    function render() {
        var ul = $('items-list'); ul.innerHTML = '';
        items.forEach(function (it, i) {
            var li = document.createElement('li');
            li.appendChild(document.createTextNode(it.name + ' ')); // textContent only: never innerHTML for user data
            var del = document.createElement('button');
            del.type = 'button'; del.textContent = 'Delete';
            del.setAttribute('aria-label', 'Delete ' + it.name);
            del.addEventListener('click', function () { items.splice(i, 1); persist(); render(); });
            li.appendChild(del); ul.appendChild(li);
        });
    }

    $('item-add').addEventListener('click', function () {
        var v = $('item-name').value.replace(/^\s+|\s+$/g, '');
        if (!v) { return; }
        items.push({ name: v });
        $('item-name').value = '';
        persist(); render();
    });

    // Load saved data. The CMS may fill the hidden field after this script runs, so retry briefly.
    function load() {
        if (loaded || dirty) { return; }
        var raw = $('itemsjson').value;
        if (!raw) { return; }
        try {
            var data = JSON.parse(raw);
            if (Array.isArray(data)) { items = data; loaded = true; render(); }
        } catch (e) { /* leave empty */ }
    }
    load(); render();
    [250, 750, 1500, 3000].forEach(function (ms) { setTimeout(load, ms); });
})();
</script>
