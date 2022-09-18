<?php

class CRM_Simple2c2p_Config {

  public static function write_log ($input, $preffix_log) {
    $simple2c2p_settings = CRM_Core_BAO_Setting::getItem("Simple2c2p Settings", 'simple2c2p_settings');
    if ($simple2c2p_settings['save_log'] == '1') {
      $masquerade_input = $input;
      $fields_to_hide = ['Signature'];
      foreach ($fields_to_hide as $field_to_hide) {
        unset($masquerade_input[$field_to_hide]);
      }
      Civi::log()->debug($preffix_log . print_r($masquerade_input, TRUE));
    }
  }
}