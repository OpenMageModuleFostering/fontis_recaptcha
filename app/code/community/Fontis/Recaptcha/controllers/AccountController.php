<?php
/**
 * Fontis Recaptcha Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Fontis
 * @package    Fontis_Recaptcha
 * @author     Denis Margetic
 * @author     Chris Norton
 * @copyright  Copyright (c) 2009 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
include_once "Mage/Customer/controllers/AccountController.php";

class Fontis_Recaptcha_AccountController extends Mage_Customer_AccountController
{
    public function createPostAction()
    {
        if (Mage::getStoreConfig("customer/recaptcha/enabled")) { // check that recaptcha is actually enabled
            // get private key from system configuration
            $privatekey = Mage::getStoreConfig("admin/recaptcha/private_key");
            // check response
            $resp = Mage::helper("recaptcha")->recaptcha_check_answer(  $privatekey,
                                                                        $_SERVER["REMOTE_ADDR"],
                                                                        $_POST["recaptcha_challenge_field"],
                                                                        $_POST["recaptcha_response_field"]
                                                                     );
            if ($resp == true) { // if recaptcha response is correct, follow core functionality
                if ($this->_getSession()->isLoggedIn()) {
                    $this->_redirect('*/*/');
                    return;
                }
                if ($this->getRequest()->isPost()) {
                    $errors = array();
                    $customer = Mage::getModel('customer/customer')->setId(null);
                    foreach (Mage::getConfig()->getFieldset('customer_account') as $code=>$node) {
                        if ($node->is('create') && ($value = $this->getRequest()->getParam($code)) !== null) {
                            $customer->setData($code, $value);
                        }
                    }
                    if ($this->getRequest()->getParam('is_subscribed', false)) {
                        $customer->setIsSubscribed(1);
                    }
                    $customer->getGroupId();
                    if ($this->getRequest()->getPost('create_address')) {
                        $address = Mage::getModel('customer/address')
                                        ->setData($this->getRequest()->getPost())
                                        ->setIsDefaultBilling($this->getRequest()->getParam('default_billing', false))
                                        ->setIsDefaultShipping($this->getRequest()->getParam('default_shipping', false))
                                        ->setId(null);
                        $customer->addAddress($address);
                        $errors = $address->validate();
                        if (!is_array($errors)) {
                            $errors = array();
                        }
                    }
                    try {
                        $validationCustomer = $customer->validate();
                        if (is_array($validationCustomer)) {
                            $errors = array_merge($validationCustomer, $errors);
                        }
                        $validationResult = count($errors) == 0;

                        if (true === $validationResult) {
                            $customer->save();
                            if ($customer->isConfirmationRequired()) {
                                $customer->sendNewAccountEmail('confirmation', $this->_getSession()->getBeforeAuthUrl());
                                $this->_getSession()->addSuccess($this->__('Account confirmation is required. Please, check your e-mail for confirmation link. To resend confirmation email please <a href="%s">click here</a>.',
                                                                 Mage::helper('customer')->getEmailConfirmationUrl($customer->getEmail()))
                                                                );
                                $this->_redirectSuccess(Mage::getUrl('*/*/index', array('_secure'=>true)));
                                return;
                            } else {
                                $this->_getSession()->setCustomerAsLoggedIn($customer);
                                $url = $this->_welcomeCustomer($customer);
                                $this->_redirectSuccess($url);
                                return;
                            }
                        } else {
                            $this->_getSession()->setCustomerFormData($this->getRequest()->getPost());
                            if (is_array($errors)) {
                                foreach ($errors as $errorMessage) {
                                    $this->_getSession()->addError($errorMessage);
                                }
                            } else {
                                $this->_getSession()->addError($this->__('Invalid customer data'));
                            }
                        }
                    } catch (Mage_Core_Exception $e) {
                        $this->_getSession()->addError($e->getMessage())
                             ->setCustomerFormData($this->getRequest()->getPost());
                    } catch (Exception $e) {
                        $this->_getSession()->setCustomerFormData($this->getRequest()->getPost())
                             ->addException($e, $this->__('Can\'t save customer'));
                    }
                }
                $this->_redirectError(Mage::getUrl('*/*/create', array('_secure'=>true)));
            }else{ // if recaptcha response is incorrect, reload the page
                $this->_redirectReferer();
                return;
            }
        } else { // if recaptcha is not enabled, use core function alone
            parent::sendmailAction();
        }
    }
}
?>
