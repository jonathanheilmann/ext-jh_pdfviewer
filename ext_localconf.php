<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
	'Heilmann.' . $_EXTKEY,
	'Viewer',
	array(
		'Viewer' => 'show',

	),
	// non-cacheable actions
	array(
	)
);