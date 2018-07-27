<?php

namespace  Layout\Core\Contracts;

use Layout\Core\Xml\Element;
use Layout\Core\Data\Stack;

interface ReaderInterface
{
    /**
     * Read children elements structure and fill stack
     *
     * @param Stack $stack
     * @param Element $element
     * @return $this
     */
    public function read(Stack $stack, Element $element);
}
