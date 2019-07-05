<?php
namespace Limonte;

class AdblockParser
{
    /**
     * @var AdblockRule[]
     */
    private $rules;

    private $cacheFolder;

    private $cacheExpire = 1; // 1 day

    public function __construct(array $rules = [])
    {
        $this->initRules($rules);
    }

    /**
     * @param array $rules
     * @return AdblockRule[]
     */
    public function initRules(array $rules = [])
    {
        $this->rules = [];
        $this->addRules($rules);
        return $this->rules;
    }

    /**
     * @param AdblockRule[] $rules
     */
    public function setInitiatedRules(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * @param  string[]  $rules
     */
    public function addRules($rules)
    {
        foreach ($rules as $rule) {
            try {
                $rule = new AdblockRule($rule);
                if (!$rule->isComment() && !$rule->isHtml()) {
                    $this->rules[] = $rule;
                }
            } catch (InvalidRuleException $e) {
                // Skip invalid rules
            }
        }

        // Sort rules, exceptions first
        usort($this->rules, function (AdblockRule $a, AdblockRule $b) {
            return (int)$a->isException() < (int)$b->isException();
        });
    }

    /**
     * @param  string|array  $path
     */
    public function loadRules($path)
    {
        // single resource
        if (is_string($path)) {
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                $content = $this->getCachedResource($path);
            } else {
                $content = @file_get_contents($path);
            }
            if ($content) {
                $rules = preg_split("/(\r\n|\n|\r)/", $content);
                $this->addRules($rules);
            }
        // array of resources
        } elseif (is_array($path)) {
            foreach ($path as $item) {
                $this->loadRules($item);
            }
        }
    }

    /**
     * @return  array
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * @param string $entry
     * @return string
     */
    public function getSearchEntry(string $entry): string
    {
        return preg_replace('/^(https?:)?(\/\/)?(www\.)?(.*)?/i', '$4', $entry);
    }

    /**
     * @param string $entry
     * @param bool $checkDirect
     * @return array
     * @throws \Exception
     */
    public function shouldBlock(string $entry, bool $checkDirect = true): array
    {
        $rules = [];
        $entry = trim($entry);

        $isUrl = (bool)filter_var($entry, FILTER_VALIDATE_DOMAIN);
        $directMatchEntry = $this->getSearchEntry($entry);

        foreach ($this->rules as $rule) {
            $isDirectMatch = $checkDirect && !$rule->isException() && strpos($rule->getRule(), $directMatchEntry) !== false;
            if ($isDirectMatch || ($isUrl && $rule->matchUrl($entry))) {
                if (!$isDirectMatch && $rule->isException()) {
                    return [];
                }
                $rules[] = $rule->getRule();
            }
        }

        return $rules;
    }

    /**
     * Get cache folder
     *
     * @return string
     */
    public function getCacheFolder()
    {
        return $this->cacheFolder;
    }

    /**
     * Set cache folder
     *
     * @param  string  $cacheFolder
     */
    public function setCacheFolder($cacheFolder)
    {
        $this->cacheFolder = rtrim($cacheFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Get cache expire (in days)
     *
     * @return integer
     */
    public function getCacheExpire()
    {
        return $this->cacheExpire;
    }

    /**
     * Set cache expire (in days)
     *
     * @param  integer  $expireInDays
     */
    public function setCacheExpire($expireInDays)
    {
        $this->cacheExpire = $expireInDays;
    }

    /**
     * Clear external resources cache
     */
    public function clearCache()
    {
        if ($this->cacheFolder) {
            foreach (glob($this->cacheFolder . '*') as $file) {
                unlink($file);
            }
        }
    }

    /**
     * @param  string  $url
     *
     * @return string
     */
    private function getCachedResource($url)
    {
        if (!$this->cacheFolder) {
            return @file_get_contents($url);
        }

        $cacheFile = $this->cacheFolder . basename($url) . md5($url);

        if (file_exists($cacheFile) && (filemtime($cacheFile) > (time() - 60 * 24 * $this->cacheExpire))) {
            // Cache file is less than five minutes old.
            // Don't bother refreshing, just use the file as-is.
            $content = @file_get_contents($cacheFile);
        } else {
            // Our cache is out-of-date, so load the data from our remote server,
            // and also save it over our cache for next time.
            $content = @file_get_contents($url);
            if ($content) {
                file_put_contents($cacheFile, $content, LOCK_EX);
            }
        }

        return $content;
    }
}
