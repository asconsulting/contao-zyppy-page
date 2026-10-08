<?php

/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */


use Contao\CoreBundle\DataContainer\PaletteManipulator;


	
/**
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_page']['palettes']['__selector__'][] = 'page_image_overwrite_meta';

// Only page types that are actually rendered as content get these fields. The
// previous implementation injected into every palette containing {meta_legend}
// - which in core 5.7 is regular, forward, redirect, root, rootfallback and the
// four error_* types - by str_replace()ing a substring of the palette, which
// corrupts the palette if that chunk ever appears twice.
$arrZyppyPageFields = array
(
	'page_image',
	'page_image_overwrite_meta',
	'page_images',
	'page_image_url',
	'page_teaser',
	'page_related',
	'rich_text_1',
	'rich_text_2',
	'rich_text_3',
	'rich_text_4',
);

foreach (array('regular', 'forward') as $strPalette)
{
	if (isset($GLOBALS['TL_DCA']['tl_page']['palettes'][$strPalette]))
	{
		PaletteManipulator::create()
			->addField($arrZyppyPageFields, 'meta_legend', PaletteManipulator::POSITION_APPEND)
			->applyToPalette($strPalette, 'tl_page')
		;
	}
}


/**
 * Subpalettes
 */
$GLOBALS['TL_DCA']['tl_page']['subpalettes']['page_image_overwrite_meta'] = 'page_image_alt,page_image_title';
	
	
/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_page']['fields']['page_image'] = array
(
	'label'					 => &$GLOBALS['TL_LANG']['tl_page']['page_image'],
	'exclude'				 => true,
	'inputType'				 => 'fileTree',
	'eval'					 => array('filesOnly'=>true, 'extensions'=>'%contao.image.valid_extensions%', 'fieldType'=>'radio', 'tl_class'=>'clr w50'),
	'sql'					 => "binary(16) NULL"
);

$GLOBALS['TL_DCA']['tl_page']['fields']['page_image_overwrite_meta'] = array
(
	'label'					 => &$GLOBALS['TL_LANG']['tl_page']['page_image_overwrite_meta'],
	'inputType'				=> 'checkbox',
	'eval'					=> array('submitOnChange'=>true, 'tl_class'=>'w50 clr'),
	'sql'					=> "char(1) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_page']['fields']['page_image_alt'] = array
(
	'label'					 => &$GLOBALS['TL_LANG']['tl_page']['page_image_alt'],
	'search'				=> true,
	'inputType'				=> 'text',
	'eval'					=> array('maxlength'=>255, 'tl_class'=>'w50'),
	'sql'					=> "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_page']['fields']['page_image_title'] = array
(
	'label'					 => &$GLOBALS['TL_LANG']['tl_page']['page_image_title'],
	'search'				=> true,
	'inputType'				=> 'text',
	'eval'					=> array('maxlength'=>255, 'tl_class'=>'w50'),
	'sql'					=> "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_page']['fields']['page_images'] = array
(
	'label'					 => &$GLOBALS['TL_LANG']['tl_page']['page_images'],
	'exclude'				 => true,
	'inputType'				 => 'fileTree',
	'eval'					 => array('filesOnly'=>true, 'extensions'=>'%contao.image.valid_extensions%', 'multiple'=>true, 'fieldType'=>'checkbox', 'tl_class'=>'clr w50'),
	'sql'					 => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_page']['fields']['page_image_url'] = array
(
	'label'					=> &$GLOBALS['TL_LANG']['tl_page']['page_image_url'],
	'search'				=> true,
	'inputType'				=> 'text',
	'eval'					=> array('rgxp'=>'url', 'maxlength'=>255, 'tl_class'=>'clr w50'),
	'sql'					=> "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_page']['fields']['page_teaser'] = array
(
	'label'					 => &$GLOBALS['TL_LANG']['tl_page']['page_teaser'],
	'exclude'				 => true,
	'inputType'				 => 'textarea',
	'search'				 => true,
	'eval'					 => array('style'=>'height:60px', 'decodeEntities'=>true, 'tl_class'=>'clr w50'),
	'sql'					 => "text NULL"
);

$GLOBALS['TL_DCA']['tl_page']['fields']['page_related'] = array
(
	'label'					=> &$GLOBALS['TL_LANG']['tl_page']['page_related'],
	'inputType'				=> 'pageTree',
	'foreignKey'			=> 'tl_page.title',
	'eval'					=> array('multiple'=>true, 'fieldType'=>'checkbox', 'isSortable'=>true, 'tl_class'=>'clr'),
	'sql'					=> "text NULL",
	'relation'				=> array('type'=>'hasMany', 'load'=>'lazy')
);

$GLOBALS['TL_DCA']['tl_page']['fields']['rich_text_1'] = array
(
	'label'					 => &$GLOBALS['TL_LANG']['tl_page']['rich_text_1'],
	'exclude'				 => true,
	'inputType'				 => 'textarea',
	'search'				 => true,
	'eval'					 => array('style'=>'height:60px', 'rte'=>'tinyMCE', 'tl_class'=>'clr long'),
	'explanation'  			 => 'insertTags',
	'sql'					 => "text NULL"
);

$GLOBALS['TL_DCA']['tl_page']['fields']['rich_text_2'] = array
(
	'label'					 => &$GLOBALS['TL_LANG']['tl_page']['rich_text_2'],
	'exclude'				 => true,
	'inputType'				 => 'textarea',
	'search'				 => true,
	'eval'					 => array('style'=>'height:60px', 'rte'=>'tinyMCE', 'tl_class'=>'clr long'),
	'explanation'  			 => 'insertTags',
	'sql'					 => "text NULL"
);

$GLOBALS['TL_DCA']['tl_page']['fields']['rich_text_3'] = array
(
	'label'					 => &$GLOBALS['TL_LANG']['tl_page']['rich_text_3'],
	'exclude'				 => true,
	'inputType'				 => 'textarea',
	'search'				 => true,
	'eval'					 => array('style'=>'height:60px', 'rte'=>'tinyMCE', 'tl_class'=>'clr long'),
	'explanation'  			 => 'insertTags',
	'sql'					 => "text NULL"
);

$GLOBALS['TL_DCA']['tl_page']['fields']['rich_text_4'] = array
(
	'label'					 => &$GLOBALS['TL_LANG']['tl_page']['rich_text_4'],
	'exclude'				 => true,
	'inputType'				 => 'textarea',
	'search'				 => true,
	'eval'					 => array('style'=>'height:60px', 'rte'=>'tinyMCE', 'tl_class'=>'clr long'),
	'explanation'  			 => 'insertTags',
	'sql'					 => "text NULL"
);
