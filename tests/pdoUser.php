<?php
namespace rOpenDev\PDOModel\Test;

use rOpenDev\PDOModel\PDOModel;

class pdoUser extends PDOModel
{

    protected $prefix = 'users_';
    public $primaryKey = array('index' => 'id', 'token' => 'token', 'rights' => 'id');
    public $table = [
                'index' => array(
                    'id'            => array('type' => 'int',   'auto_increment', 'primary key'),
                    'activated'        => array('type' => 'boolean', 'default' => 1),
                    'username'        => array('type' => 'varchar', 'lenght' => 250, 'unique'),
                    'password'        => array('type' => 'char', 'lenght' => 40),
                    'email'            => array('type' => 'varchar', 'lenght' => 255, 'unique'),
                    'grade'            => array('type' => 'tinyint', 'default' => 2),
                    'registration'    => array('type' => 'datetime'),
                ),
                'token' => array(
                    'token'        => array('type' => 'varchar', 'lenght' => 40, 'not null', 'primary key'),
                    'lifetime'    => array('type' => 'datetime'),
                    'id_index'    => array('type' => 'int', 'not null'),
                    '#engine' => 'MEMORY',
                ),
                'rights' => [
                    'id'     => ['type' => 'int', 'not null', 'primary key'],
                    'rights' => ['type' => 'text', 'not null'],
                    '#fk'    => ['users_index' => [['id'], ['id'], 'on delete cascade']],
                ],
    ];
}
