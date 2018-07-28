<?php

namespace  Layout\Core\Contracts\Generators;

use Layout\Core\Data\Structure;

interface BodyInterface
{
    
    /**
     * Traverse through all nodes
     *
     * @param string $elementName
     * @param string $type
     * @param array $data
     * @param Layout\Core\Data\Structure $structure
     * @return $this
     */
    public function generate($elementName, $type, $data, Structure $structure);
}
