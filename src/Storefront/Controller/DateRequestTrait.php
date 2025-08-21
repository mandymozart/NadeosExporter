<?php declare(strict_types=1);

namespace NadeosData\Storefront\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use DateTime;

trait DateRequestTrait {
    // Legacy date concept
    function getDateFromRequests(Request $request): DateTime
    {
        $paramsChecked = ['year', 'month'];

        $allParamsSet = 2 === count(array_intersect($paramsChecked, $request->query->keys()));
        
        if (false === $allParamsSet) {
            return (new DateTime('now'))->modify('-1 months');
        }

        $year   = $request->query->get('year');
        $month  = $request->query->get('month');

        if (false === is_numeric($year)) {
            throw new BadRequestHttpException('Das Jahr muss eine gültige Zahl sein');
        }

        if (false === is_numeric($month)) {
            throw new BadRequestHttpException('Das Monat muss ein gültiges Monat sein');
        }

        try {
            $date = new DateTime(sprintf('%d-%02d-01', $year, $month));
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Das angegebene Datum ist Fehlerhaft.');
        }

        return $date;
    }
    public function getDateRangeFromRequests(Request $request): DateTime
    {
        $dateFromParam = $request->query->get('date_from');
        
        if (!$dateFromParam) {
            // Default to start of current month if no date_from provided
            $now = new DateTime();
            return new DateTime($now->format('Y-m-01 00:00:00'));
        }
        
        try {
            return new DateTime($dateFromParam);
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Das Datum "date_from" ist fehlerhaft.');
        }
    }

    public function getDateRangeToRequests(Request $request): ?DateTime
    {
        $dateToParam = $request->query->get('date_to');
        
        if (!$dateToParam) {
            return null; // Let caller handle default
        }
        
        try {
            return new DateTime($dateToParam);
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Das Datum "date_to" ist fehlerhaft.');
        }
    }
}