<?php
require_once __DIR__ . "./../vendor/autoload.php";
date_default_timezone_set( 'UTC' );

$options = getopt('', ['key::', 'override::', 'region::', 'alias::', 'dynamo::']);
$key = preg_replace('~[^\\pL\d]+~u', '-', trim($options['key']));

if (empty($key)) {
    throw new Exception("Key is required. php ./vendor/put.php --key=[your_key]");
}

$override = empty($options['override']) ? false : filter_var($options['override'], FILTER_VALIDATE_BOOLEAN);
$region = empty($options['region']) ? "us-west-2" : $options['region'];
$alias = empty($options['alias']) ? "rw-secret" : $options['alias'];
$dynamoTable = empty($options['dynamo']) ? "secrets" : $options['dynamo'];

$marshaller = new \Aws\DynamoDb\Marshaler();
$dynamoDBClient = new \Aws\DynamoDb\DynamoDbClient([
    "region" => $region,
    "version" => "2012-08-10"
]);

/** @var $itemResult \Aws\Result */
$itemResult = $dynamoDBClient->getItem([
    'TableName' => $dynamoTable,
    'Key' => array(
        'id' => array('S' => $key)
    ),
    'ConsistentRead' => true,
]);

if (!empty($itemResult->get("Item")) && $override === false) {
    throw new Exception("Key already exists. Override the secret by --override.");
}

$kmsConfig = [
    "region" => $region,
    "version" => "2014-11-01"
];
$kmsClient = new \Aws\Kms\KmsClient($kmsConfig);

//generate new secret automatically
if (empty($secret)) {
    $kmsResult = $kmsClient->generateDataKey([
        "KeyId" => "alias/$alias",
        "KeySpec" => "AES_256"
    ]);

    $secret = base64_encode($kmsResult->get("CiphertextBlob"));
} else {
    //encrypt the secret
}

if (empty($itemResult->get("Item"))) {
    $dynamoDBClient->putItem(array(
        'TableName' => $dynamoTable,
        'Item' => $marshaller->marshalItem(["id" => $key, "secret" => $secret]),
        'ConditionExpression' => 'attribute_not_exists(id)',
        'ReturnValues' => 'ALL_OLD'
    ));
} else {
    $updateAttributes = [
        'TableName' => $dynamoTable,
        'Key' => array(
            'id' => $marshaller->marshalValue($key)
        ),
        'ExpressionAttributeNames' => ["#secrect"],
        'ExpressionAttributeValues' =>  [":secret"],
        'ConditionExpression' => 'attribute_exists(id)',
        'UpdateExpression' => "set #secrect = :secret",
        'ReturnValues' => 'ALL_NEW'
    ];

    $dynamoDBClient->updateItem($updateAttributes);
}
echo "ok\n";
