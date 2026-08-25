# Tender Impulse API PHP

PHP example code demonstrating how to integrate with Tender Impulse APIs to retrieve global tender notices, contract awards, and tender news, process encrypted API responses, validate data integrity, and download associated documents.

Tender Impulse provides access to over **20,000 global tenders daily**, helping organizations discover procurement opportunities from government agencies, public sector organizations, and international institutions worldwide.

## Getting Access

**Please note:** Tender Impulse API access is a paid service. To obtain your API credentials (Access Token and Encryption Key), please submit a request through the following form:

https://tenderimpulse.com/request-call-back

Once your request has been approved, you will receive the credentials required to authenticate and access the APIs.

## Installation

No third-party packages are required. The examples use only the cURL, OpenSSL and JSON extensions that ship with PHP — make sure they are enabled in your `php.ini`:

```ini
extension=curl
extension=openssl
```

Document downloads use `file_get_contents()` over HTTPS, so `allow_url_fopen` must also be on:

```ini
allow_url_fopen = On
```

The tender news example downloads nothing, so it does not need that setting.

## Features

* Retrieve global tender notices
* Retrieve contract awards
* Retrieve tender news articles
* Fetch id tracking so each run resumes where the last one stopped
* Download tender and contract award documents
* Secure API authentication using access tokens
* AES-encrypted response handling
* Response integrity validation using CRC/MD5
* Automatic local file storage and directory creation

## How the APIs Work

All three APIs work the same way: they return records in batches, and you page through them using an id rather than a date. Each call takes a `lastid` and returns the records that come after it, along with a `fetchid` — the id of the last record in that batch. The `fetchid` is what you pass as the `lastid` of your next call.

The full cycle is:

1. Call the API with the `lastid` you have. On the very first run, use the starting id supplied by Tender Impulse.
2. Read the records and the `fetchid` from the decrypted response, and store the `fetchid`.
3. If the batch contained records, wait a short while and call again with `lastid` set to the stored `fetchid`.
4. Repeat until a call returns an empty batch. That means you are up to date for now.
5. Start again the next day to pick up newly published records.

A few details worth knowing:

* Each call returns a limited number of records, so a full catch-up normally takes several calls.
* When a batch is empty, `fetchid` comes back equal to the `lastid` you sent, so storing it is always safe.
* Store the `fetchid` only after you have finished handling the batch. If you store it first and then fail, those records are skipped permanently — there is no way to ask for them again.
* Sending a `lastid` higher than the server's own last fetch id is rejected with a message telling you the maximum allowed value.

The examples in this repository perform a single call and store the `fetchid` for the next one. Steps 3 to 5 — waiting, repeating, and scheduling the daily run — are left for you to implement in whatever way suits your application.

## Usage

### Tenders

Configure your credentials in `example.php`:

```php
$accessToken = 'your_access_token';
$key = 'your_encryption_key';
```

Then run:

```bash
php example.php
```

### Contract Awards

Configure the same credentials in `contractAwardExample.php`, then run:

```bash
php contractAwardExample.php
```

### Tender News

Configure the same credentials in `tenderNewsExample.php`, then run:

```bash
php tenderNewsExample.php
```

Tender news articles have no attachments, so this client does not download anything and takes no store path — only the access token and key.

### Fetch Id Storage

Each example makes a single call and then stores the returned `fetchid` in a small JSON file next to the code, so the next run picks up from there. The examples deliberately do not loop — repeating the call is left to you, so the flow stays easy to read:

| Example | State file |
| --- | --- |
| `example.php` | `tender-state.json` |
| `contractAwardExample.php` | `contract-award-state.json` |
| `tenderNewsExample.php` | `tender-news-state.json` |

The file looks like this:

```json
{
  "fetchid": 8156394
}
```

When the file is missing, the example falls back to the `$initialLastId` variable at the top of the script — set that to the starting id given to you by Tender Impulse. On every later run, the stored id is used instead, so each run resumes where the previous one stopped. Deleting the file resets the example back to `$initialLastId`.

This means running the example repeatedly is what walks you through the batches: each run fetches the next batch and moves the stored id forward. Once a run reports `0` records fetched, you are up to date and can stop until the next day.

A JSON file keeps the example easy to follow. In a real integration, store the `fetchid` wherever the rest of your data lives — typically a database column, updated in the same transaction that saves the batch.

No date handling is needed anywhere in this flow: the stored `fetchid` already tells the API where to resume.

## Response Structure

The tender client returns a standardized response array:

```php
[
    'status'        => 'success',
    'tenders'       => [...],
    'last_fetch_id' => 8156394
]
```

The contract award client returns the same shape, with the records under `contracts`:

```php
[
    'status'        => 'success',
    'contracts'     => [...],
    'last_fetch_id' => 261375
]
```

The tender news client returns the same shape, with the records under `tender_news`:

```php
[
    'status'        => 'success',
    'tender_news'   => [...],
    'last_fetch_id' => 18016
]
```

In case of an error:

```php
[
    'status' => 'error',
    'msg'    => 'Error description'
]
```

## Files

| File | Purpose |
| --- | --- |
| `TenderImpulseClient.php` | Client library for the tender notices API |
| `TenderImpulseContractAwardClient.php` | Client library for the contract awards API |
| `TenderImpulseTenderNewsClient.php` | Client library for the tender news API |
| `example.php` | Runnable example for tenders |
| `contractAwardExample.php` | Runnable example for contract awards |
| `tenderNewsExample.php` | Runnable example for tender news |

## Requirements

* PHP 7.4 or later
* cURL, OpenSSL and JSON extensions enabled
* Valid Tender Impulse API credentials

## Security

All API responses are:

* Encrypted using AES-128-CBC
* Verified using MD5 checksum validation
* Authenticated using Bearer Access Tokens

For more information about Tender Impulse and its services, visit https://tenderimpulse.com.
