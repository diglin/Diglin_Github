<?php

require_once '../../app/Mage.php';
require('client.php');
require('GrantType/IGrantType.php');
require('GrantType/AuthorizationCode.php');

const CLIENT_ID     = '';
const CLIENT_SECRET = '';

const REDIRECT_URI           = 'http://dev.magento.local/lib/Oauth2/Test.php';
const AUTHORIZATION_ENDPOINT = 'https://github.com/login/oauth/authorize';
const TOKEN_ENDPOINT         = 'https://github.com/login/oauth/access_token';

$state = Mage::helper('core')->getRandomString(20);

$client = new OAuth2\Client(CLIENT_ID, CLIENT_SECRET);
if (!isset($_GET['code']))
{
    $auth_url = $client->getAuthenticationUrl(AUTHORIZATION_ENDPOINT, REDIRECT_URI, array('state' => $state, 'scope' => 'user'));
    header('Location: ' . $auth_url);
    die('Redirect');
} else if (isset($_GET['access_token'])) {
    $client->setAccessToken($_GET['access_token']);
    $response = $client->fetch('https://api.github.com/user/emails');
    Zend_Debug::dump($response);
    Zend_Debug::dump($response['result']);
} else
{
    $params = array('code' => $_GET['code'], 'redirect_uri' => REDIRECT_URI, 'state' => $state, 'scope' => 'user');
    $response = $client->getAccessToken(TOKEN_ENDPOINT, 'authorization_code', $params);
    parse_str($response['result'], $info);
    echo $info['access_token'];
    $client->setAccessToken($info['access_token']);
    $response = $client->fetch('https://api.github.com/user');
    Zend_Debug::dump($response);
    Zend_Debug::dump($response['result']);
}
