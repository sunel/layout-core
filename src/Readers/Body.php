<?php 

namespace Layout\Core\Readers;

use Layout\Core\Xml\Element;
use Layout\Core\Element\NodeReader;
use Layout\Core\Data\LayoutStack;
use Layout\Core\Contracts\ReaderInterface;

class Body implements ReaderInterface
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
     * @param Layout\Core\Data\LayoutStack $stack
     * @param Layout\Core\Xml\Element $element
     * @return $this
     */
    public function read(LayoutStack $stack, Element $element)
    {
        /** @var $node Layout\Core\Xml\Element */
        foreach ($element as $node) {
            $nodeName = $node->getName();
            if ($nodeName === 'attribute') {
                if ($node->getAttribute('name') == 'class') {
                    $stack->setBodyClass($node->getAttribute('value'));
                } else {
                    $stack->setElementAttribute(
                        'body',
                        $node->getAttribute('name'),
                        $node->getAttribute('value')
                    );
                }
            }
        }
        $this->nodeReader->read($stack, $element);
    }
}
