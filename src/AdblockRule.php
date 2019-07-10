<?php
namespace Limonte;

/**
 * Class AdblockRule
 * @package Limonte
 */
class AdblockRule
{
    const FILTER_REGEXES = [
        '/\$[script|image|stylesheet|object|object\-subrequest|subdocument|xmlhttprequest|websocket|webrtc|popup|generichide|genericblock|document|elemhide|third\-party|domain|rewrite]+.*domain=~?(.*)/i' => '$1',
        '/\$[script|image|stylesheet|object|object\-subrequest|subdocument|xmlhttprequest|websocket|webrtc|popup|generichide|genericblock|document|elemhide|third\-party|domain|rewrite]+.*$/i' => '',
        '/([\\\.\$\+\?\{\}\(\)\[\]\/])/' => '\\\\$1'
    ];

    const DOMAIN_PLACEHOLDER = 'domain=';

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
     * AdblockRule constructor.
     * @param string $rule
     * @throws InvalidRuleException
     */
    public function __construct(string $rule)
    {
        $this->rule = $rule;

        if (Str::startsWith($this->rule, '@@')) {
            $this->isException = true;
            $this->rule = mb_substr($this->rule, 2);
        }

        // comment
        if (Str::startsWith($rule, '!') || Str::startsWith($rule, '[Adblock')) {
            $this->isComment = true;

        // HTML rule
        } elseif (Str::contains($rule, '##') || Str::contains($rule, '#@#')) {
            $this->isHtml = true;

        // URI rule
        } else {
            $this->makeRegex();
        }
    }

    /**
     * @param  string $entry
     *
     * @return  boolean
     */
    public function matchEntry($entry)
    {
        $domain = parse_url($entry, PHP_URL_HOST);
        if ($this->isIncluded($domain) || !$this->isExcluded($domain)) {
            return (boolean)preg_match('/' . $this->getRegex() . '/', $entry);
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

    private function makeRegex()
    {
        if (empty($this->rule)) {
            throw new InvalidRuleException("Empty rule");
        }

        $regex = $this->rule;

        $domains = $this->getDomainsByPlaceholder($regex);
        $this->domainsExcluded = $domains['excluded'];
        $this->domainsIncluded = $domains['included'];

        foreach (self::FILTER_REGEXES as $rule => $replacement) {
            $regex = preg_replace($rule, $replacement, $regex);
        }

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
