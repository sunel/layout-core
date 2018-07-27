<?php 

namespace Layout\Core\Readers;

use Layout\Core\Xml\Element;
use Layout\Core\Data\Stack;
use Layout\Core\Element\NodeReader;
use Layout\Core\Contracts\ReaderInterface;

class Block implements ReaderInterface
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
            case 'block':
                $this->scheduleBlock($stack, $element);
                break;
            case 'referenceBlock':
                $this->scheduleReference($stack, $element);
                break;

            default:
                break;
        }
        $this->nodeReader->read($stack, $element);
    }

    /**
     * Process block element their attributes and children
     *
     * @param Layout\Core\Data\Stack $stack
     * @param Layout\Core\Xml\Element $currentElement
     * @return $this
     */
    protected function scheduleBlock(Stack $stack, Element $currentElement)
    {
        $elementName = $this->nodeReader->scheduleStructure(
            $stack,
            $currentElement,
            $currentElement->getParent()
        );
        $data = $stack->getStructureElementData($elementName, []);
        $data['attributes'] = $this->mergeBlockAttributes($data, $currentElement);
        $stack->setStructureElementData($elementName, $data);

        $configPath = (string)$currentElement->getAttribute('ifconfig');
        if (!empty($configPath)) {
            $stack->setElementToIfconfigList($elementName, $configPath);
        }
    }

    /**
     * Schedule reference data
     *
     * @param Layout\Core\Data\Stack $stack
     * @param Layout\Core\Xml\Element $currentElement
     * @return void
     */
    protected function scheduleReference(
        Stack $stack,
        Element $currentElement
    ) {
        $elementName = $currentElement->getAttribute('name');
        $elementRemove = filter_var($currentElement->getAttribute('remove'), FILTER_VALIDATE_BOOLEAN);
        if ($elementRemove) {
            $stack->setElementToRemoveList($elementName);
        } else {
            $data = $stack->getStructureElementData($elementName, []);
            $data['attributes'] = $this->mergeBlockAttributes($data, $currentElement);
            $stack->setStructureElementData($elementName, $data);
        }
    }

    /**
     * Merge Block attributes
     *
     * @param array $elementData
     * @param Layout\Core\Xml\Element $currentElement
     * @return array
     */
    protected function mergeBlockAttributes(array $elementData, Element $currentElement)
    {
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
        return $elementData['attributes'];
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
