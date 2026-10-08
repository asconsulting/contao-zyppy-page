<?php

/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */



namespace Zyppy\Page\Tests;

use Contao\Backend;
use Contao\Module;
use Contao\ModuleNavigation;
use PHPUnit\Framework\TestCase;
use Zyppy\Page\Backend\ImageSizeOptions;
use Zyppy\Page\Module\PagePreview;
use Zyppy\Page\Module\RelatedPages;


/**
 * The three classes Contao constructs itself are never loaded by the rest of
 * the suite, so a green leg says nothing about whether their parent symbols or
 * the hooks they override exist on that release. Loading them here makes every
 * leg resolve them.
 */
class FrameworkSymbolsTest extends TestCase
{
	public function testTheContaoConstructedClassesExtendTheCoreClassesTheyRelyOn(): void
	{
		$this->assertTrue(is_subclass_of(PagePreview::class, ModuleNavigation::class));
		$this->assertTrue(is_subclass_of(RelatedPages::class, Module::class));
		$this->assertTrue(is_subclass_of(ImageSizeOptions::class, Backend::class));
	}


	public function testTheNavigationHooksPagePreviewOverridesExistOnThisRelease(): void
	{
		// If core renames or re-arities either of these, the override is simply
		// never called and every preview row silently loses its image and teaser.
		foreach (array('renderNavigation', 'compileNavigationRow') as $strMethod)
		{
			$objCore = new \ReflectionMethod(Module::class, $strMethod);
			$objOurs = new \ReflectionMethod(PagePreview::class, $strMethod);

			$this->assertSame(PagePreview::class, $objOurs->getDeclaringClass()->getName(), $strMethod);
			$this->assertSame($objCore->getNumberOfParameters(), $objOurs->getNumberOfParameters(), $strMethod);
		}
	}
}
