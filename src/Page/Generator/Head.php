<?php

namespace Layout\Core\Page\Generator;

use Layout\Core\Data\LayoutStack;
use Layout\Core\Page\Layout;

class Head
{
    /**
     * Holds the resolved asset data
     * 
     * @var array
     */
    protected $resultAsset = [];

    /**
     * Genrate the head section and return it
     *
     * @param Layout\Core\Data\LayoutStack $stack
     * @param Layout\Core\Page\Layout $layout
     * @return array
     */
    public function generate(LayoutStack $stack, Layout $layout)
    {
        $result = [];
        $result['meta'] = $this->renderMetadata($stack);
        $result['title'] = $this->renderTitle($stack);
        $stack->processRemoveAssets();
        $result['js'] = $this->renderJsAssets($stack);
        $result['css'] = $this->renderCssAssets($stack);
        $result['head_includes'] = $layout->getConfigObject()->get('head.includes', '');    
        return $result;
    }

    /**
     * 
     * @param Layout\Core\Data\LayoutStack $stack
     * @return string
     */
    protected function renderTitle(LayoutStack $stack)
    {
        return '<title>' . htmlspecialchars($stack->getTitle(), ENT_COMPAT, 'UTF-8', false) . '</title>' . "\n";
    }

    /**
     * 
     * @param Layout\Core\Data\LayoutStack $stack
     * @return string
     */
    protected function renderMetadata(LayoutStack $stack)
    {
        $result = '';
        foreach ($stack->getMetadata() as $name => $content) {
            $metadataTemplate = $this->getMetadataTemplate($name);
            if (!$metadataTemplate) {
                continue;
            }
            $result .= str_replace(['%name', '%content'], [$name, $content], $metadataTemplate);
        }
        return $result;
    }

    /**
     * @param string $name
     * @return bool|string
     */
    protected function getMetadataTemplate($name)
    {
        if (strpos($name, 'og:') === 0) {
            return '<meta property="' . $name . '" content="%content"/>' . "\n";
        }

        switch ($name) {
            case 'charset':
                $metadataTemplate = '<meta charset="%content"/>' . "\n";
                break;

            case 'Content-Type':
            case 'x-ua-compatible':
            case 'Content-Security-Policy':
            case 'x-dns-prefetch-control':
            case 'set-cookie':
            case 'Window-Target':
                $metadataTemplate = '<meta http-equiv="%name" content="%content"/>' . "\n";
                break;

            default:
                $metadataTemplate = '<meta name="%name" content="%content"/>' . "\n";
                break;
        }
        return $metadataTemplate;
    }

    /**
     * 
     * @param Layout\Core\Data\LayoutStack $stack
     * @return string
     */
    protected function renderCssAssets(LayoutStack $stack)
    {
        $result = $this->renderAssets($stack);
        unset($result['js']);
        return implode('', $result);
    }

    /**
     * 
     * @param Layout\Core\Data\LayoutStack $stack
     * @return string
     */
    protected function renderJsAssets(LayoutStack $stack)
    {
        $result = $this->renderAssets($stack);
        return $result['js'];
    }

     /**
     * 
     * @param Layout\Core\Data\LayoutStack $stack
     * @return string
     */
    protected function renderAssets(LayoutStack $stack)
    {
        if(empty($this->resultAsset)) {
            $result = array_fill_keys(['base', 'css','ioc','link','js'], '');
            foreach ($stack->getAssets() as $asset) {
                $attributes = array_diff_key($asset,array_flip(['src','content_type']));
                $attributes = $this->getAttributes($attributes);
                $assetTemplate = $this->getAssetTemplate($asset['content_type'],$attributes);
                $result[$asset['content_type']] .= sprintf($assetTemplate, $asset['src']);
            }
            $this->resultAsset = $result;
        }
        return $this->resultAsset;
    }

    /**
     * @param string $contentType
     * @param string|null $attributes
     * @return string
     */
    protected function getAssetTemplate($contentType, $attributes)
    {
        switch ($contentType) {
            case 'js':
                $groupTemplate = '<script ' . $attributes . ' src="%s"></script>' . "\n";
                break;
            case 'base':
                $groupTemplate = '<base ' . $attributes . ' href="%s" />' . "\n"; 
                break;  
            case 'css':
            default:
                $groupTemplate = '<link ' . $attributes . ' href="%s" />' . "\n";
                break;
        }
        return $groupTemplate;
    }

    /**
     * Get all attributes as string
     *
     * @param array $attributes
     * @return string
     */
    protected function getAttributes($attributes)
    {
        $attributesString = '';
        foreach ($attributes as $name => $value) {
            $attributesString .= ' ' . $name . '="' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8', false) . '"';
        }
        return $attributesString;
    }
}