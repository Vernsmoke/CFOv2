<?php

namespace App\Services;

final class AccountId
{
    public const PATTERN = '/\A[0-9]{2}-[0-9]{5}\z/';
    public const MESSAGE = 'Use the ID format 24-10723 (two digits, a hyphen, and five digits).';
}
