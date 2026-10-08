<?php
 
/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */
 


/**
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['pagepreview']	= '{title_legend},name,headline,type;{image_size_legend},preview_image_size;{nav_legend},levelOffset,showLevel,hardLimit,showProtected,showHidden;{slider_legend},previewSliderItems;{reference_legend:hide},defineRoot;{template_legend:hide},customTpl,navHeaderTpl,navBodyTpl,navSubitemTpl,navSubitemLimit;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['related_pages']	= '{title_legend},name,headline,type;{image_size_legend},preview_image_size;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';
	
	
/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['preview_image_size'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_module']['preview_image_size'],
	'inputType'               => 'select',
	'foreignKey'              => 'tl_image_size.name',
	'options_callback'        => array('Zyppy\Page\Backend\ImageSizeOptions', 'getPreviewImageSizes'),
	'eval'                    => array('mandatory'=>true, 'tl_class'=>'w50'),
	'sql'                     => "int(10) unsigned NOT NULL default 0",
	'relation'                => array('type'=>'hasOne', 'load'=>'lazy')
);

$GLOBALS['TL_DCA']['tl_module']['fields']['navHeaderTpl'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_module']['navHeaderTpl'],
	'exclude'                 => true,
	'inputType'               => 'select',
	'options_callback' => static function ()
	{
		return Contao\Controller::getTemplateGroup('nav_');
	},
	'eval'                    => array('tl_class'=>'w50'),
	'sql'                     => "varchar(64) NOT NULL default ''",
	'default'                 => 'nav_pagepreview_header'
);

$GLOBALS['TL_DCA']['tl_module']['fields']['navBodyTpl'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_module']['navBodyTpl'],
	'exclude'                 => true,
	'inputType'               => 'select',
	'options_callback' => static function ()
	{
		return Contao\Controller::getTemplateGroup('nav_');
	},
	'eval'                    => array('tl_class'=>'w50'),
	'sql'                     => "varchar(64) NOT NULL default ''",
	'default'                 => 'nav_pagepreview_body'
);

$GLOBALS['TL_DCA']['tl_module']['fields']['navSubitemTpl'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_module']['navSubitemTpl'],
	'exclude'                 => true,
	'inputType'               => 'select',
	'options_callback' => static function ()
	{
		return Contao\Controller::getTemplateGroup('nav_');
	},
	'eval'                    => array('tl_class'=>'w50'),
	'sql'                     => "varchar(64) NOT NULL default ''",
	'default'                 => 'nav_default'
);

$GLOBALS['TL_DCA']['tl_module']['fields']['navSubitemLimit'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_module']['navSubitemLimit'],
	'default'                 => 3,
	'exclude'                 => true,
	'inputType'               => 'text',
	'eval'                    => array('mandatory'=>true, 'rgxp'=>'natural', 'tl_class'=>'w50'),
	'sql'                     => "smallint(5) unsigned NOT NULL default '0'"
);

/**
 * How many preview items the slider shows at once; 0 turns the slider off entirely.
 *
 * The template used to render a show="..." attribute fed by $this->numberOfItems, a value
 * no module ever assigned, so the attribute was always empty and page_slider.js disabled
 * itself on every page. This is the real field behind it, rendered as data-show.
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['previewSliderItems'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_module']['previewSliderItems'],
	'default'                 => 0,
	'exclude'                 => true,
	'inputType'               => 'text',
	'eval'                    => array('rgxp'=>'natural', 'tl_class'=>'w50'),
	'sql'                     => "smallint(5) unsigned NOT NULL default '0'"
);

