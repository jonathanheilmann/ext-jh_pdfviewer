<?php
namespace Heilmann\JhPdfviewer\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014-2016 Jonathan Heilmann <mail@jonathan-heilmann.de>
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

use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Resource\FileReference;

/**
 * Class ViewerController
 * @package Heilmann\JhPdfviewer\Controller
 */
class ViewerController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    
    /**
     * pdf
     *
     * @var array
     */
    protected $pdf;

    /**
     * imgProc - instance of GraphicalFunctions
     *
     * @var \TYPO3\CMS\Core\Imaging\GraphicalFunctions
     * @inject
     */
    protected $imgProc = null;

    /**
     * @var \TYPO3\CMS\Core\Resource\FileRepository
     * @inject
     */
    protected $fileRepository = null;

    /**
     * action show
     *
     * @return void
     */
    public function showAction()
    {
        //get instance of GraphicalFunctions
        $this->imgProc->init();
        $this->imgProc->tempPath = PATH_site . 'typo3temp/';

        //get settings and overwrite with values from typoscript if empty
        $this->pdf['width'] = (empty($this->settings['width']) ? $this->settings['pdf']['width'] : $this->settings['width']);
        $this->pdf['height'] = (empty($this->settings['height']) ? $this->settings['pdf']['height'] : $this->settings['height']);
        $this->pdf['addparams'] = (empty($this->settings['addparams']) ? $this->settings['pdf']['addparams'] : $this->settings['addparams']);
        if (empty($this->settings['format']))
            $this->settings['format'] = 'jpg';

        //set start and endpage
        $this->pdf['startpage'] = (intval($this->settings['startpage']) == 0 ? 1 : $this->settings['startpage']);
        $this->pdf['endpage'] = (intval($this->settings['endpage']) == 0 ? 1 : $this->settings['endpage']);

        //get file from fal
        //http://wiki.typo3.org/File_Abstraction_Layer
        $ttContentUid = $this->configurationManager->getContentObject()->data['uid'];
        if (isset($this->configurationManager->getContentObject()->data['_ORIG_uid']))
            $ttContentUid = $this->configurationManager->getContentObject()->data['_ORIG_uid'];

        /** @var array $files */
        $files = $this->fileRepository->findByRelation('tt_content', 'pdfFile', $ttContentUid);
        if (empty($files)) {
            $this->addFlashMessage('No pdf file found', '', AbstractMessage::ERROR);
            return; //exit by error
        }

        /** @var FileReference $file */
        $file = $files[0];

        //test if endpage is available
        $this->testEndpage($file);

        //get actualPage -> page to be rendered
        $this->pdf['actualPage'] = $this->pdf['startpage'];

        //get arguments (used by navigation)
        $arguments = $this->request->getArguments();

        //overwrite actualPage if navigation or imageNavigation has been clicked
        if (isset($arguments['uid']) && $arguments['uid'] == $ttContentUid && isset($arguments['page']))
            $this->pdf['actualPage'] = $arguments['page'];
        
        //limit actualPage to range, set in flexform
        if ($this->pdf['actualPage'] > $this->pdf['endpage'])
            $this->pdf['actualPage'] = $this->pdf['endpage'];
        if ($this->pdf['actualPage'] < $this->pdf['startpage'])
            $this->pdf['actualPage'] = $this->pdf['startpage'];
        
        //render imageNavigation (only if required)
        $this->pdf['imageNavigation']['position'] = (isset($this->settings['imgNavigationPos']) && !empty($this->settings['imgNavigationPos']) && $this->settings['imgNavigationPos'] != 'ts' ?
            $this->settings['imgNavigationPos'] : $this->settings['imageNavigation']['position']);
        if ($this->pdf['imageNavigation']['position'] != 'disabled' && ($this->pdf['startpage'] != $this->pdf['endpage']))
            $this->pdf['imageNavigation'] = $this->renderImageNavigation($file);
        
        //render pdf-image
        $img = $this->imgProc->imageMagickConvert(
            $file->getOriginalFile()->getPublicUrl(),
            $this->settings['format'],
            $this->pdf['width'],
            $this->pdf['height'],
            $this->settings['imparams'],
            $this->pdf['actualPage'] - 1,
            array(),
            1
        );
        $this->pdf['imgWidth'] = $img[0];
        $this->pdf['imgHeight'] = $img[1];
        $this->pdf['picurl'] = substr($img[3], strpos($img[3], 'typo3temp'));

        //submit settings to template
        $this->view->assign('pdf', $this->pdf);
        $this->view->assign('file', $file);
        $this->view->assign('ttContentUid', $ttContentUid);
    }

    /**
     * test endpage
     * test if $this->pdf['endpage'] is avialable
     *
     * @param FileReference $file
     */
    protected function testEndpage(FileReference $file)
    {
        if ($this->pdf['startpage'] != $this->pdf['endpage']) {
            $i = $this->pdf['startpage'];
            while ($i <= $this->pdf['endpage']) {
                $img = $this->imgProc->imageMagickConvert(
                    $file->getOriginalFile()->getPublicUrl(),
                    $this->settings['format'],
                    '1',
                    '1',
                    '',
                    $i - 1,
                    '',
                    1
                );
                if (empty($img)) {
                    $this->pdf['endpage'] = $i - 1;
                    //TODO: update tt_content
                    break;
                }
                $i++;
            }
        }
    }

    /**
     * render image-navigation
     *
     * @param FileReference $file
     * @return array
     */
    protected function renderImageNavigation(FileReference $file)
    {
        $navSettings = $this->settings['imageNavigation'];
        $pdfNav = array(
            'position' => $this->pdf['imageNavigation']['position'],
            'height' => 0,
            'image' => array(
                'margin' => array(
                    'rightLeft' => $navSettings['image']['margin']['rightLeft'],
                    'topBottom' =>$navSettings['image']['margin']['topBottom']
                )
            )
        );
        $image['width'] = (isset($navSettings['image']['width']) && !empty($navSettings['image']['width']) ? $navSettings['image']['width'] : '80m');
        $image['height'] = (isset($navSettings['image']['height']) && !empty($navSettings['image']['height']) ? $navSettings['image']['height'] : '80m');
        
        //render all thumbs
        $i = $this->pdf['startpage'];
        while ($i <= $this->pdf['endpage']) {
            $thumb = $this->imgProc->imageMagickConvert(
                $file->getOriginalFile()->getPublicUrl(),
                $this->settings['format'],
                $image['width'], 
                $image['height'], 
                $this->settings['imparams'], 
                $i - 1, 
                '', 
                1
            );
            $pdfNav['images'][$i] = array(
                'img' => substr($thumb[3], strpos($thumb[3], 'typo3temp')),
                'width' => $thumb[0],
                'height' => $thumb[1]
            );

            if ($thumb[1] > $pdfNav['height'])
                $pdfNav['height'] = $thumb[1];
            $pdfNav['scrolls']['width'] = $pdfNav['scrolls']['width'] + $thumb[0] + $pdfNav['image']['margin']['rightLeft'] * 2;

            $i++;
        }
        $pdfNav['height'] = $pdfNav['height'] + $pdfNav['image']['margin']['topBottom'] * 2;

        return $pdfNav;
    }
}