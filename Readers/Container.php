<?php 

namespace Layout\Core\Readers;

use Layout\Core\Xml\Element;
use Layout\Core\Data\Stack;
use Layout\Core\Element\NodeReader;
use Layout\Core\Contracts\ReaderInterface;

class Container implements ReaderInterface
{
    /**
     * @var Layout\Core\Element\NodeReader
     */
    protected $nodeReader;

    /**
     * Constructor
     *
     * @param NodeReader $reader
     */
    public function __construct(NodeReader $reader)
    {
        $this->nodeReader = $reader;
    }

    /**
     * Traverse through all nodes
     *
     * @param Layout\Core\Data\Stack $stack
     * @param Layout\Core\Xml\Element $element
     * @return $this
     */
    public function read(Stack $stack, Element $element)
    {
        switch ($element->getName()) {
            case 'container':
                $this->nodeReader->scheduleStructure(
                    $stack,
                    $element,
                    $element->getParent()
                );
                $this->mergeContainerAttributes($stack, $element);
                break;
            case 'referenceContainer':
                $this->containerReference($stack, $element);
                break;

            default:
                break;
        }
        $this->nodeReader->read($stack, $element);
    }

    /**
     * Traverse through all nodes
     *
     * @param Layout\Core\Data\Stack $stack
     * @param Layout\Core\Xml\Element $currentElement
     * @return $this
     */
    protected function mergeContainerAttributes(Stack $stack, Element $currentElement)
    {
        $containerName = $currentElement->getAttribute('name');
        $elementData = $stack->getStructureElementData($containerName);

        if (isset($elementData['attributes'])) {
            $keys = array_keys($elementData['attributes']);
            foreach ($keys as $key) {
                if (isset($currentElement[$key])) {
                    $elementData['attributes'][$key] = (string)$currentElement[$key];
                }
            }
        } else {
            $elementData['attributes'] = $this->getAttributes($currentElement);
        }
        $stack->setStructureElementData($containerName, $elementData);
    }

    /**
     * Handling reference of container
     *
     * If attribute remove="true" then add the element to list remove,
     * else merge container attributes
     *
     * @param Layout\Core\Data\Stack $stack
     * @param Layout\Core\Xml\Element $currentElement
     * @return $this
     */
    protected function containerReference(Stack $stack, Element $currentElement)
    {
        $containerName = $currentElement->getAttribute('name');
        $containerRemove = filter_var($currentElement->getAttribute('remove'), FILTER_VALIDATE_BOOLEAN);

        if ($containerRemove) {
            $stack->setElementToRemoveList($containerName);
        } else {
            $this->mergeContainerAttributes($stack, $currentElement);
        }
    }

    /**
     * Get all attributes for current dom element
     *
     * @param \Layout\Core\Element $element
     * @return array
     */
    protected function getAttributes($element)
    {
        $attributes = [];
        foreach ($element->attributes() as $attrName => $attrValue) {
            $attributes[$attrName] = (string)$attrValue;
        }
        return $attributes;
    }
}
