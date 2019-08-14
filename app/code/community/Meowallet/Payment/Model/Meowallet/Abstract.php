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

class Meowallet_Payment_Model_Meowallet_Abstract extends Mage_Payment_Model_Method_Abstract
{
	protected $_code = 'meowallet_abstract';

	const SANDBOX_ENVIRONMENT_ID       = 0;
	const SANDBOX_SERVICE_ENDPOINT 	   = 'https://services.sandbox.meowallet.pt/api/v2';
	const PRODUCTION_ENVIRONMENT_ID    = 1;
    const PRODUCTION_SERVICE_ENDPOINT  = 'https://services.wallet.pt/api/v2';

    private function _getPaymentConfig($path)
    {
		$code = $this->_code;
        return Mage::getStoreConfig("payment/$code/$path");
    }

	private function _getEnvironmentCode()
	{
        $environment = $this->_getPaymentConfig('environment');

		switch ($environment)
		{
            case static::SANDBOX_ENVIRONMENT_ID:
				return 'sandbox';
            case static::PRODUCTION_ENVIRONMENT_ID: 
                return 'production';
		}

		Mage::throwException("Invalid environment code '$environment'");
    }

    private function _registerRefund($payment, $amount)
    {
        $payment->registerRefundNotification($amount);
    }

    private function _registerPayment($transaction_id, $payment, $amount, $action = Meowallet_Payment_Model_Meowallet_Abstract::ACTION_AUTHORIZE)
    {
        $payment->setTransactionId($transaction_id);

        switch ($action)
        {
            case Meowallet_Payment_Model_Meowallet_Abstract::ACTION_AUTHORIZE_CAPTURE:
                $payment->registerCaptureNotification($amount);
                break;

            case Meowallet_Payment_Model_Meowallet_Abstract::ACTION_AUTHORIZE:
            default:
                $payment->registerAuthorizationNotification($amount);
                break;
        }
    }

    private function _isValidCallback($data)
    {
        $authToken    = $this->getAPIToken();
        $headers      = array(
                          'Authorization: WalletPT ' . $authToken,
                          'Content-Type: application/json',
                          'Content-Length: ' . strlen($data));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_URL, $this->getServiceEndpoint('callback/verify'));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);

        if (0 == strcasecmp('true', $response))
        {
            return true;
        }

	    if (0 != strcasecmp('false', $response))
    	{
	        Mage::log("MEOWallet callback validation returned unexpected response '$response'", Zend_Log::ALERT);
    	}

        return false;
    }

	protected function _decrypt($data)
	{
		return Mage::helper('core')->decrypt($data);
    }

    protected function _getSalesOrderModel()
    {   
        return Mage::getModel('sales/order');
    }   
	
	protected function getServiceEndpoint($path = null)
	{
        $environment = $this->_getPaymentConfig('environment');
		$url	     = null;

        switch ($environment)
        {
            case static::SANDBOX_ENVIRONMENT_ID:
                $url = static::SANDBOX_SERVICE_ENDPOINT;
                break;
            case static::PRODUCTION_ENVIRONMENT_ID:
                $url = static::PRODUCTION_SERVICE_ENDPOINT;
            	break;
        }

		if ( empty($url) )
		{
	        Mage::throwException("Empty service endpoint for environment '$environment'");
		}

		return sprintf('%s/%s', $url, $path);
	}
	
	protected function getAPIToken()
	{
		$environment = $this->_getEnvironmentCode();
		$key         = sprintf('payment/%s/%s_api_token', 
				       $this->_code,
				       $environment); 

		return $this->_decrypt( Mage::getStoreConfig($key) );
    }

    protected function processPayment($transaction_id, $invoice_id, $status, $amount, $method)
    {
        Mage::log(sprintf("Processing payment for invoice_id '%s' with status '%s', amount '%s'", $invoice_id, $status, $amount));

        $order = $this->_getSalesOrderModel()->loadByIncrementId($invoice_id);

        if (null == $order)
        {
            throw new \InvalidArgumentException("Unknown order with invoice_id '$invoice_id'");
        }

        $payment  = $order->getPayment();

        if (null == $payment)
        {
            throw new \Exception('No payment associated with an order?!');
        }

        $comment = Mage::helper('meowallet')->__('%s status update: %s<br/>Payment Method: %s', "MEO Wallet", $status, $method);
        $order->addStatusHistoryComment($comment);

        switch ($status)
        {
            case Meowallet_Payment_Model_Operation::COMPLETED:
                $action = $this->_getPaymentConfig('payment_action');
                $this->_registerPayment($transaction_id, $payment, $amount, $action);
                $order->sendOrderUpdateEmail();
                break;

            case Meowallet_Payment_Model_Operation::FAIL:
                $order->cancel();
                $order->sendOrderUpdateEmail();
                break;

            case Meowallet_Payment_Model_Operation::CREATED:
            case Meowallet_Payment_Model_Operation::PENDING:
                break;

            default:
                throw new \InvalidArgumentException("Payment operation status '$status' not handled by this module!");
        }
        $order->save();
    }

    public function processCallback($verbatim_callback)
    {
        if (false === $this->_isValidCallback($verbatim_callback))
        {
            throw new \InvalidArgumentException('Invalid callback');
        }

        $callback = json_decode($verbatim_callback);

        Mage::log(sprintf("MEOWallet callback for invoice_id '%s' with status '%s'", $callback->ext_invoiceid, $callback->operation_status));

        $this->processPayment($callback->operation_id, $callback->ext_invoiceid, $callback->operation_status, $callback->amount, $callback->method);
    }
}
