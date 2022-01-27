<?php

namespace nerdig\CustomerTwitchName;

use jtl\Connector\Event\CustomerOrder\CustomerOrderAfterPullEvent;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Model\CustomerOrder;
use jtl\Connector\Model\CustomerOrderAttr;

/**
 * Class CustomerOrderListener
 * @package jtl\CustomerOrderAdditionalData
 */
class CustomerTwitchName
{
    /**
     * @param CustomerOrderAfterPullEvent $event
     */
    public function onCustomerOrderAfterPullAction(CustomerOrderAfterPullEvent $event)
    {
        $order = $event->getCustomerOrder();

        $orderId = $order->getId()->getEndpoint();
        $twitch = "Test: ".(string) get_post_meta($orderId, 'billing_twitch', true);
        $order->setCustomerNote($twitch);
        $shipping = $order->getShippingAddress();
        $shipping->setExtraAddressLine($twitch);
        $order->addAttribute(
            (new CustomerOrderAttr())
                ->setKey('Sonstiges')
                ->setValue($twitch)
        );
        error_log("Wurde ausgeführt, Twitch: ".$twitch, 0);
        Logger::write("Wurde ausgeführt, Twitch: ".$twitch);

    }
}
