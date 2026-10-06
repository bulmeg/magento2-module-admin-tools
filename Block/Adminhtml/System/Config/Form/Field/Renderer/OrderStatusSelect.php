<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Block\Adminhtml\System\Config\Form\Field\Renderer;

use Bulmeg\AdminTools\Model\Config\Source\OrderStatus;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class OrderStatusSelect extends Select
{
    public function __construct(
        Context $context,
        private readonly OrderStatus $orderStatus,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function setInputName(string $value): self
    {
        return $this->setData('name', $value);
    }

    public function setInputId(string $value): self
    {
        return $this->setId($value);
    }

    protected function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->orderStatus->toOptionArray());
        }

        return parent::_toHtml();
    }
}
