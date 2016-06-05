<?php
namespace Heilmann\JhPdfviewer\ViewHelpers;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Jonathan Heilmann <mail@jonathan-heilmann.de>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class LinkToMfpViewHelper
 * @package Heilmann\JhPdfviewer\ViewHelpers
 */
class LinkToMfpViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper
{

    /**
     * imgProc - instance of GraphicalFunctions
     *
     * @var \TYPO3\CMS\Core\Imaging\GraphicalFunctions
     * @inject
     */
    protected $imgProc = null;

    /**
     * @param FileReference $file
     * @param array $settings
     * @param array $pdfSettings
     * @return string
     */
    public function render(FileReference $file, array $settings, array $pdfSettings)
    {
        // Configure instance of GraphicalFunctions
        $this->imgProc->init();
        $this->imgProc->tempPath = PATH_site . 'typo3temp/';
        
        // Open image with magnificpopup
        $i = $pdfSettings['startpage'];
        //render all images
        $itemList = '';
        while ($i <= $pdfSettings['endpage']) {
            $img = $this->imgProc->imageMagickConvert(
                $file->getOriginalFile()->getPublicUrl(),
                $settings['format'],
                $settings['mfp']['width'],
                $settings['mfp']['height'],
                $settings['imparams'],
                $i - 1,
                '',
                1
            );
            $itemList .= '{src: \'' . substr($img[3], strpos($img[3], 'typo3temp')) . '\',' .
                'title: \'' . LocalizationUtility::translate('page', 'jh_pdfviewer') . ' ' . $i . ' ' .
                LocalizationUtility::translate('of', 'jh_pdfviewer') . ' ' . $pdfSettings['endpage'] . '\'},';
            $i++;
        }
        return $itemList;
    }
}