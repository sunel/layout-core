<?php

namespace Layout\Core\Page;

use SimpleXMLElement;
use InvalidArgumentException;
use Layout\Core\Xml\Element;
use Layout\Core\Contracts\Profiler;
use Layout\Core\Contracts\Cacheable;
use Symfony\Component\Finder\Finder;
use Layout\Core\Contracts\ConfigResolver;
use Layout\Core\Contracts\EventsDispatcher as Dispatcher;

class Manager
{
    /**
     * Additional tag for cleaning layout cache convenience.
     */
    const LAYOUT_GENERAL_CACHE_TAG = 'LAYOUT_GENERAL_CACHE_TAG';

    /**
     * Cache id suffix for page layout
     */
    const PAGE_LAYOUT_CACHE_SUFFIX = 'page_layout';

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
     * Cumulative array of update XML strings
     *
     * @var array
     */
    protected $updates = [];

    /**
     * Handles used in this update.
     *
     * @var array
     */
    protected $handles = [];

    /**
     * In-memory cache for loaded layout updates
     *
     * @var \Layout\Core\Xml\Element[]
     */
    protected $layoutUpdatesCache = [];

    /**
     * Loaded layout handles
     *
     * @var array
     */
    protected $layoutHandles = [];

    /**
     * @var string
     */
    protected $pageLayout;

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
    }

     /**
     * Add XML update instruction
     *
     * @param string $update
     * @return $this
     */
    public function addUpdate($update)
    {
        $this->updates[] = $update;
        return $this;
    }

    /**
     * Get all registered updates as array
     *
     * @return array
     */
    public function asArray()
    {
        return $this->updates;
    }

    /**
     * Get all registered updates as string
     *
     * @return string
     */
    public function asString()
    {
        return implode('', $this->updates);
    }

    /**
     * Get layout updates as \Layout\Core\Xml\Element object
     *
     * @return \SimpleXMLElement
     */
    public function asSimplexml()
    {
        $updates = trim($this->asString());
        $updates = '<?xml version="1.0"?>'
            . '<layout xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . $updates
            . '</layout>';
        return $this->loadXmlString($updates);
    }

    /**
     * Add handle(s) to update
     *
     * @param array|string $handleName
     * @return $this
     */
    public function addHandle($handleName)
    {
        if (is_array($handleName)) {
            foreach ($handleName as $name) {
                $this->handles[$name] = 1;
            }
        } else {
            $this->handles[$handleName] = 1;
        }
        return $this;
    }

    /**
     * Remove handle from update
     *
     * @param string $handleName
     * @return $this
     */
    public function removeHandle($handleName)
    {
        unset($this->handles[$handleName]);
        return $this;
    }

    /**
     * Reset handle from update
     *
     * @return $this
     */
    public function resetHandle()
    {
        $this->handles = [];
        $this->updates = [];
        $this->layoutHandles = [];
        $this->layoutUpdatesCache = [];
        return $this;
    }

    /**
     * Get handle names array
     *
     * @return array
     */
    public function getHandles()
    {
        return array_keys($this->handles);
    }

    /**
     * Get cache id.
     *
     * @return string
     */
    public function getCacheId()
    {
        return 'LAYOUT_'.md5(implode('__', $this->getHandles()));
    }

    public function collectHandles()
    {
        $this->getLayoutXml();

        return $this->layoutHandles;
    }

    /**
     * Load layout updates by handles.
     *
     * @throws Exception
     * @return $this
     */
    public function load($handles = [])
    {
        if (is_string($handles)) {
            $handles = [$handles];
        } elseif (!is_array($handles)) {
            throw new \Exception('Invalid layout update handle');
        }
        $this->addHandle($handles);

        $cacheId = $this->getCacheId();
        $cacheIdPageLayout = $cacheId . '_' . self::PAGE_LAYOUT_CACHE_SUFFIX;

        $result = $this->loadCache($cacheId);
        if ($result) {
            $this->addUpdate($result);
            $this->pageLayout = $this->loadCache($cacheIdPageLayout);
            return $this;
        }
        
        $this->collectHandleUpdates();

        $this->fetchLayoutUpdates($this->pageLayout);

        $this->saveCache($this->asString(), $cacheId, $this->getHandles());
        $this->saveCache((string)$this->pageLayout, $cacheIdPageLayout, $this->getHandles());

        return $this;
    }

    /**
     * Retrieve already merged layout updates from files for specified area
     *
     * @return \Layout\Core\Xml\Element
     */
    public function getLayoutXml()
    {
        $section = $this->config->get('handle_layout_section');

        if (isset($this->layoutUpdatesCache[$section])) {
            return $this->layoutUpdatesCache[$section];
        }

        $cacheId = "LAYOUT_{$section}";
        $layoutXml = $this->loadCache($cacheId);
        if (!$layoutXml) {
            $fileLocations = $this->config->get('xml_location.'.$section);
            if (empty($fileLocations)) {
                throw new InvalidArgumentException("Layout file location for `{$section}` section is not given.");
            }

            if (!is_array($fileLocations)) {
                $fileLocations = [$fileLocations];
            }

            $result = $this->loadXml($fileLocations);
            $this->layoutHandles = $result[0];
            $layoutXml = $result[1];

            $this->saveCache($layoutXml, $cacheId);
            $this->saveCache(json_encode($this->layoutHandles), $cacheId.'_handles_found');
        } else {
            $handlesFound = $this->loadCache($cacheId.'_handles_found');
            $this->layoutHandles = json_decode($handlesFound, true);
        }

        $layoutXml = '<layouts xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . $layoutXml . '</layouts>';
        $this->layoutUpdatesCache[$section] = $this->loadXmlString($layoutXml);
        return $this->layoutUpdatesCache[$section];
    }

    /**
     * Collect all the layout update for the handles.
     *
     * @return \Layout\Core\Update
     */
    protected function collectHandleUpdates()
    {
        $handles = $this->getHandles();
        $handleKey = implode('__', $handles);

        $profilerKey = 'layout_update: '.$handleKey;
        $this->profiler->start($profilerKey);

        foreach ($handles as $handle) {
            $this->fetchLayoutUpdates($handle);
        }

        $this->profiler->stop($profilerKey);

        return $this;
    }

    /**
     * Add updates for the specified handle
     *
     * @param string $handle
     * @return bool
     */
    protected function fetchLayoutUpdates($handle)
    {
        $layout = $this->getLayoutXml();
        foreach ($layout->xpath("*[self::handle or self::layout][@id='{$handle}']") as $updateXml) {
            $this->fetchRecursiveUpdates($updateXml);
            $this->addUpdate($updateXml->innerXml());
        }
        return true;
    }

    /**
     * Add handles declared as '<update handle="handle_name"/>' directives
     *
     * @param \SimpleXMLElement $updateXml
     * @param array $fileLocations
     * @return $this
     */
    protected function fetchRecursiveUpdates($updateXml)
    {
        foreach ($updateXml->update as $child) {
            if (isset($child['handle'])) {
                $handle = (string) $child['handle'];
                if (!isset($this->handles[$handle])) {
                    $this->fetchLayoutUpdates($handle);
                    $this->addHandle($handle);
                }
            }
        }
        if (isset($updateXml['layout'])) {
            $this->pageLayout = (string)$updateXml['layout'];
        }
        return $this;
    }


    /**
     * Collect and merge layout updates from files
     *
     * @param array $locations
     * @return array [ $layoutHandles, $layoutStr ] 
     */
    protected function loadXml($locations)
    {

        $locations = $this->arrayFlatten($locations);

        $layoutStr = '';
        $finder = Finder::create()->files();
        $finder->name('*.xml')
                ->in($locations);

        $layoutHandles = [];

        foreach ($finder as $file) {
            $fileStr = $file->getContents();
            $fileXml = $this->loadXmlString($fileStr);

            if (!$fileXml instanceof SimpleXMLElement) {
                continue;
            }

            $handleName = basename($file->getFilename(), '.xml');
            $tagName = $fileXml->getName() === 'layout' ? 'layout' : 'handle';
            if($tagName == 'handle') {
                $layoutHandles[] = $handleName;
            }
            $handleAttributes = ' id="' . $handleName . '"' . $this->renderXmlAttributes($fileXml);
            $handleStr = '<' . $tagName . $handleAttributes . '>' . $fileXml->innerXml() . '</' . $tagName . '>';
            $layoutStr .= $handleStr;
        }

        return [$layoutHandles, $layoutStr];
    }

    /**
     * Return attributes of XML node rendered as a string
     *
     * @param \SimpleXMLElement $node
     * @return string
     */
    protected function renderXmlAttributes(\SimpleXMLElement $node)
    {
        $result = '';
        foreach ($node->attributes() as $attributeName => $attributeValue) {
            $result .= ' ' . $attributeName . '="' . $attributeValue . '"';
        }
        return $result;
    }

    /**
     * Return object representation of XML string
     *
     * @param string $xmlString
     * @return \SimpleXMLElement
     * @throws \UnexpectedValueException
     */
    protected function loadXmlString($xmlString)
    {

        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);
        libxml_clear_errors();

        $xml = simplexml_load_string($xmlString, Element::class);

        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($disableEntities);

        if ($error = libxml_get_last_error()) {
            throw new \UnexpectedValueException($error->message);
        }

        return $xml;
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

        $cacheTags[] = self::LAYOUT_GENERAL_CACHE_TAG;

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
     * Convert a multi-dimensional array into a single-dimensional array.
     * @author Sean Cannon, LitmusBox.com | seanc@litmusbox.com
     * @param  array $array The multi-dimensional array.
     * @return array
     */
    protected function arrayFlatten($array) { 
      if (!is_array($array)) { 
        return false; 
      } 
      $result = array(); 
      foreach ($array as $key => $value) { 
        if (is_array($value)) { 
          $result = array_merge($result, $this->arrayFlatten($value)); 
        } else { 
          $result[$key] = $value; 
        } 
      } 
      return $result; 
    }

    /**
     * Cleanup circular references
     *
     * Destructor should be called explicitly in order to work around the PHP bug
     * https://bugs.php.net/bug.php?id=62468
     */
    public function __destruct()
    {
        $this->updates = [];
    }
}
