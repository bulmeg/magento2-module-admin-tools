<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Test\Unit\Model\ResourceModel;

use Bulmeg\AdminTools\Model\Config;
use Bulmeg\AdminTools\Model\Phone\Normalizer;
use Bulmeg\AdminTools\Model\ResourceModel\CustomerOrders;
use Bulmeg\AdminTools\Model\ResourceModel\OrderPhone;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;

class CustomerOrdersTest extends TestCase
{
    public function testOnlyTheSameNumberIsMatchedByPhone(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getPhoneCountryCode')->willReturn('359');
        $orderPhone = $this->createMock(OrderPhone::class);
        $orderPhone->expects($this->once())->method('getCandidates')
            ->with('21234567', 100)
            ->willReturn([
                ['parent_id' => '9', 'telephone' => '+359 2 123 4567'],
                ['parent_id' => '8', 'telephone' => '0888 2 1234567'],
                ['parent_id' => '7', 'telephone' => '02 123 4567'],
                ['parent_id' => '7', 'telephone' => '02/123-45-67'],
                ['parent_id' => '6', 'telephone' => '032 123 4567'],
            ]);
        $customerOrders = new CustomerOrders(
            $this->createMock(ResourceConnection::class),
            $orderPhone,
            new Normalizer($config)
        );

        $this->assertSame([9, 7], $customerOrders->getIdsByPhone('21234567', 100));
    }
}
