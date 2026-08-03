<?php

namespace Ecomstarter\Core\DTO;

use Illuminate\Http\Request;

abstract class BaseDTO
{
    /**
     * Create a DTO from a validated request.
     * Override in each DTO to map request fields.
     */
    abstract public static function fromRequest(Request $request): static;

    /**
     * Create a DTO from a plain array.
     * Useful for consumers, jobs, and tests.
     */
    abstract public static function fromArray(array $data): static;

    /**
     * Convert DTO back to array — for repositories and legacy code.
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}