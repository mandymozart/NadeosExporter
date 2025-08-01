<?php declare(strict_types=1);

namespace NadeosData\Storefront\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use DateTime;

trait DateRequestTrait {
    function getDateFromRequests(Request $request): DateTime
    {
        // Check for explicit date_from parameter first
        $dateFromParam = $request->query->get('date_from');
        if ($dateFromParam) {
            try {
                return new DateTime($dateFromParam);
            } catch (\Exception $e) {
                throw new BadRequestHttpException('Das Datum "date_from" ist fehlerhaft.');
            }
        }

        // Fallback to year/month logic
        $paramsChecked = ['year', 'month'];
        $allParamsSet = 2 === count(array_intersect($paramsChecked, $request->query->keys()));
        
        if (false === $allParamsSet) {
            // Default to current month for backward compatibility with existing controllers
            $now = new DateTime();
            return new DateTime($now->format('Y-m-01 00:00:00'));
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
            // Start of month for "from" date
            $date = new DateTime(sprintf('%d-%02d-01 00:00:00', $year, $month));
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Das angegebene Datum ist Fehlerhaft.');
        }

        return $date;
    }

    function getDateToRequests(Request $request): ?DateTime
    {
        // Check for explicit date_to parameter first
        $dateToParam = $request->query->get('date_to');
        if ($dateToParam) {
            try {
                return new DateTime($dateToParam);
            } catch (\Exception $e) {
                throw new BadRequestHttpException('Das Datum "date_to" ist fehlerhaft.');
            }
        }

        // Fallback to year/month logic
        $paramsChecked = ['year', 'month'];
        $allParamsSet = 2 === count(array_intersect($paramsChecked, $request->query->keys()));
        
        if (false === $allParamsSet) {
            return null; // Let caller handle default
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
            // End of month for "to" date
            $date = new DateTime(sprintf('%d-%02d-01', $year, $month));
            $date->modify('last day of this month')->setTime(23, 59, 59);
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Das angegebene Datum ist Fehlerhaft.');
        }

        return $date;
    }
}