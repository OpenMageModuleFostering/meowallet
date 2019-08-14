<?php
/**
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
 */

class Meowallet_Payment_ProcheckoutController extends Mage_Core_Controller_Front_Action
{
    protected function _getRequestPayload()
    {
        return file_get_contents('php://input');
    }

    /**
     * Retrieve shopping cart model object
     *
     * @return Mage_Checkout_Model_Cart
     */
    protected function _getCart()
    {
        return Mage::getSingleton('checkout/cart');
    }

    /**
     * Get checkout session model instance
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    protected function _getSalesOrderModel()
    {
        return Mage::getModel('sales/order');
    }

    protected function _getProcheckoutModel()
    {
        return Mage::getModel('meowallet/meowallet_procheckout');
    }

    public function redirectAction()
    {
        $lastOrderId = $this->_getSession()->getLastRealOrderId();
        $order       = $this->_getSalesOrderModel()->loadByIncrementId($lastOrderId);
        $ProCheckout = $this->_getProcheckoutModel();
        $checkout    = $ProCheckout->createCheckout($order, 
                        Mage::getUrl('meowallet/procheckout/success'),
                        Mage::getUrl('meowallet/procheckout/failure'));

        $url = sprintf('%s%s%s=%s', $checkout->url_redirect,
                                    false === strpos($checkout->url_redirect, '?') ? '?' : '&',
                                    'locale',
                                    Mage::app()->getLocale()->getLocaleCode());

        $this->_redirectUrl($url);
    }

    public function failureAction()
    {
        $lastOrderId = $this->_getSession()->getLastRealOrderId();

        if ( ! $lastOrderId )
        {
            $this->_redirect('checkout/cart');
            return;
        }

        $order       = $this->_getSalesOrderModel()->loadByIncrementId($lastOrderId);
        $checkout_id = strval($this->getRequest()->getParam('checkoutid'));

        if ($order && $checkout_id && $order->getExtOrderId() == $checkout_id)
        {
            $order->cancel();
            $order->save();
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    public function successAction()
    {
        $lastOrderId = $this->_getSession()->getLastRealOrderId();
        $order       = null;

        if ($lastOrderId)
        {
            $order = $this->_getSalesOrderModel()->loadByIncrementId($lastOrderId);
        }

        if ( $order && $order->getCanSendNewEmailFlag() )
        {
            try
            {
                $order->sendNewOrderEmail();
            }
            catch (\Exception $e)
            {
                Mage::log("MEOWallet cannot send new order email. Reason: ".$e->getMessage());
            }
        }

        $this->_redirect('checkout/onepage/success');
    }

    public function callbackAction()
    {
        $callback = $this->_getRequestPayload();

        try
        {
            $this->_getProcheckoutModel()->processCallback($callback);
            $this->getResponse()->setHeader('HTTP/1.0','200',true);
        }
        catch (\InvalidArgumentException $e)
        {
            Mage::log("MEOWallet received invalid callback. Request data: '$callback'");
            $this->getResponse()->setHeader('HTTP/1.0','400',true);
        }
        catch (\Exception $e)
        {
            Mage::log('MEO Wallet error processing callback. Reason: '.$e->getMessage(), Zend_Log::ALERT);
            $this->getResponse()->setHeader('HTTP/1.0','500',true);
        }
    }
}
