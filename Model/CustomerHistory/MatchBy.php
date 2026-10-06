<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Model\CustomerHistory;

class MatchBy
{
    public const CUSTOMER = 'customer';
    public const EMAIL = 'email';
    public const PHONE = 'phone';

    public function toArray(): array
    {
        return [
            self::CUSTOMER => __('Customer Account'),
            self::EMAIL => __('Email'),
            self::PHONE => __('Phone'),
        ];
    }
}
