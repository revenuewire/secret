<?php
namespace RW;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\Kms\KmsClient;
use Predis\Client;

class Secret
{
    const CACHE_TTL = 600;

    /** @var $cache Client */
    public static $cache = null;

    /** @var $dynamoClient DynamoDbClient */
    public static $dynamoClient = null;

    /**
     * Init Cache instance
     * @param $cache Client
     * @codeCoverageIgnore
     */
    public static function initCache($cache)
    {
        if ($cache instanceof Client) {
            self::$cache = $cache;
        }
    }

    /**
     * Init Dynamo
     *
     * @param $region
     * @param null $endpoint
     * @codeCoverageIgnore
     */
    public static function initDynamo($region, $endpoint = null)
    {
        $config = [
            "region" => $region,
            "version" => "2012-08-10"
        ];

        if (!empty($endpoint)) {
            $config['endpoint'] = $endpoint;
        }

        self::$dynamoClient = new DynamoDbClient($config);
    }

    /**
     * Get the secret
     *
     * @param $key
     * @param string $region
     * @param string $table
     *
     * @return mixed|null
     * @throws \Exception
     */
    public static function get($key, $region = "us-west-2", $table = "secrets")
    {
        $cacheKey = "secret::" . implode('-', [$key, $region, $table]);

        if (self::$cache !== null) {
            if (self::$cache->exists($cacheKey)) {
                $secret = self::$cache->get($cacheKey);
                if (!empty($secret)) {
                    return $secret;
                }
            }
        }

        if (!self::$dynamoClient instanceof DynamoDbClient) {
            self::initDynamo($region);
        }

        /** @var $itemResult \Aws\Result */
        $itemResult = self::$dynamoClient->getItem([
            'TableName' => $table,
            'Key' => array(
                'id' => array('S' => $key)
            ),
            'ConsistentRead' => true,
        ]);

        if (empty($itemResult->get("Item"))) {
            throw new \InvalidArgumentException("Key does not exists.");
        }

        $marshaller = new Marshaler();
        $dynamoResult = $marshaller->unmarshalItem($itemResult->get('Item'));
        $encodedSecret = $dynamoResult['secret'];

        $kmsConfig = [
            "region" => $region,
            "version" => "2014-11-01"
        ];
        $kmsClient = new KmsClient($kmsConfig);
        $kmsResult = $kmsClient->decrypt([
            'CiphertextBlob' => base64_decode($encodedSecret),
        ]);

        $secret = $kmsResult->get('Plaintext');
        if (self::$cache !== null) {
            self::$cache->set($cacheKey, $secret);
            self::$cache->expire($cacheKey, self::CACHE_TTL);
        }

        return $secret;
    }

    /**
     * Add a key
     *
     * @param $key
     * @param string $region
     * @param string $table
     * @param string $alias
     * @param null $secret
     * @param bool $override
     * @return bool
     */
    public static function put($key, $region = "us-west-2", $table = "secrets", $alias = "rw-secret", $secret = null, $override = false)
    {
        $key = preg_replace('~[^\\pL\d_]+~u', '-', trim($key));

        if (empty($key)) {
            throw new \InvalidArgumentException("Key is required.");
        }

        if (!self::$dynamoClient instanceof DynamoDbClient) {
            self::initDynamo($region);
        }

        /** @var $itemResult \Aws\Result */
        $itemResult = self::$dynamoClient->getItem([
            'TableName' => $table,
            'Key' => array(
                'id' => array('S' => $key)
            ),
            'ConsistentRead' => true,
        ]);

        if (!empty($itemResult->get("Item")) && $override === false) {
            throw new \InvalidArgumentException("Key already exists.");
        }

        $kmsConfig = [
            "region" => $region,
            "version" => "2014-11-01"
        ];
        $kmsClient = new KmsClient($kmsConfig);
        if ($secret === null) {
            $kmsResult = $kmsClient->generateDataKey([
                "KeyId" => "alias/$alias",
                "KeySpec" => "AES_256"
            ]);

            $encodedSecret = base64_encode($kmsResult->get("CiphertextBlob"));
        } else {
            if (strlen($secret) < 8) {
                throw new \InvalidArgumentException("Secret too short!");
            }

            if (!preg_match("#[a-zA-Z]+#", $secret)) {
                throw new \InvalidArgumentException("Secret must include at least one letter!");
            }

            //encrypt the secret
            $kmsResult = $kmsClient->encrypt([
                "KeyId" => "alias/$alias",
                "Plaintext" => $secret,
            ]);
            $encodedSecret = base64_encode($kmsResult->get("CiphertextBlob"));
        }

        $marshaller = new Marshaler();
        if (empty($itemResult->get("Item"))) {
            self::$dynamoClient->putItem(array(
                'TableName' => $table,
                'Item' => $marshaller->marshalItem(["id" => $key, "secret" => $encodedSecret]),
                'ConditionExpression' => 'attribute_not_exists(id)',
                'ReturnValues' => 'ALL_OLD'
            ));
        } else {
            $updateAttributes = [
                'TableName' => $table,
                'Key' => array(
                    'id' => $marshaller->marshalValue($key)
                ),
                'ExpressionAttributeNames' => ["#secret" => "secret"],
                'ExpressionAttributeValues' => [":secret" => $marshaller->marshalValue($encodedSecret)],
                'ConditionExpression' => 'attribute_exists(id)',
                'UpdateExpression' => "set #secret = :secret",
                'ReturnValues' => 'ALL_NEW'
            ];

            self::$dynamoClient->updateItem($updateAttributes);
        }

        return true;
    }

}