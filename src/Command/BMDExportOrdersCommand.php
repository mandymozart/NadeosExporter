<?php declare(strict_types=1);

namespace NadeosData\Command;

use Generator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

use NadeosData\Services\BmdExportService;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use SplFileObject;
use DateTime;
use DateTimeZone;
use Exception;

#[AsCommand(
    name:        'nadeos:export-bmd-orders',
    description: 'Nadeos Export BMD - orders',
)]
class BMDExportOrdersCommand extends Command
{
    CONST DIRECTORY_NAME_EXPORTS = 'nadeos.exports';

    public function __construct(
        protected readonly BmdExportService $service,
        protected readonly LoggerInterface  $logger,
        protected readonly Filesystem       $filesystem
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('export bmd order files.');

        $this->addArgument(
            'year',
            InputArgument::REQUIRED,
            'year for orders to export'
        );

        $this->addArgument(
            'month',
            InputArgument::REQUIRED,
            'month for orders to export'
        );
    }

    protected function getDateFromArguments(InputInterface $input): DateTime
    {
        $year  = (int) $input->getArgument('year');
        $month = (int) $input->getArgument('month');

        return new DateTime(
            sprintf(
                "%d-%d-01 00:00:00",
                $year,
                $month
            ),
            new DateTimeZone('UTC')
        );
    }

    protected function saveFile(string $fileNamePrefix, DateTime $date, SplFileObject $file)
    {
        if (false === $this->filesystem->directoryExists(self::DIRECTORY_NAME_EXPORTS)) {
            $this->filesystem->createDirectory(self::DIRECTORY_NAME_EXPORTS);
        }

        $tempFile = fopen('php://temp', 'w+' );
        while (!$file->eof()) {
            fwrite($tempFile, $file->fgets());
        }

        fseek($tempFile, 0);

        $fileName = sprintf(
            '%s_%d_%d.csv',
            $fileNamePrefix,
            $date->format('Y'),
            $date->format('m')
        );

        $this->filesystem->writeStream(
            self::DIRECTORY_NAME_EXPORTS . DIRECTORY_SEPARATOR . $fileName,
            $tempFile
        );

        fclose($tempFile);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $date = $this->getDateFromArguments($input);
            $file = $this->service->exportOrders($date);

            $this->saveFile('orders', $date, $file);

            $this->logger->info('nadeos.export.bmd-orders successfull exported');

            return 0;
        }
        catch (Exception $error) {
            $this->logger->error($error->getMessage());
            $this->logger->error('nadeos.export.bmd-orders failed');

            return 1;
        }
    }
}
