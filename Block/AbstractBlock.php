<?php

namespace Layout\Core\Block;

use Carbon\Carbon;
use Layout\Core\Object;
use Layout\Core\Page\Layout;
use Layout\Core\Contracts\Profiler;
use Layout\Core\Contracts\ConfigResolver;
use Layout\Core\Contracts\Cacheable as Cache;
use Layout\Core\Contracts\EventsDispatcher as Dispatcher;

abstract class AbstractBlock extends Object
{
    /**
     * Cache group Tag.
     */
    const CACHE_GROUP = 'block_html';

    /**
     * Cache tags data key.
     */
    const CACHE_TAGS_DATA_KEY = 'cache_tags';

    /**
     * The cache instance.
     *
     * @var \Layout\Core\Contracts\Cacheable
     */
    protected $cache;

    /**
     * The config instance.
     *
     * @var \Layout\Core\Contracts\ConfigResolver
     */
    protected $config;

     /**
     * Event Instance.
     *
     * @var Layout\Core\Contracts\EventsDispatcher
     */
    protected $events;

    /**
     * The profiler instance.
     *
     * @var \Layout\Core\Contracts\Profiler
     */
    protected $profiler;

    /**
     * Parent layout of the block
     *
     * @var \Layout\Core\Page\Layout
     */
    protected $layout;

    /**
     * Block name in layout
     *
     * @var string
     */
    protected $nameInLayout;

    /**
    *
    * Holds the view template.
    *
    * @var string
    */
    protected $template;

    /**
     * Assigned variables for view.
     *
     * @var array
     */
    protected $viewVars = [];

    /**
     * @var \Layout\Core\Object
     */
    private static $transportObject;

    /**
     * Create a new view factory instance.
     *
     * @param \Layout\Core\Contracts\Cacheable $cache
     * @param \Layout\Core\Contracts\ConfigResolver $config
     * @param \Layout\Core\Contracts\EventsDispatcher $events
     * @param \Layout\Core\Contracts\Profiler $profiler
     */
    public function __construct(
        Cache $cache,
        ConfigResolver $config,
        Dispatcher $events,
        Profiler $profiler
    ) {
        $this->cache = $cache;
        $this->config = $config;
        $this->events = $events;
        $this->profiler = $profiler;

        $this->boot();
    }

    /**
     * Called after the constructor is initilized 
     *
     * @return void
     */
    protected function boot()
    {
        //
    }

    /**
     * Get relevant path to template.
     *
     * @return string
     */
    abstract public function getTemplate();

    /**
     * Set path to template used for generating block's output.
     *
     * @param string $template
     *
     * @return \Layout\Core\Block
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Assign variable.
     *
     * @param string|array $key
     * @param mixed        $value
     *
     * @return \Layout\Core\Block
     */
    public function assign($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->assign($k, $v);
            }
        } else {
            $this->viewVars[$key] = $value;
        }

        return $this;
    }

    /**
     * Retrieve parent block
     *
     * @return $this|bool
     */
    public function getParentBlock()
    {
        $layout = $this->getLayout();
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
     * Set layout object
     *
     * @param   \Layout\Core\Page\Layout $layout
     * @return  $this
     */
    public function setLayout(Layout $layout)
    {
        $this->layout = $layout;
        $this->events->fire('block.prepare.layout.before', ['block' => $this]);
        $this->prepareLayout();
        $this->events->fire('block.prepare.layout.after', ['block' => $this]);
        return $this;
    }

    /**
     * Preparing global layout
     *
     * You can redefine this method in child classes for changing layout
     *
     * @return $this
     */
    protected function prepareLayout()
    {
        return $this;
    }

    /**
     * Retrieve layout object
     *
     * @return \Layout\Core\Page\Layout
     * @throws \Exception
     */
    public function layout()
    {
        if (!$this->layout) {
            throw new \Exception('Layout must be initialized');
        }
        return $this->layout;
    }

    /**
     * Set block attribute value
     *
     * Wrapper for method "setData"
     *
     * @param   string $name
     * @param   mixed $value
     * @return  $this
     */
    public function setAttribute($name, $value = null)
    {
        return $this->setData($name, $value);
    }

    /**
     * Sets/changes name of a block in layout
     *
     * @param string $name
     * @return $this
     */
    public function setNameInLayout($name)
    {
        if (!empty($this->nameInLayout) && $this->layout) {
            if ($name === $this->nameInLayout) {
                return $this;
            }
            $this->getLayout()->renameElement($this->nameInLayout, $name);
        }
        $this->nameInLayout = $name;
        return $this;
    }

    /**
     * Retrieves sorted list of child names
     *
     * @return array
     */
    public function getChildNames()
    {
        $layout = $this->getLayout();
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
        $layout = $this->getLayout();
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
     * @param string $block
     * @param array $data
     * @return $this new block
     */
    public function addChild($alias, $block, $data = [])
    {
        $block = $this->getLayout()->createBlock(
            $block,
            $this->getNameInLayout() . '.' . $alias,
            ['data' => $data]
        );
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
        $layout = $this->getLayout();
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
        $layout = $this->getLayout();
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
        $layout = $this->getLayout();
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
        $layout = $this->getLayout();
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
        $layout = $this->getLayout();
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

    /**
     * Retrieve block html
     *
     * @param   string $name
     * @return  string
     */
    public function getBlockHtml($name)
    {
        $block = $this->getLayout()->getBlock($name);
        if ($block) {
            return $block->toHtml();
        }
        return '';
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
        $layout = $this->getLayout();
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
     * Get cache key informative items
     * Provide string array key to share specific info item with FPC placeholder.
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        if ($this->hasData('cache_key_info')) {
            return [
                $this->getData('cache_key_info'),
                $this->getNameInLayout(),
            ];
        }
        return [
            $this->getNameInLayout(),
        ];
    }

    /**
     * set the cache life time
     *
     * @return array
     */
    public function addCacheLifetime($time)
    {
        $this->setData('cache_lifetime', Carbon::now()->addMinutes($time));
       
        return $this;
    }
    

    /**
     * Get Key for caching block content.
     *
     * @return string
     */
    public function getCacheKey()
    {
        if ($this->hasData('cache_key')) {
            return $this->getData('cache_key');
        }
        $key = $this->getCacheKeyInfo();
        $key = array_values($key); // ignore array keys
        $key = implode('|', $key);
        $key = sha1($key);

        return $key;
    }

    /**
     * Get tags array for saving cache.
     *
     * @return array
     */
    public function getCacheTags()
    {
        $tagsCache = $this->cache->get($this->_getTagsCacheKey(), false);
        if ($tagsCache) {
            $tags = json_decode($tagsCache);
        }
        if (!isset($tags) || !is_array($tags) || empty($tags)) {
            $tags = !$this->hasData(static::CACHE_TAGS_DATA_KEY) ? [] : $this->getData(static::CACHE_TAGS_DATA_KEY);
            if (!in_array(static::CACHE_GROUP, $tags)) {
                $tags[] = static::CACHE_GROUP;
            }
        }

        return array_unique($tags);
    }

    /**
     * Add tag to block.
     *
     * @param string|array $tag
     *
     * @return \Layout\Core\Block
     */
    public function addCacheTag($tag)
    {
        $tag = is_array($tag) ? $tag : [$tag];
        $tags = !$this->hasData(static::CACHE_TAGS_DATA_KEY) ?
            $tag : array_merge($this->getData(static::CACHE_TAGS_DATA_KEY), $tag);
        $this->setData(static::CACHE_TAGS_DATA_KEY, $tags);

        return $this;
    }

    /**
     * Get block cache life time.
     *
     * @return int|null
     */
    public function getCacheLifetime()
    {
        if (!$this->hasData('cache_lifetime')) {
            return;
        }

        return $this->getData('cache_lifetime');
    }

    /**
     * Load block html from cache storage.
     *
     * @return string | false
     */
    protected function _loadCache()
    {
        if (is_null($this->getCacheLifetime()) || !$this->config->get('cache.block')) {
            return false;
        }
        $cacheKey = $this->getCacheKey();
        $cacheData = $this->cache->get($cacheKey, false);

        return $cacheData;
    }

    /**
     * Save block content to cache storage.
     *
     * @param string $data
     *
     * @return \Layout\Core\Block
     */
    protected function _saveCache($data)
    {
        if (is_null($this->getCacheLifetime()) || !$this->config->get('cache.block')) {
            return false;
        }
        $cacheKey = $this->getCacheKey();

        $tags = $this->getCacheTags();
        $this->cache->put($cacheKey, $data, $this->getCacheLifetime(), $tags);
        $this->cache->put(
            $this->_getTagsCacheKey($cacheKey),
            json_encode($tags),
            $this->getCacheLifetime(),
            $tags
        );
        return $this;
    }

    /**
     * Get cache key for tags.
     *
     * @param string $cacheKey
     *
     * @return string
     */
    protected function _getTagsCacheKey($cacheKey = null)
    {
        $cacheKey = !empty($cacheKey) ? $cacheKey : $this->getCacheKey();
        $cacheKey = md5($cacheKey.'_tags');

        return $cacheKey;
    }

    /**
     * Before rendering html, but after trying to load cache.
     *
     * @return \Layout\Core\Block\AbstractBlock
     */
    protected function beforeToHtml()
    {
        return $this;
    }

    /**
     * Produce and return block's html output.
     *
     * It is a final method, but you can override _toHtml() method in descendants if needed.
     *
     * @return string
     */
    final public function toHtml()
    {
        $this->events->fire('block.to.html.before', ['block' => $this]);

        $html = $this->_loadCache();

        if ($html === false) {
            $this->beforeToHtml();
            $html = $this->_toHtml();

            $this->_saveCache($html);
        }

        $html = $this->afterToHtml($html);

        /*
         * Use single transport object instance for all blocks
         */
        if (self::$transportObject === null) {
            self::$transportObject = new Object();
        }
        self::$transportObject->setHtml($html);

        $this->events->fire('block.to.html.after',
            ['block' => $this, 'transport' => self::$transportObject]);

        $html = self::$transportObject->getHtml();

        return $html;
    }

    /**
     * Render block HTML.
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->getTemplate()) {
            return '';
        }
        $html = $this->fetchView($this->getTemplate());

        return $html;
    }

    /**
     * Processing block html after rendering.
     *
     * @param string $html
     *
     * @return string
     */
    protected function afterToHtml($html)
    {
        return $html;
    }

    /**
     * Retrieve block view using the logic you prefer.
     *
     * @param string $fileName
     * @param array $viewVars
     * @return string
     */
    abstract protected function getView($fileName, $viewVars);

    /**
     * Retrieve block view from file (template).
     *
     * @param string $fileName
     *
     * @return string
     */
    public function fetchView($fileName)
    {
        $this->profiler->start($fileName);

        $html = '';

        // EXTR_SKIP protects from overriding
        // already defined variables
        extract($this->viewVars, EXTR_SKIP);

        if ($this->getShowTemplateHints()) {
            $html .= <<<HTML
<div style="position:relative; border:1px dotted red; margin:6px 2px; padding:18px 2px 2px 2px; zoom:1;">
<div style="position:absolute; left:0; top:0; padding:2px 5px; background:red; color:white; font:normal 11px Arial;
text-align:left !important; z-index:998;" onmouseover="this.style.zIndex='999'"
onmouseout="this.style.zIndex='998'" title="{$fileName}">{$fileName}</div>
HTML;
            $thisClass = get_class($this);
            $html .= <<<HTML
<div style="position:absolute; right:0; top:0; padding:2px 5px; background:red; color:blue; font:normal 11px Arial;
text-align:left !important; z-index:998;" onmouseover="this.style.zIndex='999'" onmouseout="this.style.zIndex='998'"
title="{$thisClass}">{$thisClass}</div>
HTML;
        }

        try {
            $this->assign('block', $this);
            $html .= $this->getView($fileName, $this->viewVars);
        } catch (\Exception $e) {
            throw $e;
        }

        if ($this->getShowTemplateHints()) {
            $html .= '</div>';
        }

        $this->profiler->stop($fileName);

        return $html;
    }

    /**
     * Check if the template hite can be shown.
     *
     * @return bool
     */
    public function getShowTemplateHints()
    {
        return $this->config->get('show_template_hint', false);
    }
}
