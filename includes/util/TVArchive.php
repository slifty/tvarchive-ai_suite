<?php
require_once("config.php");

//ffmpeg -ss 00:00:15 -i video.mp4 -vf scale=800:-1 -vframes 1 image.jpg
class TVArchive {

	const ALL = "all"

	private $start_date = null;
	private $end_date = null;

	public function setStartDate($date) {
		$this->start_date = $date;
	}
	public function setEndDate($date) {
		$this->end_date = $date;
	}

	public function getProgramsByChannel($channel = self::ALL) {}

	private function verifyFfmpeg() {
		global $ffmpeg;
		if($ffmpeg == "")
			throw error("A path to ffmpeg has not been specified");
		return;
	}

	public function saveImageFromVideo($video_path, $second) {
		global $ffmpeg;
		$this->verifyFfmpeg();
		$img_file = "/tmp/".str_replace(".","_",basename($video_path))."_".$second."_".time().".jpg";
		$cmd = $ffmpeg."  -ss ".$second.".001 -i \"".$video_path."\" -vf scale=800:-1 -vframes 1 ".$img_file." 2>&1"; 
		exec($cmd, $output);
		return $img_file;
	}
}
?>