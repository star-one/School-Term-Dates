<?php
/*
 * JADU HOMEPAGE WIDGET - front-end.php (public side) - BARE-BONES TEMPLATE
 *
 *  - %PLACEHOLDERS% are substituted by the CMS from the matching settings.php field ids
 *    (id="title" -> %TITLE%). Put them inside single-quoted PHP strings, as below.
 *  - Treat every substituted value as untrusted: validate it, and escape on output.
 *  - Use a unique widget ID so the widget can be placed on a page more than once.
 *  - Wrap helper functions in function_exists() for the same reason.
 *  - Keep CSS/JS inline and scoped to a widget-specific class; use rem units and CSS variables.
 *    Mobile-first, one breakpoint (48em = 768px) is enough unless the design needs more.
 */
$widgetTitle   = '%TITLE%';
$widgetContent = '%CONTENT%';
$itemsJson     = '%ITEMSJSON%';

$widgetID = 'widget_' . uniqid();

if (!function_exists('jaduWidgetEsc')) {
    function jaduWidgetEsc($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Server-side validation of the JSON store: anything malformed is dropped.
$items = array();
$decoded = json_decode($itemsJson, true);
if (is_array($decoded)) {
    foreach ($decoded as $row) {
        if (is_array($row) && isset($row['name']) && is_string($row['name']) && $row['name'] !== '') {
            $items[] = array('name' => $row['name']);
        }
    }
}
?>
<style>
.jadu-widget { --jw-gap: 1rem; --jw-fg: #1d2733; --jw-border: #c5cdd6; --jw-accent: #1f5fbf; color: var(--jw-fg); max-width: 100%; box-sizing: border-box; }
.jadu-widget *, .jadu-widget *::before, .jadu-widget *::after { box-sizing: border-box; }
.jadu-widget a:focus-visible, .jadu-widget button:focus-visible { outline: 0.1875rem solid var(--jw-accent); outline-offset: 0.125rem; }
</style>

<div class="jadu-widget" id="<?=$widgetID;?>">
    <h3 id="<?=$widgetID;?>-title"><?=jaduWidgetEsc($widgetTitle);?></h3>

    <?php /* The textarea content is entered by trusted CMS admins as HTML, so it is output as-is. Use jaduWidgetEsc() instead if it should be plain text. */ ?>
    <div><?=$widgetContent;?></div>

<?php if (count($items)) { ?>
    <ul>
<?php foreach ($items as $item) { ?>
        <li><?=jaduWidgetEsc($item['name']);?></li>
<?php } ?>
    </ul>
<?php } ?>
</div>

<script>
(function () {
    var root = document.getElementById('<?=$widgetID;?>');
    if (!root) { return; }
    // Widget behaviour goes here, scoped to root.
})();
</script>
