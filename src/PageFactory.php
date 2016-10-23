<?php

namespace Layout\Core;

use Layout\Core\Page\Layout;
use Layout\Core\Contracts\Profiler;
use Layout\Core\Contracts\Cacheable;
use Layout\Core\Contracts\ConfigResolver;
use Layout\Core\Contracts\EventsDispatcher as Dispatcher;

class PageFactory
{
    const PROFILER_KEY = 'dispatch::route';

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
     * The layout instance.
     *
     * @var \Layout\Core\Page\Layout
     */
    protected $layout;

    /**
     * Create a new view factory instance.
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
        $this->layout = new Layout($events, $config, $profiler, $cache);
    }

    /**
     * Get layout instance for current page
     *
     * @return \Layout\Core\Page\Layout
     */
    public function getLayout()
    {
        return $this->layout;
    }

    /**
     * Render current layout and return the resonse string
     *
     * @return array ['head' => '' ,'body' => ''] 
     */
    public function render()
    {
        return $this->initLayout()
                ->buildLayout()
                ->renderLayout();
    }

    /**
     * Set up default handles for current page
     *
     * @return $this
     */
    public function initLayout()
    {
        $this->addHandle('default');
        $this->addHandle($this->routeHandler());
        return $this;
    }

    /**
     * Build the layout for the current page
     *
     * @return $this
     */
    public function buildLayout()
    {
        $this->loadLayoutUpdates();
        $this->generateLayoutXml();
        $this->generateLayoutBlocks();
        return $this;
    }

    /**
     * Load layout updates
     *
     * @return $this
     */
    protected function loadLayoutUpdates()
    {
        $profilerKey = self::PROFILER_KEY.'::'.$this->routeHandler();

        $this->events->fire(
            'route.layout.load.before',
            ['layout' => $this->getLayout()]
        );
        $this->profiler->start("$profilerKey::layout_load");
        $this->getLayout()->getUpdate()->load();
        $this->profiler->stop("$profilerKey::layout_load");

        return $this;
    }

    /**
     * Generate layout xml
     *
     * @return $this
     */
    protected function generateLayoutXml()
    {
        $profilerKey = self::PROFILER_KEY.'::'.$this->routeHandler();

        $this->events->fire(
            'route.layout.generate.xml.before',
            ['layout' => $this->getLayout()]
        );
        $this->profiler->start("$profilerKey::layout_generate_xml");
        $this->getLayout()->generateXml();
        $this->profiler->stop("$profilerKey::layout_generate_xml");

        return $this;
    }

    /**
     * Generate layout blocks
     *
     * @return $this
     */
    protected function generateLayoutBlocks()
    {
        $profilerKey = self::PROFILER_KEY.'::'.$this->routeHandler();
        
        $this->profiler->start("$profilerKey::layout_generate_blocks");
        
        $this->events->fire(
            'route.layout.generate.blocks.before',
            ['layout' => $this->getLayout()]
        );

        $this->getLayout()->generatePageElements();
        
        $this->events->fire(
            'route.layout.generate.blocks.after',
            ['layout' => $this->getLayout()]
        );

        $this->profiler->stop("$profilerKey::layout_generate_blocks");

        return $this;
    }

    /**
     * Render page template.
     *
     * @return array ['head' => '' ,'body' => '']
     */
    public function renderLayout()
    {
        $profilerKey = self::PROFILER_KEY.'::'.$this->routeHandler();

        $this->profiler->start("$profilerKey::layout_render");

        $this->events->fire('route.layout.render.before');
        $this->events->fire('route.layout.render.before.'.$this->routeHandler());

        $output = $this->getLayout()->getOutput();
        $this->profiler->stop("$profilerKey::layout_render");
        return $output;
    }

    /**
     * @param string|string[] $handleName
     * @return $this
     */
    protected function addHandle($handleName)
    {
        $this->getLayout()->getUpdate()->addHandle($handleName);
        return $this;
    }

    /**
     * Retrieve the default layout handle name for the current request
     *
     * @return string
     */
    protected function routeHandler()
    {
        return $this->config->get('current_route_handle');
    }
}
