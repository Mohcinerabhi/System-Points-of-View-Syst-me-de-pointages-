<?php

declare(strict_types=1);

namespace Attendance\Services;

use App\Helpers\Logger;

class ISAPIClient
{
    private string $ip;
    private int $port;
    private bool $https;
    private string $username;
    private string $password;
    private int $timeout;
    private string $timezone;
    private string $debugDir;
    private ?string $lastError = null;
    private int $lastHttpCode = 0;
    private ?string $lastResponseBody = null;
    private ?string $lastRequestBody = null;
    private ?string $lastRequestUrl = null;
    private ?string $lastParserType = null;
    private int $lastParsedEventCount = 0;
    private int $lastDiscardedEventCount = 0;
    private array $lastDiscardReasons = [];
    private int $lastXmlNodeCount = 0;
    private ?array $workingAcsStrategy = null;

     private const STEP_CONFIGS = [
         1 => ['major' => null, 'minorEvent' => null],
         2 => ['major' => 5, 'minorEvent' => null],
         3 => ['major' => 0, 'minorEvent' => 0],
         4 => ['major' => 5, 'minorEvent' => 75],
         5 => ['major' => 5, 'minorEvent' => 38],
     ];

    private const ENDPOINTS = [
        ['method' => 'POST', 'path' => '/ISAPI/AccessControl/AcsEvent?format=json', 'format' => 'json'],
        ['method' => 'POST', 'path' => '/ISAPI/AccessControl/AcsEvent', 'format' => 'auto'],
        ['method' => 'POST', 'path' => '/ISAPI/AccessControl/AcsEvent/Search', 'format' => 'auto'],
        ['method' => 'POST', 'path' => '/ISAPI/AccessControl/AcsEvent/Search?format=json', 'format' => 'json'],
    ];

    private const EMP_NO_FIELDS = ['employeeNo','EmployeeNo','employeeNO','EmployeeNO','employeeID','EmployeeID','employeeId','EmployeeId','personId','PersonId','personID','PersonID','userID','UserID','userId','UserId','userNo','UserNo','cardHolderNo','CardHolderNo','cardHolderID','CardHolderID','employeeNumber','EmployeeNumber','empNo','EmpNo','staffNo','StaffNo','personNo','PersonNo'];
    private const EMP_NAME_FIELDS = ['name','Name','employeeName','EmployeeName','personName','PersonName','userName','UserName','displayName','DisplayName','fullName','FullName','firstName','FirstName','lastName','LastName'];
    private const CARD_FIELDS = ['cardNo','CardNo','cardNumber','CardNumber','badgeNo','BadgeNo','badgeNumber','BadgeNumber','cardId','CardId','cardID','CardID','accessCard','AccessCard','cardRef','CardRef','badgeId','BadgeId','badgeID','BadgeID'];
    private const TS_FIELDS = ['dateTime','DateTime','eventTime','EventTime','localTime','LocalTime','swipeTime','SwipeTime','eventDate','EventDate','captureTime','CaptureTime','time','Time','timestamp','Timestamp'];
    private const EVT_FIELDS = ['eventType','EventType','major','Major','minor','Minor','eventId','EventId','eventID','EventID','majorType','MajorType','minorType','MinorType','eventCode','EventCode','eventCodeId','EventCodeId'];
    private const DOOR_FIELDS = ['doorNo','DoorNo','door','Door','readerNo','ReaderNo','readerID','ReaderID','accessChannel','AccessChannel','channelNo','ChannelNo','doorName','DoorName','readerName','ReaderName'];

    private const DATE_FORMATS = ['Y-m-d\TH:i:sP','Y-m-d\TH:i:s\Z','Y-m-d H:i:s','Y/m/d H:i:s','YmdHis','Y-m-d','Y/m/d'];

    public function __construct(string $ip, int $port = 80, string $username = '', string $password = '', int $timeout = 10, bool $https = false, string $timezone = '+00:00')
    {
        $this->ip = $ip;
        $this->port = max(1, $port);
        $this->username = $username;
        $this->password = $password;
        $this->timeout = max(1, $timeout);
        $this->https = $https;
        $this->timezone = $timezone;
        $this->debugDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    }

    public function getIp(): string { return $this->ip; }
    public function getPort(): int { return $this->port; }
    public function getTimeout(): int { return $this->timeout; }
    public function getLastError(): ?string { return $this->lastError; }
    public function getLastHttpCode(): int { return $this->lastHttpCode; }
    public function getLastResponseBody(): ?string { return $this->lastResponseBody; }
    public function getLastRequestBody(): ?string { return $this->lastRequestBody; }
    public function getLastRequestUrl(): ?string { return $this->lastRequestUrl; }
    public function getLastParserType(): ?string { return $this->lastParserType; }
    public function getLastParsedEventCount(): int { return $this->lastParsedEventCount; }
    public function getLastDiscardedEventCount(): int { return $this->lastDiscardedEventCount; }
    public function getLastDiscardReasons(): array { return $this->lastDiscardReasons; }

    public function searchAttendance(?string $startDate = null, ?string $endDate = null, ?int $userId = null): array
    {
        $stepStart = microtime(true);

        if ($startDate === null || $startDate === '') {
            $startTime = null;
        } else {
            $startDt = new \DateTime($startDate . ' 00:00:00', new \DateTimeZone($this->timezone));
            $startTime = $startDt->format('Y-m-d\TH:i:sP');
        }

        if ($endDate === null || $endDate === '') {
            $endTime = null;
        } else {
            $endDt = new \DateTime($endDate . ' 23:59:59', new \DateTimeZone($this->timezone));
            $endTime = $endDt->format('Y-m-d\TH:i:sP');
        }

        $retryWithDefaults = false;

        $allEvents = [];
        $searchId = '1';
        $maxResults = 50;
        $position = 0;
        $totalMatches = null;
        $hasMore = true;
        $workingEndpoint = null;
        $pageErrors = [];
        $totalAttempts = 0;
        $pageCount = 0;

        while ($hasMore) {
            $pageCount++;

            if ($retryWithDefaults && ($startTime === null && $endTime === null)) {
                break;
            }

            if ($pageCount > 5000) {
                $this->saveSyncDebugLog('TOO_MANY_PAGES', [
                    'success' => false,
                    'data' => $allEvents,
                    'total_matches' => $totalMatches,
                    'page_count' => $pageCount,
                    'error' => 'Too many pages. Aborting after 5000 pages.',
                ], $startTime ?? '', $endTime ?? '');

                return [
                    'success' => false,
                    'data' => $allEvents,
                    'error' => 'Too many pages. Aborting after 5000 pages.',
                    'endpoint' => $workingEndpoint['path'] ?? null,
                    'http_code' => 0,
                    'total_matches' => $totalMatches,
                    'received_count' => count($allEvents),
                ];
            }

            if ($totalAttempts >= 5000) {
                $this->saveSyncDebugLog('TOO_MANY_ATTEMPTS', [
                    'success' => false,
                    'data' => $allEvents,
                    'total_matches' => $totalMatches,
                    'page_count' => $pageCount,
                    'error' => 'Too many HTTP requests. Aborting to prevent device overload.',
                ], $startTime ?? '', $endTime ?? '');

                return [
                    'success' => false,
                    'data' => $allEvents,
                    'error' => 'Too many HTTP requests. Aborting to prevent device overload.',
                    'endpoint' => $workingEndpoint['path'] ?? null,
                    'http_code' => 0,
                    'total_matches' => $totalMatches,
                    'received_count' => count($allEvents),
                ];
            }

            $pageResult = $this->fetchAcsEventPage($searchId, $position, $maxResults, $startTime, $endTime, $workingEndpoint);
            $totalAttempts += $pageResult['attempt'] ?? 1;

            if (!$pageResult['success'] && !$retryWithDefaults && $startTime === null && $endTime === null) {
                $errorMsg = strtolower((string) ($pageResult['error'] ?? ''));
                $httpCode = (int) ($pageResult['http_code'] ?? 0);
                $needsDates = str_contains($errorMsg, 'starttime')
                    || str_contains($errorMsg, 'endtime')
                    || str_contains($errorMsg, 'missing')
                    || str_contains($errorMsg, 'required')
                    || $httpCode === 400;

                if ($needsDates) {
                    $retryWithDefaults = true;
                    $startTime = '2020-01-01T00:00:00+00:00';
                    $endTime = (new \DateTime('now', new \DateTimeZone($this->timezone)))->format('Y-m-d\TH:i:sP');
                    $allEvents = [];
                    $position = 0;
                    $totalMatches = null;
                    $hasMore = true;
                    $workingEndpoint = null;
                    $pageErrors = [];
                    $totalAttempts = 0;
                    $pageCount = 0;
                    continue;
                }
            }

            $pageNumOfMatches = (int) ($pageResult['numOfMatches'] ?? 0);
            if ($pageNumOfMatches === 0 && !empty($pageResult['raw_response'])) {
                if (preg_match('/"numOfMatches":\s*(\d+)/', $pageResult['raw_response'], $m)) {
                    $pageNumOfMatches = (int) $m[1];
                } elseif (preg_match('/<numOfMatches>(\d+)<\/numOfMatches>/', $pageResult['raw_response'], $m)) {
                    $pageNumOfMatches = (int) $m[1];
                }
            }

            if (!$pageResult['success']) {
                $pageErrors[] = [
                    'position' => $position,
                    'error' => $pageResult['error'] ?? 'Unknown error',
                    'http_code' => $pageResult['http_code'] ?? 0,
                    'endpoint' => $pageResult['endpoint']['path'] ?? null,
                ];

                if ($pageResult['error'] === 'ENDPOINT_NOT_FOUND' && $workingEndpoint === null) {
                    $probe = $this->probeAcsEventEndpoints($startTime, $endTime);
                    if ($probe['success']) {
                        $workingEndpoint = $probe['endpoint'];
                        continue;
                    }
                    $this->saveSyncDebugLog('ENDPOINT_NOT_FOUND', [
                        'success' => false,
                        'data' => $allEvents,
                        'total_matches' => $totalMatches,
                        'page_count' => $pageCount,
                        'error' => $pageResult['error'] ?? 'No attendance endpoint supported by this terminal. Tried: ' . implode(', ', array_column(self::ENDPOINTS, 'path')),
                    ], $startTime, $endTime);

                    return [
                        'success' => false,
                        'data' => $allEvents,
                        'error' => $pageResult['error'] ?? 'No attendance endpoint supported by this terminal. Tried: ' . implode(', ', array_column(self::ENDPOINTS, 'path')),
                        'endpoint' => $pageResult['endpoint']['path'] ?? null,
                        'http_code' => $pageResult['http_code'] ?? 404,
                        'total_matches' => $totalMatches,
                        'received_count' => count($allEvents),
                        'page_errors' => $pageErrors,
                    ];
                }

                if ($workingEndpoint === null && is_string($pageResult['error'] ?? null) && (str_contains($pageResult['error'], 'notSupport') || str_contains($pageResult['error'], 'Invalid Format'))) {
                    $probe = $this->probeAcsEventEndpoints($startTime, $endTime);
                    if ($probe['success']) {
                        $workingEndpoint = $probe['endpoint'];
                        continue;
                    }
                    $this->saveSyncDebugLog('UNSUPPORTED_ENDPOINT', [
                        'success' => false,
                        'data' => $allEvents,
                        'total_matches' => $totalMatches,
                        'page_count' => $pageCount,
                        'error' => $pageResult['error'] ?? 'Attendance endpoint not supported by this terminal firmware. Tried: ' . implode(', ', array_column(self::ENDPOINTS, 'path')),
                    ], $startTime, $endTime);

                    return [
                        'success' => false,
                        'data' => $allEvents,
                        'error' => $pageResult['error'] ?? 'Attendance endpoint not supported by this terminal firmware. Tried: ' . implode(', ', array_column(self::ENDPOINTS, 'path')),
                        'endpoint' => $pageResult['endpoint']['path'] ?? null,
                        'http_code' => $pageResult['http_code'] ?? 400,
                        'total_matches' => $totalMatches,
                        'received_count' => count($allEvents),
                        'page_errors' => $pageErrors,
                    ];
                }

                $this->saveSyncDebugLog('PAGE_ERROR', [
                    'success' => false,
                    'data' => $allEvents,
                    'total_matches' => $totalMatches,
                    'page_count' => $pageCount,
                    'error' => $pageResult['error'] ?? 'Failed to fetch attendance',
                ], $startTime, $endTime);

                return [
                    'success' => false,
                    'data' => $allEvents,
                    'error' => $pageResult['error'] ?? 'Failed to fetch attendance',
                    'endpoint' => $pageResult['endpoint']['path'] ?? null,
                    'raw_response' => $pageResult['raw_response'] ?? null,
                    'http_code' => $pageResult['http_code'] ?? 0,
                    'total_matches' => $totalMatches,
                    'received_count' => count($allEvents),
                    'page_errors' => $pageErrors,
                ];
            }

            $workingEndpoint = $pageResult['endpoint'] ?? $workingEndpoint;
            $events = $pageResult['data'] ?? [];
            $allEvents = array_merge($allEvents, $events);

            if ($totalMatches === null && isset($pageResult['total_matches']) && $pageResult['total_matches'] > 0) {
                $totalMatches = (int) $pageResult['total_matches'];
            }

            $fetchedCount = count($events);

            $this->saveSyncDebugLog('PAGE_INFO', [
                'searchID' => $searchId,
                'page' => $pageCount,
                'searchResultPosition' => $position,
                'maxResults' => $maxResults,
                'totalMatches' => $totalMatches ?? 0,
                'numOfMatches' => $pageNumOfMatches,
                'processedEvents' => $fetchedCount,
            ], $startTime, $endTime);

            $position += $pageNumOfMatches;

            if ($totalMatches !== null && $position >= $totalMatches) {
                $hasMore = false;
            } elseif ($pageNumOfMatches === 0) {
                $hasMore = false;
            } elseif ($totalMatches === null && $pageNumOfMatches < $maxResults) {
                $hasMore = false;
            } else {
                $hasMore = true;
            }
        }

        if ($userId !== null) {
            $prefix = 'EMP' . str_pad((string) $userId, 3, '0', STR_PAD_LEFT);
            $allEvents = array_values(array_filter($allEvents, function ($record) use ($prefix) {
                return ($record['employeeNo'] ?? '') === $prefix;
            }));
        }

        $this->saveSyncDebugLog('SUCCESS', [
            'success' => true,
            'data' => $allEvents,
            'total_matches' => $totalMatches,
            'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            'total_attempts' => $totalAttempts,
            'page_count' => $pageCount,
        ], $startTime, $endTime);

        $this->saveSyncDebugLog('SYNC_SUMMARY', [
            'total_hikvision_records' => $totalMatches ?? 0,
            'total_imported_records' => count($allEvents),
            'total_skipped_duplicates' => 0,
            'total_processed_pages' => $pageCount,
        ], $startTime, $endTime);

        return [
            'success' => true,
            'data' => $allEvents,
            'error' => null,
            'endpoint' => $workingEndpoint['path'] ?? null,
            'raw_response' => $this->lastResponseBody,
            'http_code' => $this->lastHttpCode,
            'total_matches' => $totalMatches,
            'received_count' => count($allEvents),
        ];
    }

    public function getAttendanceRecords(string $startDate, string $endDate): array
    {
        return $this->searchAttendance($startDate, $endDate);
    }

    public function downloadAttendance(string $date = ''): array
    {
        $targetDate = $date !== '' ? $date : date('Y-m-d');
        return $this->searchAttendance($targetDate, $targetDate);
    }

    public function searchUsers(): array
    {
        $allUsers = [];
        $searchId = '1';
        $maxResults = 100;
        $position = 0;
        $totalMatches = null;
        $hasMore = true;
        $pageCount = 0;

        while ($hasMore) {
            $pageCount++;
            if ($pageCount > 20) {
                return [
                    'success' => false,
                    'data' => $allUsers,
                    'error' => 'Too many user pages. Aborting after 20 pages.',
                    'endpoint' => '/ISAPI/AccessControl/UserInfo/Search',
                    'http_code' => 0,
                    'received_count' => count($allUsers),
                ];
            }

            $pageResult = $this->fetchUserSearchPage($searchId, $position, $maxResults);

            if (!$pageResult['success']) {
                return [
                    'success' => false,
                    'data' => $allUsers,
                    'error' => $pageResult['error'] ?? 'Failed to fetch users',
                    'endpoint' => '/ISAPI/AccessControl/UserInfo/Search',
                    'http_code' => $pageResult['http_code'] ?? 0,
                    'raw_response' => $pageResult['raw_response'] ?? null,
                ];
            }

            $users = $pageResult['data'] ?? [];
            $allUsers = array_merge($allUsers, $users);

            if ($totalMatches === null && isset($pageResult['total_matches'])) {
                $totalMatches = (int) $pageResult['total_matches'];
            }

            $fetchedCount = count($users);
            $position += $fetchedCount;

            if ($totalMatches !== null && $position >= $totalMatches) {
                $hasMore = false;
            } elseif ($fetchedCount === 0) {
                $hasMore = false;
            } elseif ($totalMatches === null && $fetchedCount < $maxResults) {
                $hasMore = false;
            } elseif ($totalMatches === null && $fetchedCount === $maxResults) {
                $hasMore = true;
            } elseif ($fetchedCount < $maxResults) {
                $hasMore = false;
            }
        }

        return [
            'success' => true,
            'data' => $allUsers,
            'error' => null,
            'endpoint' => '/ISAPI/AccessControl/UserInfo/Search',
            'http_code' => $this->lastHttpCode,
            'raw_response' => $this->lastResponseBody,
            'total_matches' => $totalMatches,
            'received_count' => count($allUsers),
        ];
    }

    /**
     * Télécharge la photo de profil (visage) d'un utilisateur depuis le terminal.
     *
     * Le terminal Hikvision expose la photo via l'endpoint ISAPI :
     *   GET /ISAPI/AccessControl/UserInfo/Face/Picture?employeeNo=<no>
     * La réponse est un binaire image (JPEG/PNG) authentifiée par digest.
     *
     * @param string $employeeNo         employeeNo tel que stocké sur le terminal.
     * @param string $employeeNoString   Identifiant numérique optionnel (employeeNoString) utilisé en repli.
     * @return array{success:bool, http_code:int, content_type:string, data:?string, error:?string, request_url:string}
     */
    public function getEmployeePhoto(string $employeeNo, string $employeeNoString = ''): array
    {
        $result = [
            'success' => false,
            'http_code' => 0,
            'content_type' => '',
            'data' => null,
            'error' => null,
            'request_url' => '',
        ];

        $candidates = [];
        if ($employeeNo !== '') {
            $candidates[] = $employeeNo;
        }
        if ($employeeNoString !== '' && $employeeNoString !== $employeeNo) {
            $candidates[] = $employeeNoString;
        }

        if (empty($candidates)) {
            $result['error'] = 'No employee identifier';
            return $result;
        }

        foreach ($candidates as $candidate) {
            $attempt = $this->attemptEmployeePhotoFetch((string) $candidate, $result);
            $this->lastHttpCode = $attempt['http_code'];
            $this->lastRequestUrl = $attempt['request_url'];
            $result = $attempt;

            if ($attempt['success']) {
                $this->lastResponseBody = '<binary image data: ' . strlen((string) $attempt['data']) . ' bytes, content-type: ' . $attempt['content_type'] . '>';
                return $attempt;
            }

            $httpCode = (int) $attempt['http_code'];
            // En cas d'erreur de connexion / authentification, ne pas réessayer avec le candidat suivant.
            if ($httpCode === 0 || $httpCode === 401 || $httpCode === 403) {
                break;
            }
        }

        $this->lastError = $result['error'] ?? null;
        return $result;
    }

    /**
     * Effectue une tentative de téléchargement de la photo pour un identifiant donné.
     */
    private function attemptEmployeePhotoFetch(string $employeeNo, array $result): array
    {
        $attemptStart = microtime(true);
        $url = ($this->https ? 'https' : 'http') . '://' . $this->ip . ':' . $this->port
            . '/ISAPI/AccessControl/UserInfo/Face/Picture?employeeNo=' . urlencode($employeeNo);

        $this->logRequest('ISAPI_PHOTO_REQUEST', $url, '', ['method' => 'GET', 'path' => '/ISAPI/AccessControl/UserInfo/Face/Picture', 'format' => 'binary']);

        $ch = curl_init($url);
        if ($ch === false) {
            $result['error'] = 'curl_init failed';
            $result['request_url'] = $url;
            $this->logResponse('ISAPI_PHOTO_RESPONSE', $url, '', null, $result['error'], 0, round((microtime(true) - $attemptStart) * 1000, 2));
            return $result;
        }

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $responseHeaders[] = $header;
            return strlen($header);
        });

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Accept: image/jpeg, image/png, image/gif, image/webp, */*',
                'Connection: close',
            ],
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $this->lastError = curl_error($ch);
            $result['error'] = 'cURL error: ' . $this->lastError;
            $result['http_code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result['request_url'] = $url;
            curl_close($ch);
            $this->logResponse('ISAPI_PHOTO_RESPONSE', $url, '', null, $result['error'], $result['http_code'], round((microtime(true) - $attemptStart) * 1000, 2));
            return $result;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $result['http_code'] = $httpCode;
        $result['content_type'] = $contentType;
        $result['request_url'] = $url;

        if ($httpCode !== 200) {
            $result['error'] = 'HTTP error ' . $httpCode;
            $result['data'] = null;
            $this->logResponse('ISAPI_PHOTO_RESPONSE', $url, '', null, $result['error'], $httpCode, round((microtime(true) - $attemptStart) * 1000, 2));
            return $result;
        }

        $data = (string) $response;
        if ($data === '') {
            $result['error'] = 'Empty response';
            $result['data'] = null;
            $this->logResponse('ISAPI_PHOTO_RESPONSE', $url, '', null, $result['error'], $httpCode, round((microtime(true) - $attemptStart) * 1000, 2));
            return $result;
        }

        $result['success'] = true;
        $result['data'] = $data;
        $this->logResponse('ISAPI_PHOTO_RESPONSE', $url, '', '<binary image data: ' . strlen($data) . ' bytes>', null, $httpCode, round((microtime(true) - $attemptStart) * 1000, 2));
        return $result;
    }

    /**
     * Télécharge une photo de profil déjà exposée par le terminal (URL donnée par
     * le champ `faceURL` de UserInfo/Search). Contrairement à l'endpoint ISAPI
     * /ISAPI/AccessControl/UserInfo/Face/Picture (souvent non supporté), le
     * terminal renvoie réellement un JPEG via `faceURL`.
     *
     * @param string $faceUrl URL absolue fournie par le terminal (peut contenir un suffixe @<storage>).
     * @return array{success:bool, http_code:int, content_type:string, data:?string, error:?string, request_url:string}
     */
    public function getFacePhoto(string $faceUrl): array
    {
        $result = [
            'success' => false,
            'http_code' => 0,
            'content_type' => '',
            'data' => null,
            'error' => null,
            'request_url' => '',
        ];

        if ($faceUrl === '') {
            $result['error'] = 'No face URL';
            return $result;
        }

        $candidates = [$faceUrl];
        $at = strpos($faceUrl, '@');
        if ($at !== false) {
            $candidates[] = substr($faceUrl, 0, $at);
        }

        foreach ($candidates as $rawUrl) {
            $url = trim((string) $rawUrl);
            if ($url === '') {
                continue;
            }
            $attempt = $this->fetchRemoteImage($url, $result);
            $this->lastHttpCode = $attempt['http_code'];
            $this->lastRequestUrl = $attempt['request_url'];
            $result = $attempt;

            if ($attempt['success']) {
                return $attempt;
            }

            $httpCode = (int) $attempt['http_code'];
            // En cas d'erreur de connexion ou d'authentification, ne pas tenter la variante tronquée.
            if ($httpCode === 0 || $httpCode === 401 || $httpCode === 403) {
                break;
            }
        }

        $this->lastError = $result['error'] ?? null;
        return $result;
    }

    /**
     * Télécharge un binaire image (JPEG/PNG/…) depuis une URL absolue ou relative
     * au terminal, en s'authentifiant en digest si nécessaire.
     */
    private function fetchRemoteImage(string $url, array $result): array
    {
        $attemptStart = microtime(true);

        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $url) !== 1) {
            $url = ($this->https ? 'https' : 'http') . '://' . $this->ip . ':' . $this->port . '/' . ltrim($url, '/');
        }

        $this->logRequest('ISAPI_FACE_PHOTO_REQUEST', $url, '', ['method' => 'GET', 'path' => 'faceURL']);

        $ch = curl_init($url);
        if ($ch === false) {
            $result['error'] = 'curl_init failed';
            $result['request_url'] = $url;
            $this->logResponse('ISAPI_FACE_PHOTO_RESPONSE', $url, '', null, $result['error'], 0, round((microtime(true) - $attemptStart) * 1000, 2));
            return $result;
        }

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $responseHeaders[] = $header;
            return strlen($header);
        });

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Accept: image/jpeg, image/png, image/gif, image/webp, */*',
                'Connection: close',
            ],
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $this->lastError = curl_error($ch);
            $result['error'] = 'cURL error: ' . $this->lastError;
            $result['http_code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result['request_url'] = $url;
            curl_close($ch);
            $this->logResponse('ISAPI_FACE_PHOTO_RESPONSE', $url, '', null, $result['error'], $result['http_code'], round((microtime(true) - $attemptStart) * 1000, 2));
            return $result;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        $result['http_code'] = $httpCode;
        $result['content_type'] = $contentType;
        $result['request_url'] = $finalUrl !== '' ? $finalUrl : $url;

        if ($httpCode !== 200) {
            $result['error'] = 'HTTP error ' . $httpCode;
            $result['data'] = null;
            $this->logResponse('ISAPI_FACE_PHOTO_RESPONSE', $url, '', null, $result['error'], $httpCode, round((microtime(true) - $attemptStart) * 1000, 2));
            return $result;
        }

        $data = (string) $response;
        if ($data === '') {
            $result['error'] = 'Empty response';
            $result['data'] = null;
            $this->logResponse('ISAPI_FACE_PHOTO_RESPONSE', $url, '', null, $result['error'], $httpCode, round((microtime(true) - $attemptStart) * 1000, 2));
            return $result;
        }

        $result['success'] = true;
        $result['data'] = $data;
        $this->lastResponseBody = '<binary image data: ' . strlen($data) . ' bytes, content-type: ' . $contentType . '>';
        $this->logResponse('ISAPI_FACE_PHOTO_RESPONSE', $url, '', '<binary image data: ' . strlen($data) . ' bytes>', null, $httpCode, round((microtime(true) - $attemptStart) * 1000, 2));
        return $result;
    }

    private function probeAcsEventEndpoints(?string $startTime, ?string $endTime): array
    {
        foreach (self::ENDPOINTS as $endpoint) {
            $result = $this->fetchAcsEventPage('probe', 0, 1, $startTime, $endTime, $endpoint);
            if ($result['success'] && ($result['http_code'] ?? 0) === 200) {
                return ['success' => true, 'endpoint' => $endpoint];
            }
        }

        return ['success' => false, 'error' => 'No endpoint returned valid response'];
    }

    private function fetchAcsEventPage(int|string $searchId, int $position, int $maxResults, ?string $startTime, ?string $endTime, ?array $forcedEndpoint = null): array
    {
        $result = [
            'success' => false,
            'data' => [],
            'error' => null,
            'http_code' => 0,
            'raw_response' => null,
            'total_matches' => 0,
            'numOfMatches' => 0,
            'endpoint' => null,
            'attempt' => 0,
            'searchResultPosition' => $position,
        ];

        $endpoints = $forcedEndpoint !== null ? [$forcedEndpoint] : self::ENDPOINTS;
        $maxResults = min($maxResults, 50);

        $useCachedStrategy = false;
        if ($this->workingAcsStrategy !== null && $forcedEndpoint !== null) {
            $ws = $this->workingAcsStrategy;
            if ($ws['endpoint']['path'] === $forcedEndpoint['path'] && $ws['endpoint']['method'] === $forcedEndpoint['method']) {
                $useCachedStrategy = true;
            }
        } elseif ($this->workingAcsStrategy !== null && $forcedEndpoint === null) {
            $useCachedStrategy = true;
        }

        if ($useCachedStrategy) {
            $ws = $this->workingAcsStrategy;
            $fastResult = $this->executeAcsAttempt($ws['endpoint'], $searchId, $position, $maxResults, $startTime, $endTime, $ws['step'], $ws['format'], 'FastPath (remembered step ' . $ws['step'] . ')');
            if ($fastResult['success']) {
                $fastResult['searchResultPosition'] = $position;
                $fastResult['attempt'] = 1;
                return $fastResult;
            }
        }

        foreach ($endpoints as $endpoint) {
            for ($step = 1; $step <= 5; $step++) {
                $isXml = ($step === 5);
                $attemptStep = $step;
                $attemptFormat = $isXml ? 'xml' : $endpoint['format'];
                $attemptLabel = 'Attempt ' . ($result['attempt'] + 1) . ' (step=' . $attemptStep . ', format=' . ($isXml ? 'xml' : 'json') . ')';

                $fastResult = $this->executeAcsAttempt($endpoint, $searchId, $position, $maxResults, $startTime, $endTime, $attemptStep, $attemptFormat, $attemptLabel);
                $result['attempt']++;

                if ($fastResult['success']) {
                    $fastResult['searchResultPosition'] = $position;
                    $fastResult['attempt'] = $result['attempt'];
                    return $fastResult;
                }

                if ($fastResult['http_code'] === 404 || ($fastResult['http_code'] === 400 && str_contains($fastResult['error'] ?? '', 'notSupport'))) {
                    break;
                }

                if ($step < 5) {
                    usleep(200000);
                }

                $result['error'] = $fastResult['error'] ?? 'Unknown error';
                $result['http_code'] = $fastResult['http_code'] ?? 0;
            }
        }

        if ($result['http_code'] === 400 || $result['http_code'] === 401 || $result['http_code'] === 403) {
            $result['error'] = $result['error'] ?? 'Request failed with HTTP ' . $result['http_code'];
        } elseif ($result['http_code'] === 0) {
            $result['error'] = $result['error'] ?? 'No attendance endpoint supported';
        } else {
            $result['error'] = 'ENDPOINT_NOT_FOUND';
        }

        if ($result['raw_response'] !== null && $result['numOfMatches'] === 0) {
            $parserType = $this->detectContentType($result['raw_response']);
            $result['numOfMatches'] = $this->extractNumOfMatches($result['raw_response'], $parserType);
        }

        return $result;
    }

    private function tryXmlFallback(int|string $searchId, int $position, int $maxResults, ?string $startTime, ?string $endTime, int $step = 5): array
    {
        $endpoint = ['method' => 'POST', 'path' => '/ISAPI/AccessControl/AcsEvent', 'format' => 'xml'];
        $body = $this->buildRequestBody($endpoint, $searchId, $position, $maxResults, $startTime, $endTime, $step);
        if ($body === null) {
            return ['success' => false, 'error' => 'Failed to build XML body'];
        }

        $url = ($this->https ? 'https' : 'http') . '://' . $this->ip . ':' . $this->port . $endpoint['path'];
        $ch = curl_init($url);
        if ($ch === false) {
            return ['success' => false, 'error' => 'curl_init failed'];
        }

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $responseHeaders[] = $header;
            return strlen($header);
        });

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/xml',
                'Content-Length: ' . strlen($body),
                'Accept: application/xml, application/json, */*',
                'Connection: close',
            ],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $this->lastError = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'error' => 'cURL error: ' . curl_error($ch)];
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->saveRawHikvisionResponse($endpoint['path'], $body, $httpCode, $responseHeaders, (string) $response, $position, $maxResults);

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'XML fallback HTTP ' . $httpCode];
        }

        $this->lastResponseBody = (string) $response;
        $this->lastRequestUrl = $url;
        $this->lastRequestBody = $body;

        $events = $this->parseXmlEvents($this->lastResponseBody, $result = []);
        $totalMatches = $this->extractTotalMatches($this->lastResponseBody, 'xml');
        $numOfMatches = $this->extractNumOfMatches($this->lastResponseBody, 'xml');

        return [
            'success' => true,
            'data' => $events,
            'total_matches' => $totalMatches,
            'numOfMatches' => $numOfMatches,
            'searchResultPosition' => $position,
            'raw_response' => $this->lastResponseBody,
        ];
    }

    private function getFieldsUsed(int $step): array
    {
        $fields = ['searchID', 'searchResultPosition', 'maxResults', 'startTime', 'endTime'];

        if ($step >= 2) {
            $fields[] = 'major';
        }

        if ($step >= 3) {
            $fields[] = 'minorEvent';
        }

        return $fields;
    }

    private function logAttempt(string $url, string $method, string $contentType, string $body, string $attemptLabel, array $fieldsUsed): void
    {
        try {
            $logDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $timestamp = (new \DateTime())->format('Y-m-d H:i:s.u');
            $lines = [];
            $lines[] = "[{$timestamp}] [ACS_ATTEMPT] {$attemptLabel}";
            $lines[] = "  URL: {$url}";
            $lines[] = "  Method: {$method}";
            $lines[] = "  Content-Type: {$contentType}";
            $lines[] = "  Accept: application/xml, application/json, */*";
            $lines[] = "  Connection: close";
            $lines[] = "  Fields: " . implode(', ', $fieldsUsed);
            $lines[] = "  Request Body: " . substr($body, 0, 2000);
            $lines[] = "";

            @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'isapi_attempts.log', implode(PHP_EOL, $lines), FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
        }
    }

    private function executeAcsAttempt(array $endpoint, int|string $searchId, int $position, int $maxResults, ?string $startTime, ?string $endTime, int $step, string $format, string $attemptLabel): array
    {
        $attemptStart = microtime(true);
        $result = [
            'success' => false,
            'data' => [],
            'error' => null,
            'http_code' => 0,
            'raw_response' => null,
            'total_matches' => 0,
            'endpoint' => null,
            'elapsed_ms' => 0,
        ];

        $isXml = ($format === 'xml');
        $attemptPath = $isXml
            ? preg_replace('/\?format=json$/', '', $endpoint['path'])
            : $endpoint['path'];
        $attemptEndpoint = ['method' => $endpoint['method'] ?? 'POST', 'path' => $attemptPath, 'format' => $isXml ? 'xml' : ($endpoint['format'] ?? 'auto')];

        $body = $this->buildRequestBody($attemptEndpoint, $searchId, $position, $maxResults, $startTime, $endTime, $step);
        if ($body === null) {
            $result['error'] = 'Failed to build request body';
            $result['elapsed_ms'] = round((microtime(true) - $attemptStart) * 1000, 2);
            return $result;
        }

        $url = ($this->https ? 'https' : 'http') . '://' . $this->ip . ':' . $this->port . $attemptEndpoint['path'];
        $contentType = $isXml ? 'application/xml' : 'application/json';
        $fieldsUsed = $this->getFieldsUsed($step);

        $this->logAttempt($url, $attemptEndpoint['method'], $contentType, $body, $attemptLabel, $fieldsUsed);

        $ch = curl_init($url);
        if ($ch === false) {
            $result['error'] = 'curl_init failed';
            $result['elapsed_ms'] = round((microtime(true) - $attemptStart) * 1000, 2);
            return $result;
        }

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $responseHeaders[] = $header;
            return strlen($header);
        });

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if ($attemptEndpoint['method'] === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . $contentType,
            'Content-Length: ' . strlen($body),
            'Accept: application/xml, application/json, */*',
            'Connection: close',
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $this->lastError = curl_error($ch);
            $this->logResponse('ISAPI_RESPONSE_ERROR', $url, $body, null, $this->lastError, 0, round((microtime(true) - $attemptStart) * 1000, 2));
            curl_close($ch);
            $result['error'] = 'cURL error: ' . $this->lastError;
            $result['elapsed_ms'] = round((microtime(true) - $attemptStart) * 1000, 2);
            return $result;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->lastHttpCode = $httpCode;
        $this->lastResponseBody = (string) $response;
        $this->lastRequestBody = $body;
        $this->lastRequestUrl = $url;
        $result['http_code'] = $httpCode;
        $result['raw_response'] = $this->lastResponseBody;
        $result['endpoint'] = $endpoint;
        $result['elapsed_ms'] = round((microtime(true) - $attemptStart) * 1000, 2);

        $this->logResponse('ISAPI_RESPONSE', $url, $body, $this->lastResponseBody, null, $httpCode, round((microtime(true) - $attemptStart) * 1000, 2));

        $this->saveRawHikvisionResponse($attemptEndpoint['path'], $body, $httpCode, $responseHeaders, $this->lastResponseBody, $position, $maxResults);

        if ($httpCode !== 200) {
            $result['error'] = $this->formatHttpError($httpCode, $this->lastResponseBody);
            return $result;
        }

        if (empty($this->lastResponseBody)) {
            $result['success'] = true;
            $result['data'] = [];
            $result['total_matches'] = 0;
            $result['numOfMatches'] = 0;
            $result['elapsed_ms'] = round((microtime(true) - $attemptStart) * 1000, 2);
            return $result;
        }

        $parserType = $this->detectContentType($this->lastResponseBody);
        $this->lastParserType = $parserType;

        if ($parserType === 'xml') {
            $events = $this->parseXmlEvents($this->lastResponseBody, $result);
            $totalMatches = $this->extractTotalMatches($this->lastResponseBody, 'xml');
            $numOfMatches = $this->extractNumOfMatches($this->lastResponseBody, 'xml');
        } else {
            $decoded = json_decode($this->lastResponseBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $result['error'] = 'Invalid JSON response: ' . json_last_error_msg();
                $result['elapsed_ms'] = round((microtime(true) - $attemptStart) * 1000, 2);
                return $result;
            }
            $events = $this->parseJsonEvents($decoded, $result);
            $totalMatches = $this->extractTotalMatches($this->lastResponseBody, 'json');
            $numOfMatches = $this->extractNumOfMatches($this->lastResponseBody, 'json');
        }

        $this->lastParsedEventCount = count($events);
        $this->lastDiscardedEventCount = 0;
        $this->lastDiscardReasons = [];

        if ($this->lastParsedEventCount === 0 && !empty($this->lastResponseBody)) {
            $result['success'] = true;
            $result['data'] = [];
            $result['total_matches'] = $totalMatches;
            $result['numOfMatches'] = $numOfMatches;
            $result['elapsed_ms'] = round((microtime(true) - $attemptStart) * 1000, 2);
            return $result;
        }

        $this->workingAcsStrategy = [
            'endpoint' => $endpoint,
            'step' => $step,
            'format' => $format,
        ];

        $result['success'] = true;
        $result['data'] = $events;
        $result['total_matches'] = $totalMatches;
        $result['numOfMatches'] = $numOfMatches;
        $result['elapsed_ms'] = round((microtime(true) - $attemptStart) * 1000, 2);
        return $result;
    }

    private function buildRequestBody(array $endpoint, int|string $searchId, int $position, int $maxResults, ?string $startTime, ?string $endTime, int $step = 1): ?string
    {
        $maxResults = min($maxResults, 50);
        $stepConfig = self::STEP_CONFIGS[$step] ?? ['major' => null, 'minorEvent' => null];

        if ($endpoint['format'] === 'xml' || str_contains($endpoint['path'], 'format=xml')) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
<AcsEventCond>
    <searchID>' . htmlspecialchars((string) $searchId, ENT_XML1 | ENT_QUOTES) . '</searchID>
    <searchResultPosition>' . $position . '</searchResultPosition>
    <maxResults>' . $maxResults . '</maxResults>';

            if ($stepConfig['major'] !== null) {
                $xml .= '
    <major>' . $stepConfig['major'] . '</major>';
            }

            if ($stepConfig['minorEvent'] !== null) {
                $xml .= '
    <minorEvent>' . $stepConfig['minorEvent'] . '</minorEvent>';
            }

            if ($startTime !== null && $startTime !== '') {
                $xml .= '
    <startTime>' . htmlspecialchars($startTime, ENT_XML1 | ENT_QUOTES) . '</startTime>';
            }

            if ($endTime !== null && $endTime !== '') {
                $xml .= '
    <endTime>' . htmlspecialchars($endTime, ENT_XML1 | ENT_QUOTES) . '</endTime>';
            }

            $xml .= '
</AcsEventCond>';

            return $xml;
        }

        $bodyArray = [
            'AcsEventCond' => [
                'searchID' => (string) $searchId,
                'searchResultPosition' => $position,
                'maxResults' => $maxResults,
            ],
        ];

        if ($startTime !== null && $startTime !== '') {
            $bodyArray['AcsEventCond']['startTime'] = $startTime;
        }

        if ($endTime !== null && $endTime !== '') {
            $bodyArray['AcsEventCond']['endTime'] = $endTime;
        }

        if ($stepConfig['major'] !== null) {
            $bodyArray['AcsEventCond']['major'] = $stepConfig['major'];
        }

        if ($stepConfig['minorEvent'] !== null) {
            $bodyArray['AcsEventCond']['minor'] = $stepConfig['minorEvent'];
        }

        return json_encode($bodyArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function parseJsonEvents(array $decoded, array &$result): array
    {
        $events = [];

        if (isset($decoded['AcsEvent']) && is_array($decoded['AcsEvent'])) {
            $infoList = $this->locateInfoList($decoded['AcsEvent']);
            if ($infoList !== null) {
                foreach ($infoList as $event) {
                    if (is_array($event)) {
                        $normalized = $this->normalizeJsonEvent($event);
                        if (empty($normalized['isSystemEvent'])) {
                            $events[] = $normalized;
                        }
                    }
                }
                return $events;
            }
        }

        $candidates = $this->findEventArraysRecursive($decoded);
        foreach ($candidates as $list) {
            foreach ($list as $event) {
                if (is_array($event)) {
                    $normalized = $this->normalizeJsonEvent($event);
                    if (empty($normalized['isSystemEvent'])) {
                        $events[] = $normalized;
                    }
                }
            }
        }

        return $events;
    }

    private function locateInfoList(array $acsEvent): ?array
    {
        $keys = ['InfoList', 'Info', 'info', 'list', 'List', 'eventList', 'EventList', 'AcsEventInfo', 'AccessEvent', 'EventInfo', 'MatchList', 'data', 'rows', 'records', 'events', 'items', 'result'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $acsEvent) && is_array($acsEvent[$key])) {
                $val = $acsEvent[$key];
                if ($this->isList($val)) {
                    return $val;
                }
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        if (is_array($v) && $this->isList($v)) {
                            return $v;
                        }
                    }
                }
            }
        }

        if (count($acsEvent) <= 15) {
            foreach ($acsEvent as $k => $v) {
                if (is_array($v) && $this->isList($v) && !empty($v)) {
                    $first = $v[0];
                    if (is_array($first) && $this->countEventFields($first) >= 1) {
                        return $v;
                    }
                }
            }
        }

        return null;
    }

    private function findEventArraysRecursive(mixed $data): array
    {
        $found = [];
        if (!is_array($data)) {
            return $found;
        }

        $count = 0;
        $eventLike = 0;
        foreach ($data as $item) {
            if (is_array($item)) {
                $count++;
                $eventLike += $this->countEventFields($item);
            }
        }

        if ($count > 0 && $eventLike > 0 && $eventLike >= $count * 0.3) {
            return [$data];
        }

        foreach ($data as $value) {
            $nested = $this->findEventArraysRecursive($value);
            if (!empty($nested)) {
                $found = array_merge($found, $nested);
            }
        }

        return $found;
    }

    private function countEventFields(array $item): int
    {
        $count = 0;
        $keys = array_keys($item);
        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }
            $kl = strtolower((string) $key);
            if (in_array($kl, ['employeeno', 'employeeid', 'personid', 'userid', 'userno', 'cardholderno',
                'name', 'employeename', 'personname', 'username', 'displayname',
                'cardno', 'cardnumber', 'badgeno', 'cardid',
                'datetime', 'eventtime', 'localtime', 'swipetime', 'time', 'eventdate', 'captured',
                'eventtype', 'eventid', 'major', 'minor', 'majortype', 'minortype',
                'eventdescription', 'description',
                'door', 'doorno', 'reader', 'readerid', 'readerno', 'accesschannel',
                'eventreason', 'verified', 'doorname', 'readername'])) {
                $count++;
            }
        }
        return $count;
    }

    private function isList(array $array): bool
    {
        if (empty($array)) {
            return true;
        }
        $keys = array_keys($array);
        foreach ($keys as $i => $key) {
            if ((int) $key !== $i) {
                return false;
            }
        }
        return true;
    }

    private function normalizeJsonEvent(array $event): array
    {
        $employeeNo = (string) ($this->pick($event, ['employeeNo','employeeID','personId','userID','cardNo','cardNumber']) ?? '');
        $employeeNoString = (string) ($this->pick($event, ['employeeNoString','employeeNoString','employeeNoStr','employeeNoStrId']) ?? '');
        $employeeName = (string) ($this->pick($event, ['name','employeeName','personName','userName','displayName']) ?? '');
        $cardNo = (string) ($this->pick($event, ['cardNo','cardNumber','badgeNo','cardId']) ?? '');
        $dateTime = (string) ($this->pick($event, ['dateTime','eventTime','localTime','swipeTime','time','eventDate','captureTime']) ?? '');
        $eventType = (string) ($this->pick($event, ['eventType','eventTypeID','eventID','eventDescription','description']) ?? '');
        $eventId = (string) ($this->pick($event, ['eventId','eventID']) ?? '');
        $major = (string) ($this->pick($event, ['major','majorType']) ?? '');
        $minor = (string) ($this->pick($event, ['minor','minorType']) ?? '');

        $effectiveEmployeeNo = $employeeNo !== '' ? $employeeNo : $employeeNoString;

        $attendanceDate = '';
        $attendanceTime = '';
        if ($dateTime !== null) {
            try {
                $dt = new \DateTime($dateTime, new \DateTimeZone($this->timezone));
                $attendanceDate = $dt->format('Y-m-d');
                $attendanceTime = $dt->format('H:i:s');
            } catch (\Exception $e) {
                $attendanceDate = date('Y-m-d');
                $attendanceTime = '00:00:00';
            }
        } else {
            $attendanceDate = date('Y-m-d');
            $attendanceTime = '00:00:00';
        }

            $type = $this->normalizeEventType($eventType, $major, $minor, $eventId, $dateTime);

        $isSystemEvent = ($effectiveEmployeeNo === '' && $cardNo === '')
            || ($major !== '' && $minor !== '' && $effectiveEmployeeNo === '');

        return [
            'employeeNo' => (string) ($effectiveEmployeeNo ?? ''),
            'employeeNoString' => (string) ($employeeNoString ?? $effectiveEmployeeNo ?? ''),
            'employeeName' => (string) ($employeeName ?? ''),
            'name' => (string) ($employeeName ?? ''),
            'cardNo' => (string) ($cardNo ?? ''),
            'eventType' => (string) ($eventType ?? ''),
            'eventId' => (string) ($eventId ?? ''),
            'dateTime' => (string) ($dateTime ?? ''),
            'time' => $attendanceTime,
            'date' => $attendanceDate,
            'major' => (string) ($major ?? ''),
            'minor' => (string) ($minor ?? ''),
            'type' => $type,
            'isSystemEvent' => $isSystemEvent,
        ];
    }

    private function parseXmlEvents(string $xml, array &$result): array
    {
        $events = [];

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();

        if ($doc === false) {
            $result['error'] = 'XML parse error';
            return $events;
        }

        $xpathCandidates = [
            '//*[local-name()="AcsEvent"]/*[local-name()="AcsEventInfo"]',
            '//*[local-name()="AcsEvent"]/*[local-name()="AccessEvent"]',
            '//*[local-name()="AcsEvent"]/*[local-name()="Event"]',
            '//*[local-name()="AcsEvent"]/*[local-name()="Info"]',
            '//*[local-name()="AcsEvent"]/*[local-name()="MajorEvent"]',
            '//*[local-name()="AcsEvent"]/*',
            '//*[local-name()="Event"]',
            '//*[local-name()="AccessEvent"]',
            '//*[local-name()="AcsEventInfo"]',
            '//*[local-name()="Info"]',
            '//*[local-name()="MajorEvent"]',
        ];

        $nodes = [];
        foreach ($xpathCandidates as $xpath) {
            $found = $doc->xpath($xpath);
            if ($found !== false && !empty($found)) {
                $nodes = $found;
                break;
            }
        }

        if (empty($nodes)) {
            $all = $doc->xpath('//*[local-name()]');
            if ($all !== false) {
                $nodes = $all;
            }
        }

        $this->lastXmlNodeCount = count($nodes);

        foreach ($nodes as $node) {
            if (!($node instanceof \SimpleXMLElement)) {
                continue;
            }

            $employeeNo = (string) ($this->xmlValue($node, 'employeeNoString')
                ?? $this->xmlValue($node, 'employeeNo')
                ?? $this->xmlValue($node, 'employeeID')
                ?? $this->xmlValue($node, 'personId')
                ?? $this->xmlValue($node, 'cardNo') ?? '');

            $employeeNoString = (string) ($this->xmlValue($node, 'employeeNoString')
                ?? $this->xmlValue($node, 'employeeNoStr')
                ?? $this->xmlValue($node, 'employeeNoStrId') ?? '');

            $employeeName = (string) ($this->xmlValue($node, 'name')
                ?? $this->xmlValue($node, 'employeeName')
                ?? $this->xmlValue($node, 'personName') ?? '');

            $cardNo = (string) ($this->xmlValue($node, 'cardNo')
                ?? $this->xmlValue($node, 'cardNumber') ?? '');

            $dateTime = (string) ($this->xmlValue($node, 'dateTime')
                ?? $this->xmlValue($node, 'eventTime')
                ?? $this->xmlValue($node, 'localTime')
                ?? $this->xmlValue($node, 'time') ?? '');

            $eventType = (string) ($this->xmlValue($node, 'eventType')
                ?? $this->xmlValue($node, 'eventTypeID')
                ?? $this->xmlValue($node, 'eventID')
                ?? $this->xmlValue($node, 'eventDescription')
                ?? $this->xmlValue($node, 'description') ?? '');

            $eventId = (string) ($this->xmlValue($node, 'eventId')
                ?? $this->xmlValue($node, 'eventID') ?? '');

            $major = (string) ($this->xmlValue($node, 'major') ?? '');
            $minor = (string) ($this->xmlValue($node, 'minor') ?? '');

            $attendanceDate = '';
            $attendanceTime = '';
            if ($dateTime !== null) {
                try {
                    $dt = new \DateTime($dateTime, new \DateTimeZone($this->timezone));
                    $attendanceDate = $dt->format('Y-m-d');
                    $attendanceTime = $dt->format('H:i:s');
                } catch (\Exception $e) {
                    $attendanceDate = date('Y-m-d');
                    $attendanceTime = '00:00:00';
                }
            } else {
                $attendanceDate = date('Y-m-d');
                $attendanceTime = '00:00:00';
            }

            $effectiveEmployeeNo = $employeeNo !== '' ? $employeeNo : $employeeNoString;

            $isSystemEvent = ($effectiveEmployeeNo === '' && $cardNo === '')
                || ($major !== '' && $minor !== '' && $effectiveEmployeeNo === '');

            if (empty($isSystemEvent)) {
                $type = $this->normalizeEventType($eventType, $major, $minor, $eventId, $dateTime);
                $events[] = [
                    'employeeNo' => (string) ($effectiveEmployeeNo ?? ''),
                    'employeeNoString' => (string) ($employeeNoString ?? $effectiveEmployeeNo ?? ''),
                    'employeeName' => (string) ($employeeName ?? ''),
                    'name' => (string) ($employeeName ?? ''),
                    'cardNo' => (string) ($cardNo ?? ''),
                    'eventType' => (string) ($eventType ?? ''),
                    'eventId' => (string) ($eventId ?? ''),
                    'dateTime' => (string) ($dateTime ?? ''),
                    'time' => $attendanceTime,
                    'date' => $attendanceDate,
                    'major' => (string) ($major ?? ''),
                    'minor' => (string) ($minor ?? ''),
                    'type' => $type,
                    'isSystemEvent' => false,
                ];
            }
        }

        return $events;
    }

    private function xmlValue(\SimpleXMLElement $node, string $field): ?string
    {
        $child = $node->children()->{$field};
        if ($child !== null && (string) $child !== '') {
            return (string) $child;
        }

        $nodes = $node->xpath('.//*[local-name()="' . $field . '"]');
        if ($nodes !== false && !empty($nodes[0])) {
            return (string) $nodes[0];
        }

        return null;
    }

    private function normalizeEventType(?string $eventType, ?string $major, ?string $minor, ?string $eventId, ?string $dateTime = null): string
    {
        if ($eventType !== null) {
            $eventType = strtolower((string) $eventType);
            if (str_contains($eventType, 'entry')
                || str_contains($eventType, 'check')
                || str_contains($eventType, 'acquire')
                || str_contains($eventType, 'pass')
                || str_contains($eventType, 'access granted')
                || str_contains($eventType, 'normal')
                || str_contains($eventType, 'open')
            ) {
                return 'check_in';
            }
            if (str_contains($eventType, 'exit')
                || str_contains($eventType, 'leave')
                || str_contains($eventType, 'release')
                || str_contains($eventType, 'close')
            ) {
                return 'check_out';
            }
        }

        $majorNum = (int) ($major ?? 0);
        $minorNum = (int) ($minor ?? 0);

        if ($majorNum === 5 && $minorNum === 153) {
            if ($dateTime !== null) {
                try {
                    $dt = new \DateTime($dateTime, new \DateTimeZone($this->timezone));
                    $hour = (int) $dt->format('H');
                    return $hour < 12 ? 'check_in' : 'check_out';
                } catch (\Exception $e) {
                    return 'check_in';
                }
            }
            return 'check_in';
        }
        if ($majorNum === 5 && $minorNum === 38) {
            return 'check_in';
        }
        if ($majorNum === 5 && $minorNum === 75) {
            return 'check_in';
        }
        if ($majorNum === 5 && $minorNum === 22) {
            return 'check_in';
        }
        if ($majorNum === 5 && $minorNum === 21) {
            return 'check_out';
        }
        if ($majorNum === 3) {
            return 'check_in';
        }
        if ($eventId !== null) {
            $eventId = strtolower((string) $eventId);
            if (str_contains($eventId, '1') || str_contains($eventId, 'open') || str_contains($eventId, 'pass')) {
                return 'check_in';
            }
            if (str_contains($eventId, '2') || str_contains($eventId, 'close') || str_contains($eventId, 'exit')) {
                return 'check_out';
            }
        }
        if ($majorNum === 0 && $minorNum === 0) {
            return 'check_in';
        }

        return 'check_in';
    }

    private function pick(array $event, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $event) && $event[$key] !== null && $event[$key] !== '') {
                return (string) $event[$key];
            }
        }
        return null;
    }

    private function detectContentType(string $rawBody): string
    {
        $trimmed = ltrim($rawBody);
        if (str_starts_with($trimmed, '<?xml')) {
            return 'xml';
        }
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return 'json';
        }
        return 'json';
    }

    private function extractNumOfMatches(string $rawBody, string $contentType): int
    {
        if ($contentType === 'xml') {
            libxml_use_internal_errors(true);
            $doc = simplexml_load_string($rawBody);
            libxml_clear_errors();
            if ($doc !== false) {
                $nodes = $doc->xpath('//*[local-name()="numOfMatches"]');
                if ($nodes !== false && !empty($nodes[0])) {
                    return (int) (string) $nodes[0];
                }
            }
        } else {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                if (isset($decoded['AcsEvent']['numOfMatches'])) {
                    return (int) $decoded['AcsEvent']['numOfMatches'];
                }
                if (isset($decoded['numOfMatches'])) {
                    return (int) $decoded['numOfMatches'];
                }
            }
        }
        return 0;
    }

    private function extractTotalMatches(string $rawBody, string $contentType): int
    {
        if ($contentType === 'xml') {
            libxml_use_internal_errors(true);
            $doc = simplexml_load_string($rawBody);
            libxml_clear_errors();
            if ($doc !== false) {
                $nodes = $doc->xpath('//*[local-name()="totalMatches"]');
                if ($nodes !== false && !empty($nodes[0])) {
                    return (int) (string) $nodes[0];
                }
                $nodes = $doc->xpath('//*[local-name()="numOfMatches"]');
                if ($nodes !== false && !empty($nodes[0])) {
                    return (int) (string) $nodes[0];
                }
            }
        } else {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                if (isset($decoded['AcsEvent']['totalMatches'])) {
                    return (int) $decoded['AcsEvent']['totalMatches'];
                }
                if (isset($decoded['totalMatches'])) {
                    return (int) $decoded['totalMatches'];
                }
                if (isset($decoded['AcsEvent']['numOfMatches'])) {
                    return (int) $decoded['AcsEvent']['numOfMatches'];
                }
                if (isset($decoded['numOfMatches'])) {
                    return (int) $decoded['numOfMatches'];
                }
            }
        }
        return 0;
    }

    private function formatHttpError(int $httpCode, string $responseBody): string
    {
        if ($httpCode === 400 && !empty($responseBody)) {
            $decoded = json_decode($responseBody, true);
            if (is_array($decoded)) {
                $parts = [];
                if (isset($decoded['statusCode'])) {
                    $parts[] = 'statusCode=' . $decoded['statusCode'];
                }
                if (isset($decoded['statusString'])) {
                    $parts[] = 'statusString=' . $decoded['statusString'];
                }
                if (isset($decoded['subStatusCode'])) {
                    $parts[] = 'subStatusCode=' . $decoded['subStatusCode'];
                }
                if (isset($decoded['errorMsg'])) {
                    $parts[] = 'errorMsg=' . $decoded['errorMsg'];
                }
                if (!empty($parts)) {
                    return 'Hikvision returned HTTP 400: ' . implode(', ', $parts);
                }
                if (str_contains($responseBody, 'notSupport')) {
                    return 'Hikvision returned HTTP 400: notSupport (request format not supported by this firmware)';
                }
                if (str_contains($responseBody, 'Invalid Operation')) {
                    return 'Hikvision returned HTTP 400: Invalid Operation (request format not supported)';
                }
                return 'HTTP 400: ' . substr($responseBody, 0, 500);
            }
        }

        if ($httpCode === 401) {
            return 'Authentication failed (HTTP 401)';
        }

        if ($httpCode === 404) {
            return 'HTTP 404: endpoint not found on device';
        }

        return 'HTTP error ' . $httpCode;
    }

    private function fetchUserSearchPage(int|string $searchId, int $position, int $maxResults): array
    {
        $result = [
            'success' => false,
            'data' => [],
            'error' => null,
            'http_code' => 0,
            'raw_response' => null,
            'total_matches' => 0,
        ];

        $xmlBody = '<?xml version="1.0" encoding="UTF-8"?>
<UserInfoSearchCond>
    <searchID>' . htmlspecialchars((string) $searchId, ENT_XML1 | ENT_QUOTES) . '</searchID>
    <searchResultPosition>' . $position . '</searchResultPosition>
    <maxResults>' . $maxResults . '</maxResults>
</UserInfoSearchCond>';

        $xmlUrl = ($this->https ? 'https' : 'http') . '://' . $this->ip . ':' . $this->port . '/ISAPI/AccessControl/UserInfo/Search';

        $this->logRequest('ISAPI_USER_REQUEST', $xmlUrl, $xmlBody, ['method' => 'POST', 'path' => '/ISAPI/AccessControl/UserInfo/Search', 'format' => 'xml']);

        $ch = curl_init($xmlUrl);
        if ($ch !== false) {
            $responseHeaders = [];
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
                $responseHeaders[] = $header;
                return strlen($header);
            });

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
                CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
                CURLOPT_USERPWD => $this->username . ':' . $this->password,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $xmlBody,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/xml',
                    'Content-Length: ' . strlen($xmlBody),
                    'Accept: application/xml, application/json, */*',
                    'Connection: close',
                ],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            if ($response !== false) {
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $this->lastHttpCode = $httpCode;
                $this->lastResponseBody = (string) $response;
                $this->lastRequestBody = $xmlBody;
                $this->lastRequestUrl = $xmlUrl;
                $result['http_code'] = $httpCode;
                $result['raw_response'] = $this->lastResponseBody;

                $this->logResponse('ISAPI_USER_RESPONSE', $xmlUrl, $xmlBody, $this->lastResponseBody, null, $httpCode);
                $this->saveRawHikvisionResponse('/ISAPI/AccessControl/UserInfo/Search', $xmlBody, $httpCode, $responseHeaders, $this->lastResponseBody, 0, 100);

                if ($httpCode === 200 && !empty($this->lastResponseBody)) {
                    $decoded = json_decode($this->lastResponseBody, true);
                    if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                        $users = $this->parseJsonUsers($decoded);
                        $totalMatches = $this->extractUserTotalMatches($decoded);
                        $result['success'] = true;
                        $result['data'] = $users;
                        $result['total_matches'] = $totalMatches;
                        return $result;
                    }
                    $users = $this->parseXmlUsers($this->lastResponseBody);
                    if (!empty($users)) {
                        $totalMatches = $this->extractUserTotalMatchesFromXml($this->lastResponseBody);
                        $result['success'] = true;
                        $result['data'] = $users;
                        $result['total_matches'] = $totalMatches;
                        return $result;
                    }
                }
            } else {
                curl_close($ch);
            }
        }

        $jsonBody = json_encode(['UserInfoSearchCond' => [
            'searchID' => (string) $searchId,
            'searchResultPosition' => $position,
            'maxResults' => $maxResults,
        ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $jsonUrl = ($this->https ? 'https' : 'http') . '://' . $this->ip . ':' . $this->port . '/ISAPI/AccessControl/UserInfo/Search?format=json';

        $this->logRequest('ISAPI_USER_REQUEST', $jsonUrl, $jsonBody, ['method' => 'POST', 'path' => '/ISAPI/AccessControl/UserInfo/Search?format=json', 'format' => 'json']);

        $ch = curl_init($jsonUrl);
        if ($ch === false) {
            $result['error'] = 'curl_init failed';
            return $result;
        }

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $responseHeaders[] = $header;
            return strlen($header);
        });

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonBody),
                'Accept: application/xml, application/json, */*',
                'Connection: close',
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $this->lastError = curl_error($ch);
            $this->logResponse('ISAPI_USER_RESPONSE_ERROR', $jsonUrl, $jsonBody, null, $this->lastError);
            curl_close($ch);
            $result['error'] = 'cURL error: ' . $this->lastError;
            return $result;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->lastHttpCode = $httpCode;
        $this->lastResponseBody = (string) $response;
        $this->lastRequestBody = $jsonBody;
        $this->lastRequestUrl = $jsonUrl;
        $result['http_code'] = $httpCode;
        $result['raw_response'] = $this->lastResponseBody;

        $this->logResponse('ISAPI_USER_RESPONSE', $jsonUrl, $jsonBody, $this->lastResponseBody, null, $httpCode);
        $this->saveRawHikvisionResponse('/ISAPI/AccessControl/UserInfo/Search?format=json', $jsonBody, $httpCode, $responseHeaders, $this->lastResponseBody, $position, $maxResults);

        if ($httpCode === 404) {
            $result['error'] = 'User search endpoint not found';
            return $result;
        }

        if ($httpCode !== 200) {
            if ($httpCode === 400 && str_contains($this->lastResponseBody, 'notSupport')) {
                $xmlResult = $this->tryUserXmlFallback($searchId, $position, $maxResults);
                if ($xmlResult['success']) {
                    $result['success'] = true;
                    $result['data'] = $xmlResult['data'];
                    $result['total_matches'] = $xmlResult['total_matches'] ?? 0;
                    return $result;
                }
            }
            $result['error'] = $this->formatHttpError($httpCode, $this->lastResponseBody);
            return $result;
        }

        if (empty($this->lastResponseBody)) {
            $result['error'] = 'Empty response from terminal';
            return $result;
        }

        $decoded = json_decode($this->lastResponseBody, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            $xmlResult = $this->tryUserXmlFallback($searchId, $position, $maxResults);
            if ($xmlResult['success']) {
                $result['success'] = true;
                $result['data'] = $xmlResult['data'];
                $result['total_matches'] = $xmlResult['total_matches'] ?? 0;
                return $result;
            }
            $result['error'] = 'Invalid JSON response: ' . json_last_error_msg();
            return $result;
        }

        $users = $this->parseJsonUsers($decoded);
        $totalMatches = $this->extractUserTotalMatches($decoded);

        $result['success'] = true;
        $result['data'] = $users;
        $result['total_matches'] = $totalMatches;
        return $result;
    }

    private function tryUserXmlFallback(int|string $searchId, int $position, int $maxResults): array
    {
        $url = ($this->https ? 'https' : 'http') . '://' . $this->ip . ':' . $this->port . '/ISAPI/AccessControl/UserInfo/Search';
        $body = '<?xml version="1.0" encoding="UTF-8"?>
<UserInfoSearchCond>
    <searchID>' . htmlspecialchars((string) $searchId, ENT_XML1 | ENT_QUOTES) . '</searchID>
    <searchResultPosition>' . $position . '</searchResultPosition>
    <maxResults>' . $maxResults . '</maxResults>
</UserInfoSearchCond>';

        $ch = curl_init($url);
        if ($ch === false) {
            return ['success' => false, 'error' => 'curl_init failed'];
        }

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $responseHeaders[] = $header;
            return strlen($header);
        });

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/xml',
                'Content-Length: ' . strlen($body),
                'Accept: application/xml, application/json, */*',
                'Connection: close',
            ],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $this->lastError = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'error' => 'cURL error: ' . curl_error($ch)];
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->saveRawHikvisionResponse('/ISAPI/AccessControl/UserInfo/Search', $body, $httpCode, $responseHeaders, (string) $response, $position, $maxResults);

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'XML fallback HTTP ' . $httpCode];
        }

        $this->lastResponseBody = (string) $response;
        $this->lastRequestUrl = $url;
        $this->lastRequestBody = $body;

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($this->lastResponseBody);
        libxml_clear_errors();

        if ($doc === false) {
            return ['success' => false, 'error' => 'XML parse error in fallback'];
        }

        $users = [];
        $userNodes = $doc->xpath('//*[local-name()="UserInfo"]');
        if ($userNodes !== false) {
            foreach ($userNodes as $node) {
                $users[] = [
                    'employeeNo' => $this->xmlValue($node, 'employeeNo') ?? '',
                    'employeeNoString' => $this->xmlValue($node, 'employeeNoString') ?? '',
                    'name' => $this->xmlValue($node, 'name') ?? '',
                    'userType' => $this->xmlValue($node, 'userType') ?? 'normal',
                    'Valid' => null,
                    'doorRight' => $this->xmlValue($node, 'doorRight') ?? '',
                    'password' => $this->xmlValue($node, 'password') ?? '',
                    'localUIRight' => $this->xmlValue($node, 'localUIRight') ?? '',
                    'cardNo' => $this->xmlValue($node, 'cardNo') ?? '',
                    'id' => $this->xmlValue($node, 'id') ?? '',
                    'faceURL' => $this->xmlValue($node, 'faceURL') ?? '',
                ];
            }
        }

        $totalMatches = 0;
        $nodes = $doc->xpath('//*[local-name()="totalMatches"]');
        if ($nodes !== false && !empty($nodes[0])) {
            $totalMatches = (int) (string) $nodes[0];
        }
        $nodes = $doc->xpath('//*[local-name()="numOfMatches"]');
        if ($nodes !== false && !empty($nodes[0])) {
            $totalMatches = (int) (string) $nodes[0];
        }

        return [
            'success' => true,
            'data' => $users,
            'total_matches' => $totalMatches,
            'raw_response' => $this->lastResponseBody,
        ];
    }

    private function parseXmlUsers(string $xml): array
    {
        $users = [];

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();

        if ($doc === false) {
            return $users;
        }

        $userNodes = $doc->xpath('//*[local-name()="UserInfo"]');
        if ($userNodes === false) {
            return $users;
        }

        foreach ($userNodes as $node) {
            if (!($node instanceof \SimpleXMLElement)) {
                continue;
            }
                $users[] = [
                    'employeeNo' => $this->xmlValue($node, 'employeeNo') ?? '',
                    'employeeNoString' => $this->xmlValue($node, 'employeeNoString') ?? '',
                    'name' => $this->xmlValue($node, 'name') ?? '',
                    'userType' => $this->xmlValue($node, 'userType') ?? 'normal',
                    'Valid' => null,
                    'doorRight' => $this->xmlValue($node, 'doorRight') ?? '',
                    'password' => $this->xmlValue($node, 'password') ?? '',
                    'localUIRight' => $this->xmlValue($node, 'localUIRight') ?? '',
                    'cardNo' => $this->xmlValue($node, 'cardNo') ?? '',
                    'id' => $this->xmlValue($node, 'id') ?? '',
                    'faceURL' => $this->xmlValue($node, 'faceURL') ?? '',
                ];
        }

        return $users;
    }

    private function extractUserTotalMatchesFromXml(string $xml): int
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();

        if ($doc === false) {
            return 0;
        }

        $totalMatches = 0;
        $nodes = $doc->xpath('//*[local-name()="totalMatches"]');
        if ($nodes !== false && !empty($nodes[0])) {
            $totalMatches = (int) (string) $nodes[0];
        }
        $nodes = $doc->xpath('//*[local-name()="numOfMatches"]');
        if ($nodes !== false && !empty($nodes[0])) {
            $totalMatches = (int) (string) $nodes[0];
        }

        return $totalMatches;
    }

    private function parseJsonUsers(array $decoded): array
    {
        $users = [];
        $candidates = $this->findUserArraysRecursive($decoded);
        foreach ($candidates as $list) {
            foreach ($list as $user) {
                if (is_array($user)) {
                    $users[] = $this->normalizeUserInfo($user);
                }
            }
        }
        return $users;
    }

    private function findUserArraysRecursive(mixed $data): array
    {
        $found = [];
        if (!is_array($data)) {
            return $found;
        }

        $count = 0;
        $userLike = 0;
        foreach ($data as $item) {
            if (is_array($item)) {
                $count++;
                $userLike += $this->countUserFields($item);
            }
        }

        if ($count > 0 && $userLike > 0 && $userLike >= $count * 0.3) {
            return [$data];
        }

        foreach ($data as $value) {
            $nested = $this->findUserArraysRecursive($value);
            if (!empty($nested)) {
                $found = array_merge($found, $nested);
            }
        }

        return $found;
    }

    private function countUserFields(array $item): int
    {
        $count = 0;
        $keys = array_keys($item);
        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }
            $kl = strtolower((string) $key);
            if (in_array($kl, ['employeeno', 'employeenostring', 'employeename', 'name', 'username', 'cardno', 'cardnumber', 'badgeno', 'userid', 'usertype', 'doorright', 'localuiright', 'password'])) {
                $count++;
            }
        }
        return $count;
    }

    private function normalizeUserInfo(array $user): array
    {
        return [
            'employeeNo' => (string) ($this->pick($user, ['employeeNo','EmployeeNo','employeeNO','EmployeeNO','employeeID','EmployeeID','employeeId','EmployeeId','personId','PersonId','personID','PersonID','userID','UserID','userId','UserId','userNo','UserNo','cardHolderNo','CardHolderNo','cardHolderID','CardHolderID','employeeNumber','EmployeeNumber','empNo','EmpNo','staffNo','StaffNo','personNo','PersonNo']) ?? ''),
            'employeeNoString' => (string) ($this->pick($user, ['employeeNoString','EmployeeNoString','employeeNoStr','employeeNoStrId','employeeNoStrID']) ?? ''),
            'name' => (string) ($this->pick($user, ['name','Name','employeeName','EmployeeName','personName','PersonName','userName','UserName','displayName','DisplayName','fullName','FullName','firstName','FirstName','lastName','LastName']) ?? ''),
            'userType' => (string) ($this->pick($user, ['userType','UserType']) ?? 'normal'),
            'Valid' => $user['Valid'] ?? $user['valid'] ?? null,
            'doorRight' => (string) ($this->pick($user, ['doorRight','DoorRight','doorRightPlan']) ?? ''),
            'password' => (string) ($this->pick($user, ['password','Password']) ?? ''),
            'localUIRight' => (string) ($this->pick($user, ['localUIRight','LocalUIRight']) ?? ''),
            'cardNo' => (string) ($this->pick($user, ['cardNo','CardNo','cardNumber','CardNumber','badgeNo','BadgeNo','badgeNumber','BadgeNumber','cardId','CardId','cardID','CardID','accessCard','AccessCard','cardRef','CardRef','badgeId','BadgeId','badgeID','BadgeID']) ?? ''),
            'id' => (string) ($this->pick($user, ['id','ID','userId','UserId','userID','UserID']) ?? ''),
            'faceURL' => (string) ($this->pick($user, ['faceURL','FaceUrl','faceUrl','FaceURL','FacePhotoURL','facePhotoUrl']) ?? ''),
        ];
    }

    private function extractUserTotalMatches(array $decoded): int
    {
        if (isset($decoded['UserInfoSearch']['totalMatches'])) {
            return (int) $decoded['UserInfoSearch']['totalMatches'];
        }
        if (isset($decoded['UserInfoSearch']['numOfMatches'])) {
            return (int) $decoded['UserInfoSearch']['numOfMatches'];
        }
        if (isset($decoded['totalMatches'])) {
            return (int) $decoded['totalMatches'];
        }
        if (isset($decoded['numOfMatches'])) {
            return (int) $decoded['numOfMatches'];
        }
        return 0;
    }

    private function saveSyncDebugLog(string $status, array $result, ?string $startTime, ?string $endTime): void
    {
        try {
            if (!is_dir($this->debugDir)) {
                @mkdir($this->debugDir, 0755, true);
            }

            $timestamp = date('Y-m-d H:i:s');
            $lines = [];
            $lines[] = '=== Hikvision ISAPI Sync Debug [' . $timestamp . '] ===';
            $lines[] = 'Terminal: ' . $this->ip . ':' . $this->port;
            $lines[] = 'Status: ' . $status;
            $lines[] = 'Endpoint: ' . ($this->lastRequestUrl ?? 'N/A');
            $lines[] = 'HTTP Code: ' . ($this->lastHttpCode ?? 0);
            $lines[] = 'StartTime: ' . $startTime;
            $lines[] = 'EndTime: ' . $endTime;
            $lines[] = 'Parser: ' . ($this->lastParserType ?? 'N/A');
            $lines[] = 'Parsed Events: ' . ($this->lastParsedEventCount ?? 0);
            $lines[] = 'Request Body:';
            $lines[] = substr((string) ($this->lastRequestBody ?? ''), 0, 2000);
            $lines[] = 'Response:';
            $lines[] = substr((string) ($this->lastResponseBody ?? ''), 0, 5000);
            $lines[] = 'Error: ' . ($result['error'] ?? 'None');
            $lines[] = str_repeat('=', 60);
            $lines[] = '';

            @file_put_contents($this->debugDir . DIRECTORY_SEPARATOR . 'sync_debug.log', implode(PHP_EOL, $lines), FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // ignore logging errors
        }
    }

    private function logRequest(string $step, string $url, string $body, array $endpoint): void
    {
        try {
            $logDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $timestamp = (new \DateTime())->format('Y-m-d H:i:s.u');
            $lines = [];
            $lines[] = '[' . $timestamp . '] [' . $step . ']';
            $lines[] = '  URL: ' . $url;
            $lines[] = '  Method: ' . ($endpoint['method'] ?? 'POST');
            $lines[] = '  Endpoint Path: ' . ($endpoint['path'] ?? 'N/A');
            $lines[] = '  Format: ' . ($endpoint['format'] ?? 'json');
            $lines[] = '  Request Body:';
            $lines[] = substr($body, 0, 3000);
            $lines[] = '';

            @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'isapi_request.log', implode(PHP_EOL, $lines), FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // ignore logging errors
        }
    }

    private function logResponse(string $step, string $url, string $requestBody, ?string $responseBody, ?string $error, int $httpCode = 0, float $elapsedMs = 0): void
    {
        try {
            $logDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $timestamp = (new \DateTime())->format('Y-m-d H:i:s.u');
            $lines = [];
            $lines[] = '[' . $timestamp . '] [' . $step . ']';
            $lines[] = '  URL: ' . $url;
            $lines[] = '  HTTP Code: ' . $httpCode;
            $lines[] = '  Elapsed: ' . number_format($elapsedMs, 2) . ' ms';
            $lines[] = '  Error: ' . ($error ?? 'None');
            $lines[] = '  Request Body (sent):';
            $lines[] = substr($requestBody ?? '', 0, 2000);
            $lines[] = '  Response Body (first 5000 chars):';
            $lines[] = substr((string) $responseBody, 0, 5000);
            $lines[] = '';

            @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'isapi_response.log', implode(PHP_EOL, $lines), FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
        }
    }

    private function saveRawHikvisionResponse(string $endpoint, string $requestBody, int $httpCode, array $responseHeaders, string $rawBody, int $searchResultPosition, int $maxResults): void
    {
        try {
            $logDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $totalMatches = 0;
            $numOfMatches = 0;

            if (preg_match('/"totalMatches":\s*(\d+)/', $rawBody, $m)) {
                $totalMatches = (int) $m[1];
            } elseif (preg_match('/<totalMatches>(\d+)<\/totalMatches>/', $rawBody, $m)) {
                $totalMatches = (int) $m[1];
            }

            if (preg_match('/"numOfMatches":\s*(\d+)/', $rawBody, $m)) {
                $numOfMatches = (int) $m[1];
            } elseif (preg_match('/<numOfMatches>(\d+)<\/numOfMatches>/', $rawBody, $m)) {
                $numOfMatches = (int) $m[1];
            }

            $entry = [
                'timestamp' => (new \DateTime())->format('c'),
                'endpoint' => $endpoint,
                'request_body' => $requestBody,
                'http_code' => $httpCode,
                'response_headers' => $responseHeaders,
                'raw_body' => $rawBody,
                'pagination' => [
                    'searchResultPosition' => $searchResultPosition,
                    'maxResults' => $maxResults,
                    'totalMatches' => $totalMatches,
                    'numOfMatches' => $numOfMatches,
                ],
            ];

            $filePath = $logDir . DIRECTORY_SEPARATOR . 'raw_hikvision_response.json';
            $existing = [];
            if (file_exists($filePath)) {
                $content = @file_get_contents($filePath);
                if (is_string($content) && $content !== '') {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded)) {
                        $existing = $decoded;
                    }
                }
            }

            $existing[] = $entry;
            if (count($existing) > 200) {
                $existing = array_slice($existing, -200);
            }

            @file_put_contents($filePath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        } catch (\Throwable $e) {
        }
    }
}
