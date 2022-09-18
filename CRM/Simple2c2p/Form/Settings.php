<?php

use CRM_Simple2c2p_ExtensionUtil as E;

class CRM_Simple2c2p_Form_Settings extends CRM_Core_Form
{

    public function buildQuickForm()
    {
        $this->add('checkbox', 'save_log', 'Save log of all transactions');
        $this->add('text', 'ok_url', 'Thank You Page', ['size' => 100]);
        $this->add('text', 'not_ok_url', 'Sorry Page', ['size' => 100]);

        $this->addButtons([
            [
                'type' => 'submit',
                'name' => E::ts('Submit'),
                'isDefault' => TRUE,
            ],
        ]);
        parent::buildQuickForm();
    }

    public function setDefaultValues()
    {
        $defaults = [];
        $simple2c2p_settings = CRM_Core_BAO_Setting::getItem("Simple2c2p Settings", 'simple2c2p_settings');
        if (!empty($simple2c2p_settings)) {
            $defaults = $simple2c2p_settings;
        }
        return $defaults;
    }

    public function postProcess()
    {
        $values = $this->exportValues();
        $simple2c2p_settings['save_log'] = $values['save_log'];
        $simple2c2p_settings['ok_url'] = $values['ok_url'];
        $simple2c2p_settings['not_ok_url'] = $values['not_ok_url'];

        CRM_Core_BAO_Setting::setItem($simple2c2p_settings, "Simple2c2p Settings", 'simple2c2p_settings');
        CRM_Core_Session::setStatus(E::ts('Simple 2c2p Settings Saved', ['domain' => 'com.drkhindol.2c2p']), 'Configuration Updated', 'success');

        parent::postProcess();
    }


}
