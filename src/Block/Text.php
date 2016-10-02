<?php

namespace Layout\Core\Block;

class Text extends \Layout\Core\Block
{
    public function getTemplate()
    {
        return '';
    }

    protected function getView($fileName, $viewVars)
    {
        return '';
    }

    public function setText($text)
    {
        $this->setData('text', $text);

        return $this;
    }
    public function getText()
    {
        return $this->getData('text');
    }
    public function addText($text, $before = false)
    {
        if ($before) {
            $this->setText($text.$this->getText());
        } else {
            $this->setText($this->getText().$text);
        }
    }
    protected function _toHtml()
    {
        if (!$this->_beforeToHtml()) {
            return '';
        }

        return $this->getText();
    }
}
