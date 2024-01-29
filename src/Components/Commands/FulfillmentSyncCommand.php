<?php
/**
 * Created for plugin-core-logistic
 * Date: 16.01.2024
 * @author: Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Commands;

use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Db\Components\PluginReference;
use SalesRender\Plugin\Core\Commands\MutexCommand;
use SalesRender\Plugin\Core\Logistic\Components\Binding\Binding;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FulfillmentSyncCommand extends MutexCommand
{

    public function __construct()
    {
        parent::__construct('fulfillment:sync');
        $this->addArgument(
            'selectWithinHours',
            InputArgument::REQUIRED,
            'Select only bindings, that updated within passed hours'
        );

        $this->addOption(
            'outdatedOnly',
            'o',
            InputOption::VALUE_NONE,
            'Select only bindings, where sync time lower that update time',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $hours = (int)$input->getArgument('selectWithinHours');
        $time = time() - ($hours * 60 * 60);
        $outdatedOnly = $input->getOption('outdatedOnly') !== false;

        return $this->withMutex(function () use ($time, $outdatedOnly, $output) {
            $condition = [
                'updatedAt[>=]' => $time
            ];

            if ($outdatedOnly) {
                $condition[] = 'syncedAt[<=]updatedAt';
            }

            $records = Connector::db()->select(
                Binding::tableName(),
                ['companyId', 'pluginAlias', 'id'],
                $condition,
            );

            foreach ($records as $record) {
                Registration::freeUpMemory();
                Binding::freeUpMemory();
                Connector::setReference(new PluginReference(
                    $record['companyId'],
                    $record['pluginAlias'],
                    $record['id'],
                ));

                $output->write(json_encode($record) . ' - ');
                $binding = Binding::find();
                $binding->sync();
                $output->writeln('<success>[OK]</success>');
            }

            return self::SUCCESS;
        }, "{$this->getName()}_{$hours}_" . intval($outdatedOnly));
    }

}