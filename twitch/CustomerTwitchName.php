<?php


namespace CustomerOrderAdditionalData;

use jtl\Connector\Event\CustomerOrder\CustomerOrderAfterPullEvent;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Model\CustomerOrder;
use jtl\Connector\Model\CustomerOrderAttr;

/**
 * Class CustomerOrderListener
 * @package jtl\CustomerOrderAdditionalData
 */
class CustomerOrderListener
{
    /**
     * @param CustomerOrderAfterPullEvent $event
     */
    public function onCustomerOrderAfterPullAction(CustomerOrderAfterPullEvent $event)
    {
        $order = $event->getCustomerOrder();
        $orderId = $order->getId()->getEndpoint();
        $twitch = (string) get_post_meta($orderId, 'billing_twitch', true);
        $order->addAttribute(
            (new CustomerOrderAttr())
                ->setKey('Twitch')
                ->setValue($twitch)
        );
    }
}
