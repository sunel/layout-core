<?php

namespace Layout\Core\Data;

use Exception;

class Structure
{
    /**
     * Reserved keys for storing structural relations
     */
    const PARENT = 'parent';

    const CHILDREN = 'children';

    const GROUPS = 'groups';

    /**
     * Name increment counter
     *
     * @var array
     */
    protected $_nameIncrement = [];


    /**
     * @var array
     */
    protected $_elements = [];

    /**
     * Set elements in constructor
     *
     * @param array $elements
     */
    public function __construct(array $elements = null)
    {
        if (null !== $elements) {
            $this->importElements($elements);
        }
    }

    /**
     * Set elements from source
     *
     * @param array $elements
     * @return void
     * @throws Exception if any format issues identified
     */
    public function importElements(array $elements)
    {
        $this->_elements = $elements;
        foreach ($elements as $elementId => $element) {
            if (is_numeric($elementId)) {
                throw new Exception($this->render("Element ID must not be numeric: '%1'.", [$elementId]));
            }
            $this->assertParentRelation($elementId);
            if (isset($element[self::GROUPS])) {
                $groups = $element[self::GROUPS];
                $this->assertArray($groups);
                foreach ($groups as $groupName => $group) {
                    $this->assertArray($group);
                    if ($group !== array_flip($group)) {
                        throw new Exception($this->render(
                            "Invalid format of group '%1': %2",
                            [$groupName, var_export($group, 1)]
                        ));
                    }
                    foreach ($group as $groupElementId) {
                        $this->assertElementExists($groupElementId);
                    }
                }
            }
        }
    }

    /**
     * Register an element in structure
     *
     * Will assign an "anonymous" name to the element, if provided with an empty name
     *
     * @param string $name
     * @param string $type
     * @param string $class
     * @return string
     */
    public function createStructuralElement($name, $type, $class)
    {
        if (empty($name)) {
            $name = $this->generateAnonymousName($class);
        }
        $this->createElement($name, ['type' => $type]);
        return $name;
    }

    /**
     * Generate anonymous element name for structure
     *
     * @param string $class
     * @return string
     */
    protected function generateAnonymousName($class)
    {
        $position = strpos($class, '\\Block\\');
        $key = $position !== false ? substr($class, $position + 7) : $class;
        $key = strtolower(trim($key, '_'));

        if (!isset($this->_nameIncrement[$key])) {
            $this->_nameIncrement[$key] = 0;
        }

        do {
            $name = $key . '_' . $this->_nameIncrement[$key]++;
        } while ($this->hasElement($name));

        return $name;
    }

    /**
     * Reorder a child of a specified element
     *
     * If $offsetOrSibling is null, it will put the element to the end
     * If $offsetOrSibling is numeric (integer) value, it will put the element after/before specified position
     * Otherwise -- after/before specified sibling
     *
     * @param string $parentName
     * @param string $childName
     * @param string|int|null $offsetOrSibling
     * @param bool $after
     * @return void
     */
    public function reorderChildElement($parentName, $childName, $offsetOrSibling, $after = true)
    {
        if (is_numeric($offsetOrSibling)) {
            $offset = (int)abs($offsetOrSibling) * ($after ? 1 : -1);
            $this->reorderChild($parentName, $childName, $offset);
        } elseif (null === $offsetOrSibling) {
            $this->reorderChild($parentName, $childName, null);
        } else {
            $children = array_keys($this->getChildren($parentName));
            if ($this->getChildId($parentName, $offsetOrSibling) !== false) {
                $offsetOrSibling = $this->getChildId($parentName, $offsetOrSibling);
            }
            $sibling = $this->filterSearchMinus($offsetOrSibling, $children, $after);
            if ($childName !== $sibling) {
                $siblingParentName = $this->getParentId($sibling);
                if ($parentName !== $siblingParentName) {
                    throw new Exception(
                        "Broken reference: the '{$childName}' tries to reorder itself towards '{$sibling}', but " .
                        "their parents are different: '{$parentName}' and '{$siblingParentName}' respectively."
                    );
                }
                $this->reorderToSibling($parentName, $childName, $sibling, $after ? 1 : -1);
            }
        }
    }

    /**
     * Search for an array element using needle, but needle may be '-', which means "first" or "last" element
     *
     * Returns first or last element in the haystack, or the $needle argument
     *
     * @param string $needle
     * @param array $haystack
     * @param bool $isLast
     * @return string
     */
    protected function filterSearchMinus($needle, array $haystack, $isLast)
    {
        if ('-' === $needle) {
            if ($isLast) {
                return array_pop($haystack);
            }
            return array_shift($haystack);
        }
        return $needle;
    }

    /**
     * Verify relations of parent-child
     *
     * @param string $elementId
     * @return void
     * @throws Exception
     */
    protected function assertParentRelation($elementId)
    {
        $element = $this->_elements[$elementId];

        // element presence in its parent's nested set
        if (isset($element[self::PARENT])) {
            $parentId = $element[self::PARENT];
            $this->assertElementExists($parentId);
            if (empty($this->_elements[$parentId][self::CHILDREN][$elementId])) {
                throw new Exception($this->render(
                    "Broken parent-child relation: the '%1' is not in the nested set of '%2'.",
                    [$elementId, $parentId]
                ));
            }
        }

        // element presence in its children
        if (isset($element[self::CHILDREN])) {
            $children = $element[self::CHILDREN];
            $this->assertArray($children);
            if ($children !== array_flip(array_flip($children))) {
                throw new Exception($this->render('Invalid format of children: %1', [var_export($children, 1)]));
            }
            foreach (array_keys($children) as $childId) {
                $this->assertElementExists($childId);
                if (!isset(
                    $this->_elements[$childId][self::PARENT]
                ) || $elementId !== $this->_elements[$childId][self::PARENT]
                ) {
                    throw new Exception($this->render(
                        "Broken parent-child relation: the '%1' is supposed to have '%2' as parent.",
                        [$childId, $elementId]
                    ));
                }
            }
        }
    }

    /**
     * Get all elements
     *
     * @return array
     */
    public function elements()
    {
        return $this->_elements;
    }

    /**
     * Create new element
     *
     * @param string $elementId
     * @param array $data
     * @return void
     * @throws Exception if an element with this id already exists
     */
    public function createElement($elementId, array $data)
    {
        if (isset($this->_elements[$elementId])) {
            throw new Exception($this->render("Element with ID '%1' already exists.", [$elementId]));
        }
        $this->_elements[$elementId] = [];
        foreach ($data as $key => $value) {
            $this->setAttribute($elementId, $key, $value);
        }
    }

    /**
     * Get existing element
     *
     * @param string $elementId
     * @return array|bool
     */
    public function getElement($elementId)
    {
        return isset($this->_elements[$elementId]) ? $this->_elements[$elementId] : false;
    }

    /**
     * Whether specified element exists
     *
     * @param string $elementId
     * @return bool
     */
    public function hasElement($elementId)
    {
        return isset($this->_elements[$elementId]);
    }

    /**
     * Remove element with specified ID from the structure
     *
     * Can recursively delete all child elements.
     * Returns false if there was no element found, therefore was nothing to delete.
     *
     * @param string $elementId
     * @param bool $recursive
     * @return bool
     */
    public function unsetElement($elementId, $recursive = true)
    {
        if (isset($this->_elements[$elementId][self::CHILDREN])) {
            foreach (array_keys($this->_elements[$elementId][self::CHILDREN]) as $childId) {
                $this->assertElementExists($childId);
                if ($recursive) {
                    $this->unsetElement($childId, $recursive);
                } else {
                    unset($this->_elements[$childId][self::PARENT]);
                }
            }
        }
        $this->unsetChild($elementId);
        $wasFound = isset($this->_elements[$elementId]);
        unset($this->_elements[$elementId]);
        return $wasFound;
    }

    /**
     * Set an arbitrary value to specified element attribute
     *
     * @param string $elementId
     * @param string $attribute
     * @param mixed $value
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function setAttribute($elementId, $attribute, $value)
    {
        $this->assertElementExists($elementId);
        switch ($attribute) {
            case self::PARENT:
                // break is intentionally omitted
            case self::CHILDREN:
            case self::GROUPS:
                throw new \InvalidArgumentException("Attribute '{$attribute}' is reserved and cannot be set.");
            default:
                $this->_elements[$elementId][$attribute] = $value;
        }
        return $this;
    }

    /**
     * Get element attribute
     *
     * @param string $elementId
     * @param string $attribute
     * @return mixed
     */
    public function getAttribute($elementId, $attribute)
    {
        $this->assertElementExists($elementId);
        if (isset($this->_elements[$elementId][$attribute])) {
            return $this->_elements[$elementId][$attribute];
        }
        return false;
    }

    /**
     * Rename element ID
     *
     * @param string $oldId
     * @param string $newId
     * @return $this
     * @throws Exception if trying to overwrite another element
     */
    public function renameElement($oldId, $newId)
    {
        $this->assertElementExists($oldId);
        if (!$newId || isset($this->_elements[$newId])) {
            throw new Exception($this->render("Element with ID '%1' is already defined.", [$newId]));
        }

        // rename in registry
        $this->_elements[$newId] = $this->_elements[$oldId];

        // rename references in children
        if (isset($this->_elements[$oldId][self::CHILDREN])) {
            foreach (array_keys($this->_elements[$oldId][self::CHILDREN]) as $childId) {
                $this->assertElementExists($childId);
                $this->_elements[$childId][self::PARENT] = $newId;
            }
        }

        // rename key in its parent's children array
        if (isset($this->_elements[$oldId][self::PARENT]) && ($parentId = $this->_elements[$oldId][self::PARENT])) {
            $alias = $this->_elements[$parentId][self::CHILDREN][$oldId];
            $offset = $this->_getChildOffset($parentId, $oldId);
            unset($this->_elements[$parentId][self::CHILDREN][$oldId]);
            $this->setAsChild($newId, $parentId, $alias, $offset);
        }

        unset($this->_elements[$oldId]);
        return $this;
    }

    /**
     * Set element as a child to another element
     *
     * @param string $elementId
     * @param string $parentId
     * @param string $alias
     * @param int|null $position
     * @see _insertChild() for position explanation
     * @return void
     * @throws Exception if attempting to set parent as child to its child (recursively)
     */
    public function setAsChild($elementId, $parentId, $alias = '', $position = null)
    {
        if ($elementId == $parentId) {
            throw new Exception($this->render("The '%1' cannot be set as child to itself.", [$elementId]));
        }
        if ($this->_isParentRecursively($elementId, $parentId)) {
            throw new Exception($this->render(
                "The '%1' is a parent of '%2' recursively, therefore '%3' cannot be set as child to it.",
                [$elementId, $parentId, $elementId]
            ));
        }
        $this->unsetChild($elementId);
        unset($this->_elements[$parentId][self::CHILDREN][$elementId]);
        $this->_insertChild($parentId, $elementId, $position, $alias);
    }

    /**
     * Unset element as a child of another element
     *
     * Note that only parent-child relations will be deleted. Element itself will be retained.
     * The method is polymorphic:
     *   1 argument: element ID which is supposedly a child of some element
     *   2 arguments: parent element ID and child alias
     *
     * @param string $elementId ID of an element or its parent element
     * @param string|null $alias
     * @return $this
     */
    public function unsetChild($elementId, $alias = null)
    {
        if (null === $alias) {
            $childId = $elementId;
        } else {
            $childId = $this->getChildId($elementId, $alias);
        }
        $parentId = $this->getParentId($childId);
        unset($this->_elements[$childId][self::PARENT]);
        if ($parentId) {
            unset($this->_elements[$parentId][self::CHILDREN][$childId]);
            if (empty($this->_elements[$parentId][self::CHILDREN])) {
                unset($this->_elements[$parentId][self::CHILDREN]);
            }
        }
        return $this;
    }

    /**
     * Reorder a child element relatively to specified position
     *
     * Returns new position of the reordered element
     *
     * @param string $parentId
     * @param string $childId
     * @param int|null $position
     * @return int
     * @see _insertChild() for position explanation
     */
    public function reorderChild($parentId, $childId, $position)
    {
        $alias = $this->getChildAlias($parentId, $childId);
        $currentOffset = $this->_getChildOffset($parentId, $childId);
        $offset = $position;
        if ($position > 0) {
            if ($position >= $currentOffset + 1) {
                $offset -= 1;
            }
        } elseif ($position < 0) {
            if ($position < $currentOffset + 1 - count($this->_elements[$parentId][self::CHILDREN])) {
                if ($position === -1) {
                    $offset = null;
                } else {
                    $offset += 1;
                }
            }
        }
        $this->unsetChild($childId)->_insertChild($parentId, $childId, $offset, $alias);
        return $this->_getChildOffset($parentId, $childId) + 1;
    }

    /**
     * Reorder an element relatively to its sibling
     *
     * $offset possible values:
     *    1,  2 -- set after the sibling towards end -- by 1, by 2 positions, etc
     *   -1, -2 -- set before the sibling towards start -- by 1, by 2 positions, etc...
     *
     * Both $childId and $siblingId must be children of the specified $parentId
     * Returns new position of the reordered element
     *
     * @param string $parentId
     * @param string $childId
     * @param string $siblingId
     * @param int $offset
     * @return int
     */
    public function reorderToSibling($parentId, $childId, $siblingId, $offset)
    {
        $this->_getChildOffset($parentId, $childId);
        if ($childId === $siblingId) {
            $newOffset = $this->_getRelativeOffset($parentId, $siblingId, $offset);
            return $this->reorderChild($parentId, $childId, $newOffset);
        }
        $alias = $this->getChildAlias($parentId, $childId);
        $newOffset = $this->unsetChild($childId)->_getRelativeOffset($parentId, $siblingId, $offset);
        $this->_insertChild($parentId, $childId, $newOffset, $alias);
        return $this->_getChildOffset($parentId, $childId) + 1;
    }

    /**
     * Calculate new offset for placing an element relatively specified sibling under the same parent
     *
     * @param string $parentId
     * @param string $siblingId
     * @param int $delta
     * @return int
     */
    private function _getRelativeOffset($parentId, $siblingId, $delta)
    {
        $newOffset = $this->_getChildOffset($parentId, $siblingId) + $delta;
        if ($delta < 0) {
            $newOffset += 1;
        }
        if ($newOffset < 0) {
            $newOffset = 0;
        }
        return $newOffset;
    }

    /**
     * Get child ID by parent ID and alias
     *
     * @param string $parentId
     * @param string $alias
     * @return string|bool
     */
    public function getChildId($parentId, $alias)
    {
        if (isset($this->_elements[$parentId][self::CHILDREN])) {
            return array_search($alias, $this->_elements[$parentId][self::CHILDREN]);
        }
        return false;
    }

    /**
     * Get all children
     *
     * Returns in format 'id' => 'alias'
     *
     * @param string $parentId
     * @return array
     */
    public function getChildren($parentId)
    {
        return isset(
            $this->_elements[$parentId][self::CHILDREN]
        ) ? $this->_elements[$parentId][self::CHILDREN] : [];
    }

    /**
     * Get name of parent element
     *
     * @param string $childId
     * @return string|bool
     */
    public function getParentId($childId)
    {
        return isset($this->_elements[$childId][self::PARENT]) ? $this->_elements[$childId][self::PARENT] : false;
    }

    /**
     * Get element alias by name
     *
     * @param string $parentId
     * @param string $childId
     * @return string|bool
     */
    public function getChildAlias($parentId, $childId)
    {
        if (isset($this->_elements[$parentId][self::CHILDREN][$childId])) {
            return $this->_elements[$parentId][self::CHILDREN][$childId];
        }
        return false;
    }

    /**
     * Add element to parent group
     *
     * @param string $childId
     * @param string $groupName
     * @return bool
     */
    public function addToParentGroup($childId, $groupName)
    {
        $parentId = $this->getParentId($childId);
        if ($parentId) {
            $this->assertElementExists($parentId);
            $this->_elements[$parentId][self::GROUPS][$groupName][$childId] = $childId;
            return true;
        }
        return false;
    }

    /**
     * Get element IDs for specified group
     *
     * Note that it is expected behavior if a child has been moved out from this parent,
     * but still remained in the group of old parent. The method will return only actual children.
     * This is intentional, in case if the child returns back to the old parent.
     *
     * @param string $parentId Name of an element containing group
     * @param string $groupName
     * @return array
     */
    public function getGroupChildNames($parentId, $groupName)
    {
        $result = [];
        if (isset($this->_elements[$parentId][self::GROUPS][$groupName])) {
            foreach ($this->_elements[$parentId][self::GROUPS][$groupName] as $childId) {
                if (isset($this->_elements[$parentId][self::CHILDREN][$childId])) {
                    $result[] = $childId;
                }
            }
        }
        return $result;
    }

    /**
     * Calculate a relative offset of a child element in specified parent
     *
     * @param string $parentId
     * @param string $childId
     * @return int
     * @throws Exception if specified elements have no parent-child relation
     */
    protected function _getChildOffset($parentId, $childId)
    {
        $index = array_search($childId, array_keys($this->getChildren($parentId)));
        if (false === $index) {
            throw new Exception($this->render("The '%1' is not a child of '%2'.", [$childId, $parentId]));
        }
        return $index;
    }

    /**
     * Traverse through hierarchy and detect if the "potential parent" is a parent recursively to specified "child"
     *
     * @param string $childId
     * @param string $potentialParentId
     * @return bool
     */
    private function _isParentRecursively($childId, $potentialParentId)
    {
        $parentId = $this->getParentId($potentialParentId);
        if (!$parentId) {
            return false;
        }
        if ($parentId === $childId) {
            return true;
        }
        return $this->_isParentRecursively($childId, $parentId);
    }

    /**
     * Insert an existing element as a child to existing element
     *
     * The element must not be a child to any other element
     * The target parent element must not have it as a child already
     *
     * Offset -- into which position to insert:
     *   0     -- set as 1st
     *   1,  2 -- after 1st, second, etc...
     *  -1, -2 -- before last, before second last, etc...
     *   null  -- set as last
     *
     * @param string $targetParentId
     * @param string $elementId
     * @param int|null $offset
     * @param string $alias
     * @return void
     * @throws Exception
     */
    protected function _insertChild($targetParentId, $elementId, $offset, $alias)
    {
        $alias = $alias ?: $elementId;

        // validate
        $this->assertElementExists($elementId);
        if (!empty($this->_elements[$elementId][self::PARENT])) {
            throw new Exception($this->render(
                "The element '%1' already has a parent: '%2'",
                [$elementId, $this->_elements[$elementId][self::PARENT]]
            ));
        }
        $this->assertElementExists($targetParentId);
        $children = $this->getChildren($targetParentId);
        if (isset($children[$elementId])) {
            throw new Exception($this->render(
                "The element '%1' already a child of '%2'", [$elementId, $targetParentId]
            ));
        }
        if (false !== array_search($alias, $children)) {
            throw new Exception($this->render(
                "The element '%1' already has a child with alias '%2'",
                [$targetParentId, $alias]
            ));
        }

        // insert
        if (null === $offset) {
            $offset = count($children);
        }
        $this->_elements[$targetParentId][self::CHILDREN] = array_merge(
            array_slice($children, 0, $offset),
            [$elementId => $alias],
            array_slice($children, $offset)
        );
        $this->_elements[$elementId][self::PARENT] = $targetParentId;
    }

    /**
     * Check if specified element exists
     *
     * @param string $elementId
     * @return void
     * @throws Exception if doesn't exist
     */
    private function assertElementExists($elementId)
    {
        if (!isset($this->_elements[$elementId])) {
            throw new \OutOfBoundsException("No element found with ID '{$elementId}'.");
        }
    }

    /**
     * Check if it is an array
     *
     * @param array $value
     * @return void
     * @throws Exception
     */
    private function assertArray($value)
    {
        if (!is_array($value)) {
            throw new Exception($this->render("An array expected: %1", [var_export($value, 1)]));
        }
    }

    /**
     * Render source text
     *
     * @param string $text
     * @param array $arguments
     * @return string
     */
    private function render($text, array $arguments)
    {
        if ($arguments) {
            $placeholders = array_map([$this, 'keyToPlaceholder'], array_keys($arguments));
            $pairs = array_combine($placeholders, $arguments);
            $text = strtr($text, $pairs);
        }

        return $text;
    }

    /**
     * Get key to placeholder
     *
     * @param string|int $key
     * @return string
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function keyToPlaceholder($key)
    {
        return '%' . (is_int($key) ? strval($key + 1) : $key);
    }
}
