<?php
 
/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */



namespace Zyppy\Page\Backend;

use Contao\Backend as ContaoBackend;
use Contao\Database;


class ImageSizeOptions extends ContaoBackend
{
	
	public function getPreviewImageSizes($dc)
	{
		
		$objImageSize = Database::getInstance()->prepare("SELECT id, name, width, height, resizeMode FROM tl_image_size WHERE pid=? ORDER BY name")->execute($dc->activeRecord->pid);

		if ($objImageSize->numRows < 1)
		{
			return array();
		}

		$arrOptions = array();

		while ($objImageSize->next())
		{
			$arrOptions[$objImageSize->id] = $objImageSize->name ." [" .$objImageSize->width ."x" .$objImageSize->height ." " .$objImageSize->resizeMode ."]";
		}

		return $arrOptions;
	}	

}
