<?php
/*
 * School Term Dates - Jadu Homepage widget (front-end)
 *
 * %YEARSJSON% is substituted by the CMS from the hidden #yearsjson field in settings.php.
 * Everything below treats that value as untrusted: it is re-parsed and re-validated here
 * (server-side) and every output is escaped.
 *
 * HOW IT WORKS
 *  - A "school year" is three terms (Autumn, Spring, Summer). Each term has two parts, Term A
 *    and Term B, with a half-term break between them. Only those dates are stored; the
 *    school year's own start/end are DERIVED: first day of Autumn Term A to last day of
 *    Summer Term B. (Any "start"/"end" keys in older saved JSON are ignored.)
 *  - Current year: the year whose derived dates contain today. Between two years (the summer
 *    holiday) the NEXT year that has data becomes the current year from the day after the
 *    previous year's last day of term. If there is no later year, the last year stays current.
 *  - Term time = today falls inside any Term A / Term B period; otherwise Holiday time.
 *  - The half-term break is the gap between Term A and Term B with leading/trailing weekends
 *    trimmed, so it normally reads Monday to Friday (see termDatesBreak()).
 *  - "Add to calendar" builds an .ics file in the browser: a one-day "term starts" event, a
 *    one-day "term ends" event, and a multi-day half-term event, all marked transparent
 *    (they do not block the user's time).
 *
 * ADAPTING THIS FOR ANOTHER JURISDICTION (different term pattern)
 *  Change settings.php and this file together: they share one JSON shape,
 *  { "id": "...", "terms": { "<termKey>": { "a": {"start","end"}, "b": {"start","end"} } } }
 *  1. Number/names of terms: edit $termKeys in termDatesClean() (and TERMS in settings.php).
 *     Four-term years (e.g. Australia, New Zealand), two semesters, three trimesters or
 *     quarters (e.g. USA) just mean a different list. The first key supplies the year start
 *     and the last key the year end (see $firstKey / $lastKey).
 *  2. Parts of a term: this assumes each term is two parts with a break between. For a term
 *     with no mid-term break, delete the "b" part (settings.php fields, the validation here,
 *     termDatesBreak(), the periods list, termDatesRenderYear() and the calendar events).
 *     For several breaks, turn "a"/"b" into an array of periods and loop over it.
 *  3. Time zone: 'Europe/London' appears in termDatesParse() and when $today is created.
 *  4. Year label: termDatesYearLabel() gives "2026/27" for years spanning two calendar
 *     years, and "2026" when the year starts and ends in the same calendar year (e.g. a
 *     January-December school year in the southern hemisphere).
 *  5. Wording and format: "Term A", "Half term", "holiday", "half term" and the date format
 *     'D j M' (termDatesFmt(), term cards) and 'l j F' (termDatesFmtLong(), countdown banner) are plain strings; localise them as needed. Calendar event
 *     titles are in termDatesCalendarData().
 *  6. Layout: .term-dates__columns uses three equal columns from 48em; change
 *     grid-template-columns if there are not three terms (e.g. repeat(4, 1fr)).
 *  7. Countdown: termDatesCountdown() counts in days up to 14 days, then rounded weeks.
 *  8. Different holiday rules (e.g. staggered regional dates, or a separate list of
 *     one-off closure days) need a new array in the JSON; add it to settings.php, validate it
 *     here, and render it - the parsing/escaping pattern in this file is the one to copy.
 */
$yearsJson = '%YEARSJSON%';
$widgetID  = 'widget_' . uniqid();

if (!function_exists('termDatesClean')) {

    function termDatesEsc($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function termDatesParse($s)
    {
        if (!is_string($s) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) { return null; }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s, new DateTimeZone('Europe/London'));
        if (!$d || $d->format('Y-m-d') !== $s) { return null; }
        return $d;
    }

    /* Dates on the term cards: day of week, no year (the school year heading implies it), e.g. "Wed 2 Sep". */
    function termDatesFmt($d)
    {
        return $d->format('D j M');
    }

    /* Dates in the countdown banner, written out in full, e.g. "Friday 23 October". */
    function termDatesFmtLong($d)
    {
        return $d->format('l j F');
    }

    /* "2026/27", or "2026" when the year starts and ends in the same calendar year. */
    function termDatesYearLabel($y)
    {
        $a = $y['start']->format('Y');
        $b = $y['end']->format('Y');
        return ($a === $b) ? $a : $a . '/' . $y['end']->format('y');
    }

    /* Parse + validate the JSON; invalid years are silently dropped. */
    function termDatesClean($json)
    {
        $data = json_decode($json, true);
        if (!is_array($data)) { return array(); }
        $termKeys = array('autumn' => 'Autumn', 'spring' => 'Spring', 'summer' => 'Summer');
        $keys = array_keys($termKeys);
        $firstKey = $keys[0];
        $lastKey  = $keys[count($keys) - 1];
        $years = array();
        foreach ($data as $i => $y) {
            if (!is_array($y)) { continue; }

            $terms = array();
            $ok = true;
            $prevEnd = null;
            foreach ($termKeys as $key => $label) {
                $t = (isset($y['terms'][$key]) && is_array($y['terms'][$key])) ? $y['terms'][$key] : null;
                if (!$t) { $ok = false; break; }
                $as = termDatesParse(isset($t['a']['start']) ? $t['a']['start'] : null);
                $ae = termDatesParse(isset($t['a']['end']) ? $t['a']['end'] : null);
                $bs = termDatesParse(isset($t['b']['start']) ? $t['b']['start'] : null);
                $be = termDatesParse(isset($t['b']['end']) ? $t['b']['end'] : null);
                if (!$as || !$ae || !$bs || !$be) { $ok = false; break; }
                // A start <= A end < B start <= B end, and terms follow one another in order
                if ($as > $ae || $ae >= $bs || $bs > $be) { $ok = false; break; }
                if ($prevEnd && $as <= $prevEnd) { $ok = false; break; }
                $prevEnd = $be;
                $terms[$key] = array('label' => $label, 'as' => $as, 'ae' => $ae, 'bs' => $bs, 'be' => $be);
            }
            if (!$ok) { continue; }

            $id = isset($y['id']) ? preg_replace('/[^A-Za-z0-9_-]/', '', (string)$y['id']) : '';
            if ($id === '') { $id = 'y' . $i; }
            // The school year's own start and end are derived from the first and last term.
            $years[] = array('id' => $id, 'start' => $terms[$firstKey]['as'], 'end' => $terms[$lastKey]['be'], 'terms' => $terms);
        }
        usort($years, function ($a, $b) {
            return strcmp($a['start']->format('Y-m-d'), $b['start']->format('Y-m-d'));
        });
        return $years;
    }

    /* "This is in 5 days' time" - days if 14 or fewer, else rounded weeks. */
    function termDatesCountdown($today, $target)
    {
        $days = (int)$today->diff($target)->days;
        if ($days <= 14) {
            $n = $days;
            $unit = ($days === 1) ? 'day' : 'days';
        } else {
            $n = (int)round($days / 7);
            $unit = ($n === 1) ? 'week' : 'weeks';
        }
        $possessive = ($n === 1) ? "\u{2019}s" : "\u{2019}"; // "1 day's time" / "3 days' time"
        return 'This is in ' . $n . ' ' . $unit . $possessive . ' time.';
    }

    /* Half-term break between Term A and Term B, shown the way school calendars show it: the
     * gap between the two terms with any leading and trailing weekend trimmed off (so a break
     * that follows a Friday and precedes a Monday is Monday-Friday). Returns null when nothing
     * is left, i.e. the terms are back to back or only a weekend separates them.
     * Weekend days are ISO weekdays 6 and 7 (Saturday, Sunday); change the test below for a
     * jurisdiction with a different weekend (e.g. Friday and Saturday = 5 and 6). */
    function termDatesBreak($t)
    {
        $from = $t['ae']->modify('+1 day');
        $to   = $t['bs']->modify('-1 day');
        while ($from <= $to && (int)$from->format('N') >= 6) { $from = $from->modify('+1 day'); }
        while ($to >= $from && (int)$to->format('N') >= 6) { $to = $to->modify('-1 day'); }
        return ($from <= $to) ? array($from, $to) : null;
    }

    function termDatesRange($from, $to)
    {
        return '<p>' . termDatesEsc(termDatesFmt($from)) . ' <em>to</em> ' . termDatesEsc(termDatesFmt($to)) . '</p>';
    }

    function termDatesRenderYear($y, $withCalendar, $widgetID, $headingId)
    {
        $label = termDatesYearLabel($y);
        $out  = '<h2 id="' . $headingId . '" class="term-dates__year-title">School year ' . termDatesEsc($label) . '</h2>';
        $out .= '<div class="term-dates__columns">';
        foreach ($y['terms'] as $t) {
            $out .= '<div class="term-dates__column"><div class="term-dates__card">';
            $out .= '<h3 class="term-dates term">' . termDatesEsc($t['label']) . '</h3>';
            $out .= '<p class="term-dates__label">Term A</p>' . termDatesRange($t['as'], $t['ae']);
            $out .= '<p class="term-dates__label">Half term</p>';
            $b = termDatesBreak($t);
            $out .= $b ? termDatesRange($b[0], $b[1]) : '<p>No break between Term A and Term B</p>';
            $out .= '<p class="term-dates__label">Term B</p>' . termDatesRange($t['bs'], $t['be']);
            $out .= '</div></div>';
        }
        $out .= '</div>';
        if ($withCalendar) {
            $out .= '<p><button type="button" class="button button--primary" data-calendar-year="' . termDatesEsc($y['id']) . '">'
                  . 'Add to calendar<span class="term-dates__sr"> for school year ' . termDatesEsc($label) . '</span></button></p>';
        }
        return $out;
    }

    /* Calendar events per term: start of term (one day), end of term (one day), half term (multi-day). */
    function termDatesCalendarData($years)
    {
        $out = array();
        foreach ($years as $y) {
            $events = array();
            foreach ($y['terms'] as $t) {
                $events[] = array('title' => $t['label'] . ' term starts', 'start' => $t['as']->format('Y-m-d'), 'end' => $t['as']->format('Y-m-d'));
                $b = termDatesBreak($t);
                if ($b) {
                    $events[] = array('title' => $t['label'] . ' half term', 'start' => $b[0]->format('Y-m-d'), 'end' => $b[1]->format('Y-m-d'));
                }
                $events[] = array('title' => $t['label'] . ' term ends', 'start' => $t['be']->format('Y-m-d'), 'end' => $t['be']->format('Y-m-d'));
            }
            $out[$y['id']] = array('label' => termDatesYearLabel($y), 'events' => $events);
        }
        return $out;
    }
}

$years = termDatesClean($yearsJson);
$today = new DateTimeImmutable('today', new DateTimeZone('Europe/London'));

/* Classify years: current / future / past.
 * The year containing today is current. If today is between two years (the summer holiday),
 * the next year with data becomes current from the day after the previous year's last day. */
$currentIdx = null;
foreach ($years as $i => $y) {
    if ($y['start'] <= $today && $today <= $y['end']) { $currentIdx = $i; break; }
}
if ($currentIdx === null) {
    foreach ($years as $i => $y) { if ($y['start'] > $today) { $currentIdx = $i; break; } }
}
if ($currentIdx === null && count($years)) { $currentIdx = count($years) - 1; }

$current = ($currentIdx !== null) ? $years[$currentIdx] : null;
$future = array();
$past   = array();
foreach ($years as $i => $y) {
    if ($i === $currentIdx) { continue; }
    if ($i > $currentIdx) { $future[] = $y; } else { $past[] = $y; }
}
$past = array_reverse($past); // most recent first

/* Term time / Holiday time state, from every Term A and Term B period */
$periods = array();
foreach ($years as $y) {
    foreach ($y['terms'] as $t) {
        $periods[] = array($t['as'], $t['ae']);
        $periods[] = array($t['bs'], $t['be']);
    }
}
usort($periods, function ($a, $b) { return strcmp($a[0]->format('Y-m-d'), $b[0]->format('Y-m-d')); });

$state = null;      // 'term' | 'holiday' | null
$stateDate = null;
$inside = false;
foreach ($periods as $p) {
    if ($p[0] <= $today && $today <= $p[1]) { $inside = true; break; }
}
if ($inside) {
    $state = 'term';
    $holidayStart = null;
    foreach ($periods as $p) {
        if ($p[0] <= $today && $today <= $p[1]) { $holidayStart = $p[1]->modify('+1 day'); break; }
    }
    $changed = true;
    while ($changed) { // merge back-to-back periods
        $changed = false;
        foreach ($periods as $p) {
            if ($p[0] <= $holidayStart && $p[1] >= $holidayStart) { $holidayStart = $p[1]->modify('+1 day'); $changed = true; }
        }
    }
    $stateDate = $holidayStart;
} else {
    foreach ($periods as $p) {
        if ($p[0] > $today) { $state = 'holiday'; $stateDate = $p[0]; break; }
    }
}
?>
<style>
.term-dates { --td-gap: 1rem; --td-space: 0.75rem; --td-fg: #1d2733; --td-muted: #5d6b7a; --td-border: #ddd; --td-accent: #1f5fbf; --td-card: #fff; color: var(--td-fg); font-size: 1rem; line-height: 1.4; max-width: 100%; box-sizing: border-box; }
.term-dates *, .term-dates *::before, .term-dates *::after { box-sizing: border-box; }
.term-dates__section { display: flex; flex-direction: column; gap: var(--td-gap); }
.term-dates__next { color: #fff; background: #0f7a52; padding: 2em; }
.term-dates__columns { display: grid; grid-template-columns: 1fr; gap: var(--td-gap); }
.term-dates__column { min-width: 0; }
.term-dates__card { border: 1px solid var(--td-border); border-radius: 0.5rem; padding: var(--td-space); background: var(--td-card); height: 100%; }
.term-dates__label { font-weight: 600; color: var(--td-muted); margin-bottom: 0.25rem; }
.term-dates__twister { margin-bottom: var(--td-gap); }
.term-dates__twister > summary { cursor: pointer; font-weight: 600; }
.term-dates__twister[open] > summary { margin-bottom: var(--td-gap); }
.term-dates__year { margin-bottom: calc(var(--td-gap) * 1.5); }
.term-dates summary:focus-visible, .term-dates .button:focus-visible { outline: 0.1875rem solid var(--td-accent); outline-offset: 0.125rem; }
.term-dates__sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.term { margin: 0 !important; }
@media (min-width: 48em) { .term-dates__columns { grid-template-columns: 1fr 1fr 1fr; } }
</style>

<div class="term-dates" id="<?=$widgetID;?>" data-state="<?=termDatesEsc($state === 'term' ? 'Term time' : ($state === 'holiday' ? 'Holiday time' : ''));?>">
<?php if ($state === 'term') { ?>
    <section class="term-dates__section" aria-labelledby="<?=$widgetID;?>-next">
        <div class="term-dates__next" data-state-show="Term time">
            <h2 id="<?=$widgetID;?>-next">The next school holiday starts on <?=termDatesEsc(termDatesFmtLong($stateDate));?></h2>
            <p><?=termDatesEsc(termDatesCountdown($today, $stateDate));?></p>
        </div>
    </section>
<?php } elseif ($state === 'holiday') { ?>
    <section class="term-dates__section" aria-labelledby="<?=$widgetID;?>-next">
        <div class="term-dates__next" data-state-show="Holiday time">
            <h2 id="<?=$widgetID;?>-next">The next school half term starts on <?=termDatesEsc(termDatesFmtLong($stateDate));?></h2>
            <p><?=termDatesEsc(termDatesCountdown($today, $stateDate));?></p>
        </div>
    </section>
<?php } ?>

<?php if ($current) { ?>
    <section class="term-dates__section term-dates__year" aria-labelledby="<?=$widgetID;?>-current">
        <?=termDatesRenderYear($current, true, $widgetID, $widgetID . '-current');?>
    </section>
<?php } else { ?>
    <p>No school term dates have been published yet.</p>
<?php } ?>

<?php if (count($future)) { ?>
    <details class="term-dates__twister">
        <summary>Future school years &rarr;</summary>
<?php foreach ($future as $n => $y) { ?>
        <section class="term-dates__year" aria-labelledby="<?=$widgetID;?>-future-<?=$n;?>">
            <?=termDatesRenderYear($y, true, $widgetID, $widgetID . '-future-' . $n);?>
        </section>
<?php } ?>
    </details>
<?php } ?>

<?php if (count($past)) { ?>
    <details class="term-dates__twister">
        <summary>Past school years &rarr;</summary>
<?php foreach ($past as $n => $y) { ?>
        <section class="term-dates__year" aria-labelledby="<?=$widgetID;?>-past-<?=$n;?>">
            <?=termDatesRenderYear($y, false, $widgetID, $widgetID . '-past-' . $n);?>
        </section>
<?php } ?>
    </details>
<?php } ?>

    <script type="application/json" id="<?=$widgetID;?>-data"><?=json_encode(termDatesCalendarData($years), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);?></script>
</div>

<script>
(function () {
    var root = document.getElementById('<?=$widgetID;?>');
    if (!root) { return; }
    var data = {};
    try { data = JSON.parse(document.getElementById('<?=$widgetID;?>-data').textContent); } catch (e) { data = {}; }

    function compact(iso) { return iso.replace(/-/g, ''); }
    // All-day events end on the day AFTER their last day (DTEND is exclusive), so a one-day
    // event has DTEND = start + 1 day.
    function nextDay(iso) {
        var p = iso.split('-');
        return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2] + 1)).toISOString().slice(0, 10);
    }
    function esc(s) { return String(s).replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\r?\n/g, '\\n'); }

    function buildICS(year, id) {
        var stamp = new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
        var lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//School Term Dates//Jadu widget//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];
        year.events.forEach(function (ev, i) {
            lines.push('BEGIN:VEVENT',
                'UID:' + id + '-' + i + '@' + location.hostname,
                'DTSTAMP:' + stamp,
                'DTSTART;VALUE=DATE:' + compact(ev.start),
                'DTEND;VALUE=DATE:' + compact(nextDay(ev.end)),
                'SUMMARY:' + esc(ev.title + ' (' + year.label + ')'),
                'TRANSP:TRANSPARENT',
                'END:VEVENT');
        });
        lines.push('END:VCALENDAR');
        return lines.join('\r\n') + '\r\n';
    }

    root.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-calendar-year]') : null;
        if (!btn) { return; }
        var id = btn.getAttribute('data-calendar-year');
        var year = data[id];
        if (!year) { return; }
        var blob = new Blob([buildICS(year, id)], { type: 'text/calendar;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'school-term-dates-' + year.label.replace('/', '-') + '.ics';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    });
})();
</script>
