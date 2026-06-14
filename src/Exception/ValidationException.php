<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Exception;

use AnimalId\PartnerSdk\Http\Response;

/**
 * HTTP 422 — the request body failed server-side validation.
 */
final class ValidationException extends ApiException
{
    /** @var array<mixed> Per-field validation errors as returned by the API. */
    private $errors;

    /**
     * @param array<mixed> $errors
     */
    public function __construct(string $message, Response $response, array $errors = [])
    {
        parent::__construct($message, $response);
        $this->errors = $errors;
    }

    /**
     * @return array<mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
