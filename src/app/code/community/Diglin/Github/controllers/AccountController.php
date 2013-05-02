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

require_once 'Mage/Customer/controllers/AccountController.php';

class Diglin_Github_AccountController extends Mage_Customer_AccountController
{
    const XML_PATH_GITHUB_EXISTING_CUSTOMER_LINK_EMAIL_TEMPLATE = 'github/link_account/email_template';
    const XML_PATH_GITHUB_EXISTING_CUSTOMER_LINK_EMAIL_ENTITY = 'github/link_account/email_identity';

    /**
     * Action list where need check enabled cookie
     *
     * @var array
     */
    protected $_cookieCheckActions = array('createpost');

    /**
     * Action predispatch
     *
     * Check customer authentication for some actions
     */
    public function preDispatch()
    {
        parent::preDispatch();

        // The parent may block the dispatch
        $this->setFlag('', 'no-dispatch', false);

        if (!$this->getRequest()->isDispatched()) {
            return;
        }

        $action = $this->getRequest()->getActionName();
        $openActions = array(
                'create',
                'login',
                'existing',
                'confirmation'
        );
        $pattern = '/^(' . implode('|', $openActions) . ')/i';

        if (!preg_match($pattern, $action)) {
            if (!$this->_getSession()->authenticate($this)) {
                $this->setFlag('', 'no-dispatch', true);
            }
        } else {
            $this->_getSession()->setNoReferer(true);
        }
    }

    /**
     * Retrieve customer session model object
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession ()
    {
        return Mage::getSingleton('customer/session');
    }

    public function indexAction()
    {
        $this->_forward('login');
    }

    public function loginAction ()
    {
        /* @var $helper Diglin_Github_Helper_Data */
        $helper = Mage::helper('github');
        $code = $this->getRequest()->getParam('code');
        $returnedState = $this->getRequest()->getParam('state');

        // Optional by the Github API, we use it to prevent man-in-the-middle attack
        $state = $this->_getSession()->getData('state');
        if (empty($state)) {
            $state = Mage::helper('core')->getRandomString(20);
            $this->_getSession()->setData('state', $state);
        }

        /* @var $oauth Diglin_Github_Model_Adapter_Oauth2 */
        $oauth = Mage::getModel('github/adapter_oauth2');

        // - Get code access from Github after user login and acceptation on Github
        // - Scope value can be changed if we want to add features in future
        // (possible values with separated comma: user, user:email, user:follow, public_repo, repo, repo:status, delete_repo, notifications, gist)

        // 1) First, login the user via Github
        if (empty($code)) {
            $authentificationUrl = $oauth->getAuthenticationUrl($oauth::AUTHORIZATION_ENDPOINT, $oauth->getRedirectUri(), array(
                    'state' => $state,
                    'scope' => 'user:email'
                    ));
            $this->_redirectUrl($authentificationUrl);
            return;
        }

        // Check Man-In-The-Middle attack
        if ($returnedState != $state) {
            $this->_getSession()->addError($this->__('Sorry, we cannot accept this authentification. A problem occur during the communication with Github. Please, contact us.'));
            Diglin_Github_Model_Log::log($this->__('Possible Hacker Attack during Github Autorization Process. Code: %s - State Returned: %s - State generated: %s', $code, $returnedState, $state), Zend_Log::ERR);
            $this->_redirect('customer/account/login',  array('_secure' => true));
            return;
        }

        $params = array(
                'code' => $code,
                'redirect_uri' => $oauth->getRedirectUri(),// mandatory even if not used
                'state' => $state
                );

        // 2) Get Github Access Token to allow further API call
        $response = $oauth->getAccessToken($oauth::TOKEN_ENDPOINT, 'authorization_code', $params);
        parse_str($response['result'], $info);

        if ($response['code'] != 200 || isset($info['error'])) {
            $this->_getSession()->addError($this->__('A Problem occured while trying to get Github Authorization.'));
            Diglin_Github_Model_Log::log('Github Authorization problem ' . print_r($response, true) . print_r($params, true));
            $this->_redirect('customer/account/login', array('_secure' => true));
            return;
        }

        $token = '';
        if (!empty($info['access_token'])) {
            $helper->setAccessToken($info['access_token']);
            $token = $info['access_token'];
        } else if ($helper->getAccessToken()) {
            $token = $helper->getAccessToken();
        }

        // 3) Get user data, save it in session to use it later
        $oauth->setAccessToken($token);
        $response = $oauth->fetch($oauth::USER_PROFILE, array(), $oauth::HTTP_METHOD_GET, array('User-Agent' => $_SERVER["HTTP_USER_AGENT"]));// USER AGENT Required by Github

        if (empty($response['result']['id'])) {
            $this->_getSession()->addError($this->__("You don't seem to be logged in Github. Please, login again."));
            Diglin_Github_Model_Log::log('Github Login problem ' . print_r($response, true));
            $this->_redirect('customer/account/login', array('_secure' => true));
            return;
        } else {
            $githubId = $response['result']['id'];
        }
        
        //Diglin_Github_Model_Log::log('Github Login Debug ' . print_r($response, true));

        // Save github user data in session
        $helper->setUserData($response['result']);

        $customer = $helper->getCustomer($githubId);
        if ($customer->getId()) {
            $this->_getSession()->setCustomerAsLoggedIn($customer);
            $this->_loginPostRedirect();
        } else {
            $this->_redirect('github/account/create', array('_secure' => true));
        }
    }

    public function createAction ()
    {
        $session = $this->_getSession();
        if ($session->isLoggedIn()) {
            $this->_redirect('customer/account/index');
            return;
        }

        /* @var $helper Diglin_Github_Helper_Data */
        $helper = Mage::helper('github');

        $token = $helper->getAccessToken();
        $githubData = $helper->getUserData('github_data');

        if (empty($token) || empty($githubData)) {
            $session->addNotice($this->__('Please, login in Github before to try to register.'));
            $this->_redirect('customer/account/login',  array('_secure' => true));
            return;
        }

        $email = '';
        $firstname= '';
        $lastname = '';

        if ($githubData->getName()) {
            $name = explode(' ', $githubData->getName(), 2);
            // We have no clue of the order of the lastname or firstname, so hazard...
            $firstname = $name[0];
            $lastname = $name[1];
        }

        if ($githubData->getEmail()) {
            $email = $githubData->getEmail();
        }

        $post = $this->getRequest()->getPost();
        $post = array_merge($post, array(
                'github_id' => $githubData['id'],
                'github_login' => $githubData['login'],
                'lastname' => $lastname,
                'firstname' => $firstname,
                'email' => $email
        ));

        $session->setCustomerFormData($post);

        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->renderLayout();
    }

    public function createPostAction ()
    {
        $session = $this->_getSession();
        if ($session->isLoggedIn()) {
            $this->_redirect('customer/account/index');
            return;
        }
        $session->setEscapeMessages(true); // prevent XSS injection in user input

        /* @var $helper Diglin_Github_Helper_Data */
        $helper = Mage::helper('github');

        $data = $this->getRequest()->getParams();

        if ($data) {
            $githubData = $helper->getUserData();

            if ($data['github_id'] != $githubData->getId()) {
                $session->addError($this->__('Your Github information has not been found.'));
                $this->_redirect('customer/account/login',  array('_secure' => true));
                return;
            }

            // We use information provided by Github API instead of the form in case of form data modification
            $data ['github_id'] = $githubData->getId();
            $data ['github_login'] = $githubData->getLogin();

            $customer = Mage::getModel('customer/customer');

            // We generate a password to prevent customer validation error
            $password = $customer->generatePassword();
            $customer->setPassword($password)
                ->setConfirmation($password);

            $customer->addData($data);

            try {
                $customer->save();

                Mage::dispatchEvent('customer_register_success',
                    array('account_controller' => $this, 'customer' => $customer)
                );

                if ($customer->getId()) {
                    $session->setCustomerAsLoggedIn($customer);
                    $this->_getSession()->addSuccess(
                        $this->__('Thank you for registering with %s.', Mage::app()->getStore()->getFrontendName())
                    );

                    $successUrl = Mage::getUrl('customer/account/index', array('_secure'=>true));
                    $this->_loginPostRedirect();
                    return;
                }

            } catch (Mage_Core_Exception $e) {
                $session->setCustomerFormData($this->getRequest()->getPost());
                if ($e->getCode() === Mage_Customer_Model_Customer::EXCEPTION_EMAIL_EXISTS) {
                    $url = Mage::getUrl('customer/account/forgotpassword');
                    $message = $this->__('There is already an account with this email address. If you are sure that it is your email address, <a href="%s">click here</a> to get your password and access your account.', $url);
                    $session->setEscapeMessages(false);
                } else {
                    $message = $e->getMessage();
                }
                $session->addError($message);
            } catch (Exception $e) {
                $session->setCustomerFormData($this->getRequest()->getPost())
                    ->addException($e, $this->__('Cannot save the customer.'));
            }
        }

        $this->_redirectError(Mage::getUrl('*/*/create', array('_secure' => true)));
    }

    public function existingAction()
    {
        $customerEmail = $this->getRequest()->getParam('email');
        $githubId = (int) $this->getRequest()->getParam('github_id');
        $githubLogin = $this->getRequest()->getParam('github_login');
        $helper = Mage::helper('github');

        if (!Zend_Validate::is($customerEmail, 'EmailAddress')) {
            $this->_getSession()->addError($this->__('Email Address is not valid!'));
            $this->_redirect('*/*/create');
            return;
        }

        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId(Mage::app()->getWebsite()->getId())
            ->loadByEmail($customerEmail);

        if ($customer->getId() && !$customer->getGithubLogin()) {

            /* @var $confirmation Diglin_Github_Model_Confirmation */
            $confirmation = Mage::getModel('github/confirmation');

            $confirmation->load($customer->getId(), 'customer_id');

            if ($confirmation->getId()) {
                $confirmation->delete();
                $confirmation->unsetData();
            }

            try {
                $confirmation
                    ->setKey(md5(uniqid()))
                    ->setGithubLogin($githubLogin)
                    ->setGithubId($githubId)
                    ->setCustomerId($customer->getId())
                    ->save();

                $params = array(
                        'github_confirmation' => $confirmation,
                        'customer' => $customer,
                        'back_url' => $this->_getSession()->getBeforeAuthUrl(),
                );

                $helper->sendEmailTemplate(self::XML_PATH_GITHUB_EXISTING_CUSTOMER_LINK_EMAIL_TEMPLATE, self::XML_PATH_GITHUB_EXISTING_CUSTOMER_LINK_EMAIL_ENTITY, $params, $customer);
                $this->_getSession()->addSuccess($this->__('An email to confirm that this account belongs to you has been sent to your email address.'));
                $this->_getSession()->addSuccess($this->__('Please, click on the link into the email to confirm the link between your Githug account and your shop\'s account'));
            } catch (Exception $e) {
                $this->_getSession()->addError($this->__('A problem occured while trying to send you the confirmation email. Please, contact us.'));
                Mage::logException($e);
                $this->_redirect('*/*/create');
                return;
            }
        } else {
            $this->_getSession()->addNotice($this->__('We are sorry, we didn\'t find your account in our database or your account is already linked to a Github account.'));
            $this->_redirect('*/*/create');
            return;
        }

        $this->_redirect('customer/account/login');
    }

    public function confirmationAction()
    {
        $confirmationKey = substr($this->getRequest()->getParam('key'), 0, 255);
        //$backUrl = $this->getRequest()->getParam('back_url');
        $customerId = (int)$this->getRequest()->getParam('id');

        $customer = Mage::getModel('customer/customer')->load($customerId);
        $confirmation = Mage::getModel('github/confirmation')->load($confirmationKey, 'key');

        if ($customer->getId() && !$customer->getGithubLogin() && $confirmation->getId()) {
            try {
                $customer->setGithubId($confirmation->getGithubId())
                    ->setGithubLogin($confirmation->getGithubLogin())
                    ->save();

                $confirmation->delete();

                $this->_getSession()->setCustomerAsLoggedIn($customer);

                $this->_getSession()->addSuccess($this->__('Congratulations! You are member of our private club :-)'));
            } catch (Exception $e) {
                $this->_getSession()->addError($this->__('A problem occured while trying to save your information.'));
                Mage::logException($e);
                $this->_redirect('customer/account/login');
                return;
            }
        } else {
            $this->_getSession()->addError($this->__('Sorry, information sent are invalid! Try again to login with your Github account and to recreate the linkage.'));
            $this->_redirect('customer/account/login');
            return;
        }

        $this->_redirect('customer/account/index');
    }
}