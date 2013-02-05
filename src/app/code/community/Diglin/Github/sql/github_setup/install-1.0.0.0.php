<?php
/**
 * Diglin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 *
 * @category    Diglin
 * @package     Diglin_Github
 * @copyright   Copyright (c) 2011-2013 Diglin (http://www.diglin.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/* @var $installer Diglin_Github_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

//$entityTypeId = $installer->getEntityType('customer')->getEntityTypeId();
$installer->addAttribute('customer', 'github_id', array(
        'type' => 'int',
        'input' => 'text',
        'label' => 'Github ID',
        'required' => false,
        'user_defined' => false,
        'default' => '',
        'unique' => true,
        'note' => 'Github ID provided by Github API after first login. Please, don\'t change it or delete it if you want to delete the login',
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

Mage::getSingleton('eav/config')
    ->getAttribute('customer', 'github_id')
    ->setData('used_in_forms', array('adminhtml_customer'))
    ->save();

$table = $installer->getConnection()
    ->newTable($installer->getTable('github/confirmation'))
    ->addColumn('confirm_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
            'identity'  => true,
            'nullable'  => false,
            'primary'   => true,
        ), 'Confirmation ID')
    ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
            'nullable'  => false,
            'unsigned' => true,
        ), 'Customer Id')
    ->addColumn('github_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
            'unsigned' => true,
        ), 'Github Id')
    ->addColumn('github_login', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable'  => false,
        ), 'Github Username')
    ->addColumn('key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
                'nullable'  => false,
        ), 'Github Confirmation Key')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        ), 'Confirmation Creation Time')
    ->addForeignKey($installer->getFkName('github/confirmation', 'customer_id', 'customer/entity', 'entity_id'),
            'customer_id', $installer->getTable('customer/entity'), 'entity_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE)
    ->setComment('Github Confirmation Table');

$installer->getConnection()->createTable($table);

$installer->endSetup();