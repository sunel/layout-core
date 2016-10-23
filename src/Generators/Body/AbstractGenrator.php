<?php 

namespace Layout\Core\Generators\Body;

use Layout\Core\Data\Structure;
use Layout\Core\Data\LayoutStack;
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
     * @param Layout\Core\Data\LayoutStack $stack
     * @param Layout\Core\Data\Structure $structure
     * @return $this
     */
    abstract public function generate(LayoutStack $stack, Structure $structure);
}