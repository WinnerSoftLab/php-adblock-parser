<?php
declare(strict_types=1);

namespace Limonte\Tests;

use Limonte\AdblockParser;
use Limonte\AdblockRule;

class AdblockParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AdblockParser
     */
    protected $parser;

    public function testBlockByAddressParts()
    {
        $this->parser = new AdblockParser(['-ad-code/']);
        $this->shouldBlock([
            'http://test.com/sss-ad-code/',
            'http://test.com/sss-ad-code/sss',
            'http://test.com/-ad-code/',
        ]);
        $this->shouldNotBlock([
            'http://test.com/sss-ad-codes',
        ]);

        $this->parser = new AdblockParser(['/banner/*/img^']);
        $this->shouldBlock([
            'example.com/banner/foo/img',
            'http://example.com/banner/foo/img',
            'http://example.com/banner/foo/bar/img?param',
            'http://example.com/banner//img/foo',
            '/banner/foo/img',
            '//banner/foo/img',
            '//banner//img/foo',
        ]);
        $this->shouldNotBlock([
            'http://example.com/banner/img',
            'http://example.com/banner/foo/imgraph',
            'http://example.com/banner/foo/img.gif',
            '/banner/img',
            '/banner/foo/imgraph',
            '/banner/foo/img.gif',
        ]);
    }

    public function testRulesWithDomainFilters()
    {
        $this->parser = new AdblockParser([
            '/test/*$script,~third-party,domain=test1.com|test2.com',
        ]);
        $this->shouldBlock([
            'http://test1.com/test/',
        ]);
        $this->shouldNotBlock([
            'http://test.com/test/',
        ]);

        $this->parser = new AdblockParser([
            '/ezo/*$script,~third-party,domain=yandex.by|yandex.com|yandex.kz|yandex.ru|yandex.ua',
        ]);
        $this->shouldBlock([
            'http://yandex.by/ezo/',
            'http://yandex.ru/ezo/',
            'http://yandex.ru/ezo/sss',
            'yandex.ru/ezo/',
            'yandex.ru/ezo/sss',
        ]);
        $this->shouldNotBlock([
            'http://yandex.by/',
            'http://yandex.ru/',
            'http://yandex.ru/ezo',
            'http://yandex.ru/sss/ezo',
        ]);

        $this->parser = new AdblockParser([
            '/ezo/*$script,~third-party,domain=~yandex.by|~yandex.com|~yandex.kz|~yandex.ru|~yandex.ua',
        ]);
        $this->shouldBlock([
            'http://test.by/ezo/',
            'http://test.ru/ezo/',
            'http://test.ru/ezo/sss',
            'test.ru/ezo/',
            'test.ru/ezo/sss',
        ]);
        $this->shouldNotBlock([
            'http://yandex.by/ezo/',
            'http://yandex.ru/ezo/',
            'http://yandex.ru/ezo/sss',
            'yandex.ru/ezo/',
            'yandex.ru/ezo/sss',
            'http://test.by/',
            'http://test.ru/',
            'http://test.ru/ezo',
            'http://test.ru/sss/ezo',
        ]);

        $this->parser = new AdblockParser([
            '-advertise.$domain=~i-advertise.net|~mb-advertise.gr',
        ]);
        $this->shouldBlock([
            'http://tt-advertise.gr/',
            'tt-advertise.gr/',
        ]);
        $this->shouldNotBlock([
            'http://mb-advertise.gr/',
            'mb-advertise.gr/',
        ]);

        $this->parser = new AdblockParser([
            '-advertise.$domain=i-advertise.net|mb-advertise.gr',
        ]);
        $this->shouldBlock([
            'http://mb-advertise.gr/',
            'mb-advertise.gr/',
        ]);
        $this->shouldNotBlock([
            'http://tt-advertise.gr/',
            'tt-advertise.gr/',
        ]);

        $this->parser = new AdblockParser([
            '-advertise.$domain=i-advertise.net|mb-advertise.gr',
        ]);
        $this->shouldBlock([
            'http://mb-advertise.gr/',
            'mb-advertise.gr/',
        ]);
        $this->shouldNotBlock([
            'http://tt-advertise.gr/',
            'tt-advertise.gr/',
        ]);
    }

    public function testBlockByDomainName()
    {
        $this->parser = new AdblockParser(['||ads.example.com^']);
        $this->shouldBlock([
            'http://ads.example.com/foo.gif',
            'http://server1.ads.example.com/foo.gif',
            'https://ads.example.com:8000/',
            'ads.example.com:8000/',
            'ads.example.com:8000',
            'ads.example.com',
            'ads.example.com/',
        ]);
        $this->shouldNotBlock([
            'http://ads.example.com.ua/foo.gif',
            'example.com.ua/foo.gif',
            'example.com/foo.gif',
            'example.com/',
            'example.com',
            'http://example.com/redirect/http://ads.example.com/',
        ]);

        $this->parser = new AdblockParser(['|http://baddomain.example/']);
        $this->shouldBlock([
            'http://baddomain.example/banner.gif',
        ]);
        $this->shouldNotBlock([
            'http://gooddomain.example/analyze?http://baddomain.example',
        ]);
    }

    public function testBlockExactAddress()
    {
        $this->parser = new AdblockParser(['|http://example.com/|']);
        $this->shouldBlock([
            'http://example.com/',
        ]);
        $this->shouldNotBlock([
            'http://example.com/foo.gif',
            'http://example.info/redirect/http://example.com/',
        ]);
    }

    public function testBlockBeginningDomain()
    {
        $this->parser = new AdblockParser(['||example.com/banner.gif']);
        $this->shouldBlock([
            'http://example.com/banner.gif',
            'https://example.com/banner.gif',
            'http://www.example.com/banner.gif',
        ]);
        $this->shouldNotBlock([
            'http://badexample.com/banner.gif',
            'http://gooddomain.example/analyze?http://example.com/banner.gif',
        ]);
    }

    public function testCaretSeparator()
    {
        $this->parser = new AdblockParser(['http://example.com^']);
        $this->shouldBlock([
            'http://example.com/',
            'http://example.com:8000/ ',
        ]);
        $this->shouldNotBlock([
            'http://example.com.ar/',
        ]);

        $this->parser = new AdblockParser(['^example.com^']);
        $this->shouldBlock([
            'http://example.com:8000/foo.bar?a=12&b=%D1%82%D0%B5%D1%81%D1%82',
        ]);

        $this->parser = new AdblockParser(['^%D1%82%D0%B5%D1%81%D1%82^']);
        $this->shouldBlock([
            'http://example.com:8000/foo.bar?a=12&b=%D1%82%D0%B5%D1%81%D1%82',
        ]);

        $this->parser = new AdblockParser(['^foo.bar^']);
        $this->shouldBlock([
            'http://example.com:8000/foo.bar?a=12&b=%D1%82%D0%B5%D1%81%D1%82',
        ]);
    }

    public function testParserException()
    {
        $this->parser = new AdblockParser(['adv', '@@advice.']);
        $this->shouldBlock([
            'http://example.com/advert.html',
        ]);
        $this->shouldNotBlock([
            'http://example.com/advice.html',
        ]);

        $this->parser = new AdblockParser(['@@|http://example.com', '@@advice.', 'adv', '!foo']);
        $this->shouldBlock([
            'http://examples.com/advert.html',
        ]);
        $this->shouldNotBlock([
            'http://example.com/advice.html',
            'http://example.com/advert.html',
            'http://examples.com/advice.html',
            'http://examples.com/#!foo',
        ]);
    }

    public function testGetEntryInfo()
    {
        $this->assertEntryInfo('http://test.com', 'test.com', false);
        $this->assertEntryInfo('http://test.com/', 'test.com', false);
        $this->assertEntryInfo('test.com', 'test.com', false);
        $this->assertEntryInfo('test.com/', 'test.com', false);
        $this->assertEntryInfo('/test', '', true);
        $this->assertEntryInfo('test.com/ttt', 'test.com', true);
        //considering it to be a domain without mask if not proven otherwise
        $this->assertEntryInfo('test', 'test', false);
    }

    /**
     * @throws \Limonte\InvalidRuleException
     */
    public function testCheckRuleContainsRoute()
    {
        $this->assertRuleContainsRoute('/ezo/*$script,~third-party,domain=~yandex.by|~yandex.com|~yandex.kz|~yandex.ru|~yandex.ua');
        $this->assertRuleContainsRoute('||ads.example.com/test^');
        $this->assertRuleContainsRoute('||ads.example.com/test^');
        $this->assertRuleContainsRoute('||cal-one.net/ellington/deals_widget.php?^');
        $this->assertRuleContainsRoute('||cdn.totalfratmove.com/ttt/^$image,domain=postgradproblems.com');

        $this->assertRuleNotContainsRoute('http://example.com^');
        $this->assertRuleNotContainsRoute('||ads.example.com^');
        $this->assertRuleNotContainsRoute('||cdn.totalfratmove.com^$image,domain=postgradproblems.com');
    }

    /**
     * @param string $entry
     * @param $domain
     * @param $containsRoute
     */
    private function assertEntryInfo(string $entry, $domain, $containsRoute)
    {
        $this->parser = new AdblockParser;
        $info = $this->parser->getEntryInfo($entry);
        $this->assertTrue(($info['domain'] == $domain && $info['containsRoute'] == $containsRoute));
    }

    /**
     * @param string $rule
     * @throws \Limonte\InvalidRuleException
     */
    private function assertRuleContainsRoute(string $rule)
    {
        $this->assertTrue($this->checkRuleRoute($rule));
    }

    /**
     * @param string $rule
     * @throws \Limonte\InvalidRuleException
     */
    private function assertRuleNotContainsRoute(string $rule)
    {
        $this->assertFalse($this->checkRuleRoute($rule));
    }

    /**
     * @param string $rule
     * @return bool
     * @throws \Limonte\InvalidRuleException
     */
    private function checkRuleRoute(string $rule): bool
    {
        $rule = new AdblockRule($rule);
        $ruleString = '';
        foreach (AdblockRule::FILTER_REGEXES as $pattern => $replacement) {
            $ruleString = preg_replace($pattern, $replacement, $rule->getRule());
        }
        return $rule->checkRuleContainsRoute($ruleString);
    }


    /**
     * @param $url
     */
    private function shouldBlock($url)
    {
        if (is_string($url)) {
            $this->assertTrue((bool)$this->parser->shouldBlock($url), $url);
        } elseif (is_array($url)) {
            foreach ($url as $i) {
                $this->assertTrue((bool)$this->parser->shouldBlock($i), $i);
            }
        }
    }

    /**
     * @param $url
     */
    private function shouldNotBlock($url)
    {
        if (is_string($url)) {
            $this->assertFalse((bool)$this->parser->shouldBlock($url), $url);
        } elseif (is_array($url)) {
            foreach ($url as $i) {
                $this->assertFalse((bool)$this->parser->shouldBlock($i), $i);
            }
        }
    }
}
