<?php

require_once("AbstractDetector.php");
require_once("includes/util/ImageExtractor.php");

class MatroidDetector extends AbstractDetector {

	const MATROID_BASE_API = "https://www.matroid.com/api/0.1";

	// This will go in config eventually
	private $detector_id = "";

	private $oauth_token = "";
	private $oauth_token_expires = 0;

	private function getAccessToken() {
		if(time() < $this->oauth_token_expires)
			return $this->oauth_token;

		global $matroid_client_id;
		global $matroid_client_secret;
		
		$url = self::MATROID_BASE_API."/oauth/token";
		$post = array(
			"client_id" => $matroid_client_id,
			"client_secret" => $matroid_client_secret,
			"grant_type" => "client_credentials"
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$result = json_decode(curl_exec($ch));
		curl_close ($ch);

		$this->oauth_token = $result->access_token;
		$this->oauth_token_expires = time() + $result->expires_in - 1000;
		return $this->oauth_token;
	}

	public function registerDetector() {
		// We have to zip up the files
		$zip = new ZipArchive;
		$detector_name = "TVArchive_Matroid_".time();
		$zip_name = '/tmp/'.$detector_name.".zip";

		$zip_outcome = $zip->open($zip_name, ZIPARCHIVE::CREATE);

		if ( $zip_outcome === true) {

			foreach($this->training as $label => $files) {
				$positive_images = $files["positive_images"];
				$negative_images = $files["negative_images"];

				// Convert videos to keyframes
				$image_extractor = new ImageExtractor();
				foreach($files["positive_videos"] as $video) {
					for($x = $video["start"]; $x <= $video["end"]; $x++) {
						$image = $image_extractor->saveImageFromVideo($video["path"], $x);
						echo("\n\rCreated image from ".$x."s in ".$video["path"]);
						$positive_images[] = $image;
					}
				}
				foreach($files["negative_videos"] as $video) {
					for($x = $video["start"]; $x <= $video["end"]; $x++) {
						$image = $image_extractor->saveImageFromVideo($video["path"], $x);
						echo("\n\rCreated image from ".$x."s in ".$video["path"]);
						$negative_images[] = $image;
					}
				}

				// Create the directory for this label
				$zip->addEmptyDir($label);

				// Add positive images
				foreach($positive_images as $image) {
				    $zip->addFile($image, $label."/".time()."_".basename($image));
				}

				// Add negative images
				if(sizeof($negative_images) > 0) {
					$zip->addEmptyDir($label."/negative");

					foreach($negative_images as $image) {
					    $zip->addFile($image, $label."/negative/".time()."_".basename($image));
					}
				}
			}
		    $zip->close();
		} else {
			echo("Could not create zip (".$zip_name.") , error code ".$zip_outcome."\n\r");
		}

		// Get an oauth token
		$oauth_token = $this->getAccessToken();

		// Next, register the detector
		$url = self::MATROID_BASE_API."/detectors";
		$post = array(
			"name" => $detector_name,
			"detector_type" => "facial_recognition",
			"file" => new CurlFile($zip_name, 'application/zip')
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer '.$oauth_token,
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$result = json_decode(curl_exec($ch));
		curl_close ($ch);
		if(!property_exists($result, "detector_id")) {
			throw new Exception(print_r($result, true));
		}
		$this->setDetectorId($result->detector_id);
		return $this->getDetectorId();
	}

	public function trainDetector() {
		$detector_id = $this->getDetectorId();
		if($detector_id == "")
			throw new Exception("Unable to train a detector without a detector ID");

		// Get an oauth token
		$oauth_token = $this->getAccessToken();

		// Start the training
		$url = self::MATROID_BASE_API."/detectors/".$detector_id."/finalize";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer '.$oauth_token,
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		$result = json_decode(curl_exec($ch));
		return $result;
	}

	public function analyzeVideo($path) {
		$detector_id = $this->getDetectorId();
		if($detector_id == "")
			throw new Exception("Unable to run comparsion without a set detector ID");

		// Get an oauth token
		$oauth_token = $this->getAccessToken();

		$url = self::MATROID_BASE_API."/detectors/".$detector_id."/classify_video";
		$post = array(
			"file" => new CurlFile($path, mime_content_type($path))
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer '.$oauth_token,
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$response = curl_exec($ch);
		$result = json_decode($response);
		if(!$result) {
			print_r($response);
			die();
		}
		if(!property_exists($result, 'video_id')) {
			print_r($result);
			die();
		}

		return $result->video_id;
	}

	public function resolveVideo($video_token) {
		// Get an oauth token
		$oauth_token = $this->getAccessToken();

		$progress = 0;
		$url = self::MATROID_BASE_API."/videos/".$video_token;

		echo($oauth_token);
		echo("\n\r".self::MATROID_BASE_API."/videos/".$video_token);


		while($progress != 100) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,$url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Authorization: Bearer '.$oauth_token,
			));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($ch);
			$result = json_decode($response);
			curl_close($ch);

			if(property_exists($result, 'classification_progress')) {
				$progress = $result->classification_progress;
				echo("\n\r".$progress."%");
				sleep(5);
			} else {
				print_r($result);
				die();
			}
		}
		return $result;
	}

	public function runGoldTest($confidence=1) {
		// Get a list of specific video files
		$videos = array();
		foreach($this->gold as $label => $files) {
			$positive_images = $files["positive_images"];
			$negative_images = $files["negative_images"];
			$positive_videos = $files["positive_videos"];
			$negative_videos = $files["negative_videos"];

			foreach($positive_videos as $positive_video) {
				if(!in_array($positive_video['path'], $videos)) {
					$videos[] = $positive_video['path'];
				}
			}
			foreach($negative_videos as $negative_video) {
				if(!in_array($negative_video['path'], $videos)) {
					$videos[] = $negative_video['path'];
				}
			}
		}

		// Start the analysis of the video files
		$processed_videos = array();
		foreach($videos as $index => $video_path) {
			$processed_videos[] = array(
				"token" => $this->analyzeVideo($video_path),
				"results" => null,
				"path" => $video_path
			);
		}

		// Store the results
		$analysis_results = array();
		foreach($processed_videos as $processed_video) {
			$analysis_results[$processed_video["path"]] = $this->resolveVideo($processed_video["token"]);
		}

		// Compare the positive results
		$positive_seconds = array();
		foreach($positive_videos as $video) {
			$path = $video["path"];
			$start = $video["start"];
			$end = $video["end"];
			if(!array_key_exists($path, $positive_seconds)) {
				$positive_seconds[$path] = array();
			}


			$results = $analysis_results[$path];
			$data = $results->detections;
			for($x = $start; $x <=$end; $x += .5) {
				$index = "second_".((int)floor($x));
				if(!array_key_exists($index, $positive_seconds[$path]))
					$positive_seconds[$path][$index] = false;

				// TODO: this is hard coded to only consider a single label.
				if(property_exists($data, $x)
				&& $data->$x->{'0'}[0]->score >= $confidence * 100) {
					$positive_seconds[$path][$index] = true;
				}
			}
		}

		// Compare the negative results
		$negative_seconds = array();
		foreach($negative_videos as $video) {
			$path = $video["path"];
			$start = $video["start"];
			$end = $video["end"];
			if(!array_key_exists($path, $negative_seconds)) {
				$negative_seconds[$path] = array();
			}
			
			$results = $analysis_results[$path];
			$data = $results->detections;
			for($x = $start; $x <=$end; $x += .5) {
				$index = "second_".((int)floor($x));
				if(!array_key_exists($index, $negative_seconds[$path]))
					$negative_seconds[$path][$index] = false;

				// TODO: this is hard coded to only consider a single label.
				if(!property_exists($data, $x)
				|| $data->$x->{'0'}[0]->score < $confidence * 100) {
					$negative_seconds[$path][$index] = true;
				}
			}
		}

		$results = array();
		foreach($positive_seconds as $result) {
			if(!array_key_exists($path, $results)) {
				$results[$path] = array(
					"positive_measured" => 0,
					"positive_expected" => 0,
					"positive_array" => array(),
					"negative_mesaured" => 0,
					"negative_expected" => 0,
					"negative_array" => array()
				);
			}
			$results[$path]["positive_measured"] = count(array_filter($result));
			$results[$path]["positive_expected"] = count($result);
			$results[$path]["positive_array"] = $result;
		}
		foreach($negative_seconds as $result) {
			if(!array_key_exists($path, $results)) {
				$results[$path] = array(
					"positive_measured" => 0,
					"positive_expected" => 0,
					"positive_array" => array(),
					"negative_mesaured" => 0,
					"negative_expected" => 0,
					"negative_array" => array()
				);
			}
			$results[$path]["negative_measured"] = count(array_filter($result));
			$results[$path]["negative_expected"] = count($result);
			$results[$path]["negative_array"] = $result;
		}
		return $results;
	}
}

?>