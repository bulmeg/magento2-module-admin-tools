<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Test\Unit\Model\Phone;

use Bulmeg\AdminTools\Model\Config;
use Bulmeg\AdminTools\Model\Phone\Normalizer;
use PHPUnit\Framework\TestCase;

class NormalizerTest extends TestCase
{
    private Normalizer $normalizer;

    protected function setUp(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getPhoneCountryCode')->willReturn('359');
        $this->normalizer = new Normalizer($config);
    }

    public function testFormatsOfOneMobileNumberGiveTheSameDigits(): void
    {
        $formats = [
            '0888123456',
            '0888 123 456',
            '0888-123-456',
            '(0888) 123 456',
            '+359 888 123 456',
            '00359888123456',
            '+359 (888) 123-456',
            '+359 0888 123 456',
            '888 123 456',
        ];
        foreach ($formats as $phone) {
            $this->assertSame('888123456', $this->normalizer->normalize($phone), $phone);
        }
    }

    public function testFormatsOfOneLandlineNumberGiveTheSameDigits(): void
    {
        foreach (['02 123 4567', '+359 2 123 4567', '003592 1234567'] as $phone) {
            $this->assertSame('21234567', $this->normalizer->normalize($phone), $phone);
        }
    }

    public function testShortAndEmptyNumbers(): void
    {
        $this->assertSame('', $this->normalizer->normalize(''));
        $this->assertSame('', $this->normalizer->normalize('n/a'));
        $this->assertSame('', $this->normalizer->normalize('0000'));
    }

    public function testKeywordDigits(): void
    {
        $cases = [
            ['0888 123 456', '888123456'],
            ['0888123456', '888123456'],
            ['+359888123456', '888123456'],
            ['00359 888 123 456', '888123456'],
            ['888 123 456', '888123456'],
            ['(0888) 123-456', '888123456'],
            ['02 123 4567', '21234567'],
            ['  0888123456 ', '888123456'],
            ['0888 12', null],
            ['000154321', null],
            ['100154321', null],
            ['888123456', null],
            ['2024-01-15', null],
            ['Иван', null],
            ['ivan@example.com', null],
            ['ORD-0888123456', null],
            ['', null],
        ];
        foreach ($cases as [$keyword, $expected]) {
            $this->assertSame($expected, $this->normalizer->getKeywordDigits($keyword), $keyword);
        }
    }

    public function testMatchDigitsNeedEnoughDigits(): void
    {
        $this->assertSame('888123456', $this->normalizer->getMatchDigits('+359 888 123 456'));
        $this->assertSame('21234567', $this->normalizer->getMatchDigits('02 123 4567'));
        $this->assertNull($this->normalizer->getMatchDigits('02 123 456'));
        $this->assertNull($this->normalizer->getMatchDigits('0000000000'));
        $this->assertNull($this->normalizer->getMatchDigits(''));
    }
}
