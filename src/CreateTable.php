<?php
namespace rOpenDev\PDOModel;

use PDO;

trait CreateTable
{
    /** @var array Contain params relative to the motor **/
    protected $colParams = [
        'mysql' => [
            'NULL',
            'NOT NULL',
            'AUTO_INCREMENT',
            'PRIMARY KEY',
            'KEY',
            'UNIQUE',
            // references
            // default
        ],
        'sqlite' => [
            'NULL',
            'NOT NULL',
            'UNIQUE',
            'PRIMARY KEY',
            // check
            // default
        ],
    ];

    /**
     * Check if the database exist else create it.
     *
     */
    public function createDatabase()
    {
        $dbname = $this->dbname;

        switch (self::$pdoLink->getAttribute(PDO::ATTR_DRIVER_NAME)) {

            case 'sqlite' :
                if (empty(self::$pdoLink->query('SELECT name FROM sqlite_master')->fetchAll())) {
                    return $this->createTables();
                }
                break;

            case 'mysql' :
                if (self::$pdoLink->query('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME =  '.$this->quote($dbname).';')->fetch(PDO::FETCH_NUM) === false) {
                    self::$pdoLink->exec('CREATE DATABASE  `'.$dbname.'`; USE  `'.$dbname.'`');

                    return $this->createTables();
                }
                break;
        }

        if ($this->alwaysCreateTable) {
            $this->createTables();
        }
    }

    protected static function normalizeType($type)
    {
        switch ($type) {
            case 'slugify' :    return 'varchar';
            case 'bool'    :    return 'bit';
            default : return $type;
        }
    }

    /**
     * Create tables defined in self::$table
     *
     * @throws \Exception If an error occured during the query
     */
    public function createTables()
    {
        $tables = $this->table;
        if (!empty($tables)) {
            foreach ($tables as $name => $columns) {
                $this->createTable($this->prefix.$name, $columns);
            }
        }
    }

    /**
     * Create table
     *
     * @param string $name
     * @param array  $columns
     *
     * @throws \Exception If an error occured during the query
     */
    public function createTable($name, $columns)
    {
        $cTable = 'CREATE TABLE IF NOT EXISTS `'.$name.'` ('.CHR(10);
        foreach ($columns as $k => $v) {
            if ($k[0] == '#') {
                if ($k == '#INDEX#') {
                    $cTable .= 'INDEX('.$v.')';
                } elseif (strpos($k, '#k') === 0) {
                    $cTable .= 'KEY '.$v; // KEY keyName (column_name [,column_name...]),
                } elseif (substr($k, 0, 3) == '#fk') {
                    foreach ($v as $kk => $vv) {
                        $cTable .= self::createForeignKey($kk, $vv);
                    }
                } elseif ($k == '#pk') {
                    $cTable .= self::createPrimaryKey($v);
                }
                $cTable .= ','.CHR(10);
            } elseif (isset($v['type'])) {
                $cTable .= '`'.$k.'` '.strtoupper(self::normalizeType($v['type'])).(isset($v['lenght']) ? '('.$v['lenght'].')' : '');
                $sV = $v;
                unset($v['type'], $v['lenght']);
                foreach ($v as $vk => $vv) {
                    if ($vk === 'default') {
                        $cTable .= ' DEFAULT '.$this->formatValue($vv, $sV);
                    } elseif ($vk === 'references') {
                        $cTable .= ' REFERENCES '.$vv;
                    } else {
                        $cTable .= ' '.strtoupper($vv);
                    }
                }
                $cTable .= ','.CHR(10);
            }
        }
        $cTable = trim($cTable, ','.CHR(10)).')'.(isset($table['#engine']) ? ' ENGINE='.$table['#engine'] : '').' DEFAULT CHARSET=utf8;';
        //dbv($cTable, false);
        $this->exec($cTable);
        $this->manageError($cTable);
    }

    /**
     * Foreign key part generator for query constructor
     *
     * @param string $refTable
     * @param array  $params
     *
     * @return string
     */
    public static function createForeignKey($refTable, $params)
    {
        return 'FOREIGN KEY ('.implode(',', $params[0]).') REFERENCES '.$refTable.'('.implode(',', $params[1]).') '.strtoupper($params[2]);
    }

    /**
     * Primary key part generator
     *
     * @param array $columns wich are primary keys
     *
     * @return string
     */
    public static function createPrimaryKey($columns)
    {
        return 'PRIMARY KEY ('.implode(',', $columns).')';
    }
}
