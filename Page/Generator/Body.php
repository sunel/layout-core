<?php

namespace Layout\Core\Page\Generator;

use Layout\Core\Page\Layout;
use Layout\Core\Data\Structure;
use Layout\Core\Data\Stack;

class Body
{
    /**
     * Anonymous block counter
     *
     * @var int
     */
    protected $counter = 0;

    /**
     * @var Layout\Core\Contracts\Generators\BodyInterface[]
     */
    protected $elementGenerators = [];

    /**
     * The layout instance.
     *
     * @var \Layout\Core\Page\Layout
     */
    protected $layout;

    /**
     * Constructor
     *
     * @param array $generators
     * @param Layout\Core\Page\Layout $layout
     */
    public function __construct(array $generators = [], Layout $layout)
    {
        $this->layout = $layout;

        $generators = array_merge([
            'block' => \Layout\Core\Generators\Body\Block::class,
            'container' => \Layout\Core\Generators\Body\Container::class,
        ], $generators);

        $this->elementGenerators = array_map(function($generator) {
            return new $generator($this);
        }, $generators);
    }

    /**
     * Get layout instance for current page
     *
     * @return \Layout\Core\Page\Layout
     */
    public function layout()
    {
        return $this->layout;
    }

     /**
     * Traverse through all nodes
     *
     * @param Layout\Core\Data\Stack $stack
     * @param Layout\Core\Data\Structure $structure
     * @return $this
     */
    public function generate(Stack $stack, Structure $structure)
    {
        $this->buildStructure($stack, $structure);
        foreach ($stack->getElements() as $elementName => $element) {
            list($type, $data) = $element;
            if (!isset($this->elementGenerators[$type])) {
                continue;
            }
            $generator = $this->elementGenerators[$type];
            $generator->generate($elementName, $type, $data, $structure);            
        }
    }

    /**
     * Build structure that is based on scheduled structure
     *
     * @param Layout\Core\Data\Stack $stack
     * @param Layout\Core\Data\Structure $structure
     * @return $this
     */
    protected function buildStructure(Stack $stack, Structure $structure)
    {
        //Schedule all element into nested structure
        while (false === $stack->isStructureEmpty()) {
            $this->scheduleElement($stack, $structure, key($stack->getStructure()));
        }
        $stack->flushPaths();
        while (false === $stack->isListToSortEmpty()) {
            $this->reorderElements($stack, $structure, key($stack->getListToSort()));
        }
        foreach ($stack->getListToMove() as $elementToMove) {
            $this->moveElementInStructure($stack, $structure, $elementToMove);
        }
        foreach ($stack->getListToRemove() as $elementToRemove) {
            $this->removeElement($stack, $structure, $elementToRemove);
        }
        /*foreach ($stack->getIfconfigList() as $elementToCheckConfig) {
            list($configPath, $scopeType) = $stack->getIfconfigElement($elementToCheckConfig);
            if (!empty($configPath)
                && !$this->scopeConfig->isSetFlag($configPath, $scopeType, $this->scopeResolver->getScope())
            ) {
                $this->removeIfConfigElement($stack, $structure, $elementToCheckConfig);
            }
        }*/
        return $this;
    }

    /**
     * Process queue of structural elements and actually add them to structure, and schedule elements for generation
     *
     * The catch is to populate parents first, if they are not in the structure yet.
     * Since layout updates could come in arbitrary order, a case is possible where an element is declared in reference,
     * while referenced element itself is not declared yet.
     *
     * @param Layout\Core\Data\Stack $stack
     * @param Layout\Core\Data\Structure $structure
     * @param string $key
     * @return void
     */
    public function scheduleElement(
        Stack $stack, Structure $structure,
        $key
    ) {
        $row = $stack->getStructureElement($key);
        $data = $stack->getStructureElementData($key);
        // if we have reference container to not existed element
        if (!isset($row['type'])) {
            $stack->unsetPathElement($key);
            $stack->unsetStructureElement($key);
            return;
        }

        list($type, $alias, $parentName, $siblingName, $isAfter) = array_values($row);
        $name = $this->_createStructuralElement($structure, $key, $type, $parentName . $alias);
        if ($parentName) {
            // recursively populate parent first
            if ($stack->hasStructureElement($parentName)) {
                $this->scheduleElement($stack, $structure, $parentName);
            }
            if ($structure->hasElement($parentName)) {
                try {
                    $structure->setAsChild($name, $parentName, $alias);
                } catch (\Exception $e) {
                }
            } else {
                $stack->setElementToBrokenParentList($key);
            }
        }

        // Move from stack to scheduledElement
        $stack->unsetStructureElement($key);
        $stack->setElement($name, [$type, $data]);

        /**
         * Some elements provide info "after" or "before" which sibling they are supposed to go
         * Add element into list of sorting
         */
        if ($siblingName) {
            $stack->setElementToSortList($parentName, $name, $siblingName, $isAfter);
        }
    }

    /**
     * Reorder a child of a specified element
     *
     * @param Layout\Core\Data\Stack $stack
     * @param Layout\Core\Data\Structure $structure
     * @param string $elementName
     * @return void
     */
    protected function reorderElements(
        Stack $stack, Structure $structure,
        $elementName
    ) {
        $element = $stack->getElementToSort($elementName);
        $stack->unsetElementToSort($element[Stack::ELEMENT_NAME]);

        if (isset($element[Stack::ELEMENT_OFFSET_OR_SIBLING])) {
            $siblingElement = $stack->getElementToSort(
                $element[Stack::ELEMENT_OFFSET_OR_SIBLING]
            );

            if (
                isset($siblingElement[Stack::ELEMENT_NAME])
                    && $structure->hasElement($siblingElement[Stack::ELEMENT_NAME])
            ) {
                $this->reorderElements(
                    $stack,
                    $structure,
                    $siblingElement[Stack::ELEMENT_NAME]
                );
            }
        }

        $structure->reorderChildElement(
            $element[Stack::ELEMENT_PARENT_NAME],
            $element[Stack::ELEMENT_NAME],
            $element[Stack::ELEMENT_OFFSET_OR_SIBLING],
            $element[Stack::ELEMENT_IS_AFTER]
        );
    }

    /**
     * Move element in scheduled structure
     *
     * @param Layout\Core\Data\Stack $stack
     * @param Layout\Core\Data\Structure $structure
     * @param string $element
     * @return $this
     */
    protected function moveElementInStructure(
        Stack $stack, Structure $structure,
        $element
    ) {
        list($destination, $siblingName, $isAfter, $alias) = $stack->getElementToMove($element);
        $childAlias = $structure->getChildAlias($structure->getParentId($element), $element);
        if (!$alias && false === $structure->getChildId($destination, $childAlias)) {
            $alias = $childAlias;
        }
        $structure->unsetChild($element, $alias);
        try {
            $structure->setAsChild($element, $destination, $alias);
            $structure->reorderChildElement($destination, $element, $siblingName, $isAfter);
        } catch (\OutOfBoundsException $e) {
        }
        $stack->unsetElementFromBrokenParentList($element);
        return $this;
    }

    /**
     * Remove scheduled element
     *
     * @param Layout\Core\Data\Stack $stack
     * @param Layout\Core\Data\Structure $structure
     * @param string $elementName
     * @param bool $isChild
     * @return $this
     */
    protected function removeElement(
        Stack $stack, Structure $structure,
        $elementName,
        $isChild = false
    ) {
        $elementsToRemove = array_keys($structure->getChildren($elementName));
        $stack->unsetElement($elementName);
        foreach ($elementsToRemove as $element) {
            $this->removeElement($stack, $structure, $element, true);
        }
        if (!$isChild) {
            $structure->unsetElement($elementName);
            $stack->unsetElementFromListToRemove($elementName);
        }
        return $this;
    }

    /**
     * Register an element in structure
     *
     * Will assign an "anonymous" name to the element, if provided with an empty name
     *
     * @param Layout\Core\Data\Structure $structure
     * @param string $name
     * @param string $type
     * @param string $class
     * @return string
     */
    protected function _createStructuralElement(Structure $structure, $name, $type, $class)
    {
        if (empty($name)) {
            $name = $this->_generateAnonymousName($class);
        }
        $structure->createElement($name, ['type' => $type]);
        return $name;
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
}
