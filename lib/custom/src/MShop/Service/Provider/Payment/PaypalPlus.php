<?php

/**
 * @license    LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright  Aimeos (aimeos.org), 2015-2020
 * @package    MShop
 * @subpackage Service
 */

namespace Aimeos\MShop\Service\Provider\Payment;

use Omnipay\Omnipay as OPay;
use Aimeos\MShop\Order\Item\Base as Status;

/**
 * Payment provider for payment gateways supported by the PaypalPlus library.
 *
 * @package    MShop
 * @subpackage Service
 */
class PaypalPlus extends \Aimeos\MShop\Service\Provider\Payment\OmniPay implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
    private $beConfig = array(
        'clientid' => array(
            'code' => 'clientid',
            'internalcode' => 'clientid',
            'label' => 'client id from paypal',
            'type' => 'string',
            'internaltype' => 'string',
            'default' => '0',
            'required' => true,
        ),
        'secret' => array(
            'code' => 'secret',
            'internalcode' => 'secret',
            'label' => 'secret string from paypal',
            'type' => 'string',
            'internaltype' => 'string',
            'default' => '0',
            'required' => true,
        ),
        'address' => array(
            'code' => 'address',
            'internalcode' => 'address',
            'label' => 'Send address to payment gateway too',
            'type' => 'boolean',
            'internaltype' => 'boolean',
            'default' => '0',
            'required' => false,
        ),
        'testmode' => array(
            'code' => 'testmode',
            'internalcode' => 'testmode',
            'label' => 'Test mode without payments',
            'type' => 'boolean',
            'internaltype' => 'boolean',
            'default' => '1',
            'required' => true,
        ),
    );
    private $provider;

    public function getConfigBE(): array
    {
        $list = [];

        foreach ($this->beConfig as $key => $config) {
            $list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard($config);
        }

        return $list;
    }

    protected function getProvider(): \Omnipay\Common\GatewayInterface
    {
        if (!isset($this->provider)) {
            $this->provider = OPay::create('PayPal_Rest');
            $this->provider->setTestMode((bool) $this->getValue('testmode', false));
            $this->provider->initialize($this->getServiceItem()->getConfig());
        }

        return $this->provider;
    }

    protected function getPaymentForm(\Aimeos\MShop\Order\Item\Iface $order, array $params): \Aimeos\MShop\Common\Helper\Form\Iface
    {
        $list = [];
        $baseItem = $this->getOrderBase($order->getBaseId(), \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS);
        $addresses = $baseItem->getAddress(\Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT);

        $parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE
        | \Aimeos\MShop\Order\Item\Base\Base::PARTS_PRODUCT
        | \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS;

        $base = $this->getOrderBase($order->getBaseId(), $parts);
        $data = $this->getData($base, $order->getId(), $params);
        $urls = $this->getPaymentUrls();
        try {
            $response = $this->sendRequest($order, $data);
            $approvalUrl = "" ;
            $testmode = "" ;
            $countryid = "" ;
            if ($response->isSuccessful()) {
                $this->setOrderData($order, ['Transaction' => $response->getTransactionReference()]);
                $this->saveRepayData($response, $base->getCustomerId());
                $this->saveOrder($order->setPaymentStatus(Status::PAY_UNFINISHED));

                if (!empty($response->getData()['links']['1']['href'])) {
                    $approvalUrl = $response->getData()['links']['1']['href'] ;
                }

                $addresses = $base->getAddress(\Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT);

                if (($address = current($addresses)) !== false) {
                    $countryid = $address->getCountryId();
                }

                if (empty($approvalUrl) || empty($countryid)) {
                    throw new \Aimeos\MShop\Service\Exception($response->getMessage());
                }
            } elseif ($response->isRedirect()) {
                $this->setOrderData($order, ['Transaction' => $response->getTransactionReference()]);
                return $this->getRedirectForm($response);
            } else {
                $this->saveOrder($order->setPaymentStatus(Status::PAY_REFUSED));
                throw new \Aimeos\MShop\Service\Exception($response->getMessage());
            }
        } catch (\Exception $e) {
            throw new \Aimeos\MShop\Service\Exception($e->getMessage());
        }
        // end of preparation section to call paypal plus */

        return new \Aimeos\MShop\Common\Helper\Form\Standard('', '', [], true, $this->getPayPalPlusJs($approvalUrl, $countryid));
    }

    protected function getPayPalPlusJs($approvalUrl, $countryid)
    {
        $testmode = $this->getConfigValue('testmode') ? 'sandbox' : 'live';
        return '
		<script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript">
		</script>
		<div id="ppplus">
		</div>
		<script type="application/javascript">
		var approvalUrl = \'' . $approvalUrl . '\';
		var ppp = PAYPAL.apps.PPP({
			"approvalUrl": approvalUrl,
			"placeholder": "ppplus",
			"country":"' . $countryid . '" ,
			"mode": "' . $testmode . '",
			onContinue: function () { ppp.doCheckout(); } ,
		});
		</script>
		';
    }


    public function process(\Aimeos\MShop\Order\Item\Iface $order, array $params = []): ?\Aimeos\MShop\Common\Helper\Form\Iface
    {
        return $this->getPaymentForm($order, $params);
    }


    public function updateSync(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Aimeos\MShop\Order\Item\Iface $order
    ): \Aimeos\MShop\Order\Item\Iface {
        try {
            $provider = $this->getProvider();
            $base = $this->getOrderBase($order->getBaseId());

            $params = (array) $request->getAttributes() + (array) $request->getParsedBody() + (array) $request->getQueryParams();
            $params = $this->getData($base, $order->getId(), $params);
            $params['transactionReference'] = $this->getTransactionReference($base);

            if ($this->getValue('authorize', false) && $provider->supportsCompleteAuthorize()) {
                $response = $provider->completeAuthorize($params)->send();
                $status = Status::PAY_AUTHORIZED;
            } elseif ($provider->supportsCompletePurchase()) {
                $params['PayerID'] = "payerid-" . $this->getContext()->getUserId() ?: "payerid-" . $orderid ;
                if (!empty($request->getQueryParams()['PayerID'])) {
                    $params['PayerID'] = $request->getQueryParams()['PayerID'] ;
                }

                $response = $provider->completePurchase($params)->send();
                $status = Status::PAY_RECEIVED;
            } else {
                return $order;
            }


            // next command that get TransactionID was $response->getTransactionId() but it doesn't work
            if ($response->getRequest()->getTransactionId() != $order->getId()) {
                return $order;
            }


            if (method_exists($response, 'isSuccessful') && $response->isSuccessful()) {
                $order->setPaymentStatus($status);
            } elseif (method_exists($response, 'isPending') && $response->isPending()) {
                $order->setPaymentStatus(Status::PAY_PENDING);
            } elseif (( method_exists($response, 'isCancelled') && $response->isCancelled() )
                || ( !empty($response->getData()['name']) && $response->getData()['name'] == "PAYMENT_NOT_APPROVED_FOR_EXECUTION")
            ) {
                $order->setPaymentStatus(Status::PAY_CANCELED);
            } elseif (method_exists($response, 'isRedirect') && $response->isRedirect()) {
                $url = $response->getRedirectUrl();
                throw new \Aimeos\MShop\Service\Exception(sprintf('Unexpected redirect: %1$s', $url));
            } else {
                if ($order->getPaymentStatus() === Status::PAY_UNFINISHED) {
                    $this->saveOrder($order->setPaymentStatus(Status::PAY_REFUSED));
                }
                throw new \Aimeos\MShop\Service\Exception($response->getMessage());
            }

            $this->setOrderData($order, ['Transaction' => $response->getTransactionReference()]);
            $this->saveRepayData($response, $base->getCustomerId());
            $this->saveOrder($order);
        } catch (\Exception $e) {
            throw new \Aimeos\MShop\Service\Exception($e->getMessage());
        }
        return $order;
    }
}
