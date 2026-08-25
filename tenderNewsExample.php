<?php
require_once 'TenderImpulseTenderNewsClient.php';

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
$stateFile = __DIR__ . '/tender-news-state.json';

/**
 * Fetch id to start from the very first time this example is run.
 */
$initialLastId = 18016;

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

$client = new TenderImpulseTenderNewsClient($accessToken, $key);

$lastId = readLastId($stateFile, $initialLastId);

echo "Last Id: " . $lastId . PHP_EOL;

$result = $client->getTenderNews($lastId);

if ($result['status'] === 'success') {

    echo "Tender News Fetched: " . count($result['tender_news']) . PHP_EOL;

    echo "Last Fetch Id: " . $result['last_fetch_id'] . PHP_EOL;

    // Only the headline fields are printed here. Every article also
    // carries longdescription, which holds the full article HTML.
    foreach ($result['tender_news'] as $article) {

        print_r([
            'blogid'      => $article['blogid'],
            'blogtitle'   => $article['blogtitle'],
            'publishdate' => $article['publishdate'],
            'countries'   => $article['countries'],
            'sectors'     => $article['sectors']
        ]);
    }

    // Store the fetch id only after the batch has been handled,
    // so nothing is skipped if the run fails midway.
    writeLastId($stateFile, $result['last_fetch_id']);

} else {

    echo "Error: " . $result['msg'] . PHP_EOL;

}
?>
