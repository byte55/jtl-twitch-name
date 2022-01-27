<?php
/*
Plugin Name: Twitch to JTL
*/

namespace jtl\CustomerOrderAdditionalData;

use jtl\Connector\Event\CustomerOrder\CustomerOrderAfterPullEvent;
use jtl\Connector\Plugin\IPlugin;
use Symfony\Component\EventDispatcher\EventDispatcher;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Model\CustomerOrder;
use jtl\Connector\Model\CustomerOrderAttr;

/**
 * Class Bootstrap
 * @package jtl\CustomerOrderAdditionalData
 */
class Bootstrap implements IPlugin
{
    /**
     * @param EventDispatcher $dispatcher
     */
    public function registerListener(EventDispatcher $dispatcher)
    {
        $dispatcher->addListener(CustomerOrderAfterPullEvent::EVENT_NAME, [
            new CustomerOrderListener(),
            'onCustomerOrderAfterPullAction'
        ]);
    }
}

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
