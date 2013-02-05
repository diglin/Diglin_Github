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
class Diglin_Github_Model_Confirmation extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('github/confirmation');
    }

    protected function _beforeSave()
    {
        if (!$this->getId() || $this->isObjectNew()) {
            $this->setCreatedAt(now());
        }
        return parent::_beforeSave();
    }
}