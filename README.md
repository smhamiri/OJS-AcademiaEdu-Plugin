# Academia.edu Integration — OJS 3.5 Plugin

**Version:** 2.0.0.1  
**Type:** Generic Plugin (`plugins/generic/academiaEdu`)  
**Target platform:** Open Journal Systems (OJS) 3.5  
**Product identifier:** `academiaEdu`  
**Namespace:** `APP\plugins\generic\academiaEdu`

> **In short:** Adds clean scholarly metadata (Open Graph, citation_*, Dublin Core, JSON-LD), a per-article "quick upload package" for authors who want to post their paper on Academia.edu, and an OAI-PMH status/test page for indexing services. It does **not** log in to Academia.edu, store credentials, scrape, or auto-upload anything.

---

## Table of Contents

1. [What this plugin does and does not do](#1-what-this-plugin-does-and-does-not-do)
2. [Why there are no credentials or scraping](#2-why-there-are-no-credentials-or-scraping)
3. [Requirements](#3-requirements)
4. [File structure](#4-file-structure)
5. [Installation](#5-installation)
6. [Configuration (Settings)](#6-configuration-settings)
7. [Metadata features](#7-metadata-features)
8. [Readiness rules and report](#8-readiness-rules-and-report)
9. [Quick upload package](#9-quick-upload-package)
10. [OAI-PMH](#10-oai-pmh)
11. [Security and privacy](#11-security-and-privacy)
12. [How to verify](#12-how-to-verify)
13. [Troubleshooting](#13-troubleshooting)
14. [Limitations](#14-limitations)
15. [Upgrade and uninstall](#15-upgrade-and-uninstall)
16. [Changelog](#16-changelog)
17. [Developer notes](#17-developer-notes)

---

## 1. What this plugin does and does not do

### What it does

| Feature | Description |
|---|---|
| **Metadata preparation** | Adds Open Graph, `citation_*` (Google Scholar style), Dublin Core and JSON-LD (`ScholarlyArticle`) tags to every published article page. |
| **Avoids duplicates** | Skips the corresponding tags if OJS's own Google Scholar or Dublin Core plugin is enabled. |
| **Readiness report** | Lists which required/recommended metadata is missing for each article. |
| **Quick upload package** | A panel on the article page with copy buttons, a PDF link, and `.txt`, `.bib` and `.ris` downloads. |
| **OAI-PMH support** | Shows the status, URL, repository ID and an "Identify" test for OJS's built-in OAI endpoint; adds an OAI record link to each article's head. |

### What it does not do

- It does **not log in** to Academia.edu and does **not store any username, password or cookie**.
- It does **not scrape** anything from Academia.edu.
- It does **not automatically upload articles** to Academia.edu (see section 2 below).
- It does **not guarantee** that Academia.edu will harvest your records via OAI-PMH.

---

## 2. Why there are no credentials or scraping

- We could not find any **documented public deposit API** for Academia.edu. Login automation or scraping without an authorized API risks violating the terms of use and breaks whenever their pages change.
- Keeping a user's password in the OJS database would put authors' Academia.edu accounts at risk if the database or a backup were ever leaked.
- So this plugin gives the author **ready-made data to upload from their own account**; the decision to upload, and the rights to do so, stay with the author.

> If Academia.edu releases an official API in the future (with documentation / an API key), it can be added as a separate module.

---

## 3. Requirements

- **OJS 3.5** (uses namespaced plugins, gettext `.po` locales and the `Hook` API — it will not work on OJS 3.3 or earlier).
- The PHP version your OJS 3.5 instance requires is sufficient; the plugin needs no extra PHP extensions.
- Write permission on the server (`plugins/generic/` and the cache directories).
- For the OAI part: `oai = On` in the `[oai]` section of `config.inc.php`.

---

## 4. File structure

```
academiaEdu/
├── index.php                  # returns the plugin object (mandatory in OJS 3.4+/3.5)
├── version.xml                # product = academiaEdu, type = plugins.generic
├── AcademiaEduPlugin.php      # main class: hooks, metadata, report, OAI
├── AcademiaEduSettingsForm.php# settings form (AjaxModal)
├── README.md
├── locale/
│   ├── en/locale.po           # English
│   └── bn/locale.po           # Bengali
└── templates/
    ├── article.tpl            # upload-package panel on the article page
    ├── field.tpl              # one field with a copy button (included from article.tpl)
    ├── report.tpl             # Metadata & OAI report
    └── settingsForm.tpl       # settings form
```

**Important:** The directory name must be exactly `academiaEdu`. With `academiaEdu-2.0.0.1` or any other name, the namespace will not autoload and the plugin will not appear in the plugin list.

---

## 5. Installation

### Method A: Through the web interface

1. If an older `academiaEdu*` version exists, first disable and delete it (or remove it from `plugins/generic/`).
2. Upload the zip at **Settings → Website → Plugins → Upload A New Plugin**.
3. Run **Administration → Clear Data Caches** and **Clear Template Cache**.
4. Go to **Installed Plugins → Generic Plugins** and enable **Academia.edu Integration**.

### Method B: Manual install (if upload does not work)

```bash
cd /path/to/ojs/plugins/generic
unzip academiaEdu-2.0.0.1-ojs3_5.zip      # creates the academiaEdu/ folder
cd /path/to/ojs
php tools/installPluginVersion.php plugins/generic/academiaEdu/version.xml
```

Then clear the caches (step 3 above) and enable the plugin. Make sure file owner/permissions are readable by the web server user.

### Post-install checks

- The plugin list shows the name **Academia.edu Integration** (not `##plugins.generic…##`).
- After enabling, clicking the arrow next to the plugin shows two links: **Settings** and **Metadata & OAI report**.
- A published article page shows the "Academia.edu" panel (depending on the panel visibility setting).

---

## 6. Configuration (Settings)

**Installed Plugins → Academia.edu Integration → Settings**. Settings are stored separately for each journal (in the `plugin_settings` table).

| Setting | Default | Description |
|---|---|---|
| **Panel label** | `Academia.edu Ready` | Label of the collapsible panel on the article page. |
| **Panel visibility** | Everyone | `Everyone` / `Logged-in users only` / `Nobody` (panel hidden; metadata tags are still added). |
| **Open Graph + citation meta** | On | OG tags, plus `citation_*` tags (when the Google Scholar plugin is off). |
| **JSON-LD** | On | `ScholarlyArticle` structured data. |
| **Dublin Core** | On | `DC.*` tags (when OJS's Dublin Core plugin is off). |
| **OAI record link** | On | `<link rel="alternate">` to the article's own OAI record in the page head. |
| **BibTeX** | On | `.bib` download in the panel. |
| **RIS** | On | `.ris` download in the panel. |

The plugin is turned on/off only via the checkbox in the **Installed Plugins** list; there is no separate "enable" in the settings form.

---

## 7. Metadata features

### 7.1 Open Graph

`og:type` (article), `og:title`, `og:description` (first 300 characters of the abstract), `og:url`, `og:site_name`, `article:published_time`, `article:tag` (one per keyword).

### 7.2 `citation_*` (Google Scholar style)

`citation_title`, `citation_journal_title`, `citation_publisher`, `citation_publication_date` (`YYYY/MM/DD`), `citation_volume`, `citation_issue`, `citation_issn`, `citation_language`, `citation_doi`, `citation_abstract_html_url`, `citation_pdf_url`, and per author `citation_author`, `citation_author_institution`, `citation_author_orcid`, and per keyword `citation_keywords`.

- The PDF link is built in the form `article/download/<id>/<galleyId>`.
- Only **local PDF galleys** are considered; remote-URL galleys are skipped.

### 7.3 Dublin Core

`DC.title`, `DC.creator`, `DC.description`, `DC.date`, `DC.identifier`, `DC.source`, `DC.publisher`, `DC.rights` (license URL), `DC.type`, `DC.language` (according to the publication's language), `DC.identifier.DOI`, `DC.subject`.

### 7.4 JSON-LD

As `ScholarlyArticle`: `headline`, `description`, `url`, `datePublished`, `inLanguage`, `keywords`, `license`, `isPartOf` (Periodical + ISSN), `publisher`, `author` (name, affiliation, ORCID as `sameAs`), `identifier` (DOI URL), `encoding` (PDF). Empty values are omitted.

### 7.5 Duplicate-avoidance rules

| If this plugin is enabled | This plugin skips |
|---|---|
| OJS **Google Scholar** (`googlescholarplugin`) | all `citation_*` tags |
| OJS **Dublin Core Metadata** (`dublincoremetaplugin`) | all `DC.*` tags |

Open Graph, JSON-LD and the OAI link are always added (if the corresponding setting is on).

### 7.6 Text cleaning

HTML tags are stripped from titles, abstracts, author names etc., entities are decoded and extra whitespace is collapsed. All output is HTML-escaped; JSON-LD uses `JSON_HEX_TAG`/`JSON_HEX_AMP` so that a `</script>` inside the text cannot break the page.

---

## 8. Readiness rules and report

### Required (if missing: "Metadata incomplete")

1. Title
2. At least one author
3. Abstract
4. Publication date
5. **A DOI or** a local **PDF galley** (either one)

### Recommended (if missing: only listed)

DOI, PDF galley, keywords, ORCID for at least one author, license URL, assignment to an issue.

### Report page

**Installed Plugins → Academia.edu Integration → Metadata & OAI report**

- OAI-PMH status and endpoint (see section 10).
- A table of up to **200** published articles: title (linked), status, missing required items, missing recommended items.

> "Ready" only means the minimum metadata package exists. It does not mean Academia.edu has received or accepted the article.

---

## 9. Quick upload package

On the article page (in the "Article Details" area), a collapsible panel appears.

**Fields with copy buttons:** Title · Authors · Abstract · Keywords · Citation / published-in line · DOI link · Article URL

**Downloads:**

| Button | File |
|---|---|
| Download PDF | The article's local PDF (if any) |
| Download package | `academia-upload-package.txt` — everything in one file |
| Download .bib | `article.bib` (BibTeX) |
| Download .ris | `article.ris` (RIS) |

**Author workflow:**

1. Open the article page and expand the panel.
2. Download the PDF.
3. Log in to your own Academia.edu account, use "Add new work", attach the file and paste in the copied fields.
4. Confirm which version of the file you may upload under the publisher's copyright/sharing policy.

The copy buttons use `navigator.clipboard` over HTTPS and fall back to `execCommand('copy')` otherwise. The `.bib`/`.ris`/`.txt` downloads are generated in the browser (no files are written on the server).

---

## 10. OAI-PMH

OJS itself provides an OAI-PMH endpoint: `https://<your-site>/index.php/<journal-path>/oai`. This plugin does **not** create it; it makes it easier to use.

### What the plugin adds

1. **On the report page:** the endpoint URL, `repository_id`, enabled/disabled status, and a **"Test endpoint (Identify)"** button. The button sends a `?verb=Identify` request from the browser and checks that the response contains `repositoryName`.
2. **In each article's head:**
   ```html
   <link rel="alternate" type="application/xml" title="OAI-PMH Dublin Core record"
         href=".../oai?verb=GetRecord&identifier=oai:<repository_id>:article/<id>&metadataPrefix=oai_dc">
   ```

### Required configuration (`config.inc.php`)

```ini
[oai]
oai = On
repository_id = ojs.your-domain.org
```

Do not leave `repository_id` at its default value (e.g. `ojs.pkp.sfu.ca`) — set it to your own domain. If it is empty, the per-article OAI link is not added.

### For automatic collection

Register your endpoint URL with harvesters/aggregators such as **BASE**, **CORE** and **OpenAIRE**. Each service has its own registration process; follow the instructions on its site. They then collect your records on their own at regular intervals.

> **About Academia.edu:** We found no documentation showing that Academia.edu supports OAI-PMH harvesting. So this part does not provide direct automatic upload or connection to Academia.edu.

---

## 11. Security and privacy

- No data is sent to third-party servers; the plugin makes no outside network calls of its own (the OAI test runs from the user's browser to your own site).
- No credentials, tokens or cookies are stored.
- The settings form has CSRF and POST validation; settings can be saved only through plugin management at the journal-manager level.
- All dynamic output is escaped; JSON-LD is `</script>`-safe.
- With **Panel visibility = Everyone**, readers can also see the article's citation/abstract data and the "Metadata incomplete" status. If you don't want incomplete status shown publicly, choose `Logged-in users only` or `Nobody`.

---

## 12. How to verify

1. **Page source:** On an article page, use "View Source" and check that `og:`, `citation_`, `DC.`, `application/ld+json` and `rel="alternate"` tags are present in `<head>`. If `citation_title` appears twice, check whether the Google Scholar plugin is also enabled (this plugin's tags should then be skipped).
2. **JSON-LD:** Enter the article URL in the [Schema.org Validator](https://validator.schema.org/) or Google's Rich Results Test.
3. **OAI:** Click the Identify button on the report page, or open in a browser:
   - `…/oai?verb=Identify`
   - `…/oai?verb=ListRecords&metadataPrefix=oai_dc`
   - `…/oai?verb=GetRecord&identifier=oai:<repository_id>:article/<id>&metadataPrefix=oai_dc`
4. **Report:** Edit articles until the "Missing (required)" column in the Metadata & OAI report is empty.

---

## 13. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Plugin does not appear in the list | Folder name is not `academiaEdu`; `index.php` is not returning the plugin; `version.xml` not installed in the DB; stale cache | Fix the folder name; run `php tools/installPluginVersion.php plugins/generic/academiaEdu/version.xml`; clear caches; check `error_log`/`php.log`. |
| Name shows as `##plugins.generic.academiaEdu.displayName##` | `locale/<lang>/locale.po` missing or stale cache | Check that `locale/en/locale.po` exists; clear Data Cache and Template Cache. |
| Settings or report won't open | Plugin not enabled; PHP error | Enable it first; look for errors in `php.log`. |
| No panel on the article page | Panel visibility is `Nobody`/`Logged-in only`; theme omits the `Templates::Article::Details` hook | Check Settings; in custom themes make sure `{call_hook name="Templates::Article::Details"}` is present. |
| No `citation_*` tags | OJS's Google Scholar plugin is enabled (intentional) | That plugin's tags are being used; if you disable it, this plugin will output them. |
| No `DC.*` tags | OJS's Dublin Core plugin is enabled (intentional) | Same as above. |
| Shows "Metadata incomplete" | Required metadata is missing | Use the report page to see what is missing and edit the article. |
| DOI is empty | DOI plugin off or DOI not assigned | Check Settings → Distribution → DOIs and the article's DOI assignment. |
| No PDF link | No PDF galley, label is not `PDF`, or the galley is remote-URL | Add a local PDF galley; label it `PDF`. |
| OAI test fails | `oai = Off`; web server/firewall blocking; wrong URL | Check `config.inc.php`; open the URL directly in a browser. |
| No OAI `<link>` on article pages | OAI off; `repository_id` empty; setting off | Check the `[oai]` config and the settings. |
| Article page broken / template error | Smarty template error | Clear the Template Cache; check the last lines of `php.log`. |

If the problem persists, send the relevant lines from `php.log`/`error_log`.

---

## 14. Limitations

- No automatic upload/login to Academia.edu — the author uploads by hand.
- The report shows only the first 200 published articles (no pagination).
- PDF detection depends on the galley label (`PDF`) or OJS's PDF-galley check; unusual galleys may not be detected.
- The OAI identifier format is assumed to be `oai:<repository_id>:article/<submissionId>`; if you have a custom OAI configuration, verify the link.
- The article-page panel styling is inline and basic; you may need CSS to match your theme.
- For multilingual metadata, localized values are taken according to the publication's primary language / the current locale.
- This version is written against the OJS 3.5 codebase; behavior may differ with your instance's customizations or theme — test on staging before deploying to production.

---

## 15. Upgrade and uninstall

### Upgrade

1. Note down or screenshot your settings (they are stored in `plugin_settings` and normally survive upgrades).
2. Upload the new zip, or replace the folder and run `installPluginVersion.php`.
3. Clear caches and confirm the plugin is still enabled.

### Uninstall

1. Disable the plugin in **Installed Plugins**.
2. **Delete** it, or remove the `plugins/generic/academiaEdu/` folder.
3. Clear caches.

Leftover `plugin_settings` rows (`plugin_name = 'academiaeduplugin'`) may remain in the database; they can be safely deleted.

---

## 16. Changelog

### 2.0.0.1
- Removed the "Academia.edu profile" button from the article-page panel and the **Academia.edu profile URL** setting (a previously saved value stays in `plugin_settings` but is no longer used and can be deleted).
- Version bumped to 2.0.0.1.

### 1.3.0.0
- **Metadata:** avoids duplicate `citation_*`/DC tags; volume, issue, ISSN, publisher, ORCID, affiliation, language; `YYYY/MM/DD` date; `/download/` PDF URL; richer, `</script>`-safe JSON-LD; profile URL validation.
- **Readiness report:** list of required/recommended metadata.
- **Upload package:** copy button for every field, `.txt` package, `.bib`, `.ris`, PDF download; panel visibility setting.
- **OAI-PMH:** endpoint status, Identify test, per-article OAI record link.
- **Bug fixes:** author Collection wrongly cast with `(array)`; JS without `{literal}` in `article.tpl`; DOI retrieval updated for OJS 3.5.

### 1.2.1.0
- `index.php` now returns the plugin object (the main reason the plugin was not showing in the list).
- Folder name `academiaEdu`.
- `locale.xml` → gettext `locale.po`.
- Removed the invalid `settings.xml` and the dangerous `enabled` checkbox.
- Dropped `$this->import()`; settings via AjaxModal + `JSONMessage`.

### 1.2.0.0
- Initial version (OG/citation/DC/JSON-LD, share text, BibTeX/RIS).

---

## 17. Developer notes

- **Hooks:** `ArticleHandler::view` (prepares metadata and data), `Templates::Article::Details` (renders the panel).
- **Manage verbs:** `settings` (settings form), `report` (report). Both use `AjaxModal` + `JSONMessage`.
- **Setting keys:** `buttonLabel`, `panelVisibility`, `showMetaTags`, `showJsonLd`, `showDublinCore`, `addOaiLink`, `showBibtex`, `showRis` (flags are the strings `'1'`/`'0'`; a missing value means on).
- **Plugin name (registry):** `academiaeduplugin`; external dependencies are detected by the names `googlescholarplugin` and `dublincoremetaplugin`.
- **Locale keys:** `plugins.generic.academiaEdu.*` (`settings.*`, `missing.*`, `article.*`, `report.*`, `action.*`). To add a language, copy `locale/<code>/locale.po` and translate it.
- **Smarty note:** JS beginning with `{` must always be placed inside `{literal}…{/literal}`.
- **Code style:** PHP 8 idioms (first-class callables, `str_contains`/`str_ends_with`, typed properties).

---

## License

No license has been specified for this version. Before publishing or distributing, choose a license that fits your project's policy (for example GPL v3, which is compatible with OJS) and add it here.

