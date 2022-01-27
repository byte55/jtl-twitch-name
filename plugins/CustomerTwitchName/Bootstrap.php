<?php

namespace nerdig\CustomerTwitchName;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Event\Customer\CustomerBeforePullEvent;
use jtl\Connector\Event\CustomerOrder\CustomerOrderAfterPullEvent;
use jtl\Connector\Plugin\IPlugin;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class Bootstrap
 * @package nerdig\CustomerTwitchName
 */
class Bootstrap implements IPlugin
{
    /**
     * @param EventDispatcher $dispatcher
     */
    public function registerListener(EventDispatcher $dispatcher)
    {
        error_log("Boostrapper Wurde ausgeführt", 0);
        Logger::write("Boostrapper Wurde ausgeführt");
        $dispatcher->addListener(CustomerOrderAfterPullEvent::EVENT_NAME, [
            new CustomerTwitchName(),
            'onCustomerOrderAfterPullAction'
        ]);

        $dispatcher->addListener(CustomerBeforePullEvent::EVENT_NAME, [
            new CustomerTwitchName(),
            'onCustomerOrderAfterPullAction'
        ]);
    }
}
