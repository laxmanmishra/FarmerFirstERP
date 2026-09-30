<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A request was valid but violates a business rule (e.g. unit already
 * allocated, waiver approver equals requester). Rendered as a friendly
 * message in the UI and as HTTP 422 `business_rule_error` in the API.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message, public readonly string $rule = 'business_rule', public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
