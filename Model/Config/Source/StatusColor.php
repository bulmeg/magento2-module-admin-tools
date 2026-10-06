<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class StatusColor implements OptionSourceInterface
{
    public const WHITE = 'white';
    public const GRAY = 'gray';
    public const BLUE = 'blue';
    public const GREEN = 'green';
    public const YELLOW = 'yellow';
    public const ORANGE = 'orange';
    public const RED = 'red';
    public const PURPLE = 'purple';

    public function toArray(): array
    {
        return [
            self::WHITE => __('White'),
            self::GRAY => __('Gray'),
            self::BLUE => __('Blue'),
            self::GREEN => __('Green'),
            self::YELLOW => __('Yellow'),
            self::ORANGE => __('Orange'),
            self::RED => __('Red'),
            self::PURPLE => __('Purple'),
        ];
    }

    public function isValid(string $color): bool
    {
        return array_key_exists($color, $this->toArray());
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->toArray() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
