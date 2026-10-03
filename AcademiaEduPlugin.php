<?php

namespace APP\plugins\generic\academiaEdu;

use APP\facades\Repo;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;

class AcademiaEduPlugin extends GenericPlugin
{
    private const REPORT_LIMIT = 200;

    public function register($category, $path, $mainContextId = null)
    {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled($mainContextId)) {
                Hook::add('ArticleHandler::view', $this->articleView(...));
                Hook::add('Templates::Article::Details', $this->displayArticle(...));
            }
            return true;
        }
        return false;
    }

    public function getDisplayName() { return __('plugins.generic.academiaEdu.displayName'); }
    public function getDescription() { return __('plugins.generic.academiaEdu.description'); }

    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }
        $router = $request->getRouter();
        $url = fn (string $verb) => $router->url($request, null, null, 'manage', null, [
            'verb' => $verb,
            'plugin' => $this->getName(),
            'category' => 'generic',
        ]);
        array_unshift(
            $actions,
            new LinkAction('settings', new AjaxModal($url('settings'), $this->getDisplayName()), __('manager.plugins.settings'), null),
            new LinkAction('report', new AjaxModal($url('report'), $this->getDisplayName()), __('plugins.generic.academiaEdu.action.report'), null)
        );
        return $actions;
    }

    public function manage($args, $request)
    {
        $context = $request->getContext();
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $form = new AcademiaEduSettingsForm($this, $context->getId());
                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));

            case 'report':
                return new JSONMessage(true, $this->renderReport($request));
        }
        return parent::manage($args, $request);
    }

    /* ------------------------------------------------------------------ */
    /* Hooks                                                               */
    /* ------------------------------------------------------------------ */

    public function articleView($hookName, $args): bool
    {
        $request = $args[0] ?? null;
        $issue = $args[1] ?? null;
        $article = $args[2] ?? null;
        $publication = $args[3] ?? null;
        if (!$request || !$article || !$publication) return Hook::CONTINUE;
        $context = $request->getContext();
        if (!$context || !$this->getEnabled($context->getId())) return Hook::CONTINUE;
        $contextId = $context->getId();

        $data = $this->buildData($request, $article, $publication, $issue);
        $templateMgr = TemplateManager::getManager($request);

        // Visible panel (metadata below is always added regardless of this setting).
        $visibility = $this->getSetting($contextId, 'panelVisibility') ?: 'public';
        if ($visibility === 'public' || ($visibility === 'loggedIn' && $request->getUser())) {
            $templateMgr->assign('academiaEdu', $data);
        }

        if ($this->flag($contextId, 'showMetaTags')) $this->addMetaTags($templateMgr, $data, $contextId);
        if ($this->flag($contextId, 'showJsonLd')) $this->addJsonLd($templateMgr, $data);
        if ($this->flag($contextId, 'showDublinCore')) $this->addDublinCore($templateMgr, $data, $contextId);
        if ($this->flag($contextId, 'addOaiLink') && $data['oaiRecordUrl']) {
            $templateMgr->addHeader(
                'academiaEdu_oai',
                '<link rel="alternate" type="application/xml" title="OAI-PMH Dublin Core record" href="' . $this->e($data['oaiRecordUrl']) . '">'
            );
        }
        return Hook::CONTINUE;
    }

    public function displayArticle($hookName, $params): bool
    {
        $templateMgr = &$params[1];
        $output = &$params[2];
        if ($templateMgr->getTemplateVars('academiaEdu')) {
            $templateMgr->assign('academiaEduFieldTpl', $this->getTemplateResource('field.tpl'));
            $output .= $templateMgr->fetch($this->getTemplateResource('article.tpl'));
        }
        return Hook::CONTINUE;
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function flag(int $contextId, string $key): bool
    {
        return $this->getSetting($contextId, $key) !== '0';
    }

    private function pluginActive(string $name, int $contextId): bool
    {
        $plugin = PluginRegistry::getPlugin('generic', $name);
        return $plugin && $plugin->getEnabled($contextId);
    }

    private function s($v): string
    {
        if (is_array($v)) {
            $parts = [];
            foreach ($v as $item) {
                if (is_array($item)) $parts[] = $this->s($item);
                elseif (is_scalar($item)) $parts[] = (string) $item;
            }
            return trim(implode(', ', array_filter($parts, fn ($p) => $p !== '')));
        }
        if (is_object($v)) return '';
        return trim((string) $v);
    }

    /** Plain text: no tags, decoded entities, collapsed whitespace. */
    private function clean($v): string
    {
        $t = html_entity_decode(strip_tags($this->s($v)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    private function e($v): string
    {
        return htmlspecialchars($this->s($v), ENT_QUOTES, 'UTF-8');
    }

    private function tex(string $v): string
    {
        return str_replace(['{', '}'], '', $v);
    }

    /* ------------------------------------------------------------------ */
    /* Data                                                                */
    /* ------------------------------------------------------------------ */

    private function buildData($request, $article, $publication, $issue = null): array
    {
        $context = $request->getContext();
        $contextId = $context->getId();
        $dispatcher = $request->getDispatcher();

        $locale = (string) ($publication->getData('locale') ?: $context->getPrimaryLocale());
        $language = str_replace('_', '-', $locale);

        $title = $this->clean($publication->getLocalizedTitle());
        $abstract = $this->clean($publication->getLocalizedData('abstract'));
        $journal = $this->clean($context->getLocalizedName());
        $publisher = $this->clean($context->getData('publisherInstitution'));
        $issn = $this->s($context->getData('onlineIssn') ?: $context->getData('printIssn'));

        if (!$issue && $publication->getData('issueId')) {
            try {
                $issue = Repo::issue()->get((int) $publication->getData('issueId'));
            } catch (\Throwable $e) {
                $issue = null;
            }
        }
        $volume = $issue ? $this->s($issue->getVolume()) : '';
        $number = $issue ? $this->s($issue->getNumber()) : '';

        $articleUrl = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'article', 'view', [$article->getBestId()]);

        // Authors (the publication returns a Collection, so it must be iterated, not cast to array).
        $authors = [];
        $authorRecords = [];
        $authorList = $publication->getData('authors');
        if (is_iterable($authorList)) {
            foreach ($authorList as $author) {
                if (!is_object($author)) continue;
                $name = $this->clean($author->getFullName());
                if ($name === '') continue;
                $affiliation = '';
                if (method_exists($author, 'getLocalizedAffiliationNames')) {
                    try {
                        $affiliation = $this->clean($author->getLocalizedAffiliationNames());
                    } catch (\Throwable $e) {
                        $affiliation = '';
                    }
                }
                if ($affiliation === '') $affiliation = $this->clean($author->getLocalizedData('affiliation'));
                $authors[] = $name;
                $authorRecords[] = ['name' => $name, 'affiliation' => $affiliation, 'orcid' => $this->s($author->getData('orcid'))];
            }
        }
        $authorsText = implode(', ', $authors);

        // DOI
        $doiObject = $publication->getData('doiObject');
        $doi = $doiObject ? $this->s($doiObject->getData('doi')) : '';
        $doi = (string) preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi);
        $doiUrl = $doi ? 'https://doi.org/' . $doi : '';

        // Dates
        $datePublished = $this->s($publication->getData('datePublished'));
        $ts = $datePublished ? strtotime($datePublished) : false;
        $year = $ts ? date('Y', $ts) : '';
        $dateIso = $ts ? date('Y-m-d', $ts) : '';
        $dateMeta = $ts ? date('Y/m/d', $ts) : '';

        // Keywords
        $keywords = [];
        $rawKeywords = $publication->getLocalizedData('keywords');
        if (is_iterable($rawKeywords)) {
            foreach ($rawKeywords as $keyword) {
                $k = is_array($keyword) ? $this->clean($keyword['name'] ?? '') : $this->clean($keyword);
                if ($k !== '') $keywords[] = $k;
            }
        }
        $keywords = array_values(array_unique($keywords));

        // PDF galley (local files only; the /download/ URL is what Scholar-type crawlers expect)
        $pdfUrl = '';
        $galleys = $publication->getData('galleys');
        if (is_iterable($galleys)) {
            foreach ($galleys as $galley) {
                if (!is_object($galley) || $galley->getData('urlRemote')) continue;
                $isPdf = strtolower($this->s($galley->getData('label'))) === 'pdf';
                try {
                    if (!$isPdf && method_exists($galley, 'isPdfGalley')) $isPdf = (bool) $galley->isPdfGalley();
                } catch (\Throwable $e) {
                }
                if ($isPdf) {
                    $pdfUrl = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'article', 'download', [$article->getBestId(), $galley->getBestGalleyId()]);
                    break;
                }
            }
        }

        $licenseUrl = $this->s($publication->getData('licenseUrl'));

        // OAI-PMH (built into OJS). Identifier format: oai:<repository_id>:article/<submissionId>
        $oaiEnabled = (bool) Config::getVar('oai', 'oai');
        $oaiBase = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'oai');
        $repoId = (string) Config::getVar('oai', 'repository_id');
        $oaiRecordUrl = '';
        if ($oaiEnabled && $repoId !== '') {
            $oaiRecordUrl = $oaiBase . (str_contains($oaiBase, '?') ? '&' : '?') . http_build_query([
                'verb' => 'GetRecord',
                'identifier' => 'oai:' . $repoId . ':article/' . $article->getId(),
                'metadataPrefix' => 'oai_dc',
            ]);
        }

        // Readiness
        $missing = [];
        if ($title === '') $missing[] = 'title';
        if (!$authors) $missing[] = 'authors';
        if ($abstract === '') $missing[] = 'abstract';
        if ($dateIso === '') $missing[] = 'datePublished';
        if ($doi === '' && $pdfUrl === '') $missing[] = 'doiOrPdf';
        $recommended = [];
        if ($doi === '') $recommended[] = 'doi';
        if ($pdfUrl === '') $recommended[] = 'pdf';
        if (!$keywords) $recommended[] = 'keywords';
        if (!array_filter(array_column($authorRecords, 'orcid'))) $recommended[] = 'orcid';
        if ($licenseUrl === '') $recommended[] = 'license';
        if (!$issue) $recommended[] = 'issue';
        $label = fn (string $k) => __('plugins.generic.academiaEdu.missing.' . $k);

        // Citation + export formats
        $citation = ($authorsText !== '' ? $authorsText . ' ' : '') . ($year ? '(' . $year . '). ' : '') . $title . '. ' . $journal
            . ($volume !== '' ? ', ' . $volume : '') . ($number !== '' ? '(' . $number . ')' : '')
            . '. ' . ($doiUrl ?: $articleUrl);

        $bib = '@article{article' . $article->getBestId() . ",\n  title = {" . $this->tex($title) . "},\n  author = {"
            . $this->tex(implode(' and ', $authors)) . "},\n  journal = {" . $this->tex($journal) . '}';
        if ($year) $bib .= ",\n  year = {" . $year . '}';
        if ($volume !== '') $bib .= ",\n  volume = {" . $this->tex($volume) . '}';
        if ($number !== '') $bib .= ",\n  number = {" . $this->tex($number) . '}';
        if ($publisher !== '') $bib .= ",\n  publisher = {" . $this->tex($publisher) . '}';
        if ($issn !== '') $bib .= ",\n  issn = {" . $this->tex($issn) . '}';
        if ($doi) $bib .= ",\n  doi = {" . $this->tex($doi) . '}';
        $bib .= ",\n  url = {" . $this->tex($articleUrl) . "}\n}";

        $ris = "TY  - JOUR\nTI  - " . $title . "\n";
        foreach ($authors as $a) $ris .= 'AU  - ' . $a . "\n";
        $ris .= 'JO  - ' . $journal . "\n";
        if ($year) $ris .= 'PY  - ' . $year . "\n";
        if ($volume !== '') $ris .= 'VL  - ' . $volume . "\n";
        if ($number !== '') $ris .= 'IS  - ' . $number . "\n";
        if ($publisher !== '') $ris .= 'PB  - ' . $publisher . "\n";
        if ($issn !== '') $ris .= 'SN  - ' . $issn . "\n";
        if ($doi) $ris .= 'DO  - ' . $doi . "\n";
        foreach ($keywords as $k) $ris .= 'KW  - ' . $k . "\n";
        if ($abstract !== '') $ris .= 'AB  - ' . $abstract . "\n";
        $ris .= 'UR  - ' . $articleUrl . "\nER  - ";

        $package = "TITLE\n" . $title . "\n\nAUTHORS\n" . $authorsText . "\n\nCITATION\n" . $citation . "\n";
        if ($doiUrl) $package .= "\nDOI\n" . $doiUrl . "\n";
        $package .= "\nARTICLE URL\n" . $articleUrl . "\n";
        if ($pdfUrl) $package .= "\nPDF\n" . $pdfUrl . "\n";
        if ($keywords) $package .= "\nKEYWORDS\n" . implode(', ', $keywords) . "\n";
        if ($abstract !== '') $package .= "\nABSTRACT\n" . $abstract . "\n";

        return [
            'title' => $title, 'abstract' => $abstract, 'authors' => $authors, 'authorRecords' => $authorRecords,
            'authorsText' => $authorsText, 'journal' => $journal, 'publisher' => $publisher, 'issn' => $issn,
            'volume' => $volume, 'number' => $number, 'language' => $language,
            'doi' => $doi, 'doiUrl' => $doiUrl, 'articleUrl' => $articleUrl, 'pdfUrl' => $pdfUrl,
            'dateIso' => $dateIso, 'dateMeta' => $dateMeta, 'year' => $year,
            'keywords' => $keywords, 'keywordsText' => implode(', ', $keywords), 'licenseUrl' => $licenseUrl,
            'ready' => !$missing,
            'missing' => array_map($label, $missing), 'recommended' => array_map($label, $recommended),
            'oaiEnabled' => $oaiEnabled, 'oaiBase' => $oaiBase, 'oaiRecordUrl' => $oaiRecordUrl,
            'citation' => $citation, 'package' => $package,
            'bibtex' => $this->flag($contextId, 'showBibtex') ? $bib : '',
            'ris' => $this->flag($contextId, 'showRis') ? $ris : '',
            'buttonLabel' => $this->getSetting($contextId, 'buttonLabel') ?: 'Academia.edu Ready',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Metadata output                                                     */
    /* ------------------------------------------------------------------ */

    private function addMetaTags($tm, array $d, int $contextId): void
    {
        $tags = [
            ['property', 'og:type', 'article'],
            ['property', 'og:title', $d['title']],
            ['property', 'og:description', mb_substr($d['abstract'], 0, 300)],
            ['property', 'og:url', $d['articleUrl']],
            ['property', 'og:site_name', $d['journal']],
        ];
        if ($d['dateIso']) $tags[] = ['property', 'article:published_time', $d['dateIso']];
        foreach ($d['keywords'] as $k) $tags[] = ['property', 'article:tag', $k];

        // Only emit citation_* tags if OJS's own Google Scholar plugin isn't already doing it,
        // otherwise crawlers see duplicates.
        if (!$this->pluginActive('googlescholarplugin', $contextId)) {
            $tags[] = ['name', 'citation_title', $d['title']];
            $tags[] = ['name', 'citation_journal_title', $d['journal']];
            $tags[] = ['name', 'citation_publisher', $d['publisher']];
            $tags[] = ['name', 'citation_publication_date', $d['dateMeta']];
            $tags[] = ['name', 'citation_volume', $d['volume']];
            $tags[] = ['name', 'citation_issue', $d['number']];
            $tags[] = ['name', 'citation_issn', $d['issn']];
            $tags[] = ['name', 'citation_language', $d['language']];
            $tags[] = ['name', 'citation_doi', $d['doi']];
            $tags[] = ['name', 'citation_abstract_html_url', $d['articleUrl']];
            $tags[] = ['name', 'citation_pdf_url', $d['pdfUrl']];
            foreach ($d['authorRecords'] as $a) {
                $tags[] = ['name', 'citation_author', $a['name']];
                $tags[] = ['name', 'citation_author_institution', $a['affiliation']];
                $tags[] = ['name', 'citation_author_orcid', $a['orcid']];
            }
            foreach ($d['keywords'] as $k) $tags[] = ['name', 'citation_keywords', $k];
        }

        foreach ($tags as [$attr, $name, $content]) {
            if ($this->s($content) === '') continue;
            $tm->addHeader(
                'academiaEdu_' . md5($attr . $name . $content),
                '<meta ' . $attr . '="' . $this->e($name) . '" content="' . $this->e($content) . '">'
            );
        }
    }

    private function addDublinCore($tm, array $d, int $contextId): void
    {
        if ($this->pluginActive('dublincoremetaplugin', $contextId)) return; // avoid duplicate DC tags
        $pairs = [
            ['DC.title', $d['title']], ['DC.creator', $d['authorsText']], ['DC.description', $d['abstract']],
            ['DC.date', $d['dateIso']], ['DC.identifier', $d['articleUrl']], ['DC.source', $d['journal']],
            ['DC.publisher', $d['publisher']], ['DC.rights', $d['licenseUrl']],
            ['DC.type', 'Text'], ['DC.language', $d['language']], ['DC.identifier.DOI', $d['doi']],
        ];
        foreach ($d['keywords'] as $k) $pairs[] = ['DC.subject', $k];
        foreach ($pairs as [$name, $content]) {
            if ($this->s($content) === '') continue;
            $tm->addHeader('academiaEdu_dc_' . md5($name . $content), '<meta name="' . $this->e($name) . '" content="' . $this->e($content) . '">');
        }
    }

    private function addJsonLd($tm, array $d): void
    {
        $periodical = array_filter(['@type' => 'Periodical', 'name' => $d['journal'], 'issn' => $d['issn']]);
        $authors = array_map(function ($a) {
            return array_filter([
                '@type' => 'Person',
                'name' => $a['name'],
                'affiliation' => $a['affiliation'] ? ['@type' => 'Organization', 'name' => $a['affiliation']] : null,
                'sameAs' => $a['orcid'] ?: null,
            ]);
        }, $d['authorRecords']);

        $json = array_filter([
            '@context' => 'https://schema.org', '@type' => 'ScholarlyArticle',
            'headline' => $d['title'], 'description' => $d['abstract'], 'url' => $d['articleUrl'],
            'datePublished' => $d['dateIso'], 'inLanguage' => $d['language'],
            'keywords' => $d['keywordsText'], 'license' => $d['licenseUrl'],
            'isPartOf' => $periodical,
            'publisher' => $d['publisher'] ? ['@type' => 'Organization', 'name' => $d['publisher']] : null,
            'author' => $authors,
            'identifier' => $d['doiUrl'],
            'encoding' => $d['pdfUrl'] ? ['@type' => 'MediaObject', 'contentUrl' => $d['pdfUrl'], 'encodingFormat' => 'application/pdf'] : null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        // HEX_TAG/HEX_AMP stop article text from closing the <script> element.
        $tm->addHeader(
            'academiaEdu_jsonld',
            '<script type="application/ld+json">' . json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>'
        );
    }

    /* ------------------------------------------------------------------ */
    /* Readiness + OAI report                                              */
    /* ------------------------------------------------------------------ */

    private function renderReport($request): string
    {
        $context = $request->getContext();
        $rows = [];
        $collector = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->limit(self::REPORT_LIMIT);
        foreach ($collector->getMany() as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            $d = $this->buildData($request, $submission, $publication);
            $rows[] = [
                'id' => $submission->getId(), 'title' => $d['title'], 'url' => $d['articleUrl'],
                'ready' => $d['ready'], 'missing' => $d['missing'], 'recommended' => $d['recommended'],
            ];
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'reportRows' => $rows,
            'reportLimit' => self::REPORT_LIMIT,
            'oaiEnabled' => (bool) Config::getVar('oai', 'oai'),
            'oaiUrl' => $request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, null, 'oai'),
            'oaiRepositoryId' => (string) Config::getVar('oai', 'repository_id'),
        ]);
        return $templateMgr->fetch($this->getTemplateResource('report.tpl'));
    }
}
