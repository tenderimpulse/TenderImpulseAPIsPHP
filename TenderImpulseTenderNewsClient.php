<?php
/**
 * Tender Impulse tender news API client library for PHP.
 *
 * Provides functionality to:
 * - Retrieve tender news articles
 * - Authenticate using Bearer tokens
 * - Decrypt API responses
 * - Validate response integrity using CRC/MD5 checksums
 * - Process API data into Array
 *
 * Tender news articles carry no attachments, so this client downloads
 * nothing and needs no store path.
 */
class TenderImpulseTenderNewsClient
{
    private string $accessToken;
    private string $key;

    public function __construct(string $accessToken, string $key) {
        $this->accessToken = $accessToken;
        $this->key = $key;
    }

    /**
     * Calls Tender Impulse API and retrieves tender news records.
     *
     * @param lastId Fetch id already processed. The API returns the records
     *               that come after it, plus the fetchid to use next time.
     * @return Array containing status, tender news data and the last fetch id.
     */
    public function getTenderNews(int $lastId): array {
        try {

            $url = "https://tenderimpulse.com/web-api/news/v2/uat.php?lastid={$lastId}";

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

            $responseTenderNews = [];

            // The API payload names the records "news".
            foreach ($details['news'] as $article) {

                $responseTenderNews[] = [
                    'blogid'           => $article['blogid'],
                    'blogtitle'        => $article['blogtitle'],
                    'shortdescription' => $article['shortdescription'],
                    'longdescription'  => $article['longdescription'],
                    'seourl'           => $article['seourl'],
                    'thumbnail_image'  => $article['thumbnail_image'],
                    'publishstatus'    => $article['publishstatus'],
                    'publishdate'      => $article['publishdate'],
                    'metatitle'        => $article['metatitle'],
                    'metakeywords'     => $article['metakeywords'],
                    'source'           => $article['source'],
                    'blogstatus'       => $article['blogstatus'],
                    'sectors'          => $article['sectors'],
                    'cpvs'             => $article['cpvs'],
                    'countries'        => $article['countries'],
                    'regions'          => $article['regions'],
                    'createddate'      => $article['createddate'],
                    // Spelt this way in the API payload.
                    'ceratedtime'      => $article['ceratedtime'],
                    'updatedate'       => $article['updatedate'],
                    'updatedtime'      => $article['updatedtime']
                ];
            }

            return [
                'status'        => 'success',
                'tender_news'   => $responseTenderNews,
                'last_fetch_id' => $details['fetchid']
            ];

        } catch (Exception $e) {

            return [
                'status' => 'error',
                'msg'    => $e->getMessage()
            ];
        }
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
