<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Http;

interface Transport
{
    /**
     * Sends the request without following redirects; non-2xx responses are returned, not thrown.
     */
    public function send(Request $request): Response;
}
