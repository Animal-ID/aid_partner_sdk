<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Exception;

use AnimalId\PartnerSdk\Http\Response;

/**
 * The server replied, but the body could not be interpreted (e.g. invalid JSON).
 */
final class UnexpectedResponseException extends \RuntimeException implements PartnerSdkException
{
    /** @var Response|null */
    private $response;

    public function __construct(string $message, ?Response $response = null)
    {
        parent::__construct($message);
        $this->response = $response;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }
}
