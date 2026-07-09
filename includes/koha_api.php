<?php
require_once __DIR__ . '/config.php';

/**
 * Thin wrapper around Koha's REST API (v1).
 *
 * Relevant Koha endpoints used:
 *   POST /api/v1/oauth/token                  -> get access token (client credentials / basic)
 *   GET  /api/v1/patrons?cardnumber=...        -> find patron by library card
 *   GET  /api/v1/checkouts?patron_id=...       -> currently issued items
 *   GET  /api/v1/patrons/{id}/account          -> outstanding fines / account balance
 *   GET  /api/v1/checkouts?patron_id=...&overdue=true (for lost/overdue calc)
 *
 * Koha must have the RESTBasicAuth or RESTOAuth2ClientCredentials system
 * preference enabled, and the account in KOHA_API_USER must have circulate
 * permissions. See: https://koha-community.org/manual/ -> REST API.
 *
 * While KOHA_API_LIVE is false in config.php, this class returns clearly
 * labelled simulated data so the rest of the workflow can be built/tested
 * before your Koha server details are finalised.
 */
class KohaApi
{
    private string $base;
    private string $user;
    private string $pass;
    private bool $live;

    public function __construct()
    {
        $this->base = rtrim(KOHA_API_BASE, '/');
        $this->user = KOHA_API_USER;
        $this->pass = KOHA_API_PASS;
        $this->live = KOHA_API_LIVE;
    }

    /**
     * Looks up circulation status for a patron by library card number.
     * Returns an array: books_issued, fine_amount, lost_items, account_status, clear (bool)
     */
    public function checkPatronDues(string $cardNumber): array
    {
        if (!$this->live) {
            return $this->simulate($cardNumber);
        }

        try {
            $patron = $this->getJson('/patrons?cardnumber=' . urlencode($cardNumber));
            if (empty($patron[0])) {
                return $this->errorResult('No matching patron found in Koha for card number ' . $cardNumber);
            }
            $patronId = $patron[0]['patron_id'] ?? $patron[0]['borrowernumber'];

            $checkouts = $this->getJson('/checkouts?patron_id=' . $patronId);
            $booksIssued = is_array($checkouts) ? count($checkouts) : 0;

            $account = $this->getJson('/patrons/' . $patronId . '/account');
            $fine = (float) ($account['balance'] ?? 0);

            $lost = $this->getJson('/checkouts?patron_id=' . $patronId . '&lost=true');
            $lostItems = is_array($lost) ? count($lost) : 0;

            $status = $patron[0]['restricted'] ?? false ? 'Restricted' : 'Active';
            $clear = ($fine <= 0 && $lostItems === 0 && $status === 'Active');

            return [
                'books_issued'   => $booksIssued,
                'fine_amount'    => $fine,
                'lost_items'     => $lostItems,
                'account_status' => $status,
                'clear'          => $clear,
                'simulated'      => false,
                'error'          => null,
            ];
        } catch (Throwable $e) {
            error_log('Koha API error: ' . $e->getMessage());
            return $this->errorResult('Could not reach Koha API: ' . $e->getMessage());
        }
    }

    private function errorResult(string $error): array
    {
        return [
            'books_issued' => null, 'fine_amount' => null, 'lost_items' => null,
            'account_status' => 'Unknown', 'clear' => null, 'simulated' => false, 'error' => $error,
        ];
    }

    /** Deterministic simulated response so demoing/testing doesn't require a live Koha server. */
    private function simulate(string $cardNumber): array
    {
        $seed = crc32($cardNumber);
        $booksIssued = $seed % 4;                     // 0-3
        $fine = ($seed % 7 === 0) ? round((($seed % 500) / 100) * 25, 2) : 0.0;
        $lost = ($seed % 11 === 0) ? 1 : 0;
        $clear = ($fine <= 0 && $lost === 0);

        return [
            'books_issued'   => $booksIssued,
            'fine_amount'    => $fine,
            'lost_items'     => $lost,
            'account_status' => 'Active',
            'clear'          => $clear,
            'simulated'      => true,
            'error'          => null,
        ];
    }

    private function getJson(string $path): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $this->user . ':' . $this->pass,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_TIMEOUT        => 8,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException('cURL error: ' . curl_error($ch));
        }
        if ($httpCode >= 400) {
            throw new RuntimeException('Koha API returned HTTP ' . $httpCode);
        }
        $data = json_decode($response, true);
        return is_array($data) ? $data : [];
    }
}
