<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Ui\Component\Listing\Column;

use Bulmeg\AdminTools\Model\Config;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Telephone extends Column
{
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
        if (!$this->config->isPhoneSearchEnabled()) {
            $config = (array)$this->getData('config');
            unset($config['filter'], $config['label']);
            $config['componentDisabled'] = true;
            $config['sortable'] = false;
            $this->setData('config', $config);
        }

        parent::prepare();
    }
}
