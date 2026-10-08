<?php

declare(strict_types=1);

/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */

namespace Zyppy\Page\Tests\Template;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Renders the ported Twig templates for real.
 *
 * These templates use only core Twig - replace, slice, trim, default, loop - and
 * no Contao functions, filters or tags, so a plain Environment renders them with
 * no site and on every supported Contao version.
 *
 * One difference from a real render, corrected here on 2026-09-05 because the
 * original comment overstated it: Contao registers its own "contao_html" escaper
 * for everything in the @Contao namespace (ContaoExtension::addContaoEscaperRule)
 * and swaps it in for "html" via ContaoEscaperNodeVisitor, and that escaper skips
 * double-encoding for input-encoded values. A plain Environment escapes with
 * Twig's own strategy instead. It does not change any assertion below, because
 * every fixture here contains a raw "<" or ">", which is precisely the case where
 * ContaoEscaper escapes exactly as Twig does. Do NOT add assertions over
 * already-entity-encoded fixtures to this file - those are the ones where the two
 * escapers differ, and this harness would answer for the wrong one.
 *
 * A file-existence or string-matching test would pass on a broken template; each
 * assertion here is made against rendered output.
 */
class TwigTemplateTest extends TestCase
{
	private function twig(): Environment
	{
		$strBase = \dirname(__DIR__, 2) . '/contao/templates';

		$objLoader = new FilesystemLoader(array(
			$strBase . '/modules',
			$strBase . '/navigation',
		));

		// Contao renders with HTML autoescaping; match it exactly.
		return new Environment($objLoader, array('autoescape' => 'html', 'cache' => false));
	}

	private function item(array $arrOverride = array()): array
	{
		return array_merge(array(
			'class'       => '',
			'alias'       => 'a-page',
			'isActive'    => false,
			'link'        => 'Link text',
			'href'        => 'a-page.html',
			'title'       => 'A page',
			'pageTitle'   => '',
			'accesskey'   => '',
			'nofollow'    => false,
			'target'      => '',
			'subitems'    => '',
			'page_image'  => '',
			'page_teaser' => '',
		), $arrOverride);
	}

	private function render(string $strTemplate, array $arrItems, int $intLimit = 0, string $strLevel = 'level_1'): string
	{
		return $this->twig()->render($strTemplate, array(
			'level'  => $strLevel,
			'items'  => $arrItems,
			'module' => array('navSubitemLimit' => $intLimit),
		));
	}

	/**
	 * The whole point of the port: page_teaser reached the markup unescaped for
	 * years. Assert BOTH directions so the claim cannot pass vacuously.
	 *
	 * Only the body template renders a teaser on the INACTIVE branch. The header
	 * deliberately does not - that asymmetry is inherited from the .html5 and is
	 * preserved by the port, so asserting it here pins it down.
	 */
	public function testTeaserIsEscapedOnTheInactiveBodyBranch(): void
	{
		$arrItems = array($this->item(array(
			'page_teaser' => '<script>alert(1)</script>',
		)));

		$strBody = $this->render('nav_pagepreview_body.html.twig', $arrItems);
		$this->assertStringContainsString('&lt;script&gt;', $strBody, 'the body template must escape the teaser');
		$this->assertStringNotContainsString('<script>alert', $strBody, 'the body template must not emit a live script tag');

		$strHeader = $this->render('nav_pagepreview_header.html.twig', $arrItems);
		$this->assertStringNotContainsString('page_teaser', $strHeader, 'the header renders no teaser on the inactive branch');
	}

	public function testTeaserIsEscapedOnTheActiveBranchToo(): void
	{
		$arrItems = array($this->item(array(
			'isActive'    => true,
			'page_teaser' => '<script>alert(1)</script>',
		)));

		foreach (array('nav_pagepreview_header.html.twig', 'nav_pagepreview_body.html.twig') as $strTemplate)
		{
			$strHtml = $this->render($strTemplate, $arrItems);

			$this->assertStringContainsString('&lt;script&gt;', $strHtml);
			$this->assertStringNotContainsString('<script>alert', $strHtml);
		}
	}

	/**
	 * Escaping must NOT swallow the markup core builds for us, or every image and
	 * link on the page turns into visible entity soup.
	 */
	public function testCoreBuiltMarkupSurvivesUnescaped(): void
	{
		$arrItems = array($this->item(array(
			'link'       => '<span>Deep link</span>',
			'page_image' => '<img src="x.jpg" alt="An image">',
		)));

		$strHtml = $this->render('nav_pagepreview_header.html.twig', $arrItems);

		$this->assertStringContainsString('<img src="x.jpg" alt="An image">', $strHtml);
		$this->assertStringContainsString('<span>Deep link</span>', $strHtml);
	}

	/**
	 * rel="<alias>" is how page_slider.js pairs the header list against the body
	 * list. Presence alone is not the contract - the SEQUENCE has to match.
	 */
	public function testHeaderAndBodyEmitTheSameRelSequence(): void
	{
		$arrItems = array(
			$this->item(array('alias' => 'one')),
			$this->item(array('alias' => 'two', 'isActive' => true)),
			$this->item(array('alias' => 'three')),
		);

		$arrHeader = $this->relSequence($this->render('nav_pagepreview_header.html.twig', $arrItems));
		$arrBody = $this->relSequence($this->render('nav_pagepreview_body.html.twig', $arrItems));

		$this->assertSame(array('one', 'two', 'three'), $arrHeader);
		$this->assertSame($arrHeader, $arrBody, 'the slider pairs the two lists by rel; the sequences must agree');
	}

	public function testNavSubitemLimitSlicesBelowTheFirstLevel(): void
	{
		$arrItems = array(
			$this->item(array('alias' => 'one')),
			$this->item(array('alias' => 'two')),
			$this->item(array('alias' => 'three')),
			$this->item(array('alias' => 'four')),
		);

		$arrCapped = $this->relSequence($this->render('nav_pagepreview_header.html.twig', $arrItems, 2, 'level_2'));
		$this->assertSame(array('one', 'two'), $arrCapped, 'the cap applies from level_2 down');

		$arrTop = $this->relSequence($this->render('nav_pagepreview_header.html.twig', $arrItems, 2, 'level_1'));
		$this->assertCount(4, $arrTop, 'the cap must NOT apply to the top level');

		$arrOff = $this->relSequence($this->render('nav_pagepreview_header.html.twig', $arrItems, 0, 'level_2'));
		$this->assertCount(4, $arrOff, 'a limit of 0 means no cap');
	}

	/**
	 * first/last are computed over the SLICED list. If they were computed over
	 * the full list, "last" would land on an item that is not rendered.
	 */
	public function testFirstAndLastFollowTheSlice(): void
	{
		$arrItems = array(
			$this->item(array('alias' => 'one')),
			$this->item(array('alias' => 'two')),
			$this->item(array('alias' => 'three')),
		);

		$strHtml = $this->render('nav_pagepreview_header.html.twig', $arrItems, 2, 'level_2');

		$this->assertMatchesRegularExpression('/class="first"[^>]*rel="one"/', $strHtml);
		$this->assertMatchesRegularExpression('/class="last"[^>]*rel="two"/', $strHtml);
		$this->assertStringNotContainsString('rel="three"', $strHtml);
	}

	public function testASingleItemIsBothFirstAndLast(): void
	{
		$strHtml = $this->render('nav_pagepreview_header.html.twig', array($this->item()));

		$this->assertStringContainsString('class="first last"', $strHtml);
	}

	public function testExistingClassIsPreservedAlongsideFirstAndLast(): void
	{
		$strHtml = $this->render('nav_pagepreview_header.html.twig', array(
			$this->item(array('class' => 'sibling')),
			$this->item(array('class' => 'sibling')),
		));

		$this->assertStringContainsString('class="sibling first"', $strHtml);
		$this->assertStringContainsString('class="sibling last"', $strHtml);
	}

	public function testRelatedPagesEscapesTheTeaserAndKeepsImageMarkup(): void
	{
		$strHtml = $this->twig()->render('mod_related_pages.html.twig', array(
			'class'    => 'mod_related_pages',
			'cssID'    => ' id="rp"',
			'style'    => '',
			'headline' => '',
			'hl'       => 'h2',
			'pages'    => array(
				array(
					'url'         => 'target.html',
					'title'       => 'Stock title',
					'pageTitle'   => '',
					'page_image'  => '<img src="p.jpg" alt="">',
					'page_images' => array(),
					'page_teaser' => '<script>alert(1)</script>',
				),
			),
		));

		$this->assertStringContainsString('&lt;script&gt;', $strHtml);
		$this->assertStringNotContainsString('<script>alert', $strHtml);
		$this->assertStringContainsString('<img src="p.jpg" alt="">', $strHtml);
		// pageTitle is the optional meta override; a stock page must still show a title.
		$this->assertStringContainsString('<h6>Stock title</h6>', $strHtml);
		$this->assertStringContainsString(' id="rp"', $strHtml);
	}

	public function testPagePreviewEmitsSliderAssetsOnlyWhenConfigured(): void
	{
		$arrContext = array(
			'class'          => 'mod_pagepreview',
			'cssID'          => '',
			'style'          => '',
			'headline'       => '',
			'hl'             => 'h2',
			'request'        => 'here.html',
			'skipId'         => 'skipNavigation1',
			'skipNavigation' => 'Skip navigation',
			'headerItems'    => '<ul class="level_1"></ul>',
			'bodyItems'      => '<ul class="level_1"></ul>',
			'sliderItems'    => 0,
		);

		$strOff = $this->twig()->render('mod_pagepreview.html.twig', $arrContext);
		$this->assertStringNotContainsString('page_slider.js', $strOff);
		$this->assertStringNotContainsString('data-show', $strOff);

		$arrContext['sliderItems'] = 3;
		$strOn = $this->twig()->render('mod_pagepreview.html.twig', $arrContext);
		$this->assertStringContainsString('data-show="3"', $strOn);
		$this->assertStringContainsString('/bundles/page/js/page_slider.js', $strOn);
		$this->assertStringContainsString('/bundles/page/css/page_slider.css', $strOn);
		// The rendered navigation must not be escaped into visible markup.
		$this->assertStringContainsString('<ul class="level_1"></ul>', $strOn);
	}

	/**
	 * Both module templates keep their whole output out of tl_search, because
	 * both render OTHER pages' teasers and neither should win a search hit that
	 * belongs to the page it links to. Contao\Search::indexPage() acts on the
	 * literal comment pair, so this is a markup contract, not a code one - and a
	 * silent drop is exactly what the .html5 -> Twig port could have done.
	 *
	 * Order matters as much as presence: a "continue" before a "stop" would leave
	 * the rest of the PAGE unindexed rather than this module.
	 */
	public function testBothModuleTemplatesStayOutOfTheSearchIndex(): void
	{
		$arrRendered = array(
			'mod_pagepreview.html.twig' => $this->twig()->render('mod_pagepreview.html.twig', array(
				'class'          => 'mod_pagepreview',
				'cssID'          => '',
				'style'          => '',
				'headline'       => '',
				'hl'             => 'h2',
				'request'        => 'here.html',
				'skipId'         => 'skipNavigation1',
				'skipNavigation' => 'Skip navigation',
				'headerItems'    => '<ul class="level_1"></ul>',
				'bodyItems'      => '<ul class="level_1"></ul>',
				'sliderItems'    => 0,
			)),
			'mod_related_pages.html.twig' => $this->twig()->render('mod_related_pages.html.twig', array(
				'class'    => 'mod_related_pages',
				'cssID'    => '',
				'style'    => '',
				'headline' => '',
				'hl'       => 'h2',
				'pages'    => array(),
			)),
		);

		foreach ($arrRendered as $strTemplate => $strHtml)
		{
			$intStop = strpos($strHtml, '<!-- indexer::stop -->');
			$intContinue = strpos($strHtml, '<!-- indexer::continue -->');

			$this->assertNotFalse($intStop, $strTemplate . ' must open with indexer::stop');
			$this->assertNotFalse($intContinue, $strTemplate . ' must close with indexer::continue');
			$this->assertLessThan($intContinue, $intStop, $strTemplate . ': the stop marker has to come first');

			// One pair, not several: a stray extra marker would swallow page content
			// beyond this module.
			$this->assertSame(1, substr_count($strHtml, '<!-- indexer::stop -->'), $strTemplate);
			$this->assertSame(1, substr_count($strHtml, '<!-- indexer::continue -->'), $strTemplate);

			// The <nav> the module actually renders has to be INSIDE the pair.
			$intNav = strpos($strHtml, '<nav');
			$this->assertGreaterThan($intStop, $intNav, $strTemplate);
			$this->assertLessThan($intContinue, $intNav, $strTemplate);
		}
	}


	/**
	 * The slider assets are emitted from inside the module's own markup, so they
	 * have to sit inside the indexer pair too - a <link>/<script> left outside it
	 * would be the one part of the module the indexer still read.
	 */
	public function testTheSliderAssetsStayInsideTheIndexerPair(): void
	{
		$strHtml = $this->twig()->render('mod_pagepreview.html.twig', array(
			'class'          => 'mod_pagepreview',
			'cssID'          => '',
			'style'          => '',
			'headline'       => '',
			'hl'             => 'h2',
			'request'        => 'here.html',
			'skipId'         => 'skipNavigation1',
			'skipNavigation' => 'Skip navigation',
			'headerItems'    => '',
			'bodyItems'      => '',
			'sliderItems'    => 3,
		));

		$intContinue = strpos($strHtml, '<!-- indexer::continue -->');

		$this->assertLessThan($intContinue, strpos($strHtml, 'page_slider.css'));
		$this->assertLessThan($intContinue, strpos($strHtml, 'page_slider.js'));
	}


	private function relSequence(string $strHtml): array
	{
		preg_match_all('/<li[^>]*\brel="([^"]*)"/', $strHtml, $arrMatches);

		return $arrMatches[1];
	}
}
