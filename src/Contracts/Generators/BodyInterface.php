<?php

namespace  Layout\Core\Contracts\Generators;

use Layout\Core\Data\Structure;
use Layout\Core\Data\LayoutStack;

interface BodyInterface
{
	
    /**
     * Traverse through all elements of specified schedule structural elements of it
     *
     * @param Layout\Core\Data\LayoutStack $stack
     * @param Layout\Core\Data\Structure $structure
     * @return $this
     */
    public function generate(LayoutStack $stack, Structure $structure);
}
