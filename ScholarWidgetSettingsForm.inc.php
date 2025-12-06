<?php
/**
 * Form Pengaturan Google Scholar Widget
 * File: plugins/generic/scholarWidget/ScholarWidgetSettingsForm.inc.php
 */

import('lib.pkp.classes.form.Form');

class ScholarWidgetSettingsForm extends Form {
    
    private $contextId;
    private $plugin;
    
    public function __construct($plugin, $contextId) {
        $this->contextId = $contextId;
        $this->plugin = $plugin;
        
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        
        $this->addCheck(new FormValidator($this, 'scholarUserId', 'required', 'plugins.generic.scholarWidget.settings.scholarUserIdRequired'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }
    
    public function initData() {
        $contextId = $this->contextId;
        $plugin = $this->plugin;
        
        $this->setData('scholarUserId', $plugin->getSetting($contextId, 'scholarUserId'));
        $this->setData('widgetPosition', $plugin->getSetting($contextId, 'widgetPosition'));
        $this->setData('showHIndex', $plugin->getSetting($contextId, 'showHIndex'));
        $this->setData('showI10Index', $plugin->getSetting($contextId, 'showI10Index'));
        $this->setData('showCitationBar', $plugin->getSetting($contextId, 'showCitationBar'));
        $this->setData('widgetTitle', $plugin->getSetting($contextId, 'widgetTitle'));
    }
    
    public function readInputData() {
        $this->readUserVars(array('scholarUserId', 'widgetPosition', 'showHIndex', 'showI10Index', 'showCitationBar', 'widgetTitle'));
    }
    
    public function fetch($request, $template = null, $display = false) {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        
        // Position options
        $positionOptions = array(
            'top' => 'Paling Atas',
            'middle' => 'Tengah',
            'bottom' => 'Paling Bawah (Default)'
        );
        $templateMgr->assign('positionOptions', $positionOptions);
        
        return parent::fetch($request, $template, $display);
    }
    
    public function execute(...$functionArgs) {
        $plugin = $this->plugin;
        $contextId = $this->contextId;
        
        $plugin->updateSetting($contextId, 'scholarUserId', trim($this->getData('scholarUserId')), 'string');
        $plugin->updateSetting($contextId, 'widgetPosition', $this->getData('widgetPosition'), 'string');
        $plugin->updateSetting($contextId, 'showHIndex', $this->getData('showHIndex'), 'bool');
        $plugin->updateSetting($contextId, 'showI10Index', $this->getData('showI10Index'), 'bool');
        $plugin->updateSetting($contextId, 'showCitationBar', $this->getData('showCitationBar'), 'bool');
        $plugin->updateSetting($contextId, 'widgetTitle', trim($this->getData('widgetTitle')), 'string');
        
        parent::execute(...$functionArgs);
    }
}
?>