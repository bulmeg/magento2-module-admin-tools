<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Model\Phone;

use Bulmeg\AdminTools\Model\Config;

class Normalizer
{
    public const SIGNIFICANT_DIGITS = 9;
    public const MIN_KEYWORD_DIGITS = 7;
    public const MIN_MATCH_DIGITS = 8;

    private const KEYWORD_PATTERN = '/^[0-9\s+\-().\/]+$/';
    private const PREFIXED_PATTERN = '/^(\+|00?[1-9])/';
    private const SEPARATOR_PATTERN = '/[\s\-().\/]/';

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function normalize(string $phone): string
    {
        $digits = ltrim($this->digitsOnly($phone), '0');
        $countryCode = $this->config->getPhoneCountryCode();
        if ($countryCode !== ''
            && strlen($digits) > self::SIGNIFICANT_DIGITS
            && str_starts_with($digits, $countryCode)
        ) {
            $digits = ltrim(substr($digits, strlen($countryCode)), '0');
        }
        if (strlen($digits) > self::SIGNIFICANT_DIGITS) {
            $digits = substr($digits, -self::SIGNIFICANT_DIGITS);
        }

        return $digits;
    }

    public function getKeywordDigits(string $keyword): ?string
    {
        $keyword = trim($keyword);
        if ($keyword === '' || !preg_match(self::KEYWORD_PATTERN, $keyword)) {
            return null;
        }
        $digits = $this->normalize($keyword);
        if (preg_match(self::PREFIXED_PATTERN, $keyword) && strlen($digits) >= self::MIN_KEYWORD_DIGITS) {
            return $digits;
        }
        if (preg_match(self::SEPARATOR_PATTERN, $keyword) && strlen($digits) >= self::SIGNIFICANT_DIGITS) {
            return $digits;
        }

        return null;
    }

    public function getMatchDigits(string $phone): ?string
    {
        $digits = $this->normalize($phone);

        return strlen($digits) >= self::MIN_MATCH_DIGITS ? $digits : null;
    }

    private function digitsOnly(string $value): string
    {
        return (string)preg_replace('/\D+/', '', $value);
    }
}
