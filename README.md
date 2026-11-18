# School Term Dates: a Jadu widget and Drupal content type

A widget that shows school term and half-term dates to website visitors, with a countdown to the next holiday or the next return to school. Council and school staff maintain the dates in a simple admin form.

The Jadu widget uses Jadu's standard widget pair, a `settings.php` (the admin form) and a `front-end.php` (what the public sees). It has no database tables, no server-side storage code and no external dependencies.

## What it does

**For website visitors (`front-end.php`)**

- Shows a banner counting down to the next school holiday (when it is term time) or to the next return to school (when it is holiday time). The countdown is in days up to 14 days, then in rounded weeks.
- Shows the current school year as three cards (Autumn, Spring, Summer). Each card lists **Term A**, the **half-term** break, and **Term B**.
- Shows future and past school years, tucked away in expandable sections (native `<details>`).
- Has an **Add to calendar** button for the current and future years. It builds an `.ics` file in the browser with, for each term, a one-day "term starts" event, a one-day "term ends" event and a multi-day half-term event. All events are marked transparent, so they do not block the user's calendar.
- Moves on to the next school year automatically on the day after the current year's last day of term, as long as that year's dates have been entered.

**For CMS administrators (`settings.php`)**

- Add a school year with 12 dates: Term A and Term B start and end, for each of the three terms.
- Edit or delete any existing year.
- Validation with errors shown beside each field: all dates required, valid dates, Term A before the half-term break before Term B, terms in order, and no overlap with another school year.

A school year's own start and end dates are **derived**, not entered: the year runs from the start of Autumn Term A to the end of Summer Term B.

## Files

| File | Purpose |
| --- | --- |
| `settings.php` | Admin form and list of existing years. Writes all data to a hidden `#yearsjson` field. |
| `front-end.php` | Public display. Re-validates the JSON, works out the current year and term/holiday state, and renders everything. |
| `template/` | A bare-bones, reusable pair of `settings.php` and `front-end.php` for starting other Jadu Homepage widgets. |
| `LICENSE` | MIT licence. |

## Installation

1. In Jadu Central, create a new Homepage widget.
2. Use the contents of `settings.php` as the widget's admin settings form.
3. Use the contents of `front-end.php` as the widget's front-end code.
4. Add the widget to a Homepage, open its settings and add the school years.

(Exact menu names vary between Jadu versions, so follow your own Jadu documentation for registering a widget.)

## How the pair works

Jadu substitutes values from the admin form into the front-end file: a field with `id="yearsjson"` in `settings.php` becomes the placeholder `%YEARSJSON%` in `front-end.php`. All school years are stored as JSON in that single hidden field, so any number of years can be held without extra fields.

```json
[
  {
    "id": "y1700000000000",
    "terms": {
      "autumn": { "a": { "start": "2026-09-02", "end": "2026-10-23" },
                  "b": { "start": "2026-11-02", "end": "2026-12-18" } },
      "spring": { "a": { "start": "2027-01-05", "end": "2027-02-12" },
                  "b": { "start": "2027-02-22", "end": "2027-03-26" } },
      "summer": { "a": { "start": "2027-04-12", "end": "2027-05-28" },
                  "b": { "start": "2027-06-07", "end": "2027-07-21" } }
    }
  }
]
```

The half-term break shown to visitors is the gap between the end of Term A and the start of Term B.

### Term time and holiday time

- **Term time**: today falls within any Term A or Term B period. The banner shows when the next holiday starts.
- **Holiday time**: any other day, including half terms and the summer holiday. The banner shows when the next Term A or Term B starts.

## Adapting for another country or term pattern

Each file begins with a comment block explaining the manual changes involved. In short:

- **Number and names of terms**: edit `TERMS` in `settings.php` and `$termKeys` in `front-end.php`. Four-term years, semesters, trimesters and quarters are just a different list.
- **Parts of a term**: this widget assumes two parts per term with one break between them. Other patterns need changes to the validation, break calculation, rendering and calendar events.
- **Time zone**: `Europe/London` is set in `termDatesParse()` and where `$today` is created in `front-end.php`.
- **School year label**: `2026/27`, or `2026` for years inside one calendar year (for example southern-hemisphere years).
- **Wording, date format, layout columns and the countdown threshold** are all plain strings or CSS values you can change.

`settings.php` and `front-end.php` must always agree on the JSON shape.

## Security and accessibility notes

- The stored JSON is treated as untrusted. `front-end.php` re-parses and re-validates every date, drops invalid years, and escapes all output.
- All DOM updates in the admin form use `textContent`, never `innerHTML`, for user data.
- Every form control has a visible label, required fields are marked and use `required`, help text is linked with `aria-describedby`, and errors appear beside the field and in a live summary.
- The admin form is not a `<form>` element, because the Jadu widget builder wraps the file in its own form.
- Layout is mobile first, using rem units and CSS variables, with one breakpoint at 48em (768px).
- Native `<details>` and `<summary>` elements provide the expandable sections, so they work without JavaScript and with a keyboard.

## Limitations

- The "Add to calendar" file is generated in the browser, so it does not work without JavaScript.
- Dates apply to a single set of term dates. Staggered or per-school dates would need a different data model.
- No automated tests are included. Check any change against a range of dates, including the day a term starts or ends and the first day after a school year finishes.

## Licence

Released under the [MIT Licence](LICENSE).
