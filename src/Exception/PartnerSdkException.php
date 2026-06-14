<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Exception;

/**
 * Marker interface implemented by every exception thrown by the SDK,
 * so consumers can catch them all with a single catch block.
 */
interface PartnerSdkException extends \Throwable
{
}
