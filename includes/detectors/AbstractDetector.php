<?php

abstract class AbstractDetector {

    const POSITIVE = 1;
    const NEGATIVE = 0;
    const IS_GOLD = true;
    const IS_NOT_GOLD = false;
    const DEFAULT_LABEL = "";
    const END = -1;

    protected $training = array();
    protected $gold = array();
    protected $default_label = "default";

    private $detector_id = "";

    public abstract function registerDetector();
    public abstract function trainDetector();
    public abstract function runGoldTest($confidence);
    public abstract function analyzeVideo($path);
    //public abstract function analyzeImage($path);

    public function setDefaultLabel($label_name) {
        $this->default_label = $label_name;
    }

    public function getDetectorId() {
        return $this->detector_id;
    }
    public function setDetectorId($detector_id) {
        $this->detector_id = $detector_id;
    }

    private function guaranteeLabel($label) {
        if(!array_key_exists($label, $this->training)){
            $this->training[$label] = array(
                "positive_images" => array(),
                "negative_images" => array(),
                "positive_videos" => array(),
                "negative_videos" => array()
            );
        }
        if(!array_key_exists($label, $this->gold)){
            $this->gold[$label] = array(
                "positive_images" => array(),
                "negative_images" => array(),
                "positive_videos" => array(),
                "negative_videos" => array()
            );
        }
        return true;
    }

    public function addImage($path, $type=self::POSITIVE, $is_gold=self::IS_NOT_GOLD, $label=self::DEFAULT_LABEL) {
        if($label == self::DEFAULT_LABEL)
            $label = $this->default_label;

        $this->guaranteeLabel($label);

        if(!$is_gold) {
            if($type == self::POSITIVE)
                $this->training[$label]["positive_images"][] = $path;
            else
                $this->training[$label]["negative_images"][] = $path;
        } else {
            if($type == self::POSITIVE)
                $this->gold[$label]["positive_images"][] = $path;
            else
                $this->gold[$label]["negative_images"][] = $path;
        }
    }

    public function addVideo($path, $start=0, $end=self::END, $type=self::POSITIVE, $is_gold=self::IS_NOT_GOLD, $label=self::DEFAULT_LABEL) {
        if($label == self::DEFAULT_LABEL)
            $label = $this->default_label;

        $this->guaranteeLabel($label);

        if($end == self::END)
            throw new Exception("Right now you have to specify an explicit end to a video clip, sorry.");

        $video = array(
            "path" => $path,
            "start" => $start,
            "end" => $end
        );

        if(!$is_gold) {
            if($type == self::POSITIVE)
                $this->training[$label]["positive_videos"][] = $video;
            else
                $this->training[$label]["negative_videos"][] = $video;
        } else {
            if($type == self::POSITIVE)
                $this->gold[$label]["positive_videos"][] = $video;
            else
                $this->gold[$label]["negative_videos"][] = $video;
        }
    }
}

?>