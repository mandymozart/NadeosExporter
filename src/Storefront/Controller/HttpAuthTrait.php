<?php declare(strict_types=1);

namespace NadeosData\Storefront\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait HttpAuthTrait
{
    private const AUTH_USERNAME = "nadeosd41d8cd98f0";
    private const AUTH_PASSWORD = "d41d8cd98f00b204e9800998ecf8427e";

    private function checkAuth(Request $request): ?Response
    {
        $authHeader = $request->query->has('token')
                        ? 'Basic ' . $request->query->get('token')
                        : $request->headers->get('Authorization');

        if (!$this->isAuthorized($authHeader)) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED, [
                'WWW-Authenticate' => 'Basic realm="Secured Area"',
            ]);
        }

        return null;
    }

    private function isAuthorized(?string $authHeader): bool
    {
        if (!$authHeader || strpos($authHeader, 'Basic ') !== 0) {
            return false;
        }

        $encodedCredentials = substr($authHeader, 6);
        $decodedCredentials = base64_decode($encodedCredentials);
        [$providedUsername, $providedPassword] = explode(':', $decodedCredentials, 2) + [null, null];

        return $providedUsername === self::AUTH_USERNAME && $providedPassword === self::AUTH_PASSWORD;
    }

    protected function getToken(): string
    {
        return base64_encode(
            sprintf(
                '%s:%s',
                self::AUTH_USERNAME,
                self::AUTH_PASSWORD
            )
        );
    }
}