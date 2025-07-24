<?php declare(strict_types=1);

namespace NadeosData\Migration;

use DateTime;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1562240231AddMailTemplate extends MigrationStep
{
    const MAIL_TEMPLATE_DE_HTML = <<<MAIL
        <div style="font-family:arial; font-size:12px;">
            <h2>Hallo {{ salutation }} {{ firstname }} {{ lastname }} </h2>

            <p>Diese E-Mail enthält den Link zu Ihrer Provisionsgutschrift für {{ period }}</p>
            
            <p><a href="{{ url }}" color="#8CBE22">Download</a></p>
            
            <p>Vielen DANK für den tollen <b>Einsatz</b> und <i>viel Erfolg</i> im nächsten Monat.</p>
        </div>
    MAIL;

    const MAIL_TEMPLATE_DE_PLAIN = <<<MAIL
Hallo {{ salutation }} {{ firstname }} {{ lastname }}!

Diese E-Mail enthält den Link zu Ihrer Provisionsgutschrift für {{ period }}
{{ url }}

Vielen DANK für den tollen Einsatz und viel Erfolg im nächsten Monat.
MAIL;

    const MAIL_TEMPLATE_EN_HTML = <<<MAIL
        <div style="font-family:arial; font-size:12px;">
            <h2>Hi {{ salutation }} {{ firstname }} {{ lastname }}! </h2>

            <p>This E-Mail contains the link to your commission credit for {{ period }}</p>
            
            <p><a href="{{ url }}" color="#8CBE22">Download</a></p>
            
            <p>Thank you very much for the great <b>effort</b> and <i>best of luck</i> in the coming month.</p>
        </div>
    MAIL;

    const MAIL_TEMPLATE_EN_PLAIN = <<<MAIL
Hi {{ salutation }} {{ firstname }} {{ lastname }}!

This E-Mail contains the link to your commission credit for {{ period }}
{{ url }}
    
Thank you very much for the great effort and best of luck in the coming month.
MAIL;

    public function getCreationTimestamp(): int
    {
        return 1562240231;
    }

    public function update(Connection $connection): void
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId($connection);

        $this->createMailTemplate($connection, $mailTemplateTypeId);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getMailTemplateTypeId(Connection $connection): string
    {
        $mailTemplateTypeId = Uuid::randomHex();

        $defaultLangId  = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId       = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->insert('mail_template_type', [
            'id'                    => Uuid::fromHexToBytes($mailTemplateTypeId),
            'technical_name'        => 'nadeos.provision.commission',
            'available_entities'    => json_encode([
                'customer'  => 'customer',
                'order'     => 'order',
                'product'   => 'product'
            ]),
            'created_at'            => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id'           => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name'                  => 'Nadeos Provision Commission',
                'created_at'            => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($deLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id'           => $deLangId,
                'name'                  => 'Nadeos Provision Vorlage',
                'created_at'            => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $mailTemplateTypeId;
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<SQL
        SELECT `language`.`id`
        FROM `language`
        INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
        WHERE `locale`.`code` = :code
        SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();

        if (empty($languageId)) {
            return null;
        }

        return $languageId;
    }

    private function createMailTemplate(Connection $connection, string $mailTemplateTypeId): void
    {
        $mailTemplateId = Uuid::randomHex();

        $enGbLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deDeLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->executeStatement("
            INSERT IGNORE INTO `mail_template` (id, mail_template_type_id, system_default, created_at)
            VALUES (:id, :mailTemplateTypeId, :systemDefault, :createdAt)
        ",[
            'id'                    => Uuid::fromHexToBytes($mailTemplateId),
            'mailTemplateTypeId'    => Uuid::fromHexToBytes($mailTemplateTypeId),
            'systemDefault'         => 0,
            'createdAt'             => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if (!empty($enGbLangId)) {
            $connection->executeStatement("
                INSERT IGNORE INTO `mail_template_translation` (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
                VALUES (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
            ",[
                'mailTemplateId'    => Uuid::fromHexToBytes($mailTemplateId),
                'languageId'        => $enGbLangId,
                'senderName'        => '',
                'subject'           => 'Your provision commission',
                'description'       => 'provision commissions',
                'contentHtml'       => self::MAIL_TEMPLATE_EN_HTML,
                'contentPlain'      => self::MAIL_TEMPLATE_EN_PLAIN,
                'createdAt'         => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if (!empty($deDeLangId)) {            
            $connection->executeStatement("
                INSERT IGNORE INTO `mail_template_translation` (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
                VALUES (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
            ",[
                'mailTemplateId'    => Uuid::fromHexToBytes($mailTemplateId),
                'languageId'        => $deDeLangId,
                'senderName'        => '',
                'subject'           => 'Ihre Provisionsgutschrift',
                'description'       => 'Provisionsgutschrift',
                'contentHtml'       => self::MAIL_TEMPLATE_DE_HTML,
                'contentPlain'      => self::MAIL_TEMPLATE_DE_PLAIN,
                'createdAt'         => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

    }
}