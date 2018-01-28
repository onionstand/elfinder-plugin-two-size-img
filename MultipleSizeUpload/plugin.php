<?php

/*
$opts = array(

	'bind' => array(
        'mkdir.pre mkfile.pre rename.pre' => array(
            'Plugin.Sanitizer.cmdPreprocess'
        ),
        
        'upload.presave' => array(
            'Plugin.Sanitizer.onUpLoadPreSave',
            'Plugin.MultipleSizeUpload.onUpLoadPreSave'
        )
        
    ),



    'plugin' => array(
		'Sanitizer' => array(
			'enable' => true,
			'targets'  => array('\\','/',':','*','?','"','<','>','|',' ','š','Š','Č','č','Ć','ć','Đ','đ'), // target chars
			'replace'  => '_'    // replace to this
		),
		'MultipleSizeUpload' => array(
			'enable'         => true,
			'maxWidth'       => 300,
			'maxHeight'      => 300,
			'quality'        => 85,
			'preserveExif'   => false,
			'forceEffect'    => false,
			'targetType'     => IMG_GIF|IMG_JPG|IMG_PNG|IMG_WBMP,
			'offDropWith'    => null,
			'smallWidth'       => 300,
			'smallHeight'       => 300
		)
	),

	'debug' => true,
	'roots' => array(
		// Items volume
		array(
			'driver'        => 'LocalFileSystem',           // driver for accessing file system (REQUIRED)
			'path'          => '../../../uploads/',                 // path to files (REQUIRED)
			'URL'           => 'http://test/uploads/', // URL to files (REQUIRED)
			'trashHash'     => 't1_Lw',                     // elFinder's hash of trash folder
			'winHashFix'    => DIRECTORY_SEPARATOR !== '/', // to make hash same to Linux one on windows too
			'uploadDeny'    => array('all'),                // All Mimetypes not allowed to upload
			'uploadAllow'   => array('image', 'text/plain'),// Mimetype `image` and `text/plain` allowed to upload
			'uploadOrder'   => array('deny', 'allow'),      // allowed Mimetype `image` and `text/plain` only
			'accessControl' => 'access',                    // disable and hide dot starting files (OPTIONAL)
			'imgLib'		=>'gd',
			'tmbSize'		=> 80,
			'jpgQuality'    => 85,
			'tmbPath'    => 'thumbnails',
			'uploadMaxSize' => '15M',
			'plugin' => array(
 					'Sanitizer' => array(
						'enable' => true,
						'targets'  => array('\\','/',':','*','?','"','<','>','|',' ','š','Š','Č','č','Ć','ć','Đ','đ'), // target chars
						'replace'  => '_'    // replace to this
					),
 					'MultipleSizeUpload' => array(
						'enable'         => true,
						'maxWidth'       => 1024,
						'maxHeight'      => 1024,
						'quality'        => 85,
						'preserveExif'   => false,
						'forceEffect'    => false,
						'targetType'     => IMG_GIF|IMG_JPG|IMG_PNG|IMG_WBMP,
						'offDropWith'    => null,
						'smallWidth'     => 300,
						'smallHeight'    => 300
					)
 				)
		)
	)
);
*/

class elFinderPluginMultipleSizeUpload extends elFinderPlugin {

	public function __construct($opts) {
		$defaults = array(
			'enable'         => true,       // For control by volume driver
			'maxWidth'       => 1024,       // Path to Water mark image
			'maxHeight'      => 1024,       // Margin right pixel
			'quality'        => 95,         // JPEG image save quality
			'preserveExif'   => false,      // Preserve EXIF data (Imagick only)
			'forceEffect'    => false,      // For change quality or make progressive JPEG of small images
			'targetType'     => IMG_GIF|IMG_JPG|IMG_PNG|IMG_WBMP, // Target image formats ( bit-field )
			'offDropWith'    => null,        // To disable it if it is dropped with pressing the meta key
			                                // Alt: 8, Ctrl: 4, Meta: 2, Shift: 1 - sum of each value
			                                // In case of using any key, specify it as an array
			'smallWidth'       => 300,
			'smallHeight'       => 300
		);

		$this->opts = array_merge($defaults, $opts);

	}

	public function onUpLoadPreSave(&$thash, &$name, $src, $elfinder, $volume) {
		$opts = $this->getCurrentOpts($volume);
		
		if (! $this->iaEnabled($opts)) {
			return false;
		}
		
		$imageType = null;
		$srcImgInfo = null;
		if (extension_loaded('fileinfo') && function_exists('mime_content_type')) {
			$mime = mime_content_type($src);
			if (substr($mime, 0, 5) !== 'image') {
				return false;
			}
		}
		if (extension_loaded('exif') && function_exists('exif_imagetype')) {
			$imageType = exif_imagetype($src);
		} else {
			$srcImgInfo = getimagesize($src);
			if ($srcImgInfo === false) {
				return false;
			}
			$imageType = $srcImgInfo[2];
		}
		
		// check target image type
		$imgTypes = array(
			IMAGETYPE_GIF  => IMG_GIF,
			IMAGETYPE_JPEG => IMG_JPEG,
			IMAGETYPE_PNG  => IMG_PNG,
			IMAGETYPE_BMP  => IMG_WBMP,
			IMAGETYPE_WBMP => IMG_WBMP
		);
		if (! isset($imgTypes[$imageType]) || ! ($opts['targetType'] & $imgTypes[$imageType])) {
			return false;
		}
		
		if (! $srcImgInfo) {
			$srcImgInfo = getimagesize($src);
		}

		


		copy($src, $volume->getPath($thash).'\tn-'.$name);
		$src_300x300 = $volume->getPath($thash).'\tn-'.$name;
		$this->resize($volume, $src_300x300, $srcImgInfo, $opts['smallWidth'], $opts['smallHeight'], $opts['quality'], $opts['preserveExif']);
		

		
		if ($opts['forceEffect'] || $srcImgInfo[0] > $opts['maxWidth'] || $srcImgInfo[1] > $opts['maxHeight']) {

			return $this->resize($volume, $src, $srcImgInfo, $opts['maxWidth'], $opts['maxHeight'], $opts['quality'], $opts['preserveExif']);
		}
		return false;
	}
	
	private function resize($volume, $src, $srcImgInfo, $maxWidth, $maxHeight, $jpgQuality, $preserveExif) {
		$zoom = min(($maxWidth/$srcImgInfo[0]),($maxHeight/$srcImgInfo[1]));
		$width = round($srcImgInfo[0] * $zoom);
		$height = round($srcImgInfo[1] * $zoom);
		$unenlarge = true;
		$checkAnimated = true;
		
		return $volume->imageUtil('resize', $src, compact('width', 'height', 'jpgQuality', 'preserveExif', 'unenlarge', 'checkAnimated'));
	}

}
