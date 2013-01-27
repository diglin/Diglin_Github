<?php
require_once 'Mage/Customer/controllers/AccountController.php';

class Diglin_Github_AccountController extends Mage_Customer_AccountController
{
    /**
     * Action list where need check enabled cookie
     *
     * @var array
     */
    protected $_cookieCheckActions = array('createpost');

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
            Mage::log($this->__('Possible Hacker Attack during Github Autorization Process. Code: %s - State Returned: %s - State generated: %s', $code, $returnedState, $state), Zend_Log::ERR);
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
        $response = $oauth->fetch($oauth::USER_PROFILE);

        if (empty($response['result']['id'])) {
            $this->_getSession()->addError($this->__("You doesn't seem to be logged in Github. Please, login again."));
            $this->_redirect('customer/account/login', array('_secure' => true));
            return;
        } else {
            $githubId = $response['result']['id'];
        }

        // Save github user data in session
        $helper->setUserData($response['result']);

        $customer = $helper->getCustomer($githubId);
        if ($customer->getId()) {
            $this->_getSession()->setCustomerAsLoggedIn($customer);
            $url = Mage::getUrl('customer/account/index', array('_secure'=>true));
            if ($this->_getSession()->getBeforeAuthUrl()) {
                $url = $this->_getSession()->getBeforeAuthUrl(true);
            }
            $this->_redirectUrl($url);
            return;
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
            $this->_redirect('github/account/login',  array('_secure' => true));
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
                    if ($this->_getSession()->getBeforeAuthUrl()) {
                        $successUrl = $this->_getSession()->getBeforeAuthUrl(true);
                    }
                    $this->_redirectSuccess($successUrl);
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
}