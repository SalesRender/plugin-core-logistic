<?php
/**
 * Created for plugin-core-logistic
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Factories;


use SalesRender\Plugin\Core\Logistic\Components\Commands\FulfillmentSyncCommand;
use SalesRender\Plugin\Core\Logistic\Helpers\LogisticHelper;
use Symfony\Component\Console\Application;

class ConsoleAppFactory extends \SalesRender\Plugin\Core\Factories\ConsoleAppFactory
{

    public function build(): Application
    {
        if (LogisticHelper::isFulfillment()) {
            $this->addCronTask('*/10 * * * *', 'fulfillment:sync 12 -o');
            $this->addCronTask('15 * * * *', 'fulfillment:sync 24');
            $this->addCronTask('25 5 * * 6', 'fulfillment:sync ' . (24 * 30));
        }

        $this->addBatchCommands();
        $app = parent::build();
        $app->add(new FulfillmentSyncCommand());

        return $app;
    }

}