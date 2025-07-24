<?php declare(strict_types=1);

namespace NadeosData\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Psr\Log\LoggerInterface;
use DateTime;
use DateTimeZone;
use Exception;
use NadeosData\Services\CommissionMailService;

#[AsCommand(
    name:        'nadeos:provision-mails',
    description: 'Nadeos Provisions Mailer',
)]
class ProvisionsMailCommand extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CommissionMailService $commissionMailService,
        private readonly EntityRepository $salesChannelDomainRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('provisions mailer');

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

    private function getDefaultSalesChannelDomain()
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannel.active', true));
        $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));
        $criteria->setLimit(5);

        $domains = $this->salesChannelDomainRepository->search(
            $criteria,
            Context::createDefaultContext()
        );

        foreach($domains as $domain) {
            $url = $domain->get('url');
            if (str_contains($url, 'http')) {
                return $domain;
            }
        }

        throw new \Exception('No domain found to send mail (please add a domain to sales channel started with "https" protokoll');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $date = $this->getDateFromArguments($input);

        try {
            $this->commissionMailService->sendMails(
                $date,
                null,
                $this->getDefaultSalesChannelDomain()->get('url'),
                // 'tannheimer@shopware-agentur.at'
            );

            $this->logger->info('nadeos.provisions.mails sent successfull');

            return 0;
        }
        catch (Exception $error) {
            $this->logger->error($error->getMessage());
            $this->logger->error('nadeos.provisions.mails sent failed');

            return 1;
        }
    }
}
