# Zyppy Page

Turns Contao pages into content records, so that *other* pages can render
previews of them.

The bundle adds a set of fields to `tl_page` — an image, an image gallery, an
external image URL, a teaser, a list of related pages, and four free-form rich
text slots — and ships two front end modules plus a family of insert tags that
read them.

## Requirements

- PHP 8.1 or newer
- Contao 5.3 or newer, with no upper bound. The Zyppy Suite supports the LTS
  releases and is tested against the current non-LTS release as well: the
  test suite passes on core-bundle 5.3.0, 5.7.13 and 6.0.2 (last run
  2026-10-07).
- jQuery on the page layout, **only** if you turn the preview slider on (see
  below). Nothing else in the bundle needs it.

## Installation

```bash
composer require asconsulting/contao-zyppy-page:^5.0
```

Then run the database migration. Contao Manager does this on install and on
update; on the command line it is `vendor/bin/contao-console contao:migrate`.
It adds the page fields to `tl_page` and the module settings to `tl_module`.

## Usage

### Front end modules

| Module | Description |
|---|---|
| `pagepreview` | Renders a page subtree as a list of teasers (image + teaser text + link), driven by the navigation tree. Uses the `nav_pagepreview_header` and `nav_pagepreview_body` templates. |
| `related_pages` | Renders the pages hand-picked in a page's **Related pages** field. |

Both are configured under **Themes → Front end modules**. Each has an
**Image size** setting that selects which `tl_image_size` is used to render
`page_image`.

### The preview slider

`pagepreview` renders its subtree twice — a compact **header** index and a
scrolling **body** teaser strip — so that a slider can keep the two in step.
The two lists are matched item for item by the `rel` attribute on each `<li>`,
which carries the page alias.

The slider is **off by default**. Set the module's **Slider items** field
(`previewSliderItems`) to the number of teasers to show at once; `0` disables it,
and the module then emits neither the slider's CSS nor its JavaScript.

**The slider needs jQuery, and this bundle does not load it.** Contao ships
jQuery but only puts it on the page when the page layout says so, and nothing in
PHP can reliably tell whether a layout field or theme template has already loaded
a copy — loading a second one would break every plugin bound to the first. So:

> **Themes → Page layouts → JavaScript → tick "Add jQuery".**

If it is missing, the slider does nothing and says so in the browser console
naming that setting. Nothing else on the page is affected.

The bundle ships a deliberately plain `page_slider.css` alongside the script.
It is not decoration: the JavaScript only toggles the `active_preview` /
`inactive_preview` classes, so without a rule that hides an inactive item there
is nothing to see, and the slider sizes itself by dividing the list's width by
the width of its first item, which needs the items laid out in a row. Override
any of it from your theme stylesheet.

The arrows are inline SVG chevrons carrying the `arrow_left` / `arrow_right`
classes. Earlier versions used Font Awesome class names, which rendered as two
invisible empty anchors on any site that did not already load Font Awesome.

### Page fields

Added to the **Meta** legend of the `regular` and `forward` page palettes.

| Field | Purpose |
|---|---|
| `page_image` | A single image from the file manager. |
| `page_image_overwrite_meta` | Override the file's own alt/title metadata for this page. |
| `page_image_alt`, `page_image_title` | The override values (shown when the box above is ticked). |
| `page_images` | Multiple images, addressed by index from the insert tag. |
| `page_image_url` | An external image URL, as an alternative to a file. |
| `page_teaser` | Short teaser text used by the preview modules. |
| `page_related` | Pages to show in the `related_pages` module. |
| `rich_text_1` … `rich_text_4` | Four free-form rich text slots. Not rendered directly by this bundle — they exist to be read by insert tags, templates, and other code. See [The four rich text slots](#the-four-rich-text-slots). |

### Insert tags

All tags are also available under the alias `page_preview`.

```
{{zyppy_page::page_teaser}}
{{zyppy_page::page_teaser::<page id or alias>}}

{{zyppy_page::page_image}}
{{zyppy_page::page_image::<page id or alias>}}
{{zyppy_page::page_image::<page id or alias>::<image size id>}}

{{zyppy_page::page_images:<index>}}
{{zyppy_page::page_images:<index>::<page id or alias>}}
{{zyppy_page::page_images:<index>::<page id or alias>::<image size id>}}

{{zyppy_page::page_image_url}}
{{zyppy_page::page_image_url::<page id or alias>}}

{{zyppy_page::rich_text}}
{{zyppy_page::rich_text_1}} … {{zyppy_page::rich_text_4}}
{{zyppy_page::rich_text_2::<page id or alias>}}
```

Notes on the syntax:

- Omit the page parameter to read the **current** page.
- For `page_images`, the index is attached to the sub-command with a **single**
  colon (`page_images:2`); every other parameter is separated by `::`.
- `{{zyppy_page::rich_text}}` with no number is an alias for `rich_text_1`.
- Short aliases exist for the image and teaser tags: `teaser`, `image`,
  `images`, `image_url`.
- An unknown sub-command, or a page id that does not resolve to a published
  page, renders as an empty string rather than raising an error.

The rich text slots are also readable straight off the model in a template or
from other code:

```php
$objPage->rich_text_3
```

### The four rich text slots

`rich_text_1` … `rich_text_4` are a general-purpose per-page HTML store. They
are **not** page content in the `tl_content` sense and this bundle never renders
them: they exist so that a template, another bundle, or *another page* can read
a page's copy without walking that page's article and content-element tree.

They are the reason the insert tags above are public API.

#### What goes in which slot

**Nothing in the schema says.** The slots are numbered rather than named, on
purpose — four generic slots are cheaper than guessing four names that would be
wrong on the next site. What each slot means is therefore a **per-site
convention**, and it belongs in that site's own documentation, not here.

The one asymmetry that is fixed by the code, and the one reason to prefer a
particular slot: `{{zyppy_page::rich_text}}` with no number resolves
`rich_text_1`. Slot 1 is the only slot with a short form, so it is the natural
home for whatever the site writes most often.

Four is fixed. A fifth slot, a rename, or a move to a child table would all be
schema changes and none of them is planned.

#### Reading a slot

```
{{zyppy_page::rich_text_2}}                    the current page
{{zyppy_page::rich_text_2::<page id or alias>}} another page
{{zyppy_page::rich_text}}                      slot 1, short form
```

```php
$objPage->rich_text_3   // a PageModel, in a template or in your own code
```

An unknown slot number does **not** fall through to a property read:
`{{zyppy_page::rich_text_5}}` renders as an empty string even on a `tl_page`
that really does have a `rich_text_5` column.

**Insert tags stored inside a slot are returned verbatim.** A back end editor
can save `{{link_url::17}}` into a slot — the field offers Contao's insert-tag
help wizard — but `{{zyppy_page::rich_text_1}}` does not resolve it, because
Contao's parser never re-scans the value a resolver returned. If you want nested
tags resolved, resolve them where you print the value, the same way core does
for `tl_content.text`:

```twig
{{ page.rich_text_3|insert_tag_raw }}
```

#### Escaping and the trust boundary

This is deliberate, not incidental, so it is written down.

| Insert tag | `OutputType` | Escaped by Contao? |
|---|---|---|
| `rich_text`, `rich_text_1` … `rich_text_4` | `html` | **no** |
| `page_image`, `page_images:<n>` | `html` | **no** |
| `page_teaser` | `text` | yes (`StringUtil::specialchars()`) |
| `page_image_url` | `text` | yes (`StringUtil::specialchars()`) |

`OutputType::html` is the only value that reaches the page unescaped;
`InsertTagParser` runs everything else through `StringUtil::specialchars()`.
Read in the core source on all three supported legs: `InsertTagParser.php:132`
on 5.3.0, `:155` on 5.7.13, and — refactored into `toOutputType()` — `:606-628`
on 6.0.2. One difference matters there: on 6.0 `specialchars()` double-encodes
by default, so the two `text` tags decode the stored entities (`&#60;`, `&amp;`)
before handing over and let the parser encode exactly once. What renders is the
same on every version.

The rich text slots use it on purpose. Their DCA fields are
`'inputType' => 'textarea'` with `'rte' => 'tinyMCE'`, from which Contao derives
`allowHtml`, so every save goes through `Input::postHtml()` and is sanitised
against `allowedTags` / `allowedAttributes` — the same `rte → allowHtml →
postHtml → sanitizeHtml` path core puts `tl_content.text` through. The stored
value is already sanitised HTML; escaping it again would render visible
`&lt;p&gt;` and break the feature.

The write side is back end only. Each slot is `'exclude' => true`, so a
non-admin back end user needs the explicit `tl_page::rich_text_N` field
permission, and nothing in this bundle writes `tl_page` from the front end.

The limit of that guarantee, stated plainly: the sanitiser lives in the DCA
widget, so **anything that writes these columns outside the back end form — a
SQL import, a migration, a third-party API — writes unsanitised HTML that this
bundle will emit unescaped.** That is the same exposure `tl_content.text` has,
and it is the reason to keep imports out of these columns unless you trust the
source.

`page_teaser` is a plain textarea with no `rte`, so a tag has never been able to
reach the column through the DCA (Contao stores `<` as `&#60;`). It is text, and
it is treated as text everywhere: the module templates escape it, and since the
2026-10 review the insert tag is `OutputType::text` too, so a teaser is safe
inside an attribute as well as in running text.

### Templates

| Template | Used by |
|---|---|
| `mod_pagepreview` | `pagepreview` module wrapper |
| `nav_pagepreview_header` | Header row of the preview list |
| `nav_pagepreview_body` | Body row of the preview list |
| `mod_related_pages` | `related_pages` module |

All four are Twig templates (`.html.twig`) with flat legacy identifiers, so
they appear in the module's template dropdowns exactly as before. Override them
the way you override any Contao template, under the same names.

#### Both module templates are excluded from the search index

`mod_pagepreview` and `mod_related_pages` wrap their whole output in
`<!-- indexer::stop --> … <!-- indexer::continue -->`, which makes Contao's
indexer drop that region from `tl_search` (`Contao\Search::indexPage()`). This
is intentional and should stay.

Both modules render **other pages'** teasers. Without the wrapper, a listing
page would win the search hit for a word that only ever appears in the teaser of
a page it links to, and the visitor would be sent to the index instead of to the
page they searched for — the page itself is indexed on its own, so the correct
result is already there. That is the same reason core wraps `mod_navigation`,
and `pagepreview` is a `ModuleNavigation` subclass.

It does not cost the search any teaser text. `asconsulting/contao-zyppy-search`
reads `page_teaser` and `page_image` **straight off the `tl_page` row** when it
builds a result, not out of the indexed body, so the wrapper changes which page
matches a query and never what a result displays.

The one real consequence: teaser text that appears **nowhere** on the page it
belongs to is not searchable at all. If a site needs that, the fix is to render
the teaser on its own page — not to unwrap a listing module.

## Migration from legacy version

This package replaces the legacy package `asconsulting/zyppy_page` (namespace
`ZyppyPage\`). The old name does not resolve any more.

The four legacy Zyppy packages — page, classes, popup and search — have to be
upgraded **together, in one `composer update`**: old and new packages register
the same DCA fields and module types, so a site that briefly has both installed
double-registers. The walkthrough for the whole suite, including the order of
operations and a verification checklist, is [UPGRADING.md](UPGRADING.md) in
this repository. The part specific to this package:

- **`tl_page` is unchanged.** All twelve columns keep their names and types; no
  action.
- **`tl_module` loses `bodyNumberOfActive` and gains `previewSliderItems`.**
  The module type keys `pagepreview` and `related_pages` are unchanged, so
  existing module rows keep resolving.
- **The one thing that bites: carry the slider setting over by hand, before
  the DROP.** `bodyNumberOfActive` defaulted to 4; `previewSliderItems`
  defaults to 0, and 0 means *slider disabled*. The package ships no migration
  for it, so on upgrade every existing `pagepreview` module silently turns into
  a plain list, which is easy to miss on a smoke test. Before accepting the
  `contao:migrate` DROP of `bodyNumberOfActive`, copy the values across —
  conceptually `UPDATE tl_module SET previewSliderItems = bodyNumberOfActive
  WHERE type = 'pagepreview'`. Once the DROP is accepted the old value is gone.
  If you would rather not touch SQL, note each module's value in the back end
  first; there are usually only a handful.
- **The slider now needs jQuery from the page layout.** The bundle no longer
  loads its own copy; see **The preview slider** above.
- **Templates:** `mod_pagepreview`, `mod_related_pages`,
  `nav_pagepreview_body` and `nav_pagepreview_header` moved from `.html5` to
  `.html.twig` under the same names. A copy of any of them in the site's
  `templates/` folder keeps its `.html5` name and keeps winning on Contao 5 —
  so the site silently renders the old markup — while on Contao 6 the `.html5`
  path is gone and it breaks. Port each override to Twig and delete the
  `.html5`.

## Development

```
composer install
vendor/bin/phpunit --no-coverage
```

## Licence

Licensed under the GNU Affero General Public License v3.0 only (AGPL-3.0-only). See [LICENSE](LICENSE). Copyright Andrew Stevens.
