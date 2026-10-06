<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Test\Unit\Block\Adminhtml\Order\View\Tab;

use Bulmeg\AdminTools\Block\Adminhtml\Order\View\Tab\CustomerHistory;
use Bulmeg\AdminTools\ViewModel\CustomerHistory as CustomerHistoryViewModel;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class CustomerHistoryTest extends TestCase
{
    private function getTab(?bool $allowed): CustomerHistory
    {
        $tab = (new ObjectManager($this))->getObject(CustomerHistory::class);
        if ($allowed !== null) {
            $viewModel = $this->createMock(CustomerHistoryViewModel::class);
            $viewModel->method('isAllowed')->willReturn($allowed);
            $tab->setData('view_model', $viewModel);
        }

        return $tab;
    }

    public function testTabIsShownWhenTheHistoryIsAllowed(): void
    {
        $tab = $this->getTab(true);

        $this->assertTrue($tab->canShowTab());
        $this->assertFalse($tab->isHidden());
        $this->assertSame('Customer Order History', $tab->getTabLabel());
        $this->assertSame($tab->getTabLabel(), $tab->getTabTitle());
    }

    public function testTabIsNotShownWithoutTheRight(): void
    {
        $this->assertFalse($this->getTab(false)->canShowTab());
    }

    public function testTabIsNotShownWithoutTheViewModel(): void
    {
        $this->assertFalse($this->getTab(null)->canShowTab());
    }
}
