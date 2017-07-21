<?php

	define("ROOT", __DIR__ ."/");
	include("includes/detectors/MatroidDetector.php");
	include("includes/util/DataLoader.php");
	

	$fp = fopen('lock.txt', 'w+');
	/* Activate the LOCK_NB option on an LOCK_EX operation */
	if(!flock($fp, LOCK_EX | LOCK_NB)) {
		exit(-1);
	}

	// Set up the detector
	global $current_detector_id;
	$matroid = new MatroidDetector();
	if($current_detector_id != "") {
		$matroid->setDetectorId($current_detector_id);
	}
	else {
		echo("Training a detector");
		DataLoader::loadDetector("datasets/detectors/pelosi/detector.json", $matroid);
		DataLoader::loadDetector("datasets/detectors/trump/detector.json", $matroid);
		DataLoader::loadDetector("datasets/detectors/mcconnell/detector.json", $matroid);
		DataLoader::loadDetector("datasets/detectors/ryan/detector.json", $matroid);
		DataLoader::loadDetector("datasets/detectors/schumer/detector.json", $matroid);
		DataLoader::loadDetector("datasets/detectors/obama/detector.json", $matroid);
		DataLoader::loadDetector("datasets/detectors/mccain/detector.json", $matroid);
		DataLoader::loadDetector("datasets/detectors/trump_jr/detector.json", $matroid);
		$matroid->registerDetector();
		$matroid->trainDetector();
	}

	function get_archive_videos() {
		$url = "http://archive.org/details/tv?weekshows&output=json";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);
		$result = json_decode($response);
		return $result;
	}

	function split_video($path) {
		global $ffmpeg;
		$filesize = filesize($path);
		$cmd = $ffmpeg.' -i "'.$path.'" -acodec copy -f segment -segment_time 1200 -vcodec copy -reset_timestamps 1 -map 0 -segment_list out.list '.$path.'_OUTPUT%d.mp4';
		exec($cmd);
		$output = file_get_contents('out.list');
		$files = explode("\n", $output);
		foreach ($files as &$value) {
			if(trim($value) == "")
				continue;
			$value = 'videos/'.$value;
		}
		return $files;
	}

	function get_duration($path) {
		global $ffprobe;
		$cmd = $ffprobe.' -i '.$path.' -show_entries format=duration -v quiet -of csv="p=0"';
		echo("\n\r".$cmd."\n\r");
		exec($cmd, $output);
		print_r($output[0]);
		return $output[0];
	}

	function download_program($program) {
		global $user;
		global $sig;

		$path = dirname(__FILE__)."/videos/".$program.".mp4";
		if($program == "")
		continue;

		// Download the media
		$program_mp4 = "http://archive.org/download/".$program."/".$program.".mp4";

		echo("\n\rDownloading ".$program_mp4);

		$fp = fopen($path, 'w+');
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $program_mp4);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Cookie: logged-in-user=".$user.";logged-in-sig=".$sig));
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_exec($ch);
		fclose($fp);
		curl_close ($ch);
		return $path;
	}

	function clear_program($program) {
		$path = dirname(__FILE__)."/videos/".$program.".mp4";
		$mask = $path.'*';
		array_map('unlink', glob($mask));
	}

	function seconds_to_time($seconds) {
		$s = $seconds%60;
		$m = floor($seconds%3600/60);
		$h = floor(($seconds%86400)/3600);
		return $h.":".$m.":".$s;
	}

	function send_clips($archive_id, $clips) {
		if(sizeof($clips) == 0){
			$text = ":wastebasket: :wastebasket: :wastebasket: No Pelosi: ". $archive_id." :wastebasket: :wastebasket: :wastebasket:";
		}
		else {
			$text = ":rotating_light: :rotating_light: :rotating_light: PELOSI DETECTION ALERT :rotating_light: :rotating_light: :rotating_light:";
			$text .= "\nProgram: ".$archive_id;

			foreach($clips as $clip) {
				$start = $clip[0];
				$end = $clip[1];
				$text.= "\n * ".seconds_to_time($start)." - ".seconds_to_time($end)." <https://archive.org/details/".$archive_id."#start/".$start."/end/".$end."|(".($end - $start)."s)>";
			}
		}

		$data = array(
			"text" => $text
		);

		// Send an anonymous submit alert
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://hooks.slack.com/services/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");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		echo(curl_exec($ch));
		curl_close ($ch);
	}

	$archive_videos = get_archive_videos();
	foreach($archive_videos as $archive_id) {
		if(substr($archive_id, 0, 9) == "FOXNEWSW_") {
			if(file_exists('results/'.$archive_id.'.txt'))
				continue;

			if(!file_exists('results/'.$archive_id.'.txt')) {
				$path = download_program($archive_id);
				$pieces = split_video($path);
				$cursor = 0;
				$scores = array();
				foreach($pieces as $piece) {
					if(trim($piece) == "")
						continue;
					$duration = get_duration($piece);
					echo("\n\rAnalyzing: ".$piece);
					$token = $matroid->analyzeVideo($piece);
					echo("\n\rWaiting for results: ".$token);
					$result = $matroid->resolveVideo($token);
					foreach($result->detections as $second => $data) {
						$faces = $data->{'0'};
						$max_score = 0;
						foreach($faces as $result) {
							$max_score = max($max_score, $result->score);
						}
						$second = $second + $cursor;
						if($max_score > 90) {
							$scores[$second] = $max_score;
						}
					}
					$cursor += $duration;
				}
				$file = fopen("results/".$archive_id.".txt","w");
				fwrite($file, serialize($scores));
				fclose($file);
				clear_program($archive_id);
			}

			$str = file_get_contents("results/".$archive_id.".txt","r");
			$results = unserialize($str);
			ksort($results);
			$clips = array();
			$start = -1;
			$end = -1;
			foreach($results as $second => $score) {
				echo("\n\r".$second);
				if($start == -1) {
					$start = $second;
					$end = $second;
				}

				if($second - $end <= 3){
					$end = $second;
				}
				else {
					$clips[] = array($start, $end + 1);
					$start = $second;
					$end = $second;
				}
			}
			if($start != -1)
				$clips[] = array($start, $end + 1);

			send_clips($archive_id, $clips);
		}
	}

	// $results = $matroid->runGoldTest(.9);


	// $total_negative_expected = 0;
	// $total_negative_measured = 0;
	// $total_positive_expected = 0;
	// $total_positive_measured = 0;

	// foreach($results as $path => $result) {
	// 	$total_negative_expected += $result['negative_expected'];
	// 	$total_negative_measured += $result['negative_measured'];
	// 	$total_positive_expected += $result['positive_expected'];
	// 	$total_positive_measured += $result['positive_measured'];

	// 	if($result["positive_expected"] > 0) {
	// 		echo("\n\r".$path." ::Positive - ".$result["positive_measured"]." / ".$result["positive_expected"]." (".($result["positive_measured"]/$result["positive_expected"]).")");
	// 	} else {
	// 		echo("\n\r".$path." ::Positive - No Gold Data");
	// 	}

	// 	if($result["negative_expected"] > 0) {
	// 		echo("\n\r".$path." ::Negative - ".$result["negative_measured"]." / ".$result["negative_expected"]." (".($result["negative_measured"]/$result["negative_expected"]).")");
	// 	} else {
	// 		echo("\n\r".$path." ::Positive - No Gold Data");
	// 	}

	// 	$positive = fopen("positive.csv", 'w');
	// 	$negative = fopen("negative.csv", 'w');

	// 	foreach($result['positive_array'] as $second => $value) {
	// 		fputcsv($positive, array(
	// 			$second,
	// 			$value?1:0
	// 		));
	// 	}

	// 	foreach($result['negative_array'] as $second => $value) {
	// 		fputcsv($negative, array(
	// 			$second,
	// 			$value?1:0
	// 		));
	// 	}
	// }

	// if($total_positive_expected > 0) {
	// 	echo("\n\rTOTAL:Positive - ".$total_positive_measured." / ".$total_positive_expected. "(".($total_positive_measured/$total_positive_expected).")");
	// } else {
	// 	echo("\n\rTOTAL::Positive - No Gold Data");
	// }

	// if($total_negative_expected > 0) {
	// 	echo("\n\rTOTAL:Negative - ".$total_negative_measured." / ".$total_negative_expected. "(".($total_negative_measured/$total_negative_expected).")");
	// } else {
	// 	echo("\n\rTOTAL::Negative - No Gold Data");
	// }


	fclose($fp);

?>