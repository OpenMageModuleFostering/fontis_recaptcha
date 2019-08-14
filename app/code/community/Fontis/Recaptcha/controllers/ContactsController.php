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
include_once "Mage/Contacts/controllers/IndexController.php";

class Fontis_Recaptcha_ContactsController extends Mage_Contacts_IndexController
{    
    public function postAction()
    {
        if (Mage::getStoreConfig("sendfriend/recaptcha/enabled")) { // check that recaptcha is actually enabled
            // get private key from system configuration
            $privatekey = Mage::getStoreConfig("admin/recaptcha/private_key");
            // check response
            $resp = Mage::helper("recaptcha")->recaptcha_check_answer(  $privatekey,
                                                                        $_SERVER["REMOTE_ADDR"],
                                                                        $_POST["recaptcha_challenge_field"],
                                                                        $_POST["recaptcha_response_field"]
                                                                     );
            if ($resp == true) { // if recaptcha response is correct, follow core functionality
                $post = $this->getRequest()->getPost();
                if ( $post ) {
                    $translate = Mage::getSingleton('core/translate');
                    /* @var $translate Mage_Core_Model_Translate */
                    $translate->setTranslateInline(false);
                    try {
                        $postObject = new Varien_Object();
                        $postObject->setData($post);
                        $error = false;
                        if (!Zend_Validate::is(trim($post['name']) , 'NotEmpty')) {
                            $error = true;
                        }
                        if (!Zend_Validate::is(trim($post['comment']) , 'NotEmpty')) {
                            $error = true;
                        }
                        if (!Zend_Validate::is(trim($post['email']), 'EmailAddress')) {
                            $error = true;
                        }
                        if ($error) {
                            throw new Exception();
                        }
                        $mailTemplate = Mage::getModel('core/email_template');
                        /* @var $mailTemplate Mage_Core_Model_Email_Template */
                        $mailTemplate->setDesignConfig(array('area' => 'frontend'))
                                     ->setReplyTo($post['email'])
                                     ->sendTransactional(   Mage::getStoreConfig('contacts/email/email_template'),
                                                            Mage::getStoreConfig('contacts/email/sender_email_identity'),
                                                            Mage::getStoreConfig('contacts/email/recipient_email'),
                                                            null,
                                                            array('data' => $postObject)
                                                        );
                        if (!$mailTemplate->getSentSuccess()) {
                            throw new Exception();
                        }
                        $translate->setTranslateInline(true);
                        Mage::getSingleton('customer/session')->addSuccess(Mage::helper('contacts')->__('Your inquiry was submitted and will be responded to as soon as possible. Thank you for contacting us.'));
                        $this->_redirect('*/*/');
                        return;
                    } catch (Exception $e) {
                        $translate->setTranslateInline(true);
                        Mage::getSingleton('customer/session')->addError(Mage::helper('contacts')->__('Unable to submit your request. Please, try again later'));
                        $this->_redirect('*/*/');
                        return;
                    }
                } else {
                    $this->_redirect('*/*/');
                }
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
