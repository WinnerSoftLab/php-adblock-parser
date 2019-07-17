<?php
declare(strict_types=1);

namespace Limonte;

class AdblockParser
{
    /**
     * @var AdblockRule[]
     */
    private $rules;

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
     * @param  string[] $rules
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
     * @return  array
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * @param string $entry
     * @return array
     */
    public function shouldBlock(string $entry): array
    {
        $rules = [];
        $entry = trim($entry);

        $entryInfo = $this->getEntryInfo($entry);
        foreach ($this->rules as $rule) {
            if ($rule->matchEntry($entry, $entryInfo['domain'], $entryInfo['containsRoute'])) {
                if ($rule->isException()) {
                    return [];
                }
                $rules[] = $rule->getRule();
            }
        }

        return $rules;
    }

    /**
     * @param string $entry
     * @return array
     */
    public function getEntryInfo(string $entry): array
    {
        $containsRoute = false;
        $urlParts = parse_url($entry);
        $domain = $urlParts['host'] ?? '';
        $path = $urlParts['path'] ?? '';
        $query = (bool)($urlParts['query'] ?? '');

        //parse_url($entry, PHP_URL_HOST) works only if there is schema
        if (Str::startsWith($entry, '//') || Str::contains($entry, '://')) {
            if (strlen($path) > 1 || (bool)$query) {
                $containsRoute = true;
            }
            //route must always start with /
        } elseif (Str::startsWith($entry, '/')) {
            $containsRoute = true;
        } else {
            //additional check in case it was a route without protocol
            $parts = array_filter(explode('/', $entry, 2));
            $domain = $parts[0];
            $containsRoute = count($parts) > 1;
        }

        return [
            'domain' => rtrim($domain, '/'),
            'containsRoute' => $containsRoute
        ];
    }
}
