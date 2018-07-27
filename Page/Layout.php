<?php

namespace Layout\Core\Page;

use Layout\Core\Object;
use Layout\Core\Xml\Element;
use Layout\Core\Data\Stack;
use Layout\Core\Data\Structure;
use Layout\Core\Element\Reader;
use Layout\Core\Contracts\Profiler;
use Layout\Core\Element\NodeReader;
use Layout\Core\Contracts\Cacheable;
use Layout\Core\Contracts\ConfigResolver;
use Layout\Core\Page\Generator\Body as BodyGenerator;
use Layout\Core\Page\Generator\Head as HeadGenerator;
use Layout\Core\Contracts\EventsDispatcher as Dispatcher;

class Layout
{
    /**#@+
     * Constants of available types
     */
    const ELEMENT_TYPE_BODY = 'body';
    const ELEMENT_TYPE_HTML = 'html';
    const ELEMENT_TYPE_HEAD = 'head';
    /**#@-*/

    /**
     * Constant body attribute class
     */
    const BODY_ATTRIBUTE_CLASS = 'class';

    /**
     * The event dispatcher instance.
     *
     * @var \Layout\Core\Contracts\EventsDispatcher
     */
    protected $events;

    /**
     * The config instance.
     *
     * @var \Layout\Core\Contracts\ConfigResolver
     */
    protected $config;

    /**
     * The profiler instance.
     *
     * @var \Layout\Core\Contracts\Profiler
     */
    protected $profiler;

    /**
     * The cache instance.
     *
     * @var \Layout\Core\Contracts\Cacheable
     */
    protected $cache;

    /**
     * Layout Manager instance
     *
     * @var \Layout\Core\Page\Manager
     */
    protected $manager;

    /**
     * layout xml.
     *
     * @var \Layout\Core\Xml\Element
     */
    protected $xmlTree = null;

    /**
     * Layout structure model
     *
     * @var Layout\Core\Data\Structure
     */
    protected $structure;

    /**
     * Layout stack
     *
     * @var Layout\Core\Data\Stack
     */
    protected $layoutStack;

    /**
     * Blocks registry
     *
     * @var array
     */
    protected $blocks = [];

    /**
     * Cache of elements to output during rendering
     *
     * @var array
     */
    protected $output = [];

    /**
     * Cache of generated elements' HTML
     *
     * @var array
     */
    protected $renderElementCache = [];

    /**
     * Create a new layout instance.
     *
     * @param \Layout\Core\Contracts\EventsDispatcher $events
     * @param \Layout\Core\Contracts\ConfigResolver $config
     * @param \Layout\Core\Contracts\Profiler $profiler
     * @param \Layout\Core\Contracts\Cacheable $cache
     */
    public function __construct(
        Dispatcher $events,
        ConfigResolver $config,
        Profiler $profiler,
        Cacheable $cache
    ) {
        $this->events = $events;
        $this->config = $config;
        $this->profiler = $profiler;
        $this->cache = $cache;
        $this->manager = new Manager($events, $config, $profiler, $cache);
        $this->structure = new Structure([]);
        $this->layoutStack = new Stack;
    }

    /**
     *
     * @return \Layout\Core\Contracts\EventsDispatcher
     */
    public function getEventObject()
    {
        return $this->events;
    }

    /**
     *
     * @return \Layout\Core\Contracts\Cacheable
     */
    public function getCacheObject()
    {
        return $this->cache;
    }

    /**
     *
     * @return \Layout\Core\Contracts\ConfigResolver
     */
    public function getConfigObject()
    {
        return $this->config;
    }

    /**
     *
     * @return \Layout\Core\Contracts\Profiler
     */
    public function getProfilerObject()
    {
        return $this->profiler;
    }

    /**
     * Layout update instance.
     *
     * @return \Layout\Core\Page\Update
     */
    public function manager()
    {
        return $this->manager;
    }

    /**
     * Sets xml for this configuration
     *
     * @param \Layout\Core\Xml\Element $node
     * @return $this
     */
    public function setXml(Element $node)
    {
        $this->xmlTree = $node;

        return $this;
    }

    /**
     * Getter for xml element
     *
     * @return \Layout\Core\Xml\Element
     */
    public function getXml()
    {
        return $this->xmlTree;
    }

    /**
     * Returns node found by the $path
     *
     * @param string $path
     * @return \Layout\Core\Xml\Element|bool
     */
    public function getNode($path = null)
    {
        if (!$this->getXml() instanceof Element) {
            return false;
        } elseif ($path === null) {
            return $this->getXml();
        } else {
            return $this->getXml()->xpath($path);
        }
    }

     /**
     * Layout xml generation
     *
     * @return $this
     */
    public function generateXml()
    {
        $xml = $this->manager()->asSimplexml();
        $this->setXml($xml);
        $this->structure->importElements([]);
        $this->layoutStack = new Stack();
        return $this;
    }

    /**
     * reset the layout
     *
     * @return $this
     */
    public function reset()
    {
        $this->manager()->resetHandle();
        $this->output = [];
        $this->renderElementCache = [];
        return $this;
    }

    /**
     * Create structure of elements from the loaded XML configuration
     *
     * @return void
     */
    public function generatePageElements()
    {
        $cacheId = 'structure_' . $this->manager()->getCacheId();
        $result = $this->loadCache($cacheId);
        if ($result) {
            $this->layoutStack = unserialize($result);
        } else {
            $reader = new NodeReader($this->config->get('layout.element.readers', []));
            $reader->read($this->layoutStack, $this->getNode());
            $this->saveCache(serialize($this->layoutStack), $cacheId, $this->manager()->getHandles());
        }
        
        $body = new BodyGenerator($this->config->get('layout.body.generators', []), $this);
        $body->generate($this->layoutStack, $this->structure);

        $this->addToOutputRootContainers();
    }

    /**
     * Add parent containers to output
     *
     * @return $this
     */
    protected function addToOutputRootContainers()
    {
        foreach ($this->structure->exportElements() as $name => $element) {
            if ($element['type'] === 'container' && empty($element['parent'])) {
                $this->addOutputElement($name);
            }
        }
        return $this;
    }

    /**
     * Add an element to output
     *
     * @param string $name
     * @return $this
     */
    public function addOutputElement($name)
    {
        $this->output[$name] = $name;
        return $this;
    }

    /**
     * Remove an element from output
     *
     * @param string $name
     * @return $this
     */
    public function removeOutputElement($name)
    {
        if (isset($this->output[$name])) {
            unset($this->output[$name]);
        }
        return $this;
    }


    /**
     * Generate the head section
     *
     * @return array
     */
    public function generateHeadElemets()
    {
        return (new HeadGenerator())->generate($this->layoutStack, $this);
    }

    /**
     * @param string $elementType
     * @return string
     */
    public function renderElementAttributes($elementType)
    {
        $resultAttributes = [];
        foreach ($this->layoutStack->getElementAttributes($elementType) as $name => $value) {
            $resultAttributes[] = sprintf('%s="%s"', $name, $value);
        }
        return implode(' ', $resultAttributes);
    }

    /**
     * Add CSS class to page body tag
     *
     * @param string $className
     * @return $this
     */
    public function addBodyClass($className)
    {
        $className = preg_replace('#[^a-z0-9]+#', '-', strtolower($className));
        $bodyClasses = $this->layoutStack->getBodyClasses();
        $bodyClasses[] = $className;
        $bodyClasses = array_unique($bodyClasses);
        $this->layoutStack->setElementAttribute(
            self::ELEMENT_TYPE_BODY,
            self::BODY_ATTRIBUTE_CLASS,
            implode(' ', $bodyClasses)
        );
        return $this;
    }


    /**
     * Get all blocks marked for output
     *
     * @return array ['head' => [] ,'body' => '', 'bodyAttributes' => []]
     */
    public function getOutput()
    {
        $out = '';
        foreach ($this->output as $name) {
            $out .= $this->renderElement($name);
        }
        $head = $this->generateHeadElemets();

        return ['head' => $head, 'body' => $out, 'bodyAttributes' => $this->renderElementAttributes(self::ELEMENT_TYPE_BODY)];
    }

    /**
     * Find an element in layout, render it and return string with its output
     *
     * @param string $name
     * @param bool $useCache
     * @return string
     */
    public function renderElement($name, $useCache = true)
    {
        if (!isset($this->renderElementCache[$name]) || !$useCache) {
            if ($this->displayElement($name)) {
                $this->renderElementCache[$name] = $this->renderNonCachedElement($name);
            } else {
                return $this->renderElementCache[$name] = '';
            }
        }
        $transport = new Object();
        $transport->setData('output', $this->renderElementCache[$name]);

        $this->events->fire(
            'layout.render.element',
            ['element_name' => $name, 'layout' => $this, 'transport' => $transport]
        );
        return $transport->getData('output');
    }

    /**
     * Define whether to display element
     * Display if 'display' attribute is absent (false, null) or equal true ('1', true, 'true')
     * In any other cases - do not display
     *
     * @param string $name
     * @return bool
     */
    protected function displayElement($name)
    {
        $display = $this->structure->getAttribute($name, 'display');
        if ($display === '' || $display === false || $display === null
            || filter_var($display, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        return false;
    }

    /**
     * Render non cached element
     *
     * @param string $name
     * @return string
     * @throws \Exception
     */
    public function renderNonCachedElement($name)
    {
        $result = '';
        try {
            if ($this->isBlock($name)) {
                $result = $this->renderBlock($name);
            } else {
                $result = $this->renderContainer($name);
            }
        } catch (\Exception $e) {
            throw $e;
        }
        return $result;
    }

    /**
     * Gets HTML of block element
     *
     * @param string $name
     * @return string
     * @throws \Exception
     */
    protected function renderBlock($name)
    {
        $block = $this->getBlock($name);
        return $block ? $block->toHtml() : '';
    }

    /**
     * Gets HTML of container element
     *
     * @param string $name
     * @return string
     */
    protected function renderContainer($name)
    {
        $html = '';
        $children = $this->getChildNames($name);
        foreach ($children as $child) {
            $html .= $this->renderElement($child);
        }
        if ($html == '' || !$this->structure->getAttribute($name, 'htmlTag')) {
            return $html;
        }

        $htmlId = $this->structure->getAttribute($name, 'htmlId');
        if ($htmlId) {
            $htmlId = ' id="' . $htmlId . '"';
        }

        $htmlClass = $this->structure->getAttribute($name, 'htmlClass');
        if ($htmlClass) {
            $htmlClass = ' class="' . $htmlClass . '"';
        }

        $htmlTag = $this->structure->getAttribute($name, 'htmlTag')?:'div';

        $html = sprintf('<%1$s%2$s%3$s>%4$s</%1$s>', $htmlTag, $htmlId, $htmlClass, $html);

        return $html;
    }

    /**
     * Save block in blocks registry
     *
     * @param string $name
     * @param \Layout\Core\Block\AbstractBlock $block
     * @return $this
     */
    public function setBlock($name, $block)
    {
        $this->blocks[$name] = $block;
        return $this;
    }

    /**
     * Get block object by name
     *
     * @param string $name
     * @return \Layout\Core\Block\AbstractBlock|bool
     */
    public function getBlock($name)
    {
        if (isset($this->blocks[$name])) {
            return $this->blocks[$name];
        } else {
            return false;
        }
    }

    /**
     * Retrieve all blocks from registry as array
     *
     * @return array
     */
    public function getAllBlocks()
    {
        return $this->blocks;
    }

    /**
     * Whether specified element is a block
     *
     * @param string $name
     * @return bool
     */
    public function isBlock($name)
    {
        if ($this->structure->hasElement($name)) {
            return 'block' === $this->structure->getAttribute($name, 'type');
        }
        return false;
    }

    /**
     * Gets parent name of an element with specified name
     *
     * @param string $childName
     * @return bool|string
     */
    public function getParentName($childName)
    {
        return $this->structure->getParentId($childName);
    }

    /**
     * Get child name by alias
     *
     * @param string $parentName
     * @param string $alias
     * @return bool|string
     */
    public function getChildName($parentName, $alias)
    {
        return $this->structure->getChildId($parentName, $alias);
    }

    /**
     * Get list of child names
     *
     * @param string $parentName
     * @return array
     */
    public function getChildNames($parentName)
    {
        return array_keys($this->structure->getChildren($parentName));
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
    public function reorderChild($parentName, $childName, $offsetOrSibling, $after = true)
    {
        $this->structure->reorderChildElement($parentName, $childName, $offsetOrSibling, $after);
    }


    /**
     * Set child element into layout structure
     *
     * @param string $parentName
     * @param string $elementName
     * @param string $alias
     * @return $this
     */
    public function setChild($parentName, $elementName, $alias)
    {
        $this->structure->setAsChild($elementName, $parentName, $alias);
        return $this;
    }

    /**
     * Remove child element from parent
     *
     * @param string $parentName
     * @param string $alias
     * @return $this
     */
    public function unsetChild($parentName, $alias)
    {
        $this->structure->unsetChild($parentName, $alias);
        return $this;
    }

    /**
     * Rename element in layout and layout structure
     *
     * @param string $oldName
     * @param string $newName
     * @return bool
     */
    public function renameElement($oldName, $newName)
    {
        if (isset($this->blocks[$oldName])) {
            $block = $this->blocks[$oldName];
            $this->blocks[$oldName] = null;
            unset($this->blocks[$oldName]);
            $this->blocks[$newName] = $block;
        }
        $this->structure->renameElement($oldName, $newName);

        return $this;
    }

     /**
     * Create block instance
     *
     * @param string|\Layout\Core\Block\AbstractBlock $block
     * @param string $name
     * @param array $arguments
     * @return \Layout\Core\Block\AbstractBlock
     */
    public function createBlock($block, $name, array $arguments = [])
    {
        $block = $this->getBlockInstance($block);
        $block->setType(get_class($block));
        $block->setNameInLayout($name);
        $block->addData(isset($arguments['data']) ? $arguments['data'] : []);
        return $block;
    }

    /**
     * Create block object instance based on block type
     *
     * @param string|\Layout\Core\Block\AbstractBlock $block
     * @throws \Exception
     * @return \Layout\Core\Block\AbstractBlock
     */
    protected function getBlockInstance($block)
    {
        $e = null;
        if ($block && is_string($block)) {
            try {
                $classResolver = $this->config->get('class_resolver');
                $block = $classResolver($block);
            } catch (\ReflectionException $e) {
                throw $e;
            }
        }
        if (!$block instanceof \Layout\Core\Block\AbstractBlock) {
            throw new \Exception('Invalid block type: '. $block, 500, $e);
        }
        return $block;
    }

    /**
     * Save data to the cache, if the layout caching is allowed
     *
     * @param string $data
     * @param string $cacheId
     * @param array $cacheTags
     * @return void
     */
    protected function saveCache($data, $cacheId, array $cacheTags = [])
    {
        if (!$this->config->get('cache.layout')) {
            return false;
        }

        return $this->cache->forever($cacheId, $data, $cacheTags);
    }

    /**
     * Retrieve data from the cache, if the layout caching is allowed, or FALSE otherwise
     *
     * @param string $cacheId
     * @return string|bool
     */
    protected function loadCache($cacheId)
    {
        if (!$this->config->get('cache.layout')) {
            return false;
        }

        if (!$result = $this->cache->get($cacheId, false)) {
            return false;
        }

        return $result;
    }

    /**
     * Cleanup circular references between layout & blocks
     *
     * Destructor should be called explicitly in order to work around the PHP bug
     * https://bugs.php.net/bug.php?id=62468
     */
    public function __destruct()
    {
        $this->manager->__destruct();
        $this->manager = null;
        $this->blocks = [];
        $this->xmlTree = null;
    }
}
