<?php

namespace  Layout\Core\Contracts;

use Layout\Core\Xml\Element;
use Layout\Core\Data\LayoutStack;

interface ReaderInterface
{
    /**
     * Read children elements structure and fill stack
     *
     * @param LayoutStack $stack
     * @param Element $element
     * @return $this
     */
    public function read(LayoutStack $stack, Element $element);
}
