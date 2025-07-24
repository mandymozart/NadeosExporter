<?php declare(strict_types=1);

namespace NadeosData\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Exception;

#[AsCommand(
    name:        'nadeos:export-bmd-customers',
    description: 'Nadeos Export BMD - orders',
)]
class BMDExportCustomersCommand extends BMDExportOrdersCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('export bmd file for customers.');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $date = $this->getDateFromArguments($input);
            $file = $this->service->exportCustomers($date);

            $this->saveFile('customers', $date, $file);

            $this->logger->info('nadeos.export.bmd-customers successfull exported');

            return 0;
        }
        catch (Exception $error) {
            $this->logger->error($error->getMessage());
            $this->logger->error('nadeos.export.bmd-customers failed');

            return 1;
        }
    }
}
