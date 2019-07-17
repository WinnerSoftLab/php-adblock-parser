<?php
declare(strict_types=1);
namespace Limonte\Tests;

use Limonte\AdblockParser;
use Limonte\AdblockRule;

class AdblockRuleTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Limonte\InvalidRuleException
     */
    public function testInvalidRegex()
    {
        $invalidRule = new AdblockRule('//');
        $invalidRule->getRegex();
    }

    /**
     * @throws \Limonte\InvalidRuleException
     */
    public function testEscapeSpecialCharacters()
    {
        $rule = new AdblockRule('.$+?{}()[]/\\');
        $this->assertEquals('\.\$\+\?\{\}\(\)\[\]\/\\\\', $rule->getRegex());
    }

    /**
     * @throws \Limonte\InvalidRuleException
     */
    public function testCaret()
    {
        $rule = new AdblockRule('domain^');
        $this->assertEquals('domain([^\w\d_\-\.%]|$)', $rule->getRegex());
    }

    /**
     * @throws \Limonte\InvalidRuleException
     */
    public function testAsterisk()
    {
        $rule = new AdblockRule('domain*');
        $this->assertEquals('domain.*', $rule->getRegex());
    }

    /**
     * @throws \Limonte\InvalidRuleException
     */
    public function testVerticalBars()
    {
        $rule = new AdblockRule('||domain');
        $this->assertEquals('^([^:\/?#]+:)?(\/\/([^\/?#]*\.)?)?domain', $rule->getRegex());

        $rule = new AdblockRule('|domain');
        $this->assertEquals('^domain', $rule->getRegex());

        $rule = new AdblockRule('domain|bl||ah');
        $this->assertEquals('domain\|bl\|\|ah', $rule->getRegex());
    }

    /**
     * @throws \Limonte\InvalidRuleException
     */
    public function testMatchUrl()
    {
        $rule = new AdblockRule('swf|');
        $entry = 'http://example.com/annoyingflash.swf';
        $info = (new AdblockParser)->getEntryInfo($entry);
        $this->assertTrue($rule->matchEntry($entry, $info['domain'], $info['containsRoute']));

        $entry = 'http://example.com/swf/index.html';
        $info = (new AdblockParser)->getEntryInfo($entry);
        $this->assertFalse($rule->matchEntry($entry, $info['domain'], $info['containsRoute']));
    }

    /**
     * @throws \Limonte\InvalidRuleException
     */
    public function testComment()
    {
        $rule = new AdblockRule('!this is comment');
        $this->assertTrue($rule->isComment());
        $rule = new AdblockRule('[Adblock Plus 1.1]');
        $this->assertTrue($rule->isComment());
        $rule = new AdblockRule('non-comment rule');
        $this->assertFalse($rule->isComment());
    }
}
