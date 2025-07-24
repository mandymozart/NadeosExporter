<?php declare(strict_types=1);

namespace NadeosData\Services;

use DateTime;
use NadeosData\Dto\{
    Commission,
    CommissionItem,
    CommissionCollection
};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Mail\Service\SendMailTemplate AS Mailer;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\Content\Mail\Service\SendMailTemplateParams;
use Shopware\Core\Defaults;
use Symfony\Component\Mime\Address;
use Shopware\Core\Framework\Uuid\Uuid;
use NadeosData\Services\CommissionService;

class CommissionMailService
{
    const MAIL_TEMPLATE_TYPE = 'nadeos.provision.commission';

    public function __construct(
        private readonly CommissionService $commissionService,
        private readonly LoggerInterface $logger,
        private readonly Mailer $mailer,
        private readonly EntityRepository $mailTemplateRepository
    ) {}

    private function getMailTemplate()
    {
        $mailTemplate = $this->mailTemplateRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('mailTemplateType.technicalName', self::MAIL_TEMPLATE_TYPE))
                ->setLimit(1)
            , Context::createDefaultContext()
        )->first();

        return $mailTemplate;
    }

    public function sendMails(DateTime $date, string $group = null, string $domain, string $testmail = null): void
    {
        $commissionCollection = $this->commissionService->list($date, $group);
        $domain = rtrim($domain, '/');

        $template = $this->getMailTemplate();
        foreach($commissionCollection as $commission) {
            $email = $testmail ?? $commission->email;

            $params = new SendMailTemplateParams(
                mailTemplateId: $template->getId(),
                languageId: Defaults::LANGUAGE_SYSTEM,
                recipients: [
                    new Address($email)
                ],
                data: [
                    'salutation' => $commission->salutation,
                    'firstname'  => $commission->firstname,
                    'lastname'   => $commission->lastname,
                    'url'        => sprintf(
                        '%s/%s',
                        $domain,
                        $commission->urlPdf
                    ),
                    'period'     => sprintf(
                        '%d-%d',
                        $commission->orderYear,
                        $commission->orderMonth
                    )
                ],
                attachments: [],
            );
    
            $this->mailer->send(
                $params,
                Context::createDefaultContext()
            );
        }
    }
}