<?php
/**
 * Tender Impulse contract award API client library for PHP.
 *
 * Provides functionality to:
 * - Retrieve contract awards
 * - Authenticate using Bearer tokens
 * - Decrypt API responses
 * - Validate response integrity using CRC/MD5 checksums
 * - Download and store contract award documents
 * - Process API data into Array
 *
 */
class TenderImpulseContractAwardClient
{
    private string $storePath;
    private string $accessToken;
    private string $key;

    public function __construct(string $storePath, string $accessToken, string $key) {
        $this->storePath = rtrim($storePath, '/\\') . DIRECTORY_SEPARATOR;
        $this->accessToken = $accessToken;
        $this->key = $key;
    }

    /**
     * Calls Tender Impulse API and retrieves contract award records.
     *
     * @param lastId Fetch id already processed. The API returns the records
     *               that come after it, plus the fetchid to use next time.
     * @return Array containing status, contract award data and the last fetch id.
     */
    public function getContractAwards(int $lastId): array {
        try {

            $url = "https://tenderimpulse.com/web-api/contract-awards/v2/uat.php?lastid={$lastId}";

            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 90,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->accessToken,
                    'Content-Type: application/json'
                ]
            ]);

            $apiResponse = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception('Curl Error: ' . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception(
                    "Could not connect to tenderimpulse.com, error code: {$httpCode}"
                );
            }

            $json = json_decode($apiResponse, true);

            if (!$json) {
                throw new Exception("Invalid API response");
            }

            $decrypted = $this->decrypt($json['data']);

            $calculatedCrc = md5($decrypted);

            if (strcasecmp($calculatedCrc, $json['crc']) !== 0) {
                throw new Exception("Message transmission error");
            }

            $details = json_decode($decrypted, true);

            if (!$details) {
                throw new Exception("Invalid decrypted response");
            }

            if ($details['status'] !== 'success') {
                throw new Exception($details['msg']);
            }

            $responseContracts = [];

            foreach ($details['contracts'] as $contract) {

                // Contract awards do not always come with a document.
                if (!empty($contract['filename'])) {

                    $this->downloadFile(
                        $contract['filepath'],
                        $contract['filename']
                    );

                    $localFile = $this->storePath . $contract['filename'];

                } else {

                    $localFile = null;

                }

                $responseContracts[] = [
                    'ca_id'                 => $contract['ca_id'],
                    'organisation'          => $contract['organisation'],
                    'contracting_authority' => $contract['contracting_authority'],
                    'contract_notice_no'    => $contract['contract_notice_no'],
                    'contract_awarded_to'   => $contract['contract_awarded_to'],
                    'description'           => $contract['description'],
                    'value_of_contract'     => $contract['value_of_contract'],
                    'sectors'               => $contract['sectors'],
                    'cpv_codes'             => $contract['cpv_codes'],
                    'country'               => $contract['country'],
                    'publish_date'          => $contract['publish_date'],
                    'filename'              => $localFile,
                    'filepath'              => $localFile
                ];
            }

            return [
                'status'        => 'success',
                'contracts'     => $responseContracts,
                'last_fetch_id' => $details['fetchid']
            ];

        } catch (Exception $e) {

            return [
                'status' => 'error',
                'msg'    => $e->getMessage()
            ];
        }
    }

    private function downloadFile(string $url, string $fileName): void {
        $localPath = $this->storePath . $fileName;

        $directory = dirname($localPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $fileContents = file_get_contents($url);

        if ($fileContents === false) {
            throw new Exception(
                "Unable to download file: {$fileName}"
            );
        }

        file_put_contents($localPath, $fileContents);
    }

    private function decrypt(string $data): string {
        $parts = explode(':', $data);

        if (count($parts) !== 2) {
            throw new Exception("Invalid encrypted payload");
        }

        $encryptedData = base64_decode($parts[0]);
        $iv = base64_decode($parts[1]);

        $decrypted = openssl_decrypt(
            $encryptedData,
            'AES-128-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new Exception("Unable to decrypt");
        }

        return $decrypted;

    }
}
?>
