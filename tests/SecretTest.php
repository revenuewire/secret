<?php
class SecretTest extends \PHPUnit\Framework\TestCase
{
    public static $region = "us-west-1";
    public static $table = "secrets";
    public static $alias = "sandbox-secrets"; //kms alias


    public static function setUpBeforeClass()
    {
        $schema = array(
            "TableName" => "secrets",
            "AttributeDefinitions" => [
                [
                    'AttributeName' => 'id',
                    'AttributeType' => 'S',
                ]
            ],
            'KeySchema' => [
                [
                    'AttributeName' => 'id',
                    'KeyType' => 'HASH',
                ]
            ],
            'ProvisionedThroughput' => [
                'ReadCapacityUnits' => 5,
                'WriteCapacityUnits' => 5,
            ]
        );
        \RW\Secret::initDynamo(self::$region, "http://dynamodb:8000");
        try {
            \RW\Secret::$dynamoClient->createTable($schema);
        } catch (Exception $e) {}

        $options = ['cluster' => 'redis'];
        $redis = new \Predis\Client(array(
            'scheme'   => 'tcp',
            'host'     => "redis",
            'timeout' => 5
        ), $options);
        \RW\Secret::initCache($redis);
    }

    public static function tearDownAfterClass()
    {
        \RW\Secret::$dynamoClient->deleteItem([
            'TableName' => self::$table,
            'Key' => array(
                'id' => array('S' => "ci-test")
            ),
        ]);

        RW\Secret::$dynamoClient->deleteItem([
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
     * @depends testGet
     */
    public function testGetCache()
    {
        $secret = \RW\Secret::get('ci-test', self::$region, self::$table);
        $this->assertSame("top-secret-008", $secret); //cache hit
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
     * @expectedExceptionMessage Secret must include at least one letter!
     */
    public function testAtLeastOneLetter()
    {
        \RW\Secret::put("ci-test-3", self::$region, self::$table, self::$alias, "123456789", true);
    }

}