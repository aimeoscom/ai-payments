<?php

namespace Aimeos\MShop\Service\Provider\Payment;
// This is to use for Stripe Api and SCA method
class StripeApi
    extends \Aimeos\MShop\Service\Provider\Payment\Base
    implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
    public function isImplemented( $what ):bool
    {
        switch( $what )
        {
            case \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_CAPTURE:
            case \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_REFUND:
                return true;
        }
        return false;
    }

    /**
     * Tries to get an authorization or captures the money immediately for the given
     * order if capturing isn't supported or not configured by the shop owner.
     *
     * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
     * @parame array $params Request parameter if available
     * @return \Illuminate\Http\JsonResponse Form object with URL, action
     *  and parameters to redirect to    (e.g. to an external server of the payment
     *  provider or to a local success page)
     */
    public function process( \Aimeos\MShop\Order\Item\Iface $order, array $params = [] ):\Aimeos\MShop\Common\Helper\Form\Iface
    {
        $basket = $this->getOrderBase( $order->getBaseId() );
        $total = ($basket->getPrice()->getValue() + $basket->getPrice()->getCosts()) *100;
        $currency = $basket->getLocale()->getCurrencyId();

        // send the payment details to an external payment gateway


        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
        $intent = \Stripe\PaymentIntent::create([
            'amount' => $total,
            'currency' => $currency,
            'metadata' => ['integration_check' => 'accept_a_payment'],
        ]);

        $status = \Aimeos\MShop\Order\Item\Base::PAY_PENDING;
        $order->setPaymentStatus( $status );
        $this->saveOrder( $order );

        header('Access-Control-Allow-Origin: http://127.0.0.1:3000');
        header('Access-Control-Allow-Credentials: true');
        print json_encode(['data'=>['clientSecret'=>$intent->client_secret],'info'=>[
            'amount' => $total,
            'currency' => $currency,
            'metadata' => ['integration_check' => 'accept_a_payment'],
            'verify'=>$this->getConfigValue( 'payment.url-update' )
        ]]);
        exit;
//        return new \Aimeos\MShop\Common\Helper\Config\Standard( '', 'POST', [] );
    }


    public function updateSync( \Psr\Http\Message\ServerRequestInterface $request, \Aimeos\MShop\Order\Item\Iface $order ):\Aimeos\MShop\Order\Item\Iface
    {
        // extract status from the request
        // map the status value to one of the Aimeos payment status values



        $status = \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
        $order->setPaymentStatus( $status );
        $this->saveOrder( $order );

        return $order;
    }


    public function refund( \Aimeos\MShop\Order\Item\Iface $order ):\Aimeos\MShop\Order\Item\Iface
    {
        $orderid = $order->getId();
        // ask the payment gateway to refund the complete payment for the given order
        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

        $status = \Aimeos\MShop\Order\Item\Base::PAY_REFUND;
        $order->setPaymentStatus( $status );
        $this->saveOrder( $order );
    }
}
