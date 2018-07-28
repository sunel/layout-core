<?php

namespace Layout\Core\Block;

trait InteractsWithBlocks
{

    /**
     * Append element to the end of children list
     *
     * @param \Layout\Core\Block\AbstractBlock|string $element
     * @param string $alias
     * @return $this
     */
    public function append($element, $alias = '')
    {
        return $this->insert($element, null, true, $alias);
    }
    
    /**
     * Insert child element into specified position
     *
     * By default inserts as first element into children list
     *
     * @param \Layout\Core\Block\AbstractBlock|string $element
     * @param string|int|null $siblingName
     * @param bool $after
     * @param string $alias
     * @return $this|bool
     */
    public function insert($element, $siblingName = 0, $after = true, $alias = '')
    {
        $layout = $this->layout();
        if (!$layout) {
            return false;
        }
        if ($element instanceof \Layout\Core\Block\AbstractBlock) {
            $elementName = $element->getNameInLayout();
        } else {
            $elementName = $element;
        }
        $layout->setChild($this->nameInLayout, $elementName, $alias);
        $layout->reorderChild($this->nameInLayout, $elementName, $siblingName, $after);
        return $this;
    }

    /**
     * Retrieve parent block
     *
     * @return $this|bool
     */
    public function getParentBlock()
    {
        $layout = $this->layout();
        if (!$layout) {
            return false;
        }
        $parentName = $layout->getParentName($this->getNameInLayout());
        if ($parentName) {
            return $layout->getBlock($parentName);
        }
        return false;
    }

    /**
     * Retrieves sorted list of child names
     *
     * @return array
     */
    public function getChildNames()
    {
        $layout = $this->layout();
        if (!$layout) {
            return [];
        }
        return $layout->getChildNames($this->getNameInLayout());
    }

    /**
     * Set child block
     *
     * @param   string $alias
     * @param   \Layout\Core\Block\AbstractBlock|string $block
     * @return  $this
     */
    public function setChild($alias, $block)
    {
        $layout = $this->layout();
        if (!$layout) {
            return $this;
        }
        $thisName = $this->getNameInLayout();
        if ($layout->getChildName($thisName, $alias)) {
            $this->unsetChild($alias);
        }
        if ($block instanceof self) {
            $block = $block->getNameInLayout();
        }

        $layout->setChild($thisName, $block, $alias);

        return $this;
    }

    /**
     * Create block with name: {parent}.{alias} and set as child
     *
     * @param string $alias
     * @param string $blockName
     * @param array $data
     * @return $this new block
     */
    public function addChild($alias, $blockName, $data = [])
    {
        $name = $this->getNameInLayout() . '.' . $alias;
        $block = $this->layout()->createBlock(
            $blockName,
            $name,
            ['data' => $data]
        );

        $this->layout()->addElement($name, 'block', $blockName);
        $this->layout()->setBlock($name, $block);

        $this->setChild($alias, $block);
        return $block;
    }

    /**
     * Unset child block
     *
     * @param  string $alias
     * @return $this
     */
    public function unsetChild($alias)
    {
        $layout = $this->layout();
        if (!$layout) {
            return $this;
        }
        $layout->unsetChild($this->getNameInLayout(), $alias);
        return $this;
    }

    /**
     * Unset all children blocks
     *
     * @return $this
     */
    public function unsetChildren()
    {
        $layout = $this->layout();
        if (!$layout) {
            return $this;
        }
        $name = $this->getNameInLayout();
        $children = $layout->getChildNames($name);
        foreach ($children as $childName) {
            $layout->unsetChild($name, $childName);
        }
        return $this;
    }

    /**
     * Retrieve child block by name
     *
     * @param string $alias
     * @return \Layout\Core\Block\AbstractBlock|bool
     */
    public function getChildBlock($alias)
    {
        $layout = $this->layout();
        if (!$layout) {
            return false;
        }
        $name = $layout->getChildName($this->getNameInLayout(), $alias);
        if ($name) {
            return $layout->getBlock($name);
        }
        return false;
    }

    /**
     * Retrieve child block HTML
     *
     * @param   string $alias
     * @param   boolean $useCache
     * @return  string
     */
    public function getChildHtml($alias = '', $useCache = true)
    {
        $layout = $this->layout();
        if (!$layout) {
            return '';
        }
        $name = $this->getNameInLayout();
        $out = '';
        if ($alias) {
            $childName = $layout->getChildName($name, $alias);
            if ($childName) {
                $out = $layout->renderElement($childName, $useCache);
            }
        } else {
            foreach ($layout->getChildNames($name) as $child) {
                $out .= $layout->renderElement($child, $useCache);
            }
        }

        return $out;
    }

    /**
     * Render output of child's child element
     *
     * @param string $alias
     * @param string $childChildAlias
     * @param bool $useCache
     * @return string
     */
    public function getInnerChildHtml($alias, $childChildAlias = '', $useCache = true)
    {
        $layout = $this->layout();
        if (!$layout) {
            return '';
        }
        $childName = $layout->getChildName($this->getNameInLayout(), $alias);
        if (!$childName) {
            return '';
        }
        $out = '';
        if ($childChildAlias) {
            $childChildName = $layout->getChildName($childName, $childChildAlias);
            $out = $layout->renderElement($childChildName, $useCache);
        } else {
            foreach ($layout->getChildNames($childName) as $childChild) {
                $out .= $layout->renderElement($childChild, $useCache);
            }
        }
        return $out;
    }
}
