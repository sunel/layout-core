<?php 

namespace Layout\Core\Generators\Body;

use Layout\Core\Data\Structure;

class Container extends AbstractGenrator
{
    /**
     * {@inheritDoc}
     */
    public function generate($elementName, $type, $data, Structure $structure)
    {
        $options = $data['attributes'];
        unset($options['type']);
        foreach ($options as $key => $value) {
            $structure->setAttribute($elementName, $key, $value);
        }
        return $this;
    }
}
