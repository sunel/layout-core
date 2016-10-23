<?php 

namespace Layout\Core\Generators\Body;

use Layout\Core\Data\Structure;
use Layout\Core\Data\LayoutStack;

class Block extends AbstractGenrator
{
    /**
     * {@inheritDoc}
     */
    public function generate(LayoutStack $stack, Structure $structure)
    {
        $layout = $this->bodyGenerator->getLayout();

        $blocks = [];
        $blockActions = [];
        // Instantiate blocks and collect all actions data
        foreach ($stack->getElements() as $elementName => $element) {
            list($type, $data) = $element;
            if ($type === 'block') {
                try {
                    $block = $this->generateBlock($stack, $structure, $elementName);
                    $blocks[$elementName] = $block;
                    $layout->setBlock($elementName, $block);
                    try {
                        $block->setLayout($layout);
                        if (!empty($data['actions'])) {
                            $blockActions[$elementName] = $data['actions'];
                        }
                    } catch (\Exception $e) {
                        throw $e;
                    }
                    $stack->unsetElement($elementName);
                } catch (\Exception $e) {
                    throw $e;
                    unset($blocks[$elementName]);
                }
            }
        }
        
        // Run all actions after layout initialization
        foreach ($blockActions as $elementName => $actions) {
            try {
                foreach ($actions as $action) {
                    list($methodName, $actionArguments, $configPath) = $action;
                    #TODO
                    if (empty($configPath)) {
                    }
                    
                    $this->generateAction($blocks[$elementName], $methodName, $actionArguments);
                }
            } catch (\Exception $e) {
                throw $e;
            }
        }
        return $this;
    }

    /**
     * Create block and set related data
     *
     * @param Layout\Core\Data\LayoutStack $stack
     * @param Layout\Core\Data\Structure $structure
     * @param string $elementName
     * @return \Layout\Core\Block\AbstractBlock
     */
    protected function generateBlock(
        LayoutStack $stack,
        Structure $structure,
        $elementName
    ) {
        list(, $data) = $stack->getElement($elementName);
        $attributes = $data['attributes'];

        if (!empty($attributes['group'])) {
            $structure->addToParentGroup($elementName, $attributes['group']);
        }
        if (!empty($attributes['display'])) {
            $structure->setAttribute($elementName, 'display', $attributes['display']);
        }

        // create block
        $className = $attributes['class'];
        $block = $this->bodyGenerator->getLayout()->createBlock($className, $elementName, []);
        if (!empty($attributes['template'])) {
            $block->setTemplate($attributes['template']);
        }
        if (!empty($attributes['ttl'])) {
            $ttl = (int)$attributes['ttl'];
            $block->setTtl($ttl);
        }
        return $block;
    }

     /**
     * Run action defined in layout update
     *
     * @param \Layout\Core\Block\AbstractBlock $block
     * @param string $methodName
     * @param array $actionArguments
     * @return void
     */
    protected function generateAction($block, $methodName, $actionArguments)
    {
        call_user_func_array([$block, $methodName], $args);
    }
}
