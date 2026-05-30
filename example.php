<?php
require_once 'TenderImpulseClient.php';

/**
 * Local folder where tender documents will be stored.
 */
$storePath = __DIR__ . '/documents';

/**
 * Access token provided by Tender Impulse.
 */
$accessToken = 'your_access_token';

/**
 * AES decryption key provided by Tender Impulse.
 */
$key = 'your_encryption_key';

$client = new TenderImpulseClient($storePath, $accessToken, $key);

$lastId = 6771840;

$result = $client->getTenders($lastId);

if ($result['status'] === 'success') {

    echo "Tenders Fetched: " . count($result['tenders']) . PHP_EOL;

    echo "Last Fetch Id: " . $result['last_fetch_id'] . PHP_EOL;

    print_r($result['tenders']);

} else {

    echo "Error: " . $result['msg'] . PHP_EOL;
    
}
?>