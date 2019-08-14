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
include_once "Mage/Sendfriend/controllers/ProductController.php";

class Fontis_Recaptcha_ProductController extends Mage_Sendfriend_ProductController
{
    public function sendmailAction()
    {
        if (Mage::getStoreConfig("admin/recaptcha/when_loggedin"))
            $logged_out = !(Mage::getSingleton('customer/session')->isLoggedIn());
        else
            $logged_out = true;

        if (Mage::getStoreConfig("sendfriend/recaptcha/enabled") && $logged_out) { // check that recaptcha is actually enabled
            // get private key from system configuration
            $privatekey = Mage::getStoreConfig("admin/recaptcha/private_key");
            // check response
            $resp = Mage::helper("recaptcha")->recaptcha_check_answer(  $privatekey,
                                                                        $_SERVER["REMOTE_ADDR"],
                                                                        $_POST["recaptcha_challenge_field"],
                                                                        $_POST["recaptcha_response_field"]
                                                                     );
                                                                     
			$product = $this->_initProduct();
            $sendToFriendModel = $this->_initSendToFriendModel();
            $data = $this->getRequest()->getPost();                                                                     
                                                                     
            if ($resp == true) { // if recaptcha response is correct, follow core functionality
                
                if (!$product || !$product->isVisibleInCatalog() || !$data) {
                    $this->_forward('noRoute');
                    return;
                }
                $categoryId = $this->getRequest()->getParam('cat_id', null);
                if ($categoryId && $category = Mage::getModel('catalog/category')->load($categoryId)) {
                    Mage::register('current_category', $category);
                }
                $sendToFriendModel->setSender($this->getRequest()->getPost('sender'));
                $sendToFriendModel->setRecipients($this->getRequest()->getPost('recipients'));
                $sendToFriendModel->setIp(Mage::getSingleton('log/visitor')->getRemoteAddr());
                $sendToFriendModel->setProduct($product);
                try {
                    $validateRes = $sendToFriendModel->validate();
                    if (true === $validateRes) {
                        $sendToFriendModel->send();
                        Mage::getSingleton('catalog/session')->addSuccess($this->__('Link to a friend was sent.'));
                        $this->_redirectSuccess($product->getProductUrl());
                        return;
                    } else {
                        Mage::getSingleton('catalog/session')->setFormData($data);
                        if (is_array($validateRes)) {
                            foreach ($validateRes as $errorMessage) {
                                Mage::getSingleton('catalog/session')->addError($errorMessage);
                            }
                        } else {
                            Mage::getSingleton('catalog/session')->addError($this->__('Some problems with data.'));
                        }
                    }
                } catch (Mage_Core_Exception $e) {
                    Mage::getSingleton('catalog/session')->addError($e->getMessage());
                } catch (Exception $e) {
                    Mage::getSingleton('catalog/session')
                    ->addException($e, $this->__('Some emails were not sent'));
                    echo $e->getTraceAsString();
                }
                $this->_redirectError(Mage::getURL('*/*/send',array('id'=>$product->getId())));
            } else { // if recaptcha response is incorrect, reload the page
                Mage::getSingleton('catalog/session')->addError($this->__('Your reCAPTCHA entry is incorrect. Please try again.'));
                Mage::getSingleton('catalog/session')->setFormData($data);
                $this->_redirectReferer();
                return;
            }
        } else { // if recaptcha is not enabled, use core function alone
            parent::sendmailAction();
        }
    }
}
?>