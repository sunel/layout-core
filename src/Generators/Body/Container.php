<?php 

namespace Layout\Core\Generators\Body;

use Layout\Core\Data\Structure;
use Layout\Core\Data\LayoutStack;

class Container extends AbstractGenrator
{
    /**
     * {@inheritDoc}
     */
    public function generate(LayoutStack $stack, Structure $structure)
    {
        foreach ($stack->getElements() as $elementName => $element) {
            list($type, $data) = $element;
            if ($type === 'container') {
                $options = $data['attributes'];
                unset($options['type']);
                foreach ($options as $key => $value) {
                    $structure->setAttribute($elementName, $key, $value);
                }
                $stack->unsetElement($elementName);
            }
        }
        return $this;
    }
}
