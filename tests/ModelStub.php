<?php

/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */



namespace Zyppy\Page\Tests;


/**
 * Builds a stub of a Contao class whose magic properties are pre-populated.
 *
 * This exists because the helper was renamed. contao/test-case 5.3 offers
 * ContaoTestCase::mockClassWithProperties(); later versions offer
 * createClassWithPropertiesStub(). The suite must run on Contao 5.3, and
 * contao/test-case pins contao/core-bundle to "self.version", so a
 * --prefer-lowest install gets the 5.3 helper and a normal install gets the
 * current one. Calling either name directly makes the suite unrunnable on one
 * end of the supported range.
 */
trait ModelStub
{
	/**
	 * The return type is deliberately just object: contao/test-case 5.3 hands
	 * back a MockObject, 5.7 hands back a PHPUnit Stub, and neither is a
	 * subtype of the other. One consequence worth knowing: on the 5.3 install
	 * every "stub" here is really a mock, so any no-mock-without-expectations
	 * discipline is only actually enforced by the 5.7 run.
	 *
	 * @param array<string, mixed> $arrProperties
	 */
	protected function stubWithProperties(string $strClass, array $arrProperties): object
	{
		if (method_exists($this, 'createClassWithPropertiesStub'))
		{
			return $this->createClassWithPropertiesStub($strClass, $arrProperties);
		}

		return $this->mockClassWithProperties($strClass, $arrProperties);
	}
}