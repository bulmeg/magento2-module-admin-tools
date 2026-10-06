<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\PackageInfo;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class ModuleInfo extends Field
{
    private const MODULE_NAME = 'Bulmeg_AdminTools';
    private const REPLACED_MODULES = ['Bulmeg_SearchByPhone', 'Bulmeg_AdminStatusColor'];

    public function __construct(
        Context $context,
        private readonly PackageInfo $packageInfo,
        private readonly ModuleListInterface $moduleList,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $version = (string)$this->packageInfo->getVersion(self::MODULE_NAME);
        $htmlId = $this->_escaper->escapeHtmlAttr((string)$element->getHtmlId());
        $html = '<div class="control-value" id="' . $htmlId . '">'
            . $this->escape(self::MODULE_NAME . ($version !== '' ? ' ' . $version : ''))
            . '</div>';

        $replaced = array_values(array_filter(
            self::REPLACED_MODULES,
            fn (string $module): bool => $this->moduleList->has($module)
        ));
        if ($replaced) {
            $html .= '<div class="message message-warning">'
                . $this->escape((string)__(
                    'Replaced by Admin Tools but still enabled: %1. Disable them: bin/magento module:disable %2',
                    implode(', ', $replaced),
                    implode(' ', $replaced)
                ))
                . '</div>';
        }

        return $html;
    }

    private function escape(string $text): string
    {
        $escaped = $this->_escaper->escapeHtml($text);

        return is_string($escaped) ? $escaped : '';
    }
}
