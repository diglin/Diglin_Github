<?php

class Diglin_Github_Block_Account_Register extends Mage_Customer_Block_Form_Register
{

    public function getPostActionUrl()
    {
        return $this->getUrl('github/account/createpost');
    }

    public function getBackUrl()
    {
        return $this->getUrl('customer/account/index');
    }
}