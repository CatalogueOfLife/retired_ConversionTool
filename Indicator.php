<?php
class Indicator
{
	protected $_enabled = false;
	// settings
	protected $_marker = '.';
    protected $_breakLine = "<br>";
    protected $_iterationsPerMarker = 10; 
    protected $_markersPerLine = 50;
    
    protected $_totalNumberOfIterations = 0;
    
    protected $_iterationCounter = 0;
    protected $_markersPerCycleCounter = 0;
    protected $_cycleCounter = 0;
    
    public function setBreakLine ($breakLine)
    {
    	$this->_breakLine = $breakLine;
    }
    
    public function init($numberOfIterations, $markersPerLine = null, $iterationsPerMarker = null)
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
    
    public function iterate()
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
            if ($this->_markersPerCycleCounter >= $this->_markersPerLine) {
                if ($this->_markersPerCycleCounter > 0 && $this->_totalNumberOfIterations > 0) {
                    $current_percentage_done = round(
                        ($this->_cycleCounter / $this->_totalNumberOfIterations * 100), 1
                    );
                    echo " " . $current_percentage_done . "% done";
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
}