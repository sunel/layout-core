<?php

namespace Layout\Core\Block;

use Layout\Core\Object;
use Layout\Core\Page\Layout;
use Layout\Core\Contracts\Profiler;
use Layout\Core\Contracts\ConfigResolver;
use Layout\Core\Contracts\Cacheable as Cache;
use Layout\Core\Contracts\EventsDispatcher as Dispatcher;

abstract class AbstractBlock extends Object
{
    use Cacheable, InteractsWithBlocks;

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
     * Retrieve block view using the logic you prefer.
     *
     * @param string $fileName
     * @param array $viewVars
     * @return string
     */
    abstract protected function getView($fileName, $viewVars);

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

        $this->events->fire(
            'block.to.html.after',
            ['block' => $this, 'transport' => self::$transportObject]
        );

        $html = self::$transportObject->getHtml();

        return $html;
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
     * Before rendering html, but after trying to load cache.
     *
     * @return \Layout\Core\Block\AbstractBlock
     */
    protected function beforeToHtml()
    {
        return $this;
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

        if ($this->canShowTemplateHints()) {
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

        if ($this->canShowTemplateHints()) {
            $html .= '</div>';
        }

        $this->profiler->stop($fileName);

        return $html;
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
     * Check if the template hite can be shown.
     *
     * @return bool
     */
    public function canShowTemplateHints()
    {
        return $this->config->get('show_template_hint', false);
    }
}
