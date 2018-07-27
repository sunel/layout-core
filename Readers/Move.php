<?php 

namespace Layout\Core\Readers;

use Layout\Core\Xml\Element;
use Layout\Core\Data\Stack;
use Layout\Core\Element\NodeReader;
use Layout\Core\Contracts\ReaderInterface;

class Move implements ReaderInterface
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
        $this->scheduleMove($stack, $element);
    }

   /**
     * Process block element their attributes and children
     *
     * @param Layout\Core\Data\Stack $stack
     * @param Layout\Core\Xml\Element $currentElement
     * @return $this
     */
    protected function scheduleMove(Stack $stack, Element $currentElement)
    {
        $elementName = (string)$currentElement->getAttribute('element');
        $destination = (string)$currentElement->getAttribute('destination');
        $alias = (string)$currentElement->getAttribute('as') ?: '';
        if ($elementName && $destination) {
            list($siblingName, $isAfter) = $this->beforeAfterToSibling($currentElement);
            $scheduledStructure->setElementToMove(
                $elementName,
                [$destination, $siblingName, $isAfter, $alias]
            );
        } else {
            throw new \Exception('Element name and destination must be specified.');
        }
        return $this;
    }

    /**
     * Analyze "before" and "after" information in the node and return sibling name and whether "after" or "before"
     *
     * @param Layout\Core\Xml\Element $node
     * @return array
     */
    protected function beforeAfterToSibling($node)
    {
        $result = [null, true];
        if (isset($node['after'])) {
            $result[0] = (string)$node['after'];
        } elseif (isset($node['before'])) {
            $result[0] = (string)$node['before'];
            $result[1] = false;
        }
        return $result;
    }
}
