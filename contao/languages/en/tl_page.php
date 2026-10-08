<?php
 
/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */
 
 
 
/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_page']['page_image']        		= array('Page image', 'Please select an image for this page.');
$GLOBALS['TL_LANG']['tl_page']['page_image_overwrite_meta'] = array('Overwrite Meta Data', 'Overwrite the meta data from the file manager.');
$GLOBALS['TL_LANG']['tl_page']['page_image_alt']        	= array('Alternate Text', 'Here you can enter an alternate text for the image (&lt;em&gt;alt&lt;/em&gt; attribute).');
$GLOBALS['TL_LANG']['tl_page']['page_image_title']        	= array('Image Title', 'Here you can enter the image title (&lt;em&gt;title&lt;/em&gt; attribute).');
// The descriptions name the insert tag that reads each slot, because nothing
// else tells an editor what a numbered slot is for: the meaning of a slot is a
// per-site convention, not something the schema records. Slot 1 is the only one
// with a short form, so it is the only one that says so. Nothing in this bundle
// renders these fields - they are read by insert tags, templates and other code.
$GLOBALS['TL_LANG']['tl_page']['rich_text_1']        		= array('Rich Text 1', 'Free-form text/HTML for this page, read with {{zyppy_page::rich_text_1}} (or the short form {{zyppy_page::rich_text}}), by another page with {{zyppy_page::rich_text_1::&lt;page id or alias&gt;}}, and from a template as $objPage-&gt;rich_text_1. It is not rendered on this page unless something asks for it.');
$GLOBALS['TL_LANG']['tl_page']['rich_text_2']        		= array('Rich Text 2', 'Free-form text/HTML for this page, read with {{zyppy_page::rich_text_2}}, by another page with {{zyppy_page::rich_text_2::&lt;page id or alias&gt;}}, and from a template as $objPage-&gt;rich_text_2. It is not rendered on this page unless something asks for it.');
$GLOBALS['TL_LANG']['tl_page']['rich_text_3']        		= array('Rich Text 3', 'Free-form text/HTML for this page, read with {{zyppy_page::rich_text_3}}, by another page with {{zyppy_page::rich_text_3::&lt;page id or alias&gt;}}, and from a template as $objPage-&gt;rich_text_3. It is not rendered on this page unless something asks for it.');
$GLOBALS['TL_LANG']['tl_page']['rich_text_4']        		= array('Rich Text 4', 'Free-form text/HTML for this page, read with {{zyppy_page::rich_text_4}}, by another page with {{zyppy_page::rich_text_4::&lt;page id or alias&gt;}}, and from a template as $objPage-&gt;rich_text_4. It is not rendered on this page unless something asks for it.');

$GLOBALS['TL_LANG']['tl_page']['page_images']        		= array('Page images', 'Allows for assigning multiple images to this page.');
$GLOBALS['TL_LANG']['tl_page']['page_teaser']        		= array('Page teaser', 'Please enter teaser text for this page.');
$GLOBALS['TL_LANG']['tl_page']['page_image_url']        	= array('Image URL', 'External image for this page.');
$GLOBALS['TL_LANG']['tl_page']['page_related']        		= array('Related pages', 'Please select any related pages.');


