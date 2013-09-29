<?php
/**
 * Diglin
 *
 * @category    Diglin
 * @package     Diglin_Github
 * @copyright   Copyright (c) 2011-2013 Diglin (http://www.diglin.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Diglin_Github_Model_Observer extends Mage_Customer_Model_Observer
{
    /**
     * Add on the fly the username attribute to the customer collection
     *
     * Event: eav_collection_abstract_load_before
     *
     * @param Varien_Event_Observer $observer
     */
    public function addAttributeToCollection ($observer)
    {
        /* @var $collection Mage_Eav_Model_Entity_Collection_Abstract */
        $collection = $observer->getEvent()->getCollection();
        if ($collection->getEntity()->getType() == 'customer') {
            $collection->addAttributeToSelect('github_login');
        }

    }
}