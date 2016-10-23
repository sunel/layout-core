<?php

namespace Layout\Core\Xml;

class Element extends \SimpleXMLElement
{
    /**
     * Get block name
     *
     * @return bool|string
     */
    public function getBlockName()
    {
        $tagName = (string)$this->getName();
        $isThisBlock = empty($this['name']) || !in_array(
            $tagName,
            ['block', 'referenceBlock']
        );

        if ($isThisBlock) {
            return false;
        }
        return (string)$this['name'];
    }

    /**
     * Get element name
     *
     * Advanced version of getBlockName() method: gets name for container as well as for block
     *
     * @return string|bool
     */
    public function getElementName()
    {
        $tagName = $this->getName();
        $isThisContainer = !in_array(
            $tagName,
            ['block', 'referenceBlock', 'container', 'referenceContainer']
        );

        if ($isThisContainer) {
            return false;
        }
        return $this->getAttribute('name');
    }

    /**
     * Returns parent node for the element.
     *
     * Currently using xpath
     *
     * @return \Layout\Core\AbstractXml
     */
    public function getParent()
    {
        $arr = $this->xpath('..');
        $parent = $arr[0];

        return $parent;
    }

    /**
     * Enter description here...
     *
     * @return bool
     */
    public function hasChildren()
    {
        if (!$this->children()) {
            return false;
        }

        // simplexml bug: @attributes is in children() but invisible in foreach
        foreach ($this->children() as $k => $child) {
            return true;
        }

        return false;
    }

    /**
     * Returns attribute value by attribute name.
     *
     * @return string
     */
    public function getAttribute($name)
    {
        $attrs = $this->attributes();

        return isset($attrs[$name]) ? (string) $attrs[$name] : null;
    }

    /**
     * Returns the node and children as an array.
     *
     * @return array|string
     */
    public function asArray()
    {
        return $this->_asArray();
    }

    /**
     * asArray() analog, but without attributes.
     *
     * @return array|string
     */
    public function asCanonicalArray()
    {
        return $this->_asArray(true);
    }

    /**
     * Returns the node and children as an array.
     *
     * @param bool $isCanonical - whether to ignore attributes
     *
     * @return array|string
     */
    protected function _asArray($isCanonical = false)
    {
        $result = [];
        if (!$isCanonical) {
            // add attributes
            foreach ($this->attributes() as $attributeName => $attribute) {
                if ($attribute) {
                    $result['@'][$attributeName] = (string) $attribute;
                }
            }
        }
        // add children values
        if ($this->hasChildren()) {
            foreach ($this->children() as $childName => $child) {
                $result[$childName] = $child->_asArray($isCanonical);
            }
        } else {
            if (empty($result)) {
                // return as string, if nothing was found
                $result = (string) $this;
            } else {
                // value has zero key element
                $result[0] = (string) $this;
            }
        }

        return $result;
    }

    /**
     * Makes nicely formatted XML from the node.
     *
     * @param string   $filename
     * @param int|bool $level    if false
     *
     * @return string
     */
    public function asNiceXml($filename = '', $level = 0)
    {
        if (is_numeric($level)) {
            $pad = str_pad('', $level * 3, ' ', STR_PAD_LEFT);
            $nl = "\n";
        } else {
            $pad = '';
            $nl = '';
        }
        $out = $pad.'<'.$this->getName();
        if ($attributes = $this->attributes()) {
            foreach ($attributes as $key => $value) {
                $out .= ' '.$key.'="'.str_replace('"', '\"', (string) $value).'"';
            }
        }
        if ($this->hasChildren()) {
            $out .= '>'.$nl;
            foreach ($this->children() as $child) {
                $out .= $child->asNiceXml('', is_numeric($level) ? $level + 1 : true);
            }
            $out .= $pad.'</'.$this->getName().'>'.$nl;
        } else {
            $value = (string) $this;
            if (strlen($value)) {
                $out .= '>'.$this->xmlentities($value).'</'.$this->getName().'>'.$nl;
            } else {
                $out .= '/>'.$nl;
            }
        }
        if ((0 === $level || false === $level) && !empty($filename)) {
            file_put_contents($filename, $out);
        }

        return $out;
    }

    /**
     * Enter description here...
     *
     * @param int $level
     *
     * @return string
     */
    public function innerXml($level = 0)
    {
        $out = '';
        foreach ($this->children() as $child) {
            $out .= $child->asNiceXml($level);
        }

        return $out;
    }

    /**
     * Converts meaningful xml characters to xml entities.
     *
     * @param  string
     *
     * @return string
     */
    public function xmlentities($value = null)
    {
        if (is_null($value)) {
            $value = $this;
        }
        $value = (string) $value;

        $value = str_replace(['&', '"', "'", '<', '>'], ['&amp;', '&quot;', '&apos;', '&lt;', '&gt;'], $value);

        return $value;
    }

    /**
     * Appends $source to current node.
     *
     * @param \Layout\Core\AbstractXml $source
     *
     * @return \Layout\Core\AbstractXml
     */
    public function appendChild($source)
    {
        if ($source->children()) {
            /*
             * @see http://bugs.php.net/bug.php?id=41867 , fixed in 5.2.4
             */
            if (version_compare(phpversion(), '5.2.4', '<') === true) {
                $name = $source->children()->getName();
            } else {
                $name = $source->getName();
            }
            $child = $this->addChild($name);
        } else {
            $child = $this->addChild($source->getName(), $this->xmlentities($source));
        }
        $child->setParent($this);

        $attributes = $source->attributes();
        foreach ($attributes as $key => $value) {
            $child->addAttribute($key, $this->xmlentities($value));
        }

        foreach ($source->children() as $sourceChild) {
            $child->appendChild($sourceChild);
        }

        return $this;
    }

    /**
     * Layoutends current node with xml from $source.
     *
     * If $overwrite is false will merge only missing nodes
     * Otherwise will overwrite existing nodes
     *
     * @param \Layout\Core\AbstractXml $source
     * @param bool                       $overwrite
     *
     * @return \Layout\Core\AbstractXml
     */
    public function extend($source, $overwrite = false)
    {
        if (!$source instanceof \Layout\Core\AbstractXml) {
            return $this;
        }

        foreach ($source->children() as $child) {
            $this->extendChild($child, $overwrite);
        }

        return $this;
    }

    /**
     * Layoutends one node.
     *
     * @param \Layout\Core\AbstractXml $source
     * @param bool                       $overwrite
     *
     * @return \Layout\Core\AbstractXml
     */
    public function extendChild($source, $overwrite = false)
    {
        // this will be our new target node
        $targetChild = null;

        // name of the source node
        $sourceName = $source->getName();

        // here we have children of our source node
        $sourceChildren = $source->children();

        if (!$source->hasChildren()) {
            // handle string node
            if (isset($this->$sourceName)) {
                // if target already has children return without regard
                if ($this->$sourceName->hasChildren()) {
                    return $this;
                }
                if ($overwrite) {
                    unset($this->$sourceName);
                } else {
                    return $this;
                }
            }

            $targetChild = $this->addChild($sourceName, $source->xmlentities());
            $targetChild->setParent($this);
            foreach ($source->attributes() as $key => $value) {
                $targetChild->addAttribute($key, $this->xmlentities($value));
            }

            return $this;
        }

        if (isset($this->$sourceName)) {
            $targetChild = $this->$sourceName;
        }

        if (is_null($targetChild)) {
            // if child target is not found create new and descend
            $targetChild = $this->addChild($sourceName);
            $targetChild->setParent($this);
            foreach ($source->attributes() as $key => $value) {
                $targetChild->addAttribute($key, $this->xmlentities($value));
            }
        }

        // finally add our source node children to resulting new target node
        foreach ($sourceChildren as $childKey => $childNode) {
            $targetChild->extendChild($childNode, $overwrite);
        }

        return $this;
    }

    public function setNode($path, $value, $overwrite = true)
    {
        $arr1 = explode('/', $path);
        $arr = [];
        foreach ($arr1 as $v) {
            if (!empty($v)) {
                $arr[] = $v;
            }
        }
        $last = sizeof($arr) - 1;
        $node = $this;
        foreach ($arr as $i => $nodeName) {
            if ($last === $i) {
                if (!isset($node->$nodeName) || $overwrite) {
                    // http://bugs.php.net/bug.php?id=36795
                    // comment on [8 Feb 8:09pm UTC]
                    if (isset($node->$nodeName) && (version_compare(phpversion(), '5.2.6', '<') === true)) {
                        $node->$nodeName = $node->xmlentities($value);
                    } else {
                        $node->$nodeName = $value;
                    }
                }
            } else {
                if (!isset($node->$nodeName)) {
                    $node = $node->addChild($nodeName);
                } else {
                    $node = $node->$nodeName;
                }
            }
        }

        return $this;
    }
}
