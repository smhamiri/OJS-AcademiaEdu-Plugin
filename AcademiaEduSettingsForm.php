<?php

namespace APP\plugins\generic\academiaEdu;

use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class AcademiaEduSettingsForm extends Form
{
    private const TEXT_KEYS = ['buttonLabel', 'panelVisibility'];
    private const FLAG_KEYS = ['showMetaTags', 'showBibtex', 'showRis', 'showJsonLd', 'showDublinCore', 'addOaiLink'];
    private const VISIBILITY = ['public', 'loggedIn', 'hidden'];

    private AcademiaEduPlugin $plugin;
    private int $contextId;

    public function __construct(AcademiaEduPlugin $plugin, int $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData()
    {
        $this->setData('buttonLabel', $this->plugin->getSetting($this->contextId, 'buttonLabel') ?: 'Academia.edu Ready');
        $this->setData('panelVisibility', $this->plugin->getSetting($this->contextId, 'panelVisibility') ?: 'public');
        foreach (self::FLAG_KEYS as $key) {
            $this->setData($key, $this->plugin->getSetting($this->contextId, $key) !== '0');
        }
        parent::initData();
    }

    public function readInputData()
    {
        $this->readUserVars(array_merge(self::TEXT_KEYS, self::FLAG_KEYS));
        parent::readInputData();
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign('panelVisibilityOptions', [
            'public' => __('plugins.generic.academiaEdu.settings.visibility.public'),
            'loggedIn' => __('plugins.generic.academiaEdu.settings.visibility.loggedIn'),
            'hidden' => __('plugins.generic.academiaEdu.settings.visibility.hidden'),
        ]);
        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs)
    {
        $visibility = (string) $this->getData('panelVisibility');

        $values = [
            'buttonLabel' => trim((string) $this->getData('buttonLabel')) ?: 'Academia.edu Ready',
            'panelVisibility' => in_array($visibility, self::VISIBILITY, true) ? $visibility : 'public',
        ];
        foreach ($values as $key => $value) {
            $this->plugin->updateSetting($this->contextId, $key, $value, 'string');
        }
        foreach (self::FLAG_KEYS as $key) {
            $this->plugin->updateSetting($this->contextId, $key, $this->getData($key) ? '1' : '0', 'string');
        }
        return parent::execute(...$functionArgs);
    }
}
