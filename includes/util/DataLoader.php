<?php
require_once("ImageExtractor.php");
require_once("includes/detectors/AbstractDetector.php");

class DataLoader {

	private static function getSecondsFromTime($time) {
		if(!is_string($time))
			return $time;

		// Convert from HH:MM:SS or MM:SS form
	    $sec = 0;
	    foreach (array_reverse(explode(':', $time)) as $k => $v) $sec += pow(60, $k) * $v;
	    return $sec;
	}

	public static function loadDetector($script_file, &$detector) {

		// Load the script
		$script_json = file_get_contents($script_file);
		$script = json_decode($script_json);
		$script_directory = dirname($script_file)."/";

		// Set up the detector name
		
		$label = $script->label;

		// Load the images
		$image_directory = $script_directory.$script->image_directory."/";
		$images = scandir($image_directory);

		foreach($images as $image) {
			$filetype = filetype($image_directory.$image);
			if($filetype != "file")
				continue;

			if(!is_array(getimagesize($image_directory.$image)))
				continue;

			if(substr($image, 0, 8) != "positive")
				$detector->addImage($image_directory.$image, AbstractDetector::NEGATIVE, AbstractDetector::IS_NOT_GOLD, $label);
			else
				$detector->addImage($image_directory.$image, AbstractDetector::POSITIVE, AbstractDetector::IS_NOT_GOLD, $label);
		}
		
		// Load the videos
		foreach($script->videos as $video) {
			$video_path = $script_directory.$video->path;

			foreach($video->segments as $segment) {
				$start = self::getSecondsFromTime($segment->start);
				$end = self::getSecondsFromTime($segment->end);
				if($segment->is_positive === true)
					$detector->addVideo($video_path, $start, $end, AbstractDetector::POSITIVE, AbstractDetector::IS_NOT_GOLD, $label);
				else
					$detector->addVideo($video_path, $start, $end, AbstractDetector::NEGATIVE, AbstractDetector::IS_NOT_GOLD, $label);
			}
		}
	}

	public static function loadGold($script_file, &$detector) {
		$script_json = file_get_contents($script_file);
		$script = json_decode($script_json);
		$script_directory = dirname($script_file)."/";

		$label = $script->label;

		// Load the videos
		foreach($script->videos as $video) {
			$video_path = $script_directory.$video->path;

			foreach($video->segments as $segment) {
				$start = self::getSecondsFromTime($segment->start);
				$end = self::getSecondsFromTime($segment->end);
				if($segment->is_positive === true)
					$detector->addVideo($video_path, $start, $end, AbstractDetector::POSITIVE, AbstractDetector::IS_GOLD, $label);
				else
					$detector->addVideo($video_path, $start, $end, AbstractDetector::NEGATIVE, AbstractDetector::IS_GOLD, $label);
			}
		}

	}
}

?>