<?php
    /**
     * Initiate this function to flush the cache automatically
     * 
     * Sets various parameters so the cache is always immediately flushed.
     * This obviates the need to add flush()/ob_flush().
     */
    function alwaysFlush() {
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
        ob_implicit_flush(1);
        set_time_limit(0);
    }

    /**
     * Format exception
     * 
     * Logs and optionally dumps the exception on the screen 
     * in a better readable format.
     * 
     * @param object $e exception to be formatted
     * @returns string
     */
    function formatException(Exception $e) {
        $trace = $e->getTrace();
        $result = 'Exception: "';
        $result .= $e->getMessage();
        $result .= '" @ ';
        if($trace[0]['class'] != '') {
            $result .= $trace[0]['class'];
            $result .= '->';
        }
        $result .= $trace[0]['function'];
        $result .= '();<br>';
        return $result;
    }
