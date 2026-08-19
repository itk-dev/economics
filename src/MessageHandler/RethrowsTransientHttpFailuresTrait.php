<?php

namespace App\MessageHandler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

/**
 * Shared by the handlers that call Leantime directly, so the two agree on which 4xx is worth
 * another attempt.
 */
trait RethrowsTransientHttpFailuresTrait
{
    /**
     * The 4xx codes that mean "later" rather than "no". Everything else in the range describes the
     * request itself, and the retry budget cannot change the request.
     */
    private const RETRY_LATER_STATUS_CODES = [408, 423, 425, 429];

    /**
     * @throws ClientExceptionInterface              when the request is worth repeating, so the
     *                                               transport's retry strategy picks it up
     * @throws UnrecoverableMessageHandlingException when it is not
     */
    private function rethrowUnlessPermanent(ClientExceptionInterface $e, LoggerInterface $logger): never
    {
        if (in_array($e->getResponse()->getStatusCode(), self::RETRY_LATER_STATUS_CODES, true)) {
            throw $e;
        }

        $logger->error($e->getMessage());

        throw new UnrecoverableMessageHandlingException($e->getMessage());
    }
}
