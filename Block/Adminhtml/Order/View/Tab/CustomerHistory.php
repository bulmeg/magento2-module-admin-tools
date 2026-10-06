<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Block\Adminhtml\Order\View\Tab;

use Bulmeg\AdminTools\ViewModel\CustomerHistory as CustomerHistoryViewModel;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Widget\Tab\TabInterface;

class CustomerHistory extends Template implements TabInterface
{
    public function getTabLabel(): string
    {
        return (string)__('Customer Order History');
    }

    public function getTabTitle(): string
    {
        return $this->getTabLabel();
    }

    public function canShowTab(): bool
    {
        $viewModel = $this->getData('view_model');

        return $viewModel instanceof CustomerHistoryViewModel && $viewModel->isAllowed();
    }

    public function isHidden(): bool
    {
        return false;
    }
}
