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
class Diglin_Github_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     *
     * @param string $token
     * @return string
     */
    public function setAccessToken ($token)
    {
        return $this->getSession()->setData('github_access_token', $token);
    }

    /**
     *
     * @return string
     */
    public function getAccessToken ()
    {
        return $this->getSession()->getData('github_access_token');
    }

    /**
     * The parameter data should be similar to
     * <pre>
     * array(29) {
     * ["blog"] => string(21) "http://www.diglin.com"
     * ["location"] => string(11) "Switzerland"
     * ["type"] => string(4) "User"
     * ["subscriptions_url"] => string(49) "https://api.github.com/users/diglin/subscriptions"
     * ["url"] => string(35) "https://api.github.com/users/diglin"
     * ["repos_url"] => string(41) "https://api.github.com/users/diglin/repos"
     * ["gravatar_id"] => string(32) "5cfc92dca620b0aa0bbc2f12d9b5bfc7"
     * ["avatar_url"] => string(158) "https://secure.gravatar.com/avatar/5cfc92dca620b0aa0bbc2f12d9b5bfc7?d=https://a248.e.akamai.net/assets.github.com%2Fimages%2Fgravatars%2Fgravatar-user-420.png"
     * ["email"] => NULL
     * ["received_events_url"] => string(51) "https://api.github.com/users/diglin/received_events"
     * ["html_url"] => string(25) "https://github.com/diglin"
     * ["company"] => NULL
     * ["events_url"] => string(52) "https://api.github.com/users/diglin/events{/privacy}"
     * ["organizations_url"] => string(40) "https://api.github.com/users/diglin/orgs"
     * ["login"] => string(6) "diglin"
     * ["public_gists"] => int(0)
     * ["updated_at"] => string(20) "2013-01-22T20:03:38Z"
     * ["gists_url"] => string(51) "https://api.github.com/users/diglin/gists{/gist_id}"
     * ["hireable"] => bool(false)
     * ["public_repos"] => int(7)
     * ["followers"] => int(2)
     * ["following"] => int(6)
     * ["name"] => string(13) "Sylvain Rayé"
     * ["starred_url"] => string(58) "https://api.github.com/users/diglin/starred{/owner}{/repo}"
     * ["bio"] => NULL
     * ["followers_url"] => string(45) "https://api.github.com/users/diglin/followers"
     * ["id"] => int(1337461)
     * ["created_at"] => string(20) "2012-01-17T16:48:53Z"
     * ["following_url"] => string(45) "https://api.github.com/users/diglin/following"
     * }
     * </pre>
     *
     * @param array $data
     * @return Varien_Object
     */
    public function setUserData ($data)
    {
        return $this->getSession()->setData('github_data', $data);
    }

    /**
     * @see $this->setUserData() to see the data format returned
     *
     * @return array
     */
    public function getUserData ()
    {
        return new Varien_Object($this->getSession()->getData('github_data'));
    }

    public function getCustomer($githubId)
    {
        /* @var $resource Mage_Customer_Model_Resource_Customer_Collection */
        $resource = Mage::getResourceModel('customer/customer_collection');

        return $resource->addAttributeToFilter('github_id', $githubId)
            ->load()
            ->getFirstItem();
    }

    /**
     * Send corresponding email template
     *
     * @param string $emailTemplate configuration path of email template
     * @param string $emailSender configuration path of email identity
     * @param array $templateParams
     * @param int|null $storeId
     * @return Mage_Customer_Model_Customer
     */
    public function sendEmailTemplate($template, $sender, $templateParams = array(), $customer, $storeId = null)
    {
        /** @var $mailer Mage_Core_Model_Email_Template_Mailer */
        $mailer = Mage::getModel('core/email_template_mailer');
        $emailInfo = Mage::getModel('core/email_info');
        $emailInfo->addTo($customer->getEmail(), $customer->getName());
        $mailer->addEmailInfo($emailInfo);

        // Set all required params and send emails
        $mailer->setSender(Mage::getStoreConfig($sender, $storeId));
        $mailer->setStoreId($storeId);
        $mailer->setTemplateId(Mage::getStoreConfig($template, $storeId));
        $mailer->setTemplateParams($templateParams);
        $mailer->send();
        return $this;
    }
}