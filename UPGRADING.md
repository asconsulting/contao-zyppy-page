# Zyppy Suite — upgrading a site from the legacy packages to 5.0.0

This guide covers **all four** Zyppy Suite packages, because upgrading them is a
single operation — see §0. It lives in `contao-zyppy-page` only; the other three
repos do not carry a copy.

Every column, name and default below was read from the legacy package source
and the 5.0.0 release of each package (https://github.com/asconsulting/contao-zyppy-page
and its siblings).

**This sequence has not yet been exercised against a real legacy → 5.0.0 site
upgrade.** The findings below are verified against the code at both refs, and
the individual checks that were run live are called out where they apply — but
the upgrade as a whole has not been walked end to end on a production site.
Treat the ordering constraints in §1 and §2 as the parts to be most careful
with.

---

## 0. Applies to all four packages

**Every package was renamed, and so was its namespace.** These are not aliases;
the old names do not resolve.

| Package | legacy composer name | legacy namespace | 5.0.0 composer name | 5.0.0 namespace |
|---|---|---|---|---|
| page | `asconsulting/zyppy_page` | `ZyppyPage\` | `asconsulting/contao-zyppy-page` | `Zyppy\Page\` |
| classes | `asconsulting/zyppy_class` | `ZyppyClass\` | `asconsulting/contao-zyppy-classes` | `Zyppy\Classes\` |
| popup | `asconsulting/zyppy_popup` | `ZyppyPopup\` | `asconsulting/contao-zyppy-popup` | `Zyppy\Popup\` |
| search | `asconsulting/zyppy_search` | `ZyppySearch\` | `asconsulting/contao-zyppy-search` | `Zyppy\Search\` |

`"type": "contao-bundle"` is unchanged in all four. The core-bundle constraint
moves from `^5.3` to `>=5.3` (5.3 floor kept, upper ceiling deliberately
removed).

1. **Swap the names in one operation.** Edit `composer.json` — remove all four
   `asconsulting/zyppy_*` requirements, add the four `asconsulting/contao-zyppy-*`
   at `^5.0` — then run a single
   `composer update` for the whole set. Do not install the new packages
   alongside the old ones: both register the same DCA fields and the same
   module type keys, and a site that briefly has both will double-register.
2. **Skeleton installs only:** `config/bundles.php` names each bundle by FQCN.
   `ZyppyPage\ZyppyPageBundle::class` and friends must be hand-edited to the
   5.0.0 class names - `Zyppy\Page\PageBundle::class` for this package (the
   suite prefix dropped off the class name along with the namespace change),
   and likewise for the other three. Managed Edition installs discover
   the bundles through the ContaoManager plugin and need no edit.
3. **Project-level template overrides are the biggest silent hazard.** Every
   package's templates moved from `.html5` to `.html.twig`. A copy sitting in
   the site's `templates/` folder keeps its `.html5` name, and Contao 5's Twig
   surrogate guard makes the **higher-priority `.html5` win** — so on 5.x the
   site keeps rendering the old markup and nothing looks wrong, while on
   Contao 6 the `.html5` path is gone entirely and it breaks. Audit
   `templates/` for the names in each section below, port each to Twig, and
   delete the `.html5`.
4. **Migrate, then verify the end state — not the exit code.** `contao:migrate`
   reports success when a migration never ran at all. After migrating, assert
   the actual result (row counts, layout sections) as listed per package.
5. **Run `contao:migrate --dry-run` first and read the DROP list.** Two columns
   are being retired (`bodyNumberOfActive`, `ajaxTpl`/`disableAjax`), and one of
   them still holds data you need. See §1.

Order for the whole job: dry-run migrate → carry over page's slider values →
run migrate → verify → audit templates.

---

## 1. contao-zyppy-page — the one that needs manual data carry-over

**Columns**

- `tl_page`: **unchanged**, all 12 columns (`page_image`, `page_image_alt`,
  `page_image_overwrite_meta`, `page_image_title`, `page_image_url`,
  `page_images`, `page_related`, `page_teaser`, `rich_text_1..4`). No action.
- `tl_module`: kept `navBodyTpl`, `navHeaderTpl`, `navSubitemLimit`,
  `navSubitemTpl`, `preview_image_size`. **Removed `bodyNumberOfActive`,
  added `previewSliderItems`.**

**Module type keys are unchanged** — `pagepreview` and `related_pages`. Existing
`tl_module` rows keep resolving.

**The one thing that bites.** `bodyNumberOfActive` defaulted to **4**;
`previewSliderItems` defaults to **0**, and `0` means *slider disabled*. The
package ships **no migration**. So on upgrade, every existing `pagepreview`
module gets a fresh `previewSliderItems = 0` column and **its slider silently
turns off** — the page still renders, just as a plain list, which is easy to
miss on a smoke test.

Manual step, and it is order-sensitive: **before** accepting the
`contao:migrate` DROP of `bodyNumberOfActive`, copy the values across —
conceptually `UPDATE tl_module SET previewSliderItems = bodyNumberOfActive
WHERE type = 'pagepreview'`. Once the DROP is accepted the old value is gone
and there is nothing left to read. If you would rather not touch SQL, note each
module's value from the backend first; there are usually only a handful.

Also worth knowing: the slider needs jQuery, and the **page layout** must
supply it — the bundle deliberately does not load its own copy.

**Templates.** `mod_pagepreview`, `mod_related_pages`, `nav_pagepreview_body`,
`nav_pagepreview_header` — all four ported `.html5` → `.html.twig`, same four
names, nothing added or removed. Page deliberately has **no `.twig-root`
marker**, so the templates keep their flat legacy identifiers and continue to
appear in the DCA template dropdowns exactly as before. A site override of any
of the four must be ported to Twig.

---

## 2. contao-zyppy-classes — largest refactor, smallest upgrade

**Element data: byte-identical, zero migration.** This is the headline finding.
Legacy declared `exclusiveClass`, `commonClasses` and `globalCommonClasses` as
DCA literals on six tables (`tl_article`, `tl_content`, `tl_form`,
`tl_form_field`, `tl_module`, `tl_page`). 5.0.0 registers them from PHP
(`ClassFields::register()`) instead — which is why a naive DCA-literal diff
reports them "removed" — but the registration declares **the same three names,
on the same six tables, with the same SQL**:

- `exclusiveClass` → `string(64) default ''`
- `commonClasses` → `blob, notnull false`
- `globalCommonClasses` → `blob, notnull false`

Verified field-by-field on all six tables. **Nothing on the element side moves.
No data migration, no schema change, no re-picking classes.**

**What actually moved is the vocabulary.** Legacy `tl_settings` carried 26
fields: the 13 vocabularies (`articleClasses`, `articleCommonClasses`,
`contentClasses`, `contentCommonClasses`, `formClasses`, `formCommonClasses`,
`formFieldClasses`, `formFieldCommonClasses`, `moduleClasses`,
`moduleCommonClasses`, `pageClasses`, `pageCommonClasses`,
`globalCommonClasses`) **and** the 13 `*Required` switches. 5.0.0's
`tl_settings` keeps **only the 13 `*Required` switches**; the vocabularies move
into a real table, `tl_zyppy_class`, with a proper backend module.

**Automatic:** `ImportSettingsVocabularyMigration` reads the 13 keys out of
`system/config/localconfig.php` and writes them into `tl_zyppy_class`. It runs
on `contao:migrate`, and it is idempotent.

**The order-sensitive part.** `localconfig.php` is the migration's **only** data
source, and `shouldRun()` returns false as soon as `tl_zyppy_class` holds a
single row. Two ways to lose the vocabulary permanently:

- hand-cleaning the old keys out of `localconfig.php` before migrating — there
  is then nothing to import;
- adding even one class token in the new backend module before migrating — the
  table is no longer empty, the migration declines to run, and the rest of the
  vocabulary never arrives.

So: **migrate first, touch nothing first.** After migrating, the old keys in
`localconfig.php` are inert (nothing reads them any more) and can be removed at
leisure.

**Verify, don't trust the exit code:** check that `tl_zyppy_class` holds roughly
as many rows as you had tokens, and that all six scopes still show their class
fields under the `class_legend` in the backend.

**Templates:** none, at either ref. Nothing to audit.

**Residual-data note.** Records holding the literal `0` token in
`commonClasses` — a leftover of the old placeholder behaviour — render as
`class="0 …"`. The count was zero on the testbed, but that was a fixture-only
sample and is **not** evidence that a real site is unaffected. Worth a quick
look at real `commonClasses` values before and after.

---

## 3. contao-zyppy-popup — new field, safe default

**Columns.** Both `tl_article` and `tl_module` keep every popup column they had
(`popup`, `popupAccept`, `popupAddClose`, `popupClass`, `popupDelay`,
`popupFadeDuration`, `popupRejectUrl`, `popupReshowDelay`, `popupScrollTrigger`,
`popupTrigger`, `popupUuid`, plus `popupClear` on `tl_module`). Exactly one
column is added on each: **`popupConsentMode`**.

**It is safe by default.** `popupConsentMode` is
`varchar(16) NOT NULL default 'decorative'`, with options `decorative` and
`enforced`, and it only surfaces in the subpalette when `popupAccept` is on.
Existing popups therefore land on `decorative` and **keep behaving exactly as
they do today**; `enforced` (which actually gates content on consent) is opt-in,
per popup, whenever you want it. No data migration.

**Automatic:** `AddPopupLayoutSectionMigration` adds the Zyppy Popup layout
section to every page layout on `contao:migrate`.
**Verify:** confirm the section is present on every `tl_layout` row, not just
the one you looked at.

**The one thing that bites.** Legacy shipped
`contao/templates/modules/mod_article.html5` — an override of **Contao's own**
article template. 5.0.0 **drops it entirely** (the popup wrapper is applied
through listeners now). If a site copied that file into project `templates/`, it
is still there, still overriding core's `mod_article` for *every* article on the
site, and it references popup internals that no longer exist. Inspect and
remove `templates/mod_article.html5`.

The other template, `fe_popup_wrapper`, keeps its name and ports `.html5` →
`.html.twig`; a site override needs porting.

**Cross-repo loose end (not blocking):** ownership of `popupClear` is still
undecided between popup and search. It reads as a popup field on `tl_module`
but only means anything to the search module. Left as-is in 5.0.0.

---

## 4. contao-zyppy-search — two dead columns, one template question

**Columns.** `tl_page` unchanged (1 column, `zyppy_news`). `tl_module` keeps
`formatNewsTeaser`, `formatPageDescription`, `formatPageTeaser`,
`newsTeaserLimit`, `pageDescriptionLimit`, `pageTeaserLimit`, and **removes
`ajaxTpl` and `disableAjax`**.

**Both removals are inert.** The legacy DCA itself documents them as "never read
by any code" — they were dropdowns that configured nothing. Dropping the columns
loses no behaviour and needs no carry-over; just accept the DROP when
`contao:migrate` offers it.

**Module type key is unchanged** — `zyppy_search`. Existing `tl_module` rows keep
resolving, which was confirmed live: pre-existing `zyppy_search` rows still
render after upgrade, and there is no `tl_module` migration to run.

**Templates.** `mod_search_zyppy` and `search_zyppy` port `.html5` →
`.html.twig` under the same names. Two are **new**: `search_zyppy_results`
(the results partial the AJAX path renders) and `zyppy_search`.

**The one thing that bites.** Search is the only package in the suite that
carries a **`.twig-root` marker**, so its templates register under *prefixed*
`category/name` identifiers rather than flat basenames. That is required for the
fragment controller, but it means `Controller::getTemplateGroup()` — which
throws on any identifier containing `/` — cannot list them. The practical
upgrade check: if the site sets a **`customTpl`** on a search module, confirm it
still resolves after upgrade before calling the site done. This is a known open
question rather than a known breakage; it needs one look on the real site, not a
code change.

Transport also changed — the AJAX path is a JSON route now (`fetch`, with a rate
limiter and in-flight request abort) rather than the legacy inline handler. That
is behaviour, not data: nothing to migrate, but worth exercising the live search
box once after upgrade.

---

## 5. Verification checklist after upgrade

1. `composer show` lists all four `asconsulting/contao-zyppy-*` at `5.0.x` and
   **no** `asconsulting/zyppy_*`.
2. `contao:migrate --dry-run` — read the DROP list before running for real.
3. Page slider values carried over **before** the DROP; `previewSliderItems`
   non-zero on every module that used to slide.
4. `tl_zyppy_class` populated; the six scopes still show `class_legend`; the 13
   `*Required` switches still in Settings.
5. Popup layout section present on **every** `tl_layout` row; existing popups
   read `popupConsentMode = decorative`.
6. `templates/` contains no leftover `.html5` from any of the four — in
   particular `mod_article.html5`.
7. Front end: a `pagepreview` slider, a popup (including the accept flow), and a
   live search query each exercised once.
