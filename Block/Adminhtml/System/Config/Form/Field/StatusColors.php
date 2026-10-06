<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Block\Adminhtml\System\Config\Form\Field;

use Bulmeg\AdminTools\Block\Adminhtml\System\Config\Form\Field\Renderer\ColorSelect;
use Bulmeg\AdminTools\Block\Adminhtml\System\Config\Form\Field\Renderer\OrderStatusSelect;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;

class StatusColors extends AbstractFieldArray
{
    private ?OrderStatusSelect $statusRenderer = null;
    private ?ColorSelect $colorRenderer = null;

    protected function _prepareToRender(): void
    {
        $this->addColumn('status', [
            'label' => __('Order Status'),
            'renderer' => $this->getStatusRenderer(),
        ]);
        $this->addColumn('color', [
            'label' => __('Color'),
            'renderer' => $this->getColorRenderer(),
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = (string)__('Add Status');
    }

    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        $status = $row->getData('status');
        if ($status !== null) {
            $options['option_' . $this->getStatusRenderer()->calcOptionHash($status)] = 'selected="selected"';
        }
        $color = $row->getData('color');
        if ($color !== null) {
            $options['option_' . $this->getColorRenderer()->calcOptionHash($color)] = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
    }

    private function getStatusRenderer(): OrderStatusSelect
    {
        if ($this->statusRenderer === null) {
            $renderer = $this->getLayout()->createBlock(
                OrderStatusSelect::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            if (!$renderer instanceof OrderStatusSelect) {
                throw new \UnexpectedValueException('The order status renderer could not be created.');
            }
            $this->statusRenderer = $renderer;
        }

        return $this->statusRenderer;
    }

    private function getColorRenderer(): ColorSelect
    {
        if ($this->colorRenderer === null) {
            $renderer = $this->getLayout()->createBlock(
                ColorSelect::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            if (!$renderer instanceof ColorSelect) {
                throw new \UnexpectedValueException('The color renderer could not be created.');
            }
            $this->colorRenderer = $renderer;
        }

        return $this->colorRenderer;
    }
}
