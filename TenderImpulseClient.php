<?php
/**
 * Tender Impulse API client library for PHP.
 *
 * Provides functionality to:
 * - Retrieve tenders and contract awards
 * - Authenticate using Bearer tokens
 * - Decrypt API responses
 * - Validate response integrity using CRC/MD5 checksums
 * - Download and store tender documents
 * - Process API data into Array
 *
 */
class TenderImpulseClient
{
    private string $storePath;
    private string $accessToken;
    private string $key;

    public function __construct(string $storePath, string $accessToken, string $key) {
        $this->storePath = rtrim($storePath, '/') . '/';
        $this->accessToken = $accessToken;
        $this->key = $key;
    }

    /**
     * Calls Tender Impulse API and retrieves tender records.
     *
     * @param lastId Last tender ID already processed.
     * @return Array containing status and tender data.
     */
    public function getTenders(int $lastId): array {
        try {

            $url = "https://tenderimpulse.com/web-api/tender/v2/uat.php?lastid={$lastId}";

            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
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

            $responseTenders = [];

            foreach ($details['tenders'] as $tender) {

                $responseTender = [
                    'tender_id'         => $tender['tender_id'],
                    'title'             => $tender['title'],
                    'authority_name'    => $tender['authority_name'],
                    'address'           => $tender['address'],
                    'tel'               => $tender['tel'],
                    'fax'               => $tender['fax'],
                    'email'             => $tender['email'],
                    'web'               => $tender['web'],
                    'contact_name'      => $tender['contact_name'],
                    'contract_type'     => $tender['contract_type'],
                    'sectors'           => $tender['sectors'],
                    'cpv_codes'         => $tender['cpv_codes'],
                    'country'           => $tender['country'],
                    'original_source'   => $tender['original_source'],
                    'location'          => $tender['location'],
                    'reference'         => $tender['reference'],
                    'contract_duration' => $tender['contract_duration'],
                    'value_of_contract' => $tender['value_of_contract'],
                    'deadline'          => $tender['deadline'],
                    'other_information' => $tender['other_information'],
                    'filename'          => $this->storePath . $tender['filename']
                ];

                $this->downloadFile(
                    $tender['filepath'],
                    $tender['filename']
                );

                $responseTender['filepath'] =
                    $this->storePath . $tender['filename'];

                $responseTenders[] = $responseTender;
            }

            return [
                'status'        => 'success',
                'tenders'       => $responseTenders,
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