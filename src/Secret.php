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
            throw new \Exception("Key does not exists.");
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

}