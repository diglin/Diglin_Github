<?php
/**
 * Diglin
 *
 * @category    Diglin
 * @package     Diglin_Github
 * @copyright   Copyright (c) 2011-2013 Diglin (http://www.diglin.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Diglin_Github_Block_Adminhtml_Grid extends Mage_Adminhtml_Block_Customer_Grid
{
    
    protected function _prepareColumns()
    {
        if (Mage::getStoreConfigFlag('github/config/grid')) {
            // Set a new column username after the column name
            $this->addColumnAfter('github_login', array(
                'header'    => Mage::helper('github')->__('Github'),
                'index'     => 'github_login'
            ),
            'name');
        }
        return parent::_prepareColumns();
    }
}