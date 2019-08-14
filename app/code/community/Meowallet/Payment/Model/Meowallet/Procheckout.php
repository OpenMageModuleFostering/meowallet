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

class Meowallet_Payment_Model_Meowallet_Procheckout extends Meowallet_Payment_Model_Meowallet_Abstract
    
{
    protected $_code                    = 'meowallet_procheckout';
    protected $_canUseCheckout          = true;
    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canRefund 	            = true;
    protected $_canVoid 	            = true;
    protected $_canReviewPayment        = true;
    protected $_supportedCurrencyCodes  = array('EUR');

    protected function _getHelper()
    {
        return Mage::helper('meowallet');
    }

    /**
     * Return Order place redirect url
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('meowallet/procheckout/redirect', array('_secure' => true));
    }

    public function createCheckout($order, $url_confirm, $url_cancel)
    {
        $client  = array('name'    => $order->getCustomerName(),
                         'email'   => $order->getCustomerEmail());

        $Address = $order->getShippingAddress();

        if (null != $Address)
        {
            $street = $Address->getStreet();
            if (is_array($street))
            {
                $street = implode(' ', $street);
            }

            $client['address'] = array('country'    => $Address->getCountryId(),
                                       'address'    => $street,
                                       'city'       => $Address->getCity(),
                                       'postalcode' => $Address->postcode); // getPostCode() returns null

            $client['phone']   = $Address->getTelephone();
        }

        $payment = array('client'        => $client, 
                         'amount'        => $order->getGrandTotal(),
                         'currency'      => $order->getOrderCurrencyCode(),
                         'items'         => array(),
			             'ext_invoiceid' => $order->getIncrementId());

        $items = $order->getAllVisibleItems();
        foreach($items as $item)
        {
             $payment['items'][] = array('ref'   => $item->getProductId(),
                                         'name'  => $item->getName(),
                                         'descr' => $item->getName(),
                                         'qt'    => $item->getQtyOrdered());
        }

        $request_data = json_encode(array('payment'     => $payment,
					                      'url_confirm' => $url_confirm,
                         		          'url_cancel'  => $url_cancel));
        $authToken    = $this->getAPIToken();
        $headers      = array(
                            'Authorization: WalletPT ' . $authToken,
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($request_data)
                        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_URL, $this->getServiceEndpoint('checkout'));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = json_decode( curl_exec($ch) );
        #	$payment  = $oder->getPayment();
        #	$payment->setTransactionId($response->id);
        #	$payment->save();

        if (false == is_object($response) || false == property_exists($response, 'url_redirect'))
        {
            Mage::throwException($this->_getHelper()->__('Could not create MEO Wallet procheckout'));
        }

        return $response;
    }
}
