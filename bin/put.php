<?php
require_once __DIR__ . "./../vendor/autoload.php";
date_default_timezone_set( 'UTC' );

function read_stdin()
{
    $fr=fopen("php://stdin","r");   // open our file pointer to read from stdin
    $input = fgets($fr,128);        // read a maximum of 128 characters
    $input = rtrim($input);         // trim any trailing spaces.
    fclose ($fr);                   // close the file handle
    return $input;                  // return the text entered
}


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
if ($override === false) {
    $kmsResult = $kmsClient->generateDataKey([
        "KeyId" => "alias/$alias",
        "KeySpec" => "AES_256"
    ]);

    $encodedSecret = base64_encode($kmsResult->get("CiphertextBlob"));
} else {
    echo "Please input the secret ";
    $secret = read_stdin();
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

if (empty($itemResult->get("Item"))) {
    $dynamoDBClient->putItem(array(
        'TableName' => $dynamoTable,
        'Item' => $marshaller->marshalItem(["id" => $key, "secret" => $encodedSecret]),
        'ConditionExpression' => 'attribute_not_exists(id)',
        'ReturnValues' => 'ALL_OLD'
    ));
} else {
    $updateAttributes = [
        'TableName' => $dynamoTable,
        'Key' => array(
            'id' => $marshaller->marshalValue($key)
        ),
        'ExpressionAttributeNames' => ["#secret" => "secret"],
        'ExpressionAttributeValues' =>  [":secret" => $marshaller->marshalValue($encodedSecret)],
        'ConditionExpression' => 'attribute_exists(id)',
        'UpdateExpression' => "set #secret = :secret",
        'ReturnValues' => 'ALL_NEW'
    ];

    $dynamoDBClient->updateItem($updateAttributes);
}
echo "ok\n";
