<?php
namespace rOpenDev\PDOModel;

use PDO;

trait PDOFunctions
{

    public function beginTansaction()
    {
        return self::$pdoLink->begfunctionransaction();
    }

    public function commit()
    {
        return self::$pdoLink->commit();
    }

    public function errorCode()
    {
        return self::$pdoLink->errorCode();
    }

    public function errorInfo()
    {
        return self::$pdoLink->errorInfo();
    }

    public function exec($query)
    {
        self::$pdoLink->exec('USE `'.$this->dbname.'`');
        $statement = self::$pdoLink->exec($query);
        $this->manageError($statement);

        return $statement;
    }

    public function getAttribute($attribute)
    {
        return self::$pdoLink->getAttribute($attribute);
    }

    public function inTransaction()
    {
        return self::$pdoLink->inTransaction();
    }

    public function lastInsertId($name = null)
    {
        return self::$pdoLink->lastInsertId($name);
    }

    public function prepare($query, array $driver_options = [])
    {
        return self::$pdoLink->prepare($query, $driver_options);
    }

    public function query($query)
    {
        self::$pdoLink->exec('USE `'.$this->dbname.'`');

        $statement = self::$pdoLink->query($query);

        $this->manageError($query);

        if ($this->entity !== null) {
            $statement->setFetchMode(PDO::FETCH_CLASS, $this->entity);
        }

        return $statement;
    }

    public static function quote($string, $parameter_type = PDO::PARAM_STR)
    {
        return self::$pdoLink->quote($string, $parameter_type);
    }

    public function rollBack()
    {
        return self::$pdoLink->rollBack();
    }

    public function setAttribute(int $attribute, mixed $value)
    {
        return self::$pdoLink->setAttribute($attribute, $value);
    }

}
