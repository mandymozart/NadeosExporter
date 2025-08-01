<?php declare(strict_types=1);

namespace NadeosData\Storefront\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use DateTime;

trait DateRequestTrait {
    function getDateFromRequests(Request $request): DateTime
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

    function getDateToRequests(Request $request): ?DateTime
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