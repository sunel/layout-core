<?php

namespace Layout\Core\Element;

use ArrayAccess;
use Layout\Core\Readers;
use Layout\Core\Xml\Element;
use Layout\Core\Data\LayoutStack;
use Layout\Core\Contracts\ReaderInterface;

class NodeReader implements ArrayAccess
{
    /**
     * Anonymous block counter
     *
     * @var int
     */
    protected $counter = 0;

    /**
     * @var Layout\Core\Contracts\ReaderInterface[]
     */
    protected $nodeReaders = [];

    /**
     * Constructor
     *
     * @param array $reader
     */
    public function __construct(array $readers = [])
    {
        $readers = array_merge([
            'head' => Readers\Head::class,
            'body' => Readers\Body::class,
            'block' => Readers\Block::class,
            'referenceBlock' => Readers\Block::class,
            'container' => Readers\Container::class,
            'referenceContainer' => Readers\Container::class,
            'move' => Readers\Move::class,
        ], $readers);

        foreach ($readers as $key => $reader) {
            $this->nodeReaders[$key] = new $reader($this);
        }
    }

    /**
     * Traverse through all nodes
     *
     * @param Layout\Core\Data\LayoutStack $stack
     * @param Layout\Core\Xml\Element $element
     * @return $this
     */
    public function read(LayoutStack $stack, Element $element)
    {
        /** @var $node Layout\Core\Xml\Element */
        foreach ($element as $node) {
            $nodeName = $node->getName();
            if (!isset($this->nodeReaders[$nodeName])) {
                continue;
            }
            /** @var $reader ReaderInterface */
            $reader = $this->nodeReaders[$nodeName];
            $reader->read($stack, $node);
        }
    }

    /**
     * Populate queue for generating structural elements
     *
     * @param \Layout\Core\Data\LayoutStack $stack
     * @param \Layout\Core\Xml\Element $currentNode
     * @param \Layout\Core\Xml\Element $parentNode
     * @return string
     */
    public function scheduleStructure($stack, $currentNode, $parentNode)
    {
        // if it hasn't a name it must be generated
        if (!(string)$currentNode->getAttribute('name')) {
            $name = $this->_generateAnonymousName($parentNode->getElementName() . '_schedule_block');
            $currentNode->setAttribute('name', $name);
        }
        $path = $name = (string)$currentNode->getAttribute('name');

        // Prepare scheduled element with default parameters [type, alias, parentName, siblingName, isAfter]
        $row = [
            'type'              => $currentNode->getName(),
            'as'                => '',
            'parent_name'       => '',
            'child_name'        => null,
            'is_after'          => true,
        ];

        $parentName = $parentNode->getElementName();
        //if this element has a parent element, there must be reset [alias, parentName, siblingName, isAfter]
        if ($parentName) {
            $row['as'] = (string)$currentNode->getAttribute('as');
            $row['parent_name'] = $parentName;
            list($row['child_name'], $row['is_after']) = $this->_beforeAfterToSibling($currentNode);

            // materialized path for referencing nodes in the plain array of _stack
            if ($stack->hasPath($parentName)) {
                $path = $stack->getPath($parentName) . '/' . $path;
            }
        }

        $this->_overrideElementWorkaround($stack, $name, $path);
        $stack->setPathElement($name, $path);
        $stack->setStructureElement($name, $row);
        return $name;
    }

    /**
     * Destroy previous element with same name and all its children, if new element overrides it
     *
     * This is a workaround to handle situation, when an element emerges with name of element that already exists.
     * In this case we destroy entire structure of the former element and replace with the new one.
     *
     * @param \Layout\Core\Data\LayoutStack $stack
     * @param string $name
     * @param string $path
     * @return void
     */
    protected function _overrideElementWorkaround($stack, $name, $path)
    {
        if ($stack->hasStructureElement($name)) {
            $stack->setStructureElementData($name, []);
            foreach ($stack->getPaths() as $potentialChild => $childPath) {
                if (0 === strpos($childPath, "{$path}/")) {
                    $stack->unsetPathElement($potentialChild);
                    $stack->unsetStructureElement($potentialChild);
                }
            }
        }
    }

    /**
     * Analyze "before" and "after" information in the node and return sibling name and whether "after" or "before"
     *
     * @param \Layout\Core\Xml\Element $node
     * @return array
     */
    protected function _beforeAfterToSibling($node)
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

    /**
     * Generate anonymous element name for structure
     *
     * @param string $class
     * @return string
     */
    protected function _generateAnonymousName($class)
    {
        $key = strtolower(trim($class, '_'));
        return $key . $this->counter++;
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->nodeReaders[] = $value;
        } else {
            $this->nodeReaders[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->nodeReaders[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->nodeReaders[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->nodeReaders[$offset]) ? $this->nodeReaders[$offset] : null;
    }
}
