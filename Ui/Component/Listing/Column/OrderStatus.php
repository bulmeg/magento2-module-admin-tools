<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Ui\Component\Listing\Column;

use Bulmeg\AdminTools\Model\Config;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class OrderStatus extends Column
{
    public const JS_COMPONENT = 'Bulmeg_AdminTools/js/grid/columns/status';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Config $config,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepare(): void
    {
        if ($this->config->isStatusColorEnabled()) {
            $config = (array)$this->getData('config');
            $config['component'] = self::JS_COMPONENT;
            $config['statusColors'] = (object)$this->config->getStatusColors();
            $this->setData('config', $config);
        }

        parent::prepare();
    }
}
