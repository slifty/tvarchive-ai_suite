<?php

	include("includes/detectors/MatroidDetector.php");
	include("includes/util/DataLoader.php");
	$matroid = new MatroidDetector();

	DataLoader::loadDetector("datasets/detectors/trump/detector.json", $matroid);
	DataLoader::loadGold("datasets/gold/trump/gold.json", $matroid);

	//$matroid->registerDetector();
	//$matroid->trainDetector();
	$matroid->setDetectorId("591dcab783ad8d000daa68be");
	$results = $matroid->runGoldTest(.9);


	$total_negative_expected = 0;
	$total_negative_measured = 0;
	$total_positive_expected = 0;
	$total_positive_measured = 0;

	foreach($results as $path => $result) {
		$total_negative_expected += $result['negative_expected'];
		$total_negative_measured += $result['negative_measured'];
		$total_positive_expected += $result['positive_expected'];
		$total_positive_measured += $result['positive_measured'];

		if($result["positive_expected"] > 0) {
			echo("\n\r".$path." ::Positive - ".$result["positive_measured"]." / ".$result["positive_expected"]." (".($result["positive_measured"]/$result["positive_expected"]).")");
		} else {
			echo("\n\r".$path." ::Positive - No Gold Data");
		}

		if($result["negative_expected"] > 0) {
			echo("\n\r".$path." ::Negative - ".$result["negative_measured"]." / ".$result["negative_expected"]." (".($result["negative_measured"]/$result["negative_expected"]).")");
		} else {
			echo("\n\r".$path." ::Positive - No Gold Data");
		}

		$positive = fopen("positive.csv", 'w');
		$negative = fopen("negative.csv", 'w');

		foreach($result['positive_array'] as $second => $value) {
			fputcsv($positive, array(
				$second,
				$value?1:0
			));
		}

		foreach($result['negative_array'] as $second => $value) {
			fputcsv($negative, array(
				$second,
				$value?1:0
			));
		}
	}

	if($total_positive_expected > 0) {
		echo("\n\rTOTAL:Positive - ".$total_positive_measured." / ".$total_positive_expected. "(".($total_positive_measured/$total_positive_expected).")");
	} else {
		echo("\n\rTOTAL::Positive - No Gold Data");
	}

	if($total_negative_expected > 0) {
		echo("\n\rTOTAL:Negative - ".$total_negative_measured." / ".$total_negative_expected. "(".($total_negative_measured/$total_negative_expected).")");
	} else {
		echo("\n\rTOTAL::Negative - No Gold Data");
	}


?>