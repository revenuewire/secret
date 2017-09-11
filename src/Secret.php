<?php
namespace RW;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\Kms\KmsClient;

class Secret
{
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
        $dynamoDBClient = new DynamoDbClient([
            "region" => $region,
            "version" => "2012-08-10"
        ]);

        /** @var $itemResult \Aws\Result */
        $itemResult = $dynamoDBClient->getItem([
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

        return $kmsResult->get('Plaintext');
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
        $key = preg_replace('~[^\\pL\d]+~u', '-', trim($key));

        if (empty($key)) {
            throw new \InvalidArgumentException("Key is required.");
        }

        $dynamoDBClient = new DynamoDbClient([
            "region" => $region,
            "version" => "2012-08-10"
        ]);

        /** @var $itemResult \Aws\Result */
        $itemResult = $dynamoDBClient->getItem([
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

            if (!preg_match("#[0-9]+#", $secret)) {
                throw new \InvalidArgumentException("Secret must include at least one number!");
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
            $dynamoDBClient->putItem(array(
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

            $dynamoDBClient->updateItem($updateAttributes);
        }

        return true;
    }

}