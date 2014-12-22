<?php
namespace rOpenDev\PDOModel;

use PDO;

class Connector extends PDO
{

    /** @var string */
    private $dsn;

    /** @var string */
    private $user;

    /** @var string */
    private $password;

    /** @var string Correspond to database path for SQLite */
    private $host;

    /** @var string */
    private $port;

    /** @var array */
    private $options;

    /** @var Array \rOpenDev\PDOModel\Connector::$defaultOptions */
    public static $defaultOptions = [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        PDO::ATTR_EMULATE_PREPARES   => false,
        //PDO::ATTR_PERSISTENT      => true, // Need to benchmark
    ];

    /**
     * @param string $dsn
     * @param string $user
     * @param string $password
     * @param string $host
     * @param string $port
     * @param array $options
     *
     * @return self PDO instance
     */
    public function __construct($dsn = 'mysql', $user = null, $password = null, $host = 'localhost', $port = null, $options = [])
    {
        $this->setConfig($dsn, $user, $password, $host, $port, $options);

        $dsn = $this->getDsn();

        parent::__construct($dsn, $this->user, $this->password, $this->options);

        /**
        foreach ($this as $key => &$value) {
            if (in_array($key, ['dsn', 'user', 'password', 'host', 'port', 'options')) {
                $value = null;
            }
        }
        /**/
    }

    private function setConfig($dsn, $user, $password, $host, $port, $options)
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->host = $host;
        $this->port = $port;
        $this->options = self::$defaultOptions + $options;
    }

    private function getDsn()
    {
        switch ($this->dsn) {
            case 'sqlite' : return $this->host;
            case 'mysql'  : return 'mysql:host='.$this->host.($this->port !== null ? ';port='.$this->port : '');
        }
    }

}
