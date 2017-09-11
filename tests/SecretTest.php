<?php
class SecretTest extends \PHPUnit\Framework\TestCase
{
    public static $region = "us-west-1";
    public static $table = "secrets-test";
    public static $alias = "rw-secret"; //kms alias

    public static function tearDownAfterClass()
    {
        $dbClient = new \Aws\DynamoDb\DynamoDbClient([
            "region" => self::$region,
            "version" => "2012-08-10"
        ]);

        $dbClient->deleteItem([
            'TableName' => self::$table,
            'Key' => array(
                'id' => array('S' => "ci-test")
            ),
        ]);

        $dbClient->deleteItem([
           'TableName' => self::$table,
           'Key' => array(
               'id' => array('S' => "ci-test-2")
           ),
        ]);
   }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Key is required.
     */
   public function testEmptyKey()
   {
       \RW\Secret::put("", self::$region, self::$table, self::$alias, "top-secret-007");
   }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Key does not exists.
     */
    public function testKeyNotExists()
    {
        \RW\Secret::get("non-exists-key", self::$region, self::$table);
    }

    /**
     * @return string
     */
    public function testPut()
    {
        $result = \RW\Secret::put("ci-test", self::$region, self::$table, self::$alias, "top-secret-007");
        $this->assertSame(true, $result);
    }

    /**
     * @return string
     * @depends testPut
     */
    public function testPutOverride()
    {
        $result = \RW\Secret::put("ci-test", self::$region, self::$table, self::$alias, "top-secret-008", true);
        $this->assertSame(true, $result);
    }

    /**
     * @depends testPut
     */
    public function testGet()
    {
        $secret = \RW\Secret::get('ci-test', self::$region, self::$table);
        $this->assertSame("top-secret-008", $secret);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Key already exists.
     * @depends testPut
     */
    public function testPutFail()
    {
        \RW\Secret::put("ci-test", self::$region, self::$table, "rw-secret");
    }

    /**
     * @return string
     */
    public function testPut2()
    {
        $result = \RW\Secret::put("ci-test-2", self::$region, self::$table, self::$alias, null, true);
        $this->assertSame(true, $result);
    }

    /**
     * @depends testPut2
     */
    public function testGet2()
    {
        $secret = \RW\Secret::get("ci-test-2", self::$region, self::$table);
        $this->assertNotEmpty($secret);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Secret too short!
     */
    public function testPasswordTooShort()
    {
        \RW\Secret::put("ci-test-3", self::$region, self::$table, self::$alias, "top-007", true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Secret must include at least one number!
     */
    public function testAtLeastOneNumber()
    {
        \RW\Secret::put("ci-test-3", self::$region, self::$table, self::$alias, "top-secret", true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Secret must include at least one letter!
     */
    public function testAtLeastOneLetter()
    {
        \RW\Secret::put("ci-test-3", self::$region, self::$table, self::$alias, "123456789", true);
    }

}