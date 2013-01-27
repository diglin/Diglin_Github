<?php

/* @var $installer Diglin_Github_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

//$entityTypeId = $installer->getEntityType('customer')->getEntityTypeId();
$installer->addAttribute('customer', 'github_id', array(
        'type' => 'int',
        'input' => 'hidden',
        'label' => 'Github ID',
        'required' => false,
        'user_defined' => false,
        'default' => '',
        'unique' => true,
        'note' => 'Github ID provided by Github API after first login',
        'visible' => false,
));

$installer->addAttribute('customer', 'github_login', array(
        'type' => 'varchar',
        'input' => 'text',
        'label' => 'Github Login',
        'required' => false,
        'user_defined' => false,
        'default' => '',
        'unique' => true,
        'note' => 'Github Login provided by Github API after first login',
        'visible' => true
));

Mage::getSingleton('eav/config')
    ->getAttribute('customer', 'github_login')
    ->setData('used_in_forms', array('adminhtml_customer','customer_account_edit', 'customer_account_create'))
    ->save();

$installer->endSetup();