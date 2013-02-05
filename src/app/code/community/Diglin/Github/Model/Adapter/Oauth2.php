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

// Light Oauth2 Library located to /lib/Oauth2 of Magento installation
require_once 'Oauth2/client.php';
require_once 'Oauth2/GrantType/IGrantType.php';
require_once 'Oauth2/GrantType/AuthorizationCode.php';

class Diglin_Github_Model_Adapter_Oauth2 extends OAuth2\Client
{
    const AUTHORIZATION_ENDPOINT = 'https://github.com/login/oauth/authorize';
    const TOKEN_ENDPOINT         = 'https://github.com/login/oauth/access_token';
    const USER_PROFILE           = 'https://api.github.com/user';

    public function __construct ($clientId = null, $clientSecret = null, $clientAuth = self::AUTH_TYPE_URI, $certificateFile = null)
    {
        if (empty($clientId)) {
            $clientId = Mage::getStoreConfig('github/config/client_id');
        }

        if (empty($clientSecret)) {
            $clientSecret = Mage::helper('core')->decrypt(Mage::getStoreConfig('github/config/client_secret'));
        }

        return parent::__construct($clientId, $clientSecret, $clientAuth, $certificateFile);
    }

    public function getRedirectUri ()
    {
        return Mage::getUrl('github/account/login');
    }
}