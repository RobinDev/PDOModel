<?php
namespace rOpenDev\PDOModel\Test;

use rOpenDev\PDOModel\Connector;

class PDOModelTest extends \PHPUnit_Framework_TestCase
{

    public function __construct()
    {
        $pdoLink = new Connector('mysql', 'root', 'admin');

        pdoUser::setLink($pdoLink);

        $this->pdoUser = pdoUser::instance('aaaUsers', true);
        $this->pdoUser->createTables();


        $this->testUser = ['username' => 'Robin', 'password' => sha1('admin(NORMAL)'), 'email' => 'contact@robin-d.fr', 'registration' => date('Y-m-d H:i:s')];
    }
    /**
     * Test that true does in fact equal true
     */
    public function testInsertEdit()
    {
        $this->pdoUser->edit('index', $this->testUser);

        $s = $this->pdoUser->prepare('SELECT username FROM users_index WHERE email = ?');
        $s->execute([$this->testUser['email']]);
        $this->assertTrue($s->fetchColumn() == $this->testUser['username']);

        $userToEdit = $this->pdoUser->query('SELECT * FROM users_index WHERE email = '.$this->pdoUser->quote($this->testUser['email']))->fetch();
        $userToEdit['username'] = 'New Robin';
        $this->pdoUser->edit('index', $userToEdit);

        $s = $this->pdoUser->prepare('SELECT username FROM users_index WHERE email = ?');
        $s->execute([$this->testUser['email']]);
        $username = $s->fetchColumn();
        $this->assertTrue($username != $this->testUser['username']);
        $this->assertTrue($username == $userToEdit['username']);
    }

    /**
     * Test with Entity
     */
    public function testWithEntity()
    {
        $this->pdoUser->setEntity('\rOpenDev\PDOModel\Test\UserEntity');

        $user = $this->pdoUser->query('SELECT * FROM users_index WHERE email = '.$this->pdoUser->quote($this->testUser['email']))->fetch();
        $this->assertTrue($user->id == 1);
    }

    public function destruct()
    {
        $this->pdoUser->dropDataBase();
    }
}
