.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _typoscript-setup:

TypoScript Setup
================

.. _css:

CSS
---

To remove the default css of the extension (responsive css too), add this line to your setup:

.. code-block:: typoscript

	plugin.tx_jhpdfviewer._CSS_DEFAULT_STYLE >

.. _magnific-popup:

Magnific Popup
--------------

If you want to change the size of the images in Magnific Popup, the default size set for popups will be used (definde in constants "styles.content.imgtext.linkWrap.width" and "styles.content.imgtext.linkWrap.height").

If you want to use another size within the pdf viewer, modify

.. code-block:: typoscript

	plugin.tx_jhpdfviewer.settings.mfp.width =
	plugin.tx_jhpdfviewer.settings.mfp.height =

to your needs.


.. _image-navigation:

Image navigation
----------------

The image navigation could be configured by some changes in setup.

Path:

.. code-block:: typoscript

	plugin.tx_jhpdfviewer.settings.imageNavigation

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Property
         Property:

   Data type
         Data type:

   Description
         Description:

   Default
         Default:


.. container:: table-row

   Property
         image.width

   Data type
         string

   Description
         Width of image in px.

   Default
         80m

.. container:: table-row

   Property
         image.height

   Data type
         string

   Description
         Height of image in px.

   Default
         80m


.. container:: table-row

   Property
         image.margin.rightLeft

   Data type
         int

   Description
         Right and left margin of each image.

   Default
         2


.. container:: table-row

   Property
         image.margin.topBottom

   Data type
         int

   Description
         Top and bottom margin of each image.

   Default
         4


.. ###### END~OF~TABLE ######


Example
^^^^^^^
.. code-block:: typoscript

	plugin.tx_jhpdfviewer.settings.imageNavigation {
		image {
			width = 80m
			height = 80m
			margin {
				rightLeft = 2
				topBottom = 4
			}
		}
	}