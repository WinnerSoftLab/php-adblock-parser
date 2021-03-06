<?php
declare(strict_types=1);

namespace Limonte;

/**
 * Class AdblockRule
 * @package Limonte
 */
class AdblockRule
{
    const FILTER_REGEXES = [
        '/\$[script|image|stylesheet|object|object\-subrequest|subdocument|xmlhttprequest|websocket|webrtc|popup|generichide|genericblock|document|elemhide|third\-party|domain|rewrite]+.*domain=~?(.*)/i' => '',
        '/\$[script|image|stylesheet|object|object\-subrequest|subdocument|xmlhttprequest|websocket|webrtc|popup|generichide|genericblock|document|elemhide|third\-party|domain|rewrite]+.*$/i' => '',
        '/([\\\.\$\+\?\{\}\(\)\[\]\/])/' => '\\\\$1'
    ];

    const DOMAIN_PLACEHOLDER = 'domain=';

    const VERSION_HEADER = '[Adblock';
    const EXCEPTION_RULE = '@@';
    const COMMENT_RULE = '!';
    const HTML_RULE = '##';
    const HTML_ELEMENT_HIDE_RULE = '#?#';
    const HTML_ELEMENT_HIDE_EXCEPTION_RULE = '#@#';

    /**
     * @var string
     */
    private $rule;

    /**
     * @var string
     */
    private $regex;

    /**
     * @var bool
     */
    private $isComment = false;

    /**
     * @var bool
     */
    private $isHtml = false;

    /**
     * @var bool
     */
    private $isException = false;

    /**
     * @var array
     */
    private $domainsIncluded = [];

    /**
     * @var array
     */
    private $domainsExcluded = [];

    /**
     * @var bool
     */
    private $isContainsDomain = false;

    /**
     * @var bool
     */
    private $isContainsRoute = false;


    /**
     * AdblockRule constructor.
     * @param string $rule
     * @throws InvalidRuleException
     */
    public function __construct(string $rule)
    {
        $this->rule = $rule;

        if (Str::startsWith($this->rule, self::EXCEPTION_RULE)) {
            $this->isException = true;
            $this->rule = mb_substr($this->rule, 2);
        }

        // comment
        if (Str::startsWith($rule, '!') || Str::startsWith($rule, self::VERSION_HEADER)) {
            $this->isComment = true;

        // HTML rule
        } elseif (Str::contains($rule, self::HTML_RULE)
            || Str::contains($rule, self::HTML_ELEMENT_HIDE_RULE)
            || Str::contains($rule, self::HTML_ELEMENT_HIDE_EXCEPTION_RULE)
        ) {
            $this->isHtml = true;

        // URI rule
        } else {
            $this->makeRegex();
        }
    }

    /**
     * @param string $entry
     * @param string $entryDomain
     * @param bool $entryContainsRoute
     * @return bool
     */
    public function matchEntry(string $entry, string $entryDomain, bool $entryContainsRoute): bool
    {
        $checkDomainOnly = $entryDomain && !$entryContainsRoute;
        $checkRouteOnly = $entryContainsRoute && !$entryDomain;
        $checkFullUrl = $entryDomain && $entryContainsRoute;

        $ruleContainsDomainOnly = $this->isContainsDomain && !$this->isContainsRoute;
        $ruleContainsRouteOnly = $this->isContainsRoute && !$this->isContainsDomain;

        $isIncluded = (!$this->domainsIncluded || $this->isIncluded($entryDomain)) && (!$this->domainsExcluded || !$this->isExcluded($entryDomain));

        if (
            ($checkFullUrl && $isIncluded)
            || ($checkDomainOnly && $ruleContainsDomainOnly && $isIncluded)
            || ($checkRouteOnly && $ruleContainsRouteOnly)
        ) {
            return (bool)preg_match('/' . $this->getRegex() . '/', $entry);
        }

        return false;
    }

    /**
     * @return  string
     */
    public function getRegex()
    {
        return $this->regex;
    }

    /**
     * @return  string
     */
    public function getRule()
    {
        return $this->rule;
    }

    /**
     * @return  boolean
     */
    public function isComment()
    {
        return $this->isComment;
    }

    /**
     * @return  boolean
     */
    public function isHtml()
    {
        return $this->isHtml;
    }

    /**
     * @return  boolean
     */
    public function isException()
    {
        return $this->isException;
    }

    /**
     * @param $domain
     * @return bool
     */
    public function isIncluded($domain): bool
    {
        return in_array($domain, $this->domainsIncluded);
    }

    /**
     * @param $domain
     * @return bool
     */
    public function isExcluded($domain): bool
    {
        return in_array($domain, $this->domainsExcluded);
    }

    /**
     * @return bool
     */
    public function isContainsDomain(): bool
    {
        return $this->isContainsDomain;
    }

    /**
     * @return bool
     */
    public function isContainsRoute(): bool
    {
        return $this->isContainsRoute;
    }

    private function makeRegex()
    {
        if (empty($this->rule) || $this->rule === '//') {
            throw new InvalidRuleException("Empty rule");
        }

        $regex = $this->rule;

        $domains = $this->getDomainsByPlaceholder($regex);
        $this->domainsExcluded = $domains['excluded'];
        $this->domainsIncluded = $domains['included'];
        $this->isContainsDomain = (
            (
                Str::contains($this->rule, '://')
                || Str::startsWith($this->rule, '|')
            )
            || ($this->domainsIncluded || $this->domainsExcluded)
        );

        foreach (self::FILTER_REGEXES as $rule => $replacement) {
            $regex = preg_replace($rule, $replacement, $regex);
        }

        $this->isContainsRoute = !$this->isContainsDomain || $this->checkRuleContainsRoute($regex);

        // Separator character ^ matches anything but a letter, a digit, or
        // one of the following: _ - . %. The end of the address is also
        // accepted as separator.
        $regex = str_replace("^", "([^\w\d_\-\.%]|$)", $regex);

        // * symbol
        $regex = str_replace("*", ".*", $regex);

        // | in the end means the end of the address
        if (Str::endsWith($regex, '|')) {
            $regex = mb_substr($regex, 0, mb_strlen($regex) - 1) . '$';
        }

        // || in the beginning means beginning of the domain name
        if (Str::startsWith($regex, '||')) {
            if (mb_strlen($regex) > 2) {
                // http://tools.ietf.org/html/rfc3986#appendix-B
                $regex = "^([^:\/?#]+:)?(\/\/([^\/?#]*\.)?)?" . mb_substr($regex, 2);
            }
        // | in the beginning means start of the address
        } elseif (Str::startsWith($regex, '|')) {
            $regex = '^' . mb_substr($regex, 1);
        }

        // other | symbols should be escaped
        $regex = preg_replace("/\|(?![\$])/", "\|$1", $regex);


        $this->regex = $regex;
    }

    /**
     * @param $rule
     * @return bool
     */
    public function checkRuleContainsRoute($rule)
    {
        $validForCheckEntry = str_replace(['://', ':\/\/', '|', '^', '$'], '', $rule);
        $parts = array_filter(explode('/', $validForCheckEntry, 2));
        $route = $parts[1] ?? '';

        return strlen($route) > 1;
    }

    /**
     * @param string $rule
     * @return array
     */
    private function getDomainsByPlaceholder(string $rule): array
    {
        $results = [
            'included' => [],
            'excluded' => []
        ];
        $domains = '';
        $pos = strpos($rule, self::DOMAIN_PLACEHOLDER);
        if ($pos !== false) {
            $domains = substr($rule, $pos + strlen(self::DOMAIN_PLACEHOLDER), strlen($rule));
        }

        foreach (array_filter(explode('|', $domains)) as $domain) {
            if (strpos($domain, '~') !== false) {
                $results['excluded'][] = trim(str_replace('~', '', $domain));
            } else {
                $results['included'][] = trim($domain);
            }
        }

        return $results;
    }
}
