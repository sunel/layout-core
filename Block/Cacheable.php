<?php

namespace Layout\Core\Block;

use Carbon\Carbon;

trait Cacheable
{
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
}
