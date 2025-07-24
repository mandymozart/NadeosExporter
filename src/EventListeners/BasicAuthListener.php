<?php declare(strict_types=1);

namespace NadeosData\EventListeners;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Response;

class BasicAuthListener
{
    private const AUTH_USERNAME = "nadeosd41d8cd98f0";
    private const AUTH_PASSWORD = "d41d8cd98f00b204e9800998ecf8427e";

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Authentifizierung nur für bestimmte Pfade aktivieren
        $protectedPaths = ['/route1', '/route2', '/route3'];
        if (!in_array($request->getPathInfo(), $protectedPaths, true)) {
            return;
        }

        $authHeader = $request->headers->get('Authorization');

        if (!$this->isAuthorized($authHeader)) {
            $response = new Response('Unauthorized', Response::HTTP_UNAUTHORIZED, [
                'WWW-Authenticate' => 'Basic realm="Secured Area"',
            ]);
            $event->setResponse($response);
        }
    }

    private function isAuthorized(?string $authHeader): bool
    {
        if (!$authHeader || strpos($authHeader, 'Basic ') !== 0) {
            return false;
        }

        $encodedCredentials = substr($authHeader, 6);
        $decodedCredentials = base64_decode($encodedCredentials);
        [$username, $password] = explode(':', $decodedCredentials, 2) + [null, null];

        return $username === self::AUTH_USERNAME && $password === self::AUTH_PASSWORD;
    }
}