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
     * Holds the current handle for the layout.
     * 
     * @var string
     */
    protected $currentHandle;

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
    public function layout()
    {
        return $this->layout;
    }

    /**
     * Render current layout and return the resonse string
     *
     * @param string $handle
     * @return array ['head' => [] ,'body' => '', 'bodyAttributes' => []]
     */
    public function render($handle)
    {
        $this->currentHandle = $handle;

        return $this->init()
                ->build()
                ->output();
    }

    /**
     * reset the layout for the current page
     *
     * @return $this
     */
    public function reset()
    {
        $this->layout()->reset();
        return $this;
    }

    /**
     * Set up default handles for current page
     *
     * @return $this
     */
    public function init()
    {
        return $this->addHandle([
            'default', $this->currentHandle()
        ]);
    }

    /**
     * Build the layout for the current page
     *
     * @return $this
     */
    public function build()
    {
        return $this->loadUpdates()
            ->generateXml()
            ->generateBlocks();
    }

    /**
     * Render page template.
     *
     * @return array ['head' => [] ,'body' => '', 'bodyAttributes' => []]
     */
    public function output()
    {
        $profilerKey = self::PROFILER_KEY.'::'.$this->currentHandle();

        $this->profiler->start("$profilerKey::layout_render");

        $this->events->fire('route.layout.render.before');
        $this->events->fire('route.layout.render.before.'.$this->currentHandle());

        $this->layout()->addBodyClass($this->currentHandle());

        $output = $this->layout()->getOutput();
        
        $this->profiler->stop("$profilerKey::layout_render");

        return $output;
    }

    /**
     * @param string|string[] $handleName
     * @return $this
     */
    public function addHandle($handleName)
    {
        $this->layout()->manager()->addHandle($handleName);
        return $this;
    }

    /**
     * Retrieve the default layout handle name for the current request
     *
     * @return string
     */
    public function currentHandle()
    {
        return $this->currentHandle;
    }

    /**
     * Load layout updates
     *
     * @return $this
     */
    protected function loadUpdates()
    {
        $profilerKey = self::PROFILER_KEY.'::'.$this->currentHandle();

        $this->events->fire(
            'route.layout.load.before',
            ['layout' => $this->layout()]
        );
        $this->profiler->start("$profilerKey::layout_load");
        $this->layout()->manager()->load();
        $this->profiler->stop("$profilerKey::layout_load");

        return $this;
    }

    /**
     * Generate layout xml
     *
     * @return $this
     */
    protected function generateXml()
    {
        $profilerKey = self::PROFILER_KEY.'::'.$this->currentHandle();

        $this->events->fire(
            'route.layout.generate.xml.before',
            ['layout' => $this->layout()]
        );
        $this->profiler->start("$profilerKey::layout_generate_xml");
        $this->layout()->generateXml();
        $this->profiler->stop("$profilerKey::layout_generate_xml");

        return $this;
    }

    /**
     * Generate layout blocks
     *
     * @return $this
     */
    protected function generateBlocks()
    {
        $profilerKey = self::PROFILER_KEY.'::'.$this->currentHandle();
        
        $this->profiler->start("$profilerKey::layout_generate_blocks");
        
        $this->events->fire(
            'route.layout.generate.blocks.before',
            ['layout' => $this->layout()]
        );

        $this->layout()->generatePageElements();
        
        $this->events->fire(
            'route.layout.generate.blocks.after',
            ['layout' => $this->layout()]
        );

        $this->profiler->stop("$profilerKey::layout_generate_blocks");

        return $this;
    }
}
