<?php

namespace Layout\Core\Block;

use Layout\Core\Exceptions\InvalidBlockException;

class Lists extends Text
{
    protected function _toHtml()
    {
        $this->setText('');
        foreach ($this->getSortedChildren() as $name) {
            $block = $this->getLayout()->getBlock($name);
            if (!$block) {
                throw new InvalidBlockException('Invalid block type:'.$block);
            }
            $this->addText($block->toHtml());
        }

        return parent::_toHtml();
    }
}
