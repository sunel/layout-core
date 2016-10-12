<?php

namespace Layout\Core;

use Carbon\Carbon;
use Layout\Core\Contracts\Profiler;
use Layout\Core\Contracts\ConfigResolver;
use Layout\Core\Contracts\Cacheable as Cache;
use Layout\Core\Contracts\EventsDispatcher as Dispatcher;

abstract class Block extends Object
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
     * Block name in layout.
     *
     * @var string
     */
    protected $nameInLayout;

    /**
     * Parent layout of the block.
     *
     * @var \Layout\Core\Layout
     */
    protected $layout;

    /**
     * Parent block.
     *
     * @var \Layout\Core\Block
     */
    protected $parent;

    /**
     * Short alias of this block that was refered from parent.
     *
     * @var string
     */
    protected $alias;

    /**
     * Suffix for name of anonymous block.
     *
     * @var string
     */
    protected $anonSuffix;

    /**
     * Contains references to child block objects.
     *
     * @var array
     */
    protected $children = [];

    /**
     * Sorted children list.
     *
     * @var array
     */
    protected $sortedChildren = [];

    /**
     * Children blocks HTML cache array.
     *
     * @var array
     */
    protected $childrenHtmlCache = [];

    /**
     * Arbitrary groups of child blocks.
     *
     * @var array
     */
    protected $childGroups = [];

    /**
     * Whether this block was not explicitly named.
     *
     * @var bool
     */
    protected $isAnonymous = false;

    /**
     * Parent block.
     *
     * @var \Layout\Core\Block
     */
    protected $parentBlock;

    /**
     * Array of block sort priority instructions.
     *
     * @var array
     */
    protected $sortInstructions = [];

    /**
     * @var \Layout\Core\Object
     */
    private static $transportObject;

    /**
     * Path to template file in theme.
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
     * Create a new view factory instance.
     *
     * @param \Layout\Core\Contracts\Cacheable $cache
     * @param \Layout\Core\Contracts\ConfigResolver $config
     * @param \Layout\Core\Contracts\Profiler $profile
     */
    public function __construct(
        Cache $cache, 
        ConfigResolver $config, 
        Dispatcher $events, 
        Profiler $profile
    )
    {
        $this->cache = $cache;
        $this->config = $config;
        $this->events = $events;
        $this->profile = $profile;
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
     * Retrieve parent block.
     *
     * @return \Layout\Core\Block
     */
    public function getParentBlock()
    {
        return $this->parentBlock;
    }

    /**
     * Set parent block.
     *
     * @param \Layout\Core\Block $block
     *
     * @return \Layout\Core\Block
     */
    public function setParentBlock(Block $block)
    {
        $this->parentBlock = $block;

        return $this;
    }

    /**
     * Set layout object.
     *
     * @param \Layout\Core\Layout $layout
     *
     * @return \Layout\Core\Block
     */
    public function setLayout(Layout $layout)
    {
        $this->layout = $layout;
        $this->events->fire('block.prepare.layout.before', ['block' => $this]);
        $this->_prepareLayout();
        $this->events->fire('block.prepare.layout.after', ['block' => $this]);

        return $this;
    }

    /**
     * Preparing global layout.
     *
     * You can redefine this method in child classes for changing layout
     *
     * @return \Layout\Core\Block
     */
    protected function _prepareLayout()
    {
        return $this;
    }

    /**
     * Retrieve layout object.
     *
     * @return \Layout\Core\Layout
     */
    public function getLayout()
    {
        return $this->layout;
    }

    /**
     * Check if block is using auto generated (Anonymous) name.
     *
     * @return bool
     */
    public function getIsAnonymous()
    {
        return $this->isAnonymous;
    }

    /**
     * Set the anonymous flag.
     *
     * @param bool $flag
     *
     * @return \Layout\Core\Block
     */
    public function setIsAnonymous($flag)
    {
        $this->isAnonymous = (bool) $flag;

        return $this;
    }

    /**
     * Returns anonymous block suffix.
     *
     * @return string
     */
    public function getAnonSuffix()
    {
        return $this->anonSuffix;
    }

    /**
     * Set anonymous suffix for current block.
     *
     * @param string $suffix
     *
     * @return \Layout\Core\Block
     */
    public function setAnonSuffix($suffix)
    {
        $this->anonSuffix = $suffix;

        return $this;
    }

    /**
     * Returns block alias.
     *
     * @return string
     */
    public function getBlockAlias()
    {
        return $this->alias;
    }

    /**
     * Set block alias.
     *
     * @param string $alias
     *
     * @return \Layout\Core\Block
     */
    public function setBlockAlias($alias)
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Set block's name in layout and unsets previous link if such exists.
     *
     * @param string $name
     *
     * @return \Layout\Core\Block
     */
    public function setNameInLayout($name)
    {
        if (!empty($this->nameInLayout) && $this->getLayout()) {
            $this->getLayout()->unsetBlock($this->nameInLayout)
                ->setBlock($name, $this);
        }
        $this->nameInLayout = $name;

        return $this;
    }

    /**
     * Alias for getName method.
     *
     * @return string
     */
    public function getNameInLayout()
    {
        return $this->nameInLayout;
    }

    /**
     * Retrieve sorted list of children.
     *
     * @return array
     */
    public function getSortedChildren()
    {
        $this->sortChildren();

        return $this->sortedChildren;
    }

    /**
     * Generate url by route and parameters.
     *
     * @param string $route
     * @param array  $params
     *
     * @return string
     */
    public function getUrl($route = '', $params = [])
    {
        return route($route, $params);
    }

    /**
     * Set block attribute value.
     *
     * Wrapper for method "setData"
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return \Layout\Core\Block
     */
    public function setAttribute($name, $value = null)
    {
        return $this->setData($name, $value);
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
     * Set child block.
     *
     * @param string        $alias
     * @param \Layout\Core\Block $block
     *
     * @return \Layout\Core\Block
     */
    public function setChild($alias, $block)
    {
        if (is_string($block)) {
            $block = $this->getLayout()->getBlock($block);
        }
        if (!$block) {
            return $this;
        }

        if ($block->getIsAnonymous()) {
            $suffix = $block->getAnonSuffix();
            if (empty($suffix)) {
                $suffix = 'child'.sizeof($this->children);
            }
            $blockName = $this->getNameInLayout().'.'.$suffix;

            if ($this->getLayout()) {
                $this->getLayout()->unsetBlock($block->getNameInLayout())
                    ->setBlock($blockName, $block);
            }

            $block->setNameInLayout($blockName);
            $block->setIsAnonymous(false);

            if (empty($alias)) {
                $alias = $blockName;
            }
        }

        $block->setParentBlock($this);
        $block->setBlockAlias($alias);
        $this->children[$alias] = $block;

        return $this;
    }

    /**
     * Unset child block.
     *
     * @param string $alias
     *
     * @return \Layout\Core\Block
     */
    public function unsetChild($alias)
    {
        if (isset($this->children[$alias])) {
            /** @var \Layout\Core\Block $block */
            $block = $this->children[$alias];
            $name = $block->getNameInLayout();
            unset($this->children[$alias]);
            $key = array_search($name, $this->sortedChildren);
            if ($key !== false) {
                unset($this->sortedChildren[$key]);
            }
        }

        return $this;
    }

    /**
     * Append child block.
     *
     * @param \Layout\Core\Block|string $block
     * @param string               $alias
     *
     * @return \Layout\Core\Block
     */
    public function append($block, $alias = '')
    {
        $this->insert($block, '', true, $alias);

        return $this;
    }

    /**
     * Insert child block.
     *
     * @param \Layout\Core\Block|string $block
     * @param string               $siblingName
     * @param bool                 $after
     * @param string               $alias
     *
     * @return object $this
     */
    public function insert($block, $siblingName = '', $after = false, $alias = '')
    {
        if (is_string($block)) {
            $block = $this->getLayout()->getBlock($block);
        }
        if (!$block) {
            /*
             * if we don't have block - don't throw exception because
             * block can simply removed using layout method remove
             */
            return $this;
        }
        if ($block->getIsAnonymous()) {
            $this->setChild('', $block);
            $name = $block->getNameInLayout();
        } elseif ('' != $alias) {
            $this->setChild($alias, $block);
            $name = $block->getNameInLayout();
        } else {
            $name = $block->getNameInLayout();
            $this->setChild($name, $block);
        }

        if ($siblingName === '') {
            if ($after) {
                array_push($this->sortedChildren, $name);
            } else {
                array_unshift($this->sortedChildren, $name);
            }
        } else {
            $key = array_search($siblingName, $this->sortedChildren);
            if (false !== $key) {
                if ($after) {
                    $key++;
                }
                array_splice($this->sortedChildren, $key, 0, $name);
            } else {
                if ($after) {
                    array_push($this->sortedChildren, $name);
                } else {
                    array_unshift($this->sortedChildren, $name);
                }
            }

            $this->sortInstructions[$name] = [$siblingName, (bool) $after, false !== $key];
        }

        return $this;
    }

    /**
     * Call a child and unset it, if callback matched result.
     *
     * $params will pass to child callback
     * $params may be array, if called from layout with elements with same name, for example:
     * ...<foo>value_1</foo><foo>value_2</foo><foo>value_3</foo>
     *
     * Or, if called like this:
     * ...<foo>value_1</foo><bar>value_2</bar><baz>value_3</baz>
     * - then it will be $params1, $params2, $params3
     *
     * It is no difference anyway, because they will be transformed in appropriate way.
     *
     * @param string $alias
     * @param string $callback
     * @param mixed  $result
     * @param array  $params
     *
     * @return \Layout\Core\Block
     */
    public function unsetCallChild($alias, $callback, $result, $params)
    {
        $child = $this->getChild($alias);
        if ($child) {
            $args = func_get_args();
            $alias = array_shift($args);
            $callback = array_shift($args);
            $result = (string) array_shift($args);
            if (!is_array($params)) {
                $params = $args;
            }

            if ($result == call_user_func_array([&$child, $callback], $params)) {
                $this->unsetChild($alias);
            }
        }

        return $this;
    }

    /**
     * Unset all children blocks.
     *
     * @return \Layout\Core\Block
     */
    public function unsetChildren()
    {
        $this->children = [];
        $this->sortedChildren = [];

        return $this;
    }

    /**
     * Retrieve child block by name.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getChild($name = '')
    {
        if ($name === '') {
            return $this->children;
        } elseif (isset($this->children[$name])) {
            return $this->children[$name];
        }

        return false;
    }

    /**
     * Retrieve child block HTML.
     *
     * @param string $name
     * @param bool   $useCache
     * @param bool   $sorted
     *
     * @return string
     */
    public function getChildHtml($name = '', $useCache = true, $sorted = false)
    {
        if ($name === '') {
            if ($sorted) {
                $children = [];
                foreach ($this->getSortedChildren() as $childName) {
                    $children[$childName] = $this->getLayout()->getBlock($childName);
                }
            } else {
                $children = $this->getChild();
            }
            $out = '';
            foreach ($children as $child) {
                $out .= $this->_getChildHtml($child->getBlockAlias(), $useCache);
            }

            return $out;
        } else {
            return $this->_getChildHtml($name, $useCache);
        }
    }

    /**
     * @param string $name      Parent block name
     * @param string $childName OPTIONAL Child block name
     * @param bool   $useCache  OPTIONAL Use cache flag
     * @param bool   $sorted    OPTIONAL @see getChildHtml()
     *
     * @return string
     */
    public function getChildChildHtml($name, $childName = '', $useCache = true, $sorted = false)
    {
        if (empty($name)) {
            return '';
        }
        $child = $this->getChild($name);
        if (!$child) {
            return '';
        }

        return $child->getChildHtml($childName, $useCache, $sorted);
    }

    /**
     * Obtain sorted child blocks.
     *
     * @return array
     */
    public function getSortedChildBlocks()
    {
        $children = [];
        foreach ($this->getSortedChildren() as $childName) {
            $children[$childName] = $this->getLayout()->getBlock($childName);
        }

        return $children;
    }

    /**
     * Retrieve child block HTML.
     *
     * @param string $name
     * @param bool   $useCache
     *
     * @return string
     */
    protected function _getChildHtml($name, $useCache = true)
    {
        if ($useCache && isset($this->childrenHtmlCache[$name])) {
            return $this->childrenHtmlCache[$name];
        }

        $child = $this->getChild($name);

        if (!$child) {
            $html = '';
        } else {
            $this->_beforeChildToHtml($name, $child);
            $html = $child->toHtml();
        }

        $this->childrenHtmlCache[$name] = $html;

        return $html;
    }

    /**
     * Prepare child block before generate html.
     *
     * @param string        $name
     * @param \Layout\Core\Block $child
     */
    protected function _beforeChildToHtml($name, $child)
    {
    }

    /**
     * Retrieve block html.
     *
     * @param string $name
     *
     * @return string
     */
    public function getBlockHtml($name)
    {
        if (!($layout = $this->getLayout())) {
            return '';
        }
        if (!($block = $layout->getBlock($name))) {
            return '';
        }

        return $block->toHtml();
    }

    /**
     * Sort block's children.
     *
     * @param bool $force force re-sort all children
     *
     * @return \Layout\Core\Block
     */
    public function sortChildren($force = false)
    {
        foreach ($this->sortInstructions as $name => $list) {
            list($siblingName, $after, $exists) = $list;
            if ($exists && !$force) {
                continue;
            }
            $this->sortInstructions[$name][2] = true;

            $index = array_search($name, $this->sortedChildren);
            $siblingKey = array_search($siblingName, $this->sortedChildren);

            if ($index === false || $siblingKey === false) {
                continue;
            }

            if ($after) {
                // insert after block
                if ($index == $siblingKey + 1) {
                    continue;
                }
                // remove sibling from array
                array_splice($this->sortedChildren, $index, 1, []);
                // insert sibling after
                array_splice($this->sortedChildren, $siblingKey + 1, 0, [$name]);
            } else {
                // insert before block
                if ($index == $siblingKey - 1) {
                    continue;
                }
                // remove sibling from array
                array_splice($this->sortedChildren, $index, 1, []);
                // insert sibling after
                array_splice($this->sortedChildren, $siblingKey, 0, [$name]);
            }
        }

        return $this;
    }

    /**
     * Make sure specified block will be registered in the specified child groups.
     *
     * @param string        $groupName
     * @param \Layout\Core\Block $child
     */
    public function addToChildGroup($groupName, \Layout\Block $child)
    {
        if (!isset($this->childGroups[$groupName])) {
            $this->childGroups[$groupName] = [];
        }
        if (!in_array($child->getBlockAlias(), $this->childGroups[$groupName])) {
            $this->childGroups[$groupName][] = $child->getBlockAlias();
        }
    }

    /**
     * Add self to the specified group of parent block.
     *
     * @param string $groupName
     *
     * @return \Layout\Core\Block
     */
    public function addToParentGroup($groupName)
    {
        $this->getParentBlock()->addToChildGroup($groupName, $this);

        return $this;
    }

    /**
     * Get a group of child blocks.
     *
     * Returns an array of <alias> => <block>
     * or an array of <alias> => <callback_result>
     * The callback currently supports only $this methods and passes the alias as parameter
     *
     * @param string $groupName
     * @param string $callback
     * @param bool   $skipEmptyResults
     *
     * @return array
     */
    public function getChildGroup($groupName, $callback = null, $skipEmptyResults = true)
    {
        $result = [];
        if (!isset($this->childGroups[$groupName])) {
            return $result;
        }
        foreach ($this->getSortedChildBlocks() as $block) {
            $alias = $block->getBlockAlias();
            if (in_array($alias, $this->childGroups[$groupName])) {
                if ($callback) {
                    $row = $this->$callback($alias);
                    if (!$skipEmptyResults || $row) {
                        $result[$alias] = $row;
                    }
                } else {
                    $result[$alias] = $block;
                }
            }
        }

        return $result;
    }

    /**
     * Get a value from child block by specified key.
     *
     * @param string $alias
     * @param string $key
     *
     * @return mixed
     */
    public function getChildData($alias, $key = '')
    {
        $child = $this->getChild($alias);
        if ($child) {
            return $child->getData($key);
        }
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
    public function addCacheKeyInfo($info)
    {
        $this->setData('cache_lifetime', $info);

        return $this;
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
     * @return \Layout\Core\Block
     */
    protected function _beforeToHtml()
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
            $this->_beforeToHtml();
            $html = $this->_toHtml();

            $this->_saveCache($html);
        }

        $html = $this->_afterToHtml($html);

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
     * Processing block html after rendering.
     *
     * @param string $html
     *
     * @return string
     */
    protected function _afterToHtml($html)
    {
        return $html;
    }

    public function getShowTemplateHints()
    {
        return $this->config->get('show_template_hint', false);
    }

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
        $this->profile->start($fileName);

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
        } catch (Exception $e) {
            throw $e;
        }

        if ($this->getShowTemplateHints()) {
            $html .= '</div>';
        }

        $this->profile->stop($fileName);

        return $html;
    }

    /**
     * Render block.
     *
     * @return string
     */
    public function renderView()
    {
        $html = $this->fetchView($this->getTemplate());

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
        $html = $this->renderView();

        return $html;
    }

    /**
     * Get chilren blocks count.
     *
     * @return int
     */
    public function countChildren()
    {
        return count($this->children);
    }
}
