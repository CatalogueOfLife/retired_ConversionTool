<?php
class Indicator
{
	protected $_enabled = false;
	// settings
	protected $_marker = '.';
    protected $_breakLine = "<br>";
    protected $_iterationsPerMarker = 10; 
    protected $_markersPerLine = 75;
    
    protected $_totalNumberOfIterations = 0;
    
    protected $_iterationCounter = 0;
    protected $_markersPerCycleCounter = 0;
    protected $_cycleCounter = 0;
    protected $_summarizedDuration = 0;
    
    public function setBreakLine ($breakLine)
    {
    	$this->_breakLine = $breakLine;
    }
    
    public function init($numberOfIterations, $markersPerLine = null, 
        $iterationsPerMarker = null)
    {
    	if($markersPerLine !== null) {
    		$this->_markersPerLine = (int)$markersPerLine;
    	}
        if($iterationsPerMarker !== null) {
            $this->_iterationsPerMarker = (int)$iterationsPerMarker;
        }
    	$this->_enabled = true;
        $this->_totalNumberOfIterations = (int)$numberOfIterations;
        $this->reset();
    }
    
    public function iterate($duration = false)
    {
    	if(!$this->_enabled) {
    		return;
    	}
    	$this->_iterationCounter ++;
    	$this->_cycleCounter ++;
    	if ($this->_iterationCounter >= $this->_iterationsPerMarker) {
            echo $this->_marker;
            flush();
            $this->_iterationCounter = 0;
            $this->_markersPerCycleCounter ++;
            if ($duration) {
                $this->_summarizedDuration += $duration;
            }
            if ($this->_markersPerCycleCounter >= $this->_markersPerLine) {
                if ($this->_markersPerCycleCounter > 0 && 
                        $this->_totalNumberOfIterations > 0) {
                    $currentPercentageDone = round(
                        ($this->_cycleCounter / 
                        $this->_totalNumberOfIterations * 100), 1
                    );
                    echo ' ' . $currentPercentageDone . '% done';
                }
                if ($this->_summarizedDuration > 0) {
                    $this->_printRemainingTime();
                }
    		    echo $this->_breakLine;
    		    flush();
    		    $this->_markersPerCycleCounter = 0;
            }
     	}
    }
    
    public function disable() {
    	$this->reset();
    	$this->_enabled = false;
    }
    
    public function reset()
    {
    	$this->_iterationCounter = 0;
    	$this->_markersPerCycleCounter = 0;
    	$this->_cycleCounter = 0;
    }
    
    public function formatRemainingTime($time)
    {
        $remaining = '';
        $days = floor($time / 86400);
        $hours = floor((($time / 86400) - $days) * 24);
        $minutes = floor((((($time / 86400) - $days) * 24) - 
            $hours) * 60);
        $seconds = floor((((((($time / 86400) - $days) * 24) - 
            $hours) * 60) - $minutes) * 60);
        foreach (array($days, $hours, $minutes, $seconds) as $unit) {
            if ($unit < 10) {
                $remaining .= "0";
            }
            $remaining .= $unit.":";
        }
        return substr($remaining, 0, -1);
    }
    
    private function _printRemainingTime()
    {
        $averageRequest = $this->_summarizedDuration / $this->_cycleCounter;
        $remainingTime = $this->formatRemainingTime(
            ($this->_totalNumberOfIterations - 
            $this->_cycleCounter) * $averageRequest);
        echo '; '.round((1 / $averageRequest), 2).
            ' request/s, '.$remainingTime.' remaining';
    }
}