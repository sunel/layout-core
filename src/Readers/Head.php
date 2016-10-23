<?php 

namespace Layout\Core\Readers;

use Layout\Core\Xml\Element;
use Layout\Core\Data\LayoutStack;
use Layout\Core\Element\NodeReader;
use Layout\Core\Contracts\ReaderInterface;

class Head implements ReaderInterface
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
     * Read children elements structure and fill stack
     *
     * @param LayoutStack $stack
     * @param Element $element
     * @return $this
     */
    public function read(LayoutStack $stack, Element $element)
    {
        foreach ($element as $node) {
            switch ($node->getName()) {
                case 'css':
                    $node->addAttribute('content_type', 'css');
                    $node->addAttribute('rel', 'stylesheet');
                    $stack->addAssets($node->getAttribute('src'), $this->getAttributes($node));
                    break;
                case 'script':
                    $node->addAttribute('content_type', 'js');
                    $stack->addAssets($node->getAttribute('src'), $this->getAttributes($node));
                    break;
                case 'link':
                    $node->addAttribute('content_type', 'link');
                    $stack->addAssets($node->getAttribute('src'), $this->getAttributes($node));
                    break;
                case 'base':
                    $node->addAttribute('content_type', 'base');
                    $stack->addAssets($node->getAttribute('src'), $this->getAttributes($node));
                    break;    
                case 'remove':
                    $stack->removeAssets($node->getAttribute('src'));
                    break;
                case 'title':
                    $stack->setTitle($node);
                    break;
                case 'meta':
                    if ($node->getAttribute('http_equiv')) {
                        $metadataName = $node->getAttribute('http_equiv');
                    } else if ($node->getAttribute('property')) {
                        $metadataName = $node->getAttribute('property');
                    } else {
                        $metadataName = $node->getAttribute('name');
                    }

                    $stack->setMetaData($metadataName, $node->getAttribute('content'));
                    break;
                case 'attribute':
                    $stack->setElementAttribute(
                        'head',
                        $node->getAttribute('name'),
                        $node->getAttribute('value')
                    );
                    break;

            }
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
