<?php 

namespace Layout\Core\Generators\Body;

use Layout\Core\Data\Structure;
use Layout\Core\Contracts\Generators\BodyInterface;
use Layout\Core\Page\Generator\Body as BodyGenerator;

abstract class AbstractGenrator implements BodyInterface
{
    /**
     * @var Layout\Core\Page\Generator\Body
     */
    protected $bodyGenerator;

    /**
     * Constructor
     *
     * @param BodyGenerator $generator
     */
    public function __construct(BodyGenerator $generator)
    {
        $this->bodyGenerator = $generator;
    }

    /**
     * Traverse through all nodes
     *
     * @param string $elementName
     * @param string $type
     * @param array $data
     * @param Layout\Core\Data\Structure $structure
     * @return $this
     */
    abstract public function generate($elementName, $type, $data, Structure $structure);
}