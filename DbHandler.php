<?php
class DbHandler
{
    private static $instance = array();
    
    /**
     *
     * the constructor is set to private so
     * so nobody can create a new instance using new
     *
     */
    private function __construct()
    {
    }
    
    public static function createInstance($id, array $config,
        array $options = array())
    {
        if(isset(self::$instance[$id])) {
            return false;
        }
        try {
            $dsn =  $config['driver'] . ':host=' . $config['host'] . 
                    ';dbname=' . $config['dbname'];
            ($config['port'] != '' ? $dsn .= ';port=' . $config['port'] : '');
        	self::$instance[$id] = new PDO($dsn,
	            $config['username'],
	            $config['password'],
	            $options
	        );
	        self::$instance[$id]->setAttribute(
	            PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION
	        );
	        // Bug fix: PDO does not seem to recognize
	        // array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
	        // as a way to set up a utf8 connection. We are forcing
	        // it with a query if this option is set.
            if (in_array('set names utf8', array_map('strtolower', $options))) {
    	        self::$instance[$id]->query('SET NAMES "utf8"');
	        }
	        return true;
        }
        catch (PDOException $e) {
	        echo $e->getMessage();
	        exit;
	    }
    }
    
    /* TODO: implement??
    public static closeInstance($name)
    {
    }
    */
    
    /**
     *
     * Return DB instance or create intitial connection
     *
     * @return object (PDO)
     *
     * @access public
     *
     */
    public static function getInstance($id)
    {
        if(!isset(self::$instance[$id])) {
            throw new Exception('There\'s no instance with id ' . $id);
        }
        return self::$instance[$id];
    }
    
    /**
     *
     * Like the constructor, we make __clone private
     * so nobody can clone the instance
     *
     */
    private function __clone()
    {}

}