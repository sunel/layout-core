<?php

namespace Layout\Core;

use Layout\Core\Contracts\Profiler;
use Layout\Core\Contracts\ConfigResolver;
use Layout\Core\Contracts\EventsDispatcher as Dispatcher;

class Factory
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
     * The layout instance.
     *
     * @var \Layout\Core\Layout
     */
    protected $layout;

    /**
     * Array of custom handles.
     *
     * @var array
     */
    protected $customHandles = [];

    /**
     * Create a new view factory instance.
     *
     * @param \Layout\Core\Contracts\EventsDispatcher $events
     * @param \Layout\Core\Contracts\ConfigResolver $config
     * @param \Layout\Core\Contracts\Profiler $profile
     */
    public function __construct(
        Dispatcher $events, 
        ConfigResolver $config,
        Profiler $profile
    ) {
        $this->events = $events;
        $this->config = $config;
        $this->profile = $profile;
    }

    /**
     * Get the evaluated view contents.
     *
     * @param null|string $handles
     * @param bool  $generateBlocks
     * @param bool  $generateXml
     * @param bool  $disableRouteHandle
     *
     * @return html;
     */
    public function render(
        $handles = null,
        $generateBlocks = true,
        $generateXml = true,
        $disableRouteHandle = false
    ) {
        return $this->loadHandles($handles, $disableRouteHandle)
            ->loadLayout($generateBlocks, $generateXml)
            ->renderLayout();
    }

    /**
     * Load the handels to the layout.
     *
     * @param null|string $handles
     * @param bool  $disableRouteHandle
     *
     * @return \Layout\Core\Factory;
     */
    public function loadHandles($handles = null, $disableRouteHandle = false)
    {
        // if handles were specified in arguments load them first
        if (false !== $handles && '' !== $handles) {
            $this->getLayout()->getUpdate()->addHandle($handles ? $handles : 'default');
        }
        // add default layout handles for this action
        if (!$disableRouteHandle) {
            $this->addRouteLayoutHandles();
        }
                
        $this->customHandle();
        $this->operatingSystemHandle();
        $this->browserHandle();
        $this->loadLayoutUpdates();

        return $this;
    }

    /**
     * Load layout by handles(s).
     *
     * @param bool             $generateBlocks
     * @param bool             $generateXml
     *
     * @return \Layout\Core\Factory
     */
    public function loadLayout($generateBlocks = true, $generateXml = true)
    {
        if (!$generateXml) {
            return $this;
        }
        $this->generateLayoutXml();
        if (!$generateBlocks) {
            return $this;
        }
        $this->generateLayoutBlocks();
        $this->_isLayoutLoaded = true;

        return $this;
    }

    /**
     * Rendering layout.
     *
     * @param string $output
     */
    public function renderLayout($output = '')
    {
        $profilerKey = self::PROFILER_KEY.'::'.$this->routeHandler();

        $this->profile->start("$profilerKey::layout_render");
        if ('' !== $output) {
            $this->getLayout()->addOutputBlock($output);
        }

        $this->events->fire('route.layout.render.before');
        $this->events->fire('route.layout.render.before.'.$this->routeHandler());

        $output = $this->getLayout()->getOutput();
        $this->profile->stop("$profilerKey::layout_render");
        return $output;
    }

    public function addCustomHandle($value)
    {
        $this->customHandles[] = $value;
        return $this;
    }

    /**
     * Retrieve current layout object.
     *
     * @return \Layout\Core\Layout
     */
    public function getLayout()
    {
        if (!$this->layout) {
            throw new \Exception("Layout Instace is not set");
        }
        return $this->layout;
    }

    /**
     * Retrieve current layout object.
     *
     * @return \Layout\Core\Layout
     */
    public function setLayout(Layout $layout)
    {
        $this->layout = $layout;
        return $this;
    }

    protected function addRouteLayoutHandles()
    {
        $update = $this->getLayout()->getUpdate();
        // load action handle
        if (!empty($this->routeHandler())) {
            $update->addHandle($this->routeHandler());
        }

        return $this;
    }

    protected function loadLayoutUpdates()
    {
        $profilerKey = self::PROFILER_KEY.'::'.$this->routeHandler();
        // dispatch event for adding handles to layout update
        $this->events->fire(
            'route.layout.load.before',
            ['layout' => $this->getLayout()]
        );
        // load layout updates by specified handles
        $this->profile->start("$profilerKey::layout_load");
        $this->getLayout()->getUpdate()->load();
        $this->profile->stop("$profilerKey::layout_load");

        return $this;
    }

    protected function generateLayoutXml()
    {
        $profilerKey = self::PROFILER_KEY.'::'.$this->routeHandler();

        $this->events->fire(
            'route.layout.generate.xml.before',
            ['layout' => $this->getLayout()]
        );

        // generate xml from collected text updates
        $this->profile->start("$profilerKey::layout_generate_xml");
        $this->getLayout()->generateXml();
        $this->profile->stop("$profilerKey::layout_generate_xml");

        return $this;
    }

    protected function generateLayoutBlocks()
    {
        $profilerKey = self::PROFILER_KEY.'::'.$this->routeHandler();
        // dispatch event for adding xml layout elements
        $this->events->fire(
            'route.layout.generate.blocks.before',
            ['layout' => $this->getLayout()]
        );

        // generate blocks from xml layout
        $this->profile->start("$profilerKey::layout_generate_blocks");
        $this->getLayout()->generateBlocks();
        $this->getLayout()->fixMissedBlock();
        $this->profile->stop("$profilerKey::layout_generate_blocks");

        $this->events->fire(
            'route.layout.generate.blocks.after',
            ['layout' => $this->getLayout()]
        );

        return $this;
    }

    protected function routeHandler()
    {
        return $this->config->get('current_route_handle');
    }

    protected function customHandle()
    {
        $update = $this->getLayout()->getUpdate();
        foreach ($this->customHandles as $value) {
            $update->addHandle($value);
        }
    }

    /**
     * Add a handle for operating systems, e.g.:
     * <layout>
     *   <operating_system_linux>
     *   </operating_system_linux>
     * </layout>.
     *
     * @return Layout\Factory
     */
    public function operatingSystemHandle()
    {
        $agent = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match('/Linux/', $agent)) {
            $os = 'linux';
        } elseif (preg_match('/Win/', $agent)) {
            $os = 'windows';
        } elseif (preg_match('/Mac/', $agent)) {
            $os = 'osx';
        } else {
            $os = null;
        }
        if ($os) {
            $update = $this->getLayout()->getUpdate();
            $update->addHandle('operating_system_'.$os);
        }

        return $this;
    }

    /**
     * Add layout handle for browser type, e.g.:
     * <layout>
     *   <browser_firefox>
     *   </browser_firefox>
     * </layout>.
     *
     * @return Layout\Core\Factory
     */
    public function browserHandle()
    {
        $agent = $_SERVER['HTTP_USER_AGENT'];
        if (stripos($agent, 'Firefox') !== false) {
            $agent = 'firefox';
        } elseif (stripos($agent, 'MSIE') !== false) {
            $agent = 'ie';
        } elseif (stripos($agent, 'iPad') !== false) {
            $agent = 'ipad';
        } elseif (stripos($agent, 'Android') !== false) {
            $agent = 'android';
        } elseif (stripos($agent, 'Chrome') !== false) {
            $agent = 'chrome';
        } elseif (stripos($agent, 'Safari') !== false) {
            $agent = 'safari';
        } else {
            $agent = null;
        }
        if ($agent) {
            $update = $this->getLayout()->getUpdate();
            $update->addHandle('browser_'.$agent);
        }

        return $this;
    }
}