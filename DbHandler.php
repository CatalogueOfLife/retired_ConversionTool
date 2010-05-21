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
        self::$instance[$id] = new PDO(
            $config['driver'] . ':host=' . $config['host'] . ';dbname=' .
                $config['dbname'],
            $config['username'],
            $config['password'],
            $options
        );
        self::$instance[$id]->setAttribute(
            PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION
        );
        return true;
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