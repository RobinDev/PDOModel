<?php
namespace rOpenDev\PDOModel;

use PDO;
use Exception;

class PDOModel //extends PDO
{
    use CreateTable, Instanciator, PDOFunctions;

    /** @var \PDO **/
    public static $pdoLink;

    /** @var string contain next requests **/
    public $rows = '';

    /** @var array ? **/
    public $tableInRows = [];

    /** @var array Config **/
    protected $alwaysCreateTable = false;

    /** @var bool **/
    public $_returnQuery = false;

    /** @var string Contain prefix for tables **/
    protected $prefix = '';

    /** @var array Will contain tables in child class **/
    protected $table;

    /** @var string **/
    protected $entity;

    /**
     * @param \PDO
     */
    public static function setLink($link)
    {
        self::$pdoLink = $link;
    }

    /**
     * Permits to fetch with a class
     *
     * @param string $entity class Name (with namespaces) to call when fetching
     *
     * @return self
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;

        return $this;
    }

    /**
     * Throw an exception printing the query
     *
     * @throws \Exception
     */
    protected function manageError($query)
    {
        $errorInfo = $this->errorInfo();

        if ($errorInfo[0] != '00000') {
            throw new Exception(str_replace(array(';'.chr(10), ';'), ';'.chr(10).$errorInfo[0].' - '.$errorInfo[2].chr(10), $query));
        }
    }

    /**
     * Format value
     *
     * @param string $var
     * @param array  $params column SQL
     *
     * @return string ready for insert/update
     */
    public function formatValue($var, $params)
    {
        if (isset($params['type'])) {
            if ($var === '') {
                if (in_array('auto_increment', $params)) {
                    return 'null';
                }
                if (isset($params['default'])) {
                    return $params['default'] === '' ? '""' : $this->formatValue($params['default'], $params);
                }
            } elseif ($var === null) {
                if (in_array('not null', $params)) {
                    return "''";
                }
                if (isset($params['default'])) {
                    return $params['default'] === '' ? '""' : $this->formatValue($params['default'], $params);
                }

                return 'null';
            }
            switch ($params['type']) { //case 'timestamp' :
                case 'bool' :        $var = $var ? 1 : 0;                                        break;
                case 'datetime' :    $var = '"'.date('Y-m-d H:i:s', strtotime($var)).'"';    break;
                case 'date' :        $var = '"'.date('Y-m-d', strtotime($var)).'"';            break;
                case 'integer' : case 'int' : case 'tinyint' : case 'smallint' : case 'bigint' :
                    $var = (int) $var;
                    break;
                case 'float' : case 'decimal' :  case 'double' : case 'real' : case 'double precision' :
                    $var = floatval($var);
                    break;
                case 'slugify' :    $var = '"'.Helper::slugify($var).'"';                    break;
                case 'binary' :    $var = 'x\''.bin2hex($var).'\'';                        break;
                default :
                    // Découpe la variable si elle est plus longue que prévue dans le schéma de la table
                    if (isset($params['lenght']) && strlen($var)>$params['lenght']) {
                        $var = substr($var, 0, $params['lenght']);
                    }
                    $var = $this->quote($var);
                break;
            }
        }

        return $var;
    }

    /**
     * Format update set
     *
     * @param array $keys
     * @param array $data
     *
     * @return string
     */
    public function formatUpdateSet($keys, $data)
    {
        $str = '';
        foreach ($keys as $k => $v) {
            // Garde les colonnes && les colonnes où il y a une donnée à mettre à jour
            if (strpos($k, '#') === false && isset($data[$k])) {
                $str .= '`'.$k.'` = '.$this->formatValue($data[$k], $v).',';
            }
        }

        return rtrim($str, ',');
    }

    /**
     * Format insert values
     *
     * @param array $keys
     * @param array $data
     *
     * @return string
     */
    public function formatInsertValues($keys, $data)
    {
        $str = '';
        foreach ($keys as $k => $v) {
            if (strpos($k, '#') === false) {
                $str .= $this->formatValue((isset($data[$k]) ? $data[$k] : null), $v).',';
            }
        }

        return rtrim($str, ',');
    }

    /**
     * Format in values
     *
     * @param array $arr
     * @param array $params
     *
     * @return string
     */
    public function formatIn($arr, $params)
    {
        foreach ($arr as $a) {
            $str = $this->formatValue($a, $params).',';
        }

        return '('.rtrim(',').')';
    }

    /**
     * Persit a row (insert or update if exist)
     *
     * @param string $table La table dans laquelle les données seront persistées
     * @param array  $data  Les données
     *
     * @return int Nombre de lignes modifiées
     */
    public function edit($table, $data)
    {
        if (isset($this->primaryKey[$table])) {
            $pKey = $this->primaryKey[$table];
            if (
                (in_array('auto_increment', $this->table[$table][$pKey]) && isset($data[$pKey]))
                 || (!in_array('auto_increment', $this->table[$table][$pKey]) && $this->rowExist($table, $pKey, $data[$pKey]))
              ) {
                return $this->update($table, $data, $pKey.' = '.$this->quote($data[$pKey]));
            }
        }

        return $this->insert($table, $data, 0);
    }

    /*
     * Insert a row
     *
     * @param string $table
     * @param array  $data
     * @param int    $replace 0: INSERT, 1: REPLACE, 2: INSERT IGNORE
     *
     * @return mixed
     */
    public function insert($table, $data, $replace = 0)
    {
        $query = ($replace === 1 ? 'REPLACE' : 'INSERT').($replace === 2 ? ' IGNORE' : '').' INTO `'.$this->prefix.$table.'` VALUES('.$this->formatInsertValues($this->table[$table], $data).')';
        //echo $query; echo chr(10);
        return $this->_returnQuery ? $query : $this->exec($query);
    }

    /**
     * Update a row
     *
     * @param string $table
     * @param array  $data
     * @param string $where
     *
     * @return mixed
     */
    public function update($table, $data, $where)
    {
        $query = 'UPDATE `'.$this->prefix.$table.'` SET '.$this->formatUpdateSet($this->table[$table], $data).' WHERE '.$where;

        return $this->_returnQuery ? $query : $this->exec($query);
    }

    /**
     * Test if a row exist
     *
     * @param string $table
     * @param string $key
     * @param string $value Will be wuoted
     *
     * @return bool
     */
    public function rowExist($table, $key, $value)
    {
        $res = $this->query('SELECT COUNT(*) FROM `'.$this->prefix.$table.'` WHERE '.$key.' =  '.$this->quote($value).' LIMIT 1');

        return $res && $res->fetchColumn() > 0 ? true : false;
    }

    /**
     * Prépare dans la variable $this->rows une série de requête
     *
     * @param string $table
     * @param array  $data
     * @param int    $replace 0: INSERT, 1: REPLACE, 2: INSERT IGNORE
     *
     * @return self
     */
    public function massEdit($table, $data, $replace = 0)
    {
        $this->_returnQuery = true;
        $this->rows .= $replace>0 ? $this->insert($table, $data, $replace).';' : $this->edit($table, $data).';';
        $this->tableInRows[$table] = 1;
        $this->_returnQuery = false;

        return $this;
    }

    /**
     * Prépare dans la valriable $this->rows, une requête insérant plusieurs lignes
     *
     * @param string $table
     * @param array  $data    !Attention, doit contenir plusieurs ligne sinon utiliser self::massEdit()
     *                        Nécessite tout les champs de la table pour chaque ligne (si plus, ils seront enlevés... si moins = erreur lors de l'execution)
     * @param int    $replace 0: INSERT, 1: REPLACE, 2: INSERT IGNORE
     *
     * @return self
     */
    public function massInsert($table, $data, $replace)
    {
        $query = ($replace === 1 ? 'REPLACE' : 'INSERT').($replace === 2 ? ' IGNORE' : '').' INTO `'.$this->prefix.$table.'` VALUES';

        foreach ($data as $d) {
            $query .= '('.$this->formatInsertValues($this->table[$table], $d).'),';
        }

        $this->tableInRows[$table] = 1;
        $this->rows .= trim($query, ',').';';

        return $this;
    }

    /**
     * Exec requests contained in self::$rows
     *
     * @param mixed $lockDB            FALSE if there isn't need lock else STRING `table WRITE[, table2 READ[,...]]`
     * @param bool  $disableSQLChecked Disable foreign key and unique MySQL Checks
     *
     * @throws Exception if there is an error during the request
     *
     * @return int Number of modified rows
     */
    public function execMassEdit($lockDB = false, $disableSQLChecked = false)
    {
        if (empty($this->rows)) {
            return 0;
        }

        if ($disableSQLChecked) {
            $this->exec('SET @@session.unique_checks = 0; SET @@session.foreign_key_checks = 0;');
        }

        if ($lockDB !== false) {
            $this->exec('LOCK TABLES '.$lockDB.';');
        }

        $s = $this->exec('BEGIN;'.$this->rows.'COMMIT;');

        if ($lockDB !== false) {
            $this->exec('UNLOCK TABLES;');
        }

        if ($disableSQLChecked) {
            $this->exec('SET @@session.unique_checks = 1; SET @@session.foreign_key_checks = 1;');
        }

        if ($this->errorInfo()[0] != '00000') {
            throw new Exception(get_called_class().'::execMassEdit() [ERROR] '.$this->errorInfo()[0]);
        }

        $this->rows = '';

        return $s; //return $s->rowCount();
    }

    /**
     * Select
     *
     * @param string $table
     * @param string $columns
     * @param string $whereOrderLimit & co
     *
     * @return array
     */
    public function select($table, $columns = '*', $whereOrderLimit = '')
    {
        $q = 'SELECT '.$columns.' FROM `'.$this->prefix.$table.'` '.$whereOrderLimit;
        if ($this->_returnQuery) {
            return $q;
        } else {
            $r = $this->query($q);

            return $r ? $r->fetchAll() : [];
        }
    }

    /**
     * Count the number of rows in a table
     *
     * @param string $table
     * @param string $afterFrom (where, group by, have...)
     *
     * @return int
     */
    public function countRow($table = 'index', $afterFrom)
    {
        return (int) $this->queryOne('SELECT COUNT(1) FROM `'.$this->prefix.$table.'` '.$afterFrom);
    }

    /** Deprecated **/
    public function queryOne($query)
    {
        $query = $this->query($query);
        if (is_object($query)) {
            $result = $query->fetch();
            if ($result !== false && count($result) == 1) {
                // Si par exemple query = 'SELECT tagada FROM pourquoi_pas' renvoie string (tagada)
                return array_shift($result);
            } elseif ($result !== false) {
                return $result;
            }
        }
    }

    /**
     * Charge une association clef/valeur pour une table donnée
     *
     * @param string $key
     * @param string $value If value is not set, `1` will be set to replace it
     * @param string $table
     *
     * @return array
     */
    public function load($key, $value = null, $table)
    {
        return (array) $this->query('SELECT DISTINCT `'.$key.'`'.($value !== null ? ',`'.$value.'`' : ',1').' FROM `'.$this->prefix.$table.'`')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Optimize table from selected database
     * MySQL only
     *
     * @return array Containing results from the requests (key is the table name `database.tablename` and value is a string returned by MySQL
     */
    public function optimize()
    {
        $results = [];
        $dbs = $this->query('SELECT TABLE_SCHEMA, TABLE_NAME FROM information_schema.TABLES  WHERE TABLE_TYPE=\'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM);
        foreach ($dbs as $t) {
            $r = $this->query('OPTIMIZE TABLE `'.$t[0].'`.`'.$t[1].'`')->fetch();
            $results[$t[0].'.'.$t[1]] = $r['Msg_text'];
        }

        return $results;
    }

    /**
     * Delete current database
     * @return int
     */
    public function dropDataBase()
    {
        return self::$pdoLink->exec('DROP DATABASE '.$this->dbname);
    }

    /**
     * Delete table $name
     *
     * @param string $name
     *
     * @return int
     */
    public function dropTable($name)
    {
        return $this->exec('DROP TABLE '.$name);
    }

}
