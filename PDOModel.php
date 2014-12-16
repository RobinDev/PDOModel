<?php
namespace rOpenDev\PDOModel;

use \PDO;

class PDOModel Extends PDO {

	/** @var string contain next requests **/
	public $rows = '';

	/** @var array ? **/
	public $tableInRows = [];

	/** @var array Config **/
	protected $config = [
		'dsn'=>'mysql',
		'user'=>userMysql,
		'password'=>passwordMysql,
		'host'=>'localhost',
		//'port'=>3307,
		'databasePath'=>'data/',
		'alwaysCreateTable'=> true,
	];

	/** @var object's array Contain opened connexion (pdolink) **/
	private static $instance;

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
		'sqlite'=> [
			'NULL',
			'NOT NULL',
			'UNIQUE',
			'PRIMARY KEY',
			// check
			// default
		]
	];

	/** @var bool **/
	public $_returnQuery = false;

	/** var string **/
	protected $prefix = '';

	/**
	 * Changer $checkIfDatabaseExist à false par défault !
	 *
	 * @param string $dbname
	 * @param bool   $checkIfDatabaseExist
	 *
	 * @return self (PDO Link corresponding to the called class)
	 */
	public static function instance($dbname, $checkIfDatabaseExist=false)
	{
		 $cls = get_called_class();
		 $intance_name = $dbname.$cls;
		if(!isset(self::$instance[$intance_name]))
			self::$instance[$intance_name] = new $cls($dbname, $checkIfDatabaseExist);
		return self::$instance[$intance_name];
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

	/**
	 * Constructor
	 *
	 * @param string $dbname
	 * @param bool   $checkIfDatabaseExist
	 */
	function __construct($dbname = 'default', $checkIfDatabaseExist=true)
	{
		// Connexion to Mysql host or Sqlite File
		switch($this->config['dsn']) {
			case 'sqlite' : $dsn = $this->config['databasePath'].$dbname; break;
			case 'mysql'  : $dsn = 'mysql:host='.$this->config['host'].(isset($this->config['port']) ? ';port='.$this->config['port'] : ''); break;
		}

		parent::__construct($dsn, $this->config['user'], $this->config['password'],
			array(
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_STRINGIFY_FETCHES => false,
				PDO::ATTR_EMULATE_PREPARES => false,
			)
		);

		$this->dbname = $dbname;

		if($checkIfDatabaseExist) {
			$this->createDatabase();
		} else {
			$this->exec('USE `'.$this->dbname.'`');
		}

	}

	/**
	 * Check if the database exist else create it.
	 *
	 */
	function createDatabase()
	{
		$dbname = $this->dbname;

		switch($this->config['dsn']) {

			case 'sqlite' :
				if(empty($this->query('SELECT name FROM sqlite_master')->fetchAll())) {
					return $this->createTable();
				}
				break;

			case 'mysql' :
				if($this->query('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME =  '.PDO::quote($dbname).';')->fetch(PDO::FETCH_NUM) === false) {
					$this->exec('CREATE DATABASE  `'.$dbname.'`; USE  `'.$dbname.'`');
					return $this->createTable();
				} else {
					$this->exec('USE `'.$dbname.'`');
				}
				break;
		}

		if($this->config['alwaysCreateTable']) {
			$this->createTable();
		}
	}

	static function normalizeType($type)
	{
		switch($type) {
			case 'slugify' : 	return 'varchar';
			case 'bool' : 		return 'bit';
			default :			return $type;
		}
	}

	/**
	 * Query constructor for create table (and exec)
	 */
	function createTable()
	{
		$table = $this->table;
		if(!empty($table)) {
			foreach($table as $t => $c) {
				$cTable = 'CREATE TABLE IF NOT EXISTS `'.$this->prefix.$t.'` ('.CHR(10);
				foreach($c as $k => $v) {

					if($k[0] == '#') {
						if($k == '#INDEX#') {
							$cTable .= 'INDEX('.$v.')';
						}
						elseif(strpos($k, '#k') === 0) {
							$cTable .= 'KEY '.$v; // KEY keyName (column_name [,column_name...]),
						}
						elseif(substr($k, 0, 3) == '#fk') {
							foreach($v as $kk => $vv) {
								$cTable .= self::createForeignKey($kk, $vv);
							}
						}
						elseif($k=='#pk') {
							$cTable .= self::createPrimaryKey($v);
						}
						$cTable .= ','.CHR(10);
					}
					elseif(isset($v['type'])) {
						$cTable .= '`'.$k.'` '.strtoupper(self::normalizeType($v['type'])).(isset($v['lenght']) ? '('.$v['lenght'].')' : '');
						$sV=$v; unset($v['type'], $v['lenght']);
						foreach($v as $vk => $vv) {
							if($vk === 'default') {
								$cTable .= ' DEFAULT '.$this->formatValue($vv, $sV);
							} elseif($vk === 'references') {
								$cTable .= ' REFERENCES '.$vv;
							}
							else {
								$cTable .= ' '.strtoupper($vv);
							}
						}
						$cTable .= ','.CHR(10);
					}

				}
				$cTable = trim($cTable, ','.CHR(10)).')'.(isset($table['#engine']) ? ' ENGINE='.$table['#engine'] : '').' DEFAULT CHARSET=utf8;';
				//dbv($cTable, false);
				$this->exec($cTable);
				if($this->errorInfo()[0] != '00000') {
					exit(str_replace(array(';'.chr(10), ';'), ';'.chr(10).$this->errorInfo()[0].' - '.$this->errorInfo()[2].chr(10), $cTable));
				}
			}
		}
	}

	/**
	 * Foreign key part generator for query constructor
	 *
	 * @param string $refTable
	 * @param array  $params
	 *
	 * @return string
	 */
	static function createForeignKey($refTable, $params)
	{
		return 'FOREIGN KEY ('.implode(',',$params[0]).') REFERENCES '.$refTable.'('.implode(',',$params[1]).') '.strtoupper($params[2]);
	}

	/**
	 * Primary key part generator
	 *
	 * @param array $columns wich are primary keys
	 *
	 * @return string
	 */
	static function createPrimaryKey($columns)
	{
		return 'PRIMARY KEY ('.implode(',',$columns).')';
	}

	/**
	 * Format value
	 *
	 * @param string $var
	 * @param array  $params column SQL
	 *
	 * @return string ready for insert/update
	 */
	function formatValue($var, $params)
	{
		if(isset($params['type'])) {
			if($var === '') {
				if(in_array('auto_increment', $params)) return 'null';
				if(isset($params['default'])) return $params['default'] === '' ? '""' : $this->formatValue($params['default'], $params);
			}
			elseif($var === null) {
				if(in_array('not null', $params)) return "''";
				if(isset($params['default'])) return $params['default'] === '' ? '""' : $this->formatValue($params['default'], $params);
				return 'null';
			}
			switch($params['type']) { //case 'timestamp' :
				case 'bool' : 		$var = $var ? 1 : 0; 										break;
				case 'datetime' : 	$var = '"'.date('Y-m-d H:i:s', strtotime($var)).'"'; 	break;
				case 'date' :		$var = '"'.date('Y-m-d', strtotime($var)).'"'; 			break;
				case 'integer' : case 'int' : case 'tinyint' : case 'smallint' : case 'bigint' :
					$var = (int) $var;
					break;
				case 'float' : case 'decimal' :  case 'double' : case 'real' : case 'double precision' :
					$var = floatval($var);
					break;
				case 'slugify' : 	$var = '"'.self::slugify($var).'"'; 					break;
				case 'binary' : 	$var = 'x\''.bin2hex($var).'\''; 						break;
				default :
					// Découpe la variable si elle est plus longue que prévue dans le schéma de la table
					if(isset($params['lenght']) && strlen($var)>$params['lenght']) {
						$var = substr($var, 0, $params['lenght']);
					}
					$var = PDO::QUOTE($var);
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
	function formatUpdateSet($keys, $data) {
		$str = '';
		foreach($keys as $k => $v) {
			// Garde les colonnes && les colonnes où il y a une donnée à mettre à jour
			if(strpos($k, '#') === false && isset($data[$k])) {
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
		foreach($keys as $k => $v) {
			if(strpos($k, '#') === false) {
				$str .= $this->formatValue( (isset($data[$k]) ? $data[$k] : null) , $v).',';
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
		foreach($arr as $a) {
			$str = $this->formatValue($a, $params).',';
		}
		return '('.rtrim(',').')';
	}

	/**
	 * Persit a row (insert or update if exist)
	 *
	 * @param string $table  La table dans laquelle les données seront persistées
	 * @param array  $data   Les données
	 *
	 * @return int Nombre de lignes modifiées
	 */
	public function edit($table, $data)
	{
		if(isset($this->primaryKey[$table])) {
			$pKey = $this->primaryKey[$table];
			if(
				(in_array('auto_increment', $this->table[$table][$pKey]) && isset($data[$pKey]))
				 || (!in_array('auto_increment', $this->table[$table][$pKey]) && $this->rowExist($table, $pKey, $data[$pKey]))
			  ) {
				return $this->update($table, $data, $pKey.' = '.PDO::QUOTE($data[$pKey]));
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
	function insert($table, $data, $replace = 0)
	{
		$query = ($replace===1?'REPLACE':'INSERT').($replace===2?' IGNORE':'').' INTO `'.$this->prefix.$table.'` VALUES('.$this->formatInsertValues($this->table[$table], $data).')';
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
	function update($table, $data, $where)
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
	function rowExist($table, $key, $value)
	{
		$res = $this->query('SELECT COUNT(*) FROM `'.$this->prefix.$table.'` WHERE '.$key.' =  '.PDO::QUOTE($value).' LIMIT 1');
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
	function massEdit($table, $data, $replace=0)
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
	function massInsert($table, $data, $replace)
	{
		$query = ($replace===1?'REPLACE':'INSERT').($replace===2?' IGNORE':'').' INTO `'.$this->prefix.$table.'` VALUES';

		foreach($data as $d) {
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
	function execMassEdit($lockDB=false, $disableSQLChecked=false)
	{
		if(empty($this->rows))
			return 0;

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

		if($this->errorInfo()[0] != '00000') {
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
	function select($table, $columns='*', $whereOrderLimit='')
	{
		$q = 'SELECT '.$columns.' FROM `'.$this->prefix.$table. '` ' .$whereOrderLimit;
		if($this->_returnQuery) {
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
	function countRow($table = 'index', $afterFrom)
	{
		return (int) $this->queryOne('SELECT COUNT(1) FROM `'.$this->prefix.$table. '` ' .$afterFrom);
	}

	/** Deprecated **/
	function queryOne($query) {
		$query = $this->query($query);
		if(is_object($query)) {
			$result = $query->fetch();
			if($result !== false && count($result) == 1) // Si par exemple query = 'SELECT tagada FROM pourquoi_pas' renvoie string (tagada)
				return array_shift($result);
			elseif($result !== false)
				return $result;
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
	function load($key,$value=null,$table)
	{
		return (array) $this->query('SELECT DISTINCT `'.$key.'`'.($value!==null ? ',`'.$value.'`':',1').' FROM `'.$this->prefix.$table. '`')->fetchAll(PDO::FETCH_KEY_PAIR);
	}

	/**
	 * Optimize table from selected database
	 * MySQL only
	 *
	 * @return array Containing results from the requests (key is the table name `database.tablename` and value is a string returned by MySQL
	 */
	function optimize() {
		$results = [];
		$dbs = $this->query('SELECT TABLE_SCHEMA, TABLE_NAME FROM information_schema.TABLES  WHERE TABLE_TYPE=\'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM);
		foreach($dbs as $t) {
			$r = $this->query('OPTIMIZE TABLE `'.$t[0].'`.`'.$t[1].'`')->fetch();
			$results[$t[0].'.'.$t[1]] = $r['Msg_text'];
		}
		return $results;
	}

	public static function slugify($str, $authorized = '[^a-z0-9/\.]')
	{
		$str = str_replace(array('\'', '"'),'',$str);
		if($str !== mb_convert_encoding(mb_convert_encoding($str,'UTF-32','UTF-8'),'UTF-8','UTF-32')) {
			$str = mb_convert_encoding($str,'UTF-8');
		}
		$str = htmlentities($str,ENT_NOQUOTES,'UTF-8');
		$str = preg_replace('`&([a-z]{1,2})(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig);`i','$1',$str);
		$str = preg_replace(array('`'.$authorized.'`i','`[-]+`'),'-',$str);
		return strtolower(trim($str,'-'));
	}

	public static function truncate($string, $max_length = 90, $replacement = '...', $trunc_at_space = false)
	{
		$max_length -= strlen($replacement);
		$string_length = strlen($string);
		if($string_length <= $max_length) {
			return $string;
		}
		if ($trunc_at_space && ($space_position = strrpos($string, ' ', $max_length-$string_length))) {
			$max_length = $space_position;
		}
		return substr_replace($string, $replacement, $max_length);
	}
}
