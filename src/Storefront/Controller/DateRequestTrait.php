<?php declare(strict_types=1);

namespace NadeosData\Storefront\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use DateTime;

trait DateRequestTrait {
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
}