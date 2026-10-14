<?php

namespace App\Exceptions;

use RuntimeException;

class ListingImageRuleException extends RuntimeException
{
    public static function limitReached(): self
    {
        return new self(__('app.listings.images.limit_reached'));
    }

    public static function wouldExceedLimit(): self
    {
        return new self(__('app.listings.images.would_exceed_limit'));
    }

    public static function lastImageRequired(): self
    {
        return new self(__('app.listings.images.last_image_required'));
    }
}
