<?php
require_once 'TenderImpulseContractAwardClient.php';

/**
 * Local folder where contract award documents will be stored.
 */
$storePath = __DIR__ . '/contract-award-documents';

/**
 * Access token provided by Tender Impulse.
 */
$accessToken = 'your_access_token';

/**
 * AES decryption key provided by Tender Impulse.
 */
$key = 'your_encryption_key';

/**
 * File where the last fetch id is stored between runs.
 */
$stateFile = __DIR__ . '/contract-award-state.json';

/**
 * Fetch id to start from the very first time this example is run.
 */
$initialLastId = 261374;

/**
 * Reads the stored fetch id, or falls back to the initial one.
 */
function readLastId(string $stateFile, int $initialLastId): int {

    if (!file_exists($stateFile)) {
        return $initialLastId;
    }

    $state = json_decode(file_get_contents($stateFile), true);

    return (int) $state['fetchid'];
}

/**
 * Stores the fetch id so the next call resumes from here.
 */
function writeLastId(string $stateFile, int $fetchId): void {

    file_put_contents(
        $stateFile,
        json_encode(['fetchid' => $fetchId], JSON_PRETTY_PRINT)
    );
}

$client = new TenderImpulseContractAwardClient($storePath, $accessToken, $key);

$lastId = readLastId($stateFile, $initialLastId);

echo "Last Id: " . $lastId . PHP_EOL;

$result = $client->getContractAwards($lastId);

if ($result['status'] === 'success') {

    echo "Contract Awards Fetched: " . count($result['contracts']) . PHP_EOL;

    echo "Last Fetch Id: " . $result['last_fetch_id'] . PHP_EOL;

    print_r($result['contracts']);

    // Store the fetch id only after the batch has been handled,
    // so nothing is skipped if the run fails midway.
    writeLastId($stateFile, $result['last_fetch_id']);

} else {

    echo "Error: " . $result['msg'] . PHP_EOL;

}
?>
