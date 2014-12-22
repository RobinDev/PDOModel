<?php
namespace rOpenDev\PDOModel;

trait Instanciator
{
    /** @var object's array Contain opened connexion (pdolink) **/
    private static $instance;

    /** @var string **/
    protected $dbname;

    /**
     * Constructor
     *
     * @param string $dbname
     * @param bool   $checkIfDatabaseExist
     */
    public function __construct($dbname = 'default', $checkIfDatabaseExist = true)
    {
        $this->dbname = $dbname;


        if ($checkIfDatabaseExist) {
            $this->createDatabase();
        }
    }

    /**
     * Get a self instance
     *
     * @param string $dbname
     * @param bool   $checkIfDatabaseExist
     *
     * @return self (PDO Link corresponding to the called class)
     */
    public static function instance($dbname, $checkIfDatabaseExist = false)
    {
        $cls = get_called_class();
        $intance_name = $dbname.$cls;
        if (!isset(self::$instance[$intance_name])) {
            self::$instance[$intance_name] = new $cls($dbname, $checkIfDatabaseExist);
        }

        return self::$instance[$intance_name];
    }

    /**
     * Alias for self::close_instance
     * @param string $dbname
     */
    public static function close($dbname)
    {
        self::close_instance($dbname);
    }

    /**
     * Close the pdo connexion
     *
     * @param string $dbname
     */
    public static function close_instance($dbname)
    {
        $cls = get_called_class();
        $intance_name = $dbname.$cls;
        self::$instance[$intance_name] = null;
        unset(self::$instance[$intance_name]);
    }
}
