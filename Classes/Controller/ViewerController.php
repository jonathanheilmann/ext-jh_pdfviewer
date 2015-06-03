<?php
namespace TYPO3\JhPdfviewer\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Jonathan Heilmann <mail@jonathan-heilmann.de>
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

use \TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Core\Utility\DebugUtility;

/**
 *
 *
 * @package jh_pdfviewer
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class ViewerController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {
	/**
	 * pdf
	 *
	 * @var array
	 */
	private $pdf;

	/**
	 * imgProc - instance of GraphicalFunctions
	 *
	 * @var object
	 */
	private $imgProc;

	/**
	 * viewerRepository
	 *
	 * @var \TYPO3\JhPdfviewer\Domain\Repository\ViewerRepository
	 * @inject
	 */
	protected $viewerRepository;

	/**
	 * Injects the viewerRepository
	 *
	 * @param \TYPO3\JhPdfviewer\Domain\Repository\ViewerRepository $viewerRepository the repository to inject
	 * @return void
	 */
	public function injectViewerRepository(\TYPO3\JhPdfviewer\Domain\Repository\ViewerRepository $viewerRepository) {
		$this->viewerRepository = $viewerRepository;
	}

	/**
	 * action show
	 *
	 * @return void
	 */
	public function showAction() {
		//get instance of GraphicalFunctions
		$this->imgProc = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Imaging\\GraphicalFunctions');
		$this->imgProc->init();
		$this->imgProc->tempPath = PATH_site.'typo3temp/';

		//get settings and overwrite with values from typoscript if empty
		$this->pdf['width'] = (empty($this->settings['width']) ? $this->settings['pdf']['width'] : $this->settings['width']);
		$this->pdf['height'] = (empty($this->settings['height']) ? $this->settings['pdf']['height'] : $this->settings['height']);
		$this->pdf['addparams'] = (empty($this->settings['addparams']) ? $this->settings['pdf']['addparams'] : $this->settings['addparams']);
		if(empty($this->settings['format'])) $this->settings['format'] = 'jpg';

		//get arguments (used by navigation)
		$arguments = $this->request->getArguments();

		//set start and endpage
		$this->pdf['startpage'] = (intval($this->settings['startpage']) == 0 ? 1 : $this->settings['startpage']);
		$this->pdf['endpage'] = (intval($this->settings['endpage']) == 0 ? 1 : $this->settings['endpage']);

		//get file from fal
		//http://wiki.typo3.org/File_Abstraction_Layer
		$this->pdf['uid'] = $this->configurationManager->getContentObject()->data['uid'];
		if (isset($this->configurationManager->getContentObject()->data['_ORIG_uid'])) $this->pdf['uid'] = $this->configurationManager->getContentObject()->data['_ORIG_uid'];
		$fileRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\FileRepository');
		$fileObjects = $fileRepository->findByRelation('tt_content', 'pdfFile', $this->pdf['uid']);
		if (empty($fileObjects)) return; //exit by error
		$fileObject = $fileObjects[0];
		$file['reference'] = $fileObject->getReferenceProperties();
		$file['original'] = $fileObject->getOriginalFile()->getProperties();
		$file['original']['publicUrl'] = $fileObject->getOriginalFile()->getPublicUrl();

		//get title, titletag (description) and alttag from fal-reference
		$this->pdf['title'] = $file['reference']['title'];
		$this->pdf['titletag'] = $file['reference']['description'];
		$this->pdf['alttag'] = $file['reference']['alternative'];

		//test if endpage is available
		$this->testEndpage($file['original']['publicUrl']);

		//get actualPage -> page to be rendered
		$this->pdf['actualPage'] = ((isset($arguments['page']) && !empty($arguments['page'])) ? $arguments['page'] : $this->pdf['startpage']);
		//overwrite actualPage if navigation or imageNavigation has been clicked
		if (isset($arguments['uid']) && $arguments['uid'] == $this->pdf['uid']) {
			if (isset($arguments['nav']) && $arguments['nav'] == 'prev') $this->pdf['actualPage'] -= 1;
			if (isset($arguments['nav']) && $arguments['nav'] == 'next') $this->pdf['actualPage'] += 1;
			if (isset($arguments['jumpToPage'])) $this->pdf['actualPage'] = $arguments['jumpToPage'];
		}
		//limit actualPage to range, set in flexform
		if($this->pdf['actualPage'] > $this->pdf['endpage']) $this->pdf['actualPage'] = $this->pdf['endpage'];
		if($this->pdf['actualPage'] < $this->pdf['startpage']) $this->pdf['actualPage'] = $this->pdf['startpage'];

		//render navigation (only if required)
		if ((isset($this->settings['hideNavigation']) && !$this->settings['hideNavigation']) && ($this->pdf['startpage'] != $this->pdf['endpage'])) {
			$this->renderNavigation();
		}

		//render imageNavigation (only if required)
		$this->pdf['imageNavigation']['position'] = (isset($this->settings['imgNavigationPos']) && !empty($this->settings['imgNavigationPos']) && $this->settings['imgNavigationPos'] != 'ts' ?
			$this->settings['imgNavigationPos'] : $this->settings['imageNavigation']['position']);
		if ($this->pdf['imageNavigation']['position'] != 'disabled' && ($this->pdf['startpage'] != $this->pdf['endpage'])) {
			$this->pdf['imageNavigation'] = $this->renderImageNavigation($file);
		}

		//render downloadLink
		if (isset($this->settings['hideDownloadAdvice']) && !$this->settings['hideDownloadAdvice']) {
			$title = (!empty($file['reference']['title']) ? $file['reference']['title'] : $file['original']['name']);
			$this->pdf['downloadLink'] = '<a href="' . GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . $file['original']['publicUrl'] . '" target="_new" >' . $title . '</a>';
		}

		//hide parts if they has been disabled by flexform or empty
		$this->pdf['displayTitle'] = ((isset($this->settings['hideTitle']) && $this->settings['hideTitle']) || empty($this->pdf['title']) ? 0 : 1);
		$this->pdf['displayIndex'] = ((isset($this->settings['hideIndex']) && $this->settings['hideIndex']) || ($this->pdf['startpage'] == $this->pdf['endpage']) ? 0 : 1);
		$this->pdf['displayNavigation'] = ((isset($this->settings['hideNavigation']) && $this->settings['hideNavigation']) || ($this->pdf['startpage'] == $this->pdf['endpage']) ? 0 : 1);
		$this->pdf['displayDownloadAdvice'] = (isset($this->settings['hideDownloadAdvice']) && $this->settings['hideDownloadAdvice'] ? 0 : 1);

		//render pdf-image
		$img = $this->imgProc->imageMagickConvert($file['original']['publicUrl'], $this->settings['format'], $this->pdf['width'], $this->pdf['height'], $this->settings['imparams'], $this->pdf['actualPage']-1, array(), 1);
		$this->pdf['imgWidth'] = $img[0];
		$this->pdf['imgHeight'] = $img[1];
		$this->pdf['picurl'] = GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . substr($img[3],strpos($img[3],'typo3temp'));

		//finalize imageNavigation (only if required)
		if ($this->pdf['imageNavigation']['position'] != 'disabled' && ($this->pdf['startpage'] != $this->pdf['endpage'])) {
			$this->renderFinalImageNavigation($img);
		}

		//render link of pdf-image
		$this->renderLink($file);

		//submit settings to template
		$this->view->assign('pdf', $this->pdf);
	}

	/**
	 * test endpage
	 *
	 * test if $this->pdf['endpage'] is avialable
	 *
	 * @param string $fuleURL publicUrl to pdf-file
	 * @return void
	 */
	private function testEndpage($fileUrl) {
		if($this->pdf['startpage'] != $this->pdf['endpage']) {
			$i = $this->pdf['startpage'];
			while ($i <= $this->pdf['endpage']) {
				$img = $this->imgProc->imageMagickConvert($fileUrl, $this->settings['format'], '1', '1', '', $i-1, '', 1);
				if(empty($img)) {
					$this->pdf['endpage'] = $i-1;
					//TODO: update tt_content
					break;
				}
				$i++;
			}
		}
	}

	/**
	 * render text-navigation
	 *
	 * @return void
	 */
	private function renderNavigation() {
		//render previous link
		if ($this->pdf['actualPage'] == $this->pdf['startpage']) {
			$this->pdf['prevLink'] = LocalizationUtility::translate('prevLink', 'jh_pdfviewer' );
		} else {
			$prevLink = $this->uriBuilder->setArguments(array('tx_jhpdfviewer_viewer'=>array('uid'=> $this->pdf['uid'], 'page' => $this->pdf['actualPage'], 'nav' => 'prev')))->setCreateAbsoluteUri(TRUE)->build();
			$this->pdf['prevLink'] = '<a href="'.$prevLink.'">'.LocalizationUtility::translate('prevLink', 'jh_pdfviewer' ).'</a>';
		}
		//render next link
		if ($this->pdf['actualPage'] == $this->pdf['endpage']) {
			$this->pdf['nextLink'] = LocalizationUtility::translate('nextLink', 'jh_pdfviewer' );
		} else {
			$nextLink = $this->uriBuilder->setArguments(array('tx_jhpdfviewer_viewer'=>array('uid'=> $this->pdf['uid'], 'page' => $this->pdf['actualPage'], 'nav' => 'next')))->setCreateAbsoluteUri(TRUE)->build();
			$this->pdf['nextLink'] = '<a href="'.$nextLink.'">'.LocalizationUtility::translate('nextLink', 'jh_pdfviewer' ).'</a>';
		}
	}

	/**
	 * render image-navigation
	 *
	 * @param array $file info about the fal file
	 * @return array
	 */
	private function renderImageNavigation($file) {
		$i = $this->pdf['startpage'];
		$navSettings = $this->settings['imageNavigation'];
		$pdfNav = array();
		$pdfNav['position'] = $this->pdf['imageNavigation']['position'];
		$image['width'] = (isset($navSettings['image']['width']) && !empty($navSettings['image']['width']) ? $navSettings['image']['width'] : '80m');
		$image['height'] = (isset($navSettings['image']['height']) && !empty($navSettings['image']['height']) ? $navSettings['image']['height'] : '80m');
		$pdfNav['height'] = 0;
		$pdfNav['width'] = 0;
		$pdfNav['image']['margin']['rightLeft'] = $navSettings['image']['margin']['rightLeft'];
		$pdfNav['image']['margin']['topBottom'] = $navSettings['image']['margin']['topBottom'];
		//render all thumbs
		while ($i <= $this->pdf['endpage']) {
			$thumb = $this->imgProc->imageMagickConvert($file['original']['publicUrl'], $this->settings['format'], $image['width'], $image['height'], $this->settings['imparams'], $i-1, '', 1);
			$pdfNav['images'][$i]['img'] = substr($thumb[3],strpos($thumb[3],'typo3temp'));
			$pdfNav['images'][$i]['width'] = $thumb[0];
			$pdfNav['images'][$i]['height'] = $thumb[1];
			if ($pdfNav['position'] == 'top' || $pdfNav['position'] == 'bottom') {
				if ($thumb[1] > $pdfNav['height']) $pdfNav['height'] = $thumb[1];
				$pdfNav['scrolls']['width'] = $pdfNav['scrolls']['width'] + $thumb[0] + $pdfNav['image']['margin']['rightLeft'] * 2;
			}
			/*if ($pdfNav['position'] == 'left' || $pdfNav['position'] == 'right') {
				if ($thumb[0] > $pdfNav['width']) $pdfNav['width'] = $thumb[0];
			}*/
			$pdfNav['images'][$i]['alt'] = LocalizationUtility::translate('page', 'jh_pdfviewer' ) . ' ' . $i . ' ' .
				LocalizationUtility::translate('of', 'jh_pdfviewer' ) . ' ' . $this->pdf['endpage'];
			$pdfNav['images'][$i]['title'] = $i . '/' . $this->pdf['endpage'];
			$link = $this->uriBuilder->setArguments(array('tx_jhpdfviewer_viewer'=>array('uid'=> $this->pdf['uid'], 'jumpToPage' => $i)))->setCreateAbsoluteUri(TRUE)->build();
			$pdfNav['images'][$i]['startLink'] = '<a href="'.$link.'" style="width: '.$thumb[0].'px; display: inline-block;">';
			$pdfNav['images'][$i]['endLink'] = '</a>';
			$i++;
		}
		if ($pdfNav['position'] == 'top' || $pdfNav['position'] == 'bottom') {
			$pdfNav['height'] = $pdfNav['height'] + $pdfNav['image']['margin']['topBottom'] * 2;
		}
		/*if ($pdfNav['position'] == 'left' || $pdfNav['position'] == 'right') {
			$pdfNav['width'] = $pdfNav['width'] + $pdfNav['image']['margin']['rightLeft'] * 2;
		}*/
		return $pdfNav;
	}

	/**
	 * finalize image-navigation
	 *
	 * @param array $img info about the rendered pdf-img
	 * @return array
	 */
	private function renderFinalImageNavigation($img) {
		if ($this->pdf['imageNavigation']['position'] == 'top' || $this->pdf['imageNavigation']['position'] == 'bottom') {
			$this->pdf['imageNavigation']['width'] = $img[0];
		}
		/*if ($this->pdf['imageNavigation']['position'] == 'left' || $this->pdf['imageNavigation']['position'] == 'right') {
			$this->pdf['imageNavigation']['height'] = $img[1];
		}*/
	}

	/**
	 * render link
	 *
	 * @param array $file info about the fal file
	 * @return void
	 */
	private function renderLink($file) {
		if(isset($file['reference']['link']) && !empty($file['reference']['link']) && isset($this->settings['linkTo']) && $this->settings['linkTo'] == 'none') {
			//link, given by fal reference
			$cObj = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
			//get link
			$htmlLink = $cObj->getTypoLink('', $file['reference']['link']);
			//we net the link parameters only...
			$pattern = array (
				0 => '/<a /',
				1 => '/\s>(.*)<\/a>/',
			);
			$rawLinkParams = preg_replace(array(0 => '/<a /', 1 => '/\s>(.*)<\/a>/'), '', $htmlLink);
			$this->pdf['startLink'] = '<a ' . $rawLinkParams . ' >';
			$this->pdf['endLink'] = '</a>';
		} else if(isset($this->settings['linkTo']) && $this->settings['linkTo'] == 'link2doc') {
			//link to pdf
			$this->pdf['startLink'] = '<a href="' . GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . $file['original']['publicUrl'] . '" target="_new" >';
			$this->pdf['endLink'] = '</a>';
		} else if(isset($this->settings['linkTo']) && $this->settings['linkTo'] == 'mfp') {
			//open image with magnificpopup
			$this->pdf['startLink'] = '';
			$this->pdf['endLink'] = '';
			$i = $this->pdf['startpage'];
			//render all images
			while ($i <= $this->pdf['endpage']) {
				$lightboxImg = $this->imgProc->imageMagickConvert($file['original']['publicUrl'], $this->settings['format'], $this->settings['mfp']['width'], $this->settings['mfp']['height'], $this->settings['imparams'], $i-1, '', 1);
				$itemlist .= '{src: \''.substr($lightboxImg[3],strpos($lightboxImg[3],'typo3temp')).'\', title: \'' .
					LocalizationUtility::translate('page', 'jh_pdfviewer' ) . ' ' . $i . ' ' .
					LocalizationUtility::translate('of', 'jh_pdfviewer' ) . ' ' . $this->pdf['endpage'] . '\'},';
				$i++;
			}
			//render javascript code
			$mfpTransGallery = 'tPrev: \''.LocalizationUtility::translate('mfp.gallery.tPrev', 'jh_pdfviewer' ).'\', tNext: \''.LocalizationUtility::translate('mfp.gallery.tNext', 'jh_pdfviewer' ).'\', '.
				'tCounter: \''.LocalizationUtility::translate('mfp.gallery.tCounter', 'jh_pdfviewer' ).'\'';
			$mfpTranslation = 'tClose: \''.LocalizationUtility::translate('mfp.tClose', 'jh_pdfviewer' ).'\', tLoading: \''.LocalizationUtility::translate('mfp.tLoading', 'jh_pdfviewer' ).'\', '.
				'image: {tError: \''.LocalizationUtility::translate('mfp.image.tError', 'jh_pdfviewer' ).'\'},';
			$goTo = $this->pdf['actualPage'] - 1;
			$js  = 'jQuery(document).ready(function($) {';
			$js .= '$(\'.pdf-image-'.$this->pdf['uid'].'\').magnificPopup({items: ['.$itemlist.'], gallery: {enabled: true,'.$mfpTransGallery.'}, type: \'image\', ';
			$js .= $mfpTranslation.'callbacks: {open: function() {$.magnificPopup.instance.goTo('.$goTo.');}}});';
			$js .= '});';
			//add javascript code to footer
			$GLOBALS['TSFE']->getPageRenderer()->addJsFooterInlineCode('tx_jhpdfviewer', $js);
		}
	}

}
?>