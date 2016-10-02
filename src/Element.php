<?php

namespace Layout\Core;

class Element extends AbstractXml
{
    public function getBlockName()
    {
        $tagName = (string) $this->getName();
        if ('block' !== $tagName && 'reference' !== $tagName || empty($this['name'])) {
            return false;
        }

        return (string) $this['name'];
    }
}
