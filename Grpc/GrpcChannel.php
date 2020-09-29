<?php

namespace KPHP\Grpc;

class CurlHandler {
  /**
   * @kphp-const
   * In PHP, $rawCurlHandler is resource; in KPHP - int
   * @var int
   */
  public $rawCurlHandler;
  /**
   * @kphp-const
   * @var int
   */
  public $selfId = 0;
  /** @var bool */
  public $responseReady = false;
  /** @var bool */
  public $inProgress = false;

  private const DEFAULT_HTTP2_HEADERS = [
    'Content-Type: application/grpc+proto',
    'TE: trailers'
  ];

  public function __construct(int $selfId) {
    $this->rawCurlHandler = curl_init();
    $this->selfId = $selfId;
  }

  /**
   * @param string[] $customHttp2Headers
   */
  public function prepareCurlOptions(string $url, string $body, array $customHttp2Headers,
                                     int $callTimeoutMs, int $connectionTimeoutMs): void {
    curl_setopt($this->rawCurlHandler, CURLOPT_HTTP_VERSION, 5);  // CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE
    curl_setopt($this->rawCurlHandler, CURLOPT_POST, 1);
    curl_setopt($this->rawCurlHandler, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($this->rawCurlHandler, CURLOPT_PRIVATE, $this->selfId);
    curl_setopt($this->rawCurlHandler, CURLOPT_URL, $url);
    curl_setopt($this->rawCurlHandler, CURLOPT_POSTFIELDS, $body);
    curl_setopt($this->rawCurlHandler, CURLOPT_TIMEOUT_MS, $callTimeoutMs);
    curl_setopt($this->rawCurlHandler, CURLOPT_CONNECTTIMEOUT_MS, $connectionTimeoutMs);
    #ifndef KittenPHP
    curl_setopt($this->rawCurlHandler, CURLOPT_NOSIGNAL, 1);
    #endif

    // small optimization to avoid excess reallocations
    $headers = $customHttp2Headers ? $customHttp2Headers + self::DEFAULT_HTTP2_HEADERS : self::DEFAULT_HTTP2_HEADERS;
    curl_setopt($this->rawCurlHandler, CURLOPT_HTTPHEADER, $headers);
  }

  /**
   * @kphp-inline
   */
  public function anythingHasBeenSent(): bool {
    $uploaded_data_size = curl_getinfo($this->rawCurlHandler, CURLINFO_SIZE_UPLOAD);
    return is_numeric($uploaded_data_size) && $uploaded_data_size > 0;
  }

  public function getRequestDetails(): string {
    $curlInfo = curl_getinfo($this->rawCurlHandler);
    $details = '';
    $statusCode = $curlInfo['http_code'];
    if ($statusCode) {
      $details .= "http_code = $statusCode; ";
    }
    $localIp = $curlInfo['local_ip'];
    $localPort = $curlInfo['local_port'];
    if ($localIp || $localPort) {
      $details .= 'local address: '.($localIp ?: 'unknown').':'.($localPort ?: "unknown").'; ';
    }
    $remoteIp = $curlInfo['primary_ip'];
    $remotePort = $curlInfo['primary_port'];
    if ($remoteIp || $remotePort) {
      $details .= 'remote address: '.($remoteIp ?: 'unknown').':'.($remotePort ?: "unknown").'; ';
    }
    return $details;
  }
}

/**
 * "gRPC channel" actually is a set of curl handlers, unified to one curl multi handler
 * for a specifit removeHost (host:port or http[s]://host:port)
 */
class GrpcChannel {
  /**
   * In PHP, $curlMultiHandler is resource; in KPHP - int
   * @var int
   */
  private $curlMultiHandler = 0;
  /**
   * @kphp-const
   * @var string
   */
  private $remoteHost;
  /**
   * @kphp-const
   * @var int
   */
  private $defaultCallTimeoutMs;
  /**
   * @kphp-const
   * @var int
   */
  private $defaultConnectionTimeoutMs;
  /** @var CurlHandler[] */
  private $curlHandlerPool = [];

  // Minimally meaningful timeout (1ms) for curl_multi_select, values smaller will be rounded to 0
  private const MIN_SELECT_TIMEOUT = 0.001;

  /**
   * Extract id for $curlHandlerPool
   * In PHP, $curlHandlerRaw is resource, in KPHP - int
   * @param int $curlHandlerRaw
   */
  private static function getCurlHandlerIdFromRaw($curlHandlerRaw): int {
    return (int)curl_getinfo($curlHandlerRaw, CURLINFO_PRIVATE);
  }

  private function getCurlHandlerForSending(): CurlHandler {
    foreach ($this->curlHandlerPool as $curlHandler) {
      if (!$curlHandler->inProgress) {
        return $curlHandler;
      }
    }
    $curlHandler = new CurlHandler(count($this->curlHandlerPool));
    $this->curlHandlerPool[] = $curlHandler;
    return $curlHandler;
  }

  private function makeCurlErrorMessage(CurlHandler $curlHandler): string {
    $errorMsg = '';
    $curlError = curl_error($curlHandler->rawCurlHandler);
    if ($curlError) {
      $errorMsg = 'curl error: '.$curlError;
    }
    /** @noinspection KphpParameterTypeMismatchInspection */
    $multiErrno = curl_multi_errno($this->curlMultiHandler);
    if ($multiErrno !== CURLM_OK) {
      $errorMsg .= ($errorMsg ? '; ' : '').'curl multi error: '.curl_multi_strerror($multiErrno);
    }
    return $errorMsg;
  }

  private function dispatchAndWaitResponseFor(CurlHandler $curlHandler): void {
    $active = 0;
    do {
      if ($curlHandler->responseReady) {
        return;
      }

      $status = curl_multi_exec($this->curlMultiHandler, $active);
      $info = curl_multi_info_read($this->curlMultiHandler);
      while ($info !== false) {
        if (isset($info['handle'])) {
          $readyCurlHandlerId = self::getCurlHandlerIdFromRaw($info['handle']);
          if ($readyCurlHandlerId === $curlHandler->selfId) {
            return;
          }
          $this->curlHandlerPool[$readyCurlHandlerId]->responseReady = true;
        }
        $info = curl_multi_info_read($this->curlMultiHandler);
      }

      if ($active > 0) {
        sched_yield();
        curl_multi_select($this->curlMultiHandler, self::MIN_SELECT_TIMEOUT);
      }
    } while ($active && $status === CURLM_OK);
  }

  private function trySendImmediately(CurlHandler $curlHandler): void {
    $active = 0;
    // our goal here: try to connect and send query, but if fails, do it in wait()
    curl_multi_exec($this->curlMultiHandler, $active);
    if ($active > 0 && !$curlHandler->anythingHasBeenSent()) {
      // there is a chance, that we exit curl_multi_select with another event actually
      curl_multi_select($this->curlMultiHandler, self::MIN_SELECT_TIMEOUT);
      curl_multi_exec($this->curlMultiHandler, $active);
    }
  }

  public function __construct(string $remoteHost, int $defaultCallTimeoutMs = 2500, int $defaultConnectionTimeoutMs = 500) {
    $this->remoteHost = $remoteHost;
    $this->defaultCallTimeoutMs = $defaultCallTimeoutMs;
    $this->defaultConnectionTimeoutMs = $defaultConnectionTimeoutMs;
    if ($remoteHost !== '') {
      /** @noinspection KphpAssignmentTypeMismatchInspection */
      $this->curlMultiHandler = curl_multi_init();
      curl_multi_setopt($this->curlMultiHandler, CURLMOPT_PIPELINING, 2);   // CURLPIPE_MULTIPLEX
    }
  }

  #ifndef KittenPHP
  public function __destruct() {
    // not to leak in PHP
    foreach ($this->curlHandlerPool as $curlHandler) {
      // all requests must be inactive
      curl_close($curlHandler->rawCurlHandler);
    }
    if ($this->remoteHost !== '') {
      curl_multi_close($this->curlMultiHandler);
    }
  }
  #endif

  /**
   * @kphp-inline
   */
  public static function createInvalidConnection(): self {
    return new GrpcChannel('');
  }

  /**
   * @kphp-inline
   */
  public function getRemoteHost(): string {
    return $this->remoteHost;
  }

  /**
   * @param string[] $customHttp2Headers
   */
  public function sendAsync(string $methodName, string $curlBinaryData, array $customHttp2Headers,
                            int $customCallTimeoutMs, int $customConnectionTimeoutMs, bool $trySendImmediately): int {
    if ($this->remoteHost === '') {
      // do nothing, we'll get a warning in getResult()
      return -1;
    }

    $curlHandler = $this->getCurlHandlerForSending();
    $curlHandler->prepareCurlOptions($this->remoteHost.$methodName, $curlBinaryData, $customHttp2Headers,
      $customCallTimeoutMs ?: $this->defaultCallTimeoutMs,
      $customConnectionTimeoutMs ?: $this->defaultConnectionTimeoutMs);
    $curlHandler->responseReady = false;
    $curlHandler->inProgress = true;

    curl_multi_add_handle($this->curlMultiHandler, $curlHandler->rawCurlHandler);
    if ($trySendImmediately) {
      $this->trySendImmediately($curlHandler);
    }
    return $curlHandler->selfId;
  }

  /**
   * @return \tuple(string, string|null, string|null) [$curlBytes; error null; error tag]
   */
  public function getResult(int $curlHandlerId) {
    if ($this->remoteHost === '') {
      return tuple('', 'Connection invalid; maybe, could not get proxy config?', 'bad_channel_usage');
    }
    if (!isset($this->curlHandlerPool[$curlHandlerId])) {
      return tuple('', "Got unknown curlHandlerId=$curlHandlerId", 'bad_channel_usage');
    }
    $curlHandler = $this->curlHandlerPool[$curlHandlerId];
    if (!$curlHandler->inProgress) {
      return tuple('', "Passed curlHandlerId=$curlHandlerId hasn't been started", 'bad_channel_usage');
    }

    $this->dispatchAndWaitResponseFor($curlHandler);
    $curlBytes = curl_multi_getcontent($curlHandler->rawCurlHandler);
    curl_multi_remove_handle($this->curlMultiHandler, $curlHandler->rawCurlHandler);
    $curlHandler->inProgress = false;

    if ($curlBytes === null || $curlBytes === false) {
      $errorMessage = $this->makeCurlErrorMessage($curlHandler) ?: 'unknown curl error';
      $curlErrno = curl_errno($curlHandler->rawCurlHandler);
      curl_reset($curlHandler->rawCurlHandler);
      return tuple('', $errorMessage, "curl_errno_$curlErrno");
    }

    // an empty string returned - probably, incorrect method name
    if (strlen($curlBytes) == 0) {
      $details = $curlHandler->getRequestDetails().$this->makeCurlErrorMessage($curlHandler);
      $curlErrno = curl_errno($curlHandler->rawCurlHandler);
      curl_reset($curlHandler->rawCurlHandler);
      return tuple('', 'Invalid response: empty string, '.($details ?: "no details"), "curl_errno_$curlErrno");
    }

    curl_reset($curlHandler->rawCurlHandler);
    // '1' means packet is encoded, for now we accept only un-encoded packets
    if (ord($curlBytes[0]) !== 0) {
      if ($curlBytes[0] === '<') {    // probably html, "bad gateway" page for example
        return tuple('', 'Invalid response: '.str_replace(["\n", "\r"], '', $curlBytes), 'invalid_response');
      }
      return tuple('', 'encode bit is present, not supported', 'invalid_response');
    }

    // length of protobuf message
    $replyLength = unpack('N', substr($curlBytes, 1, 4))[1];

    // check length
    if ($replyLength + 5 !== strlen($curlBytes)) {
      return tuple('', 'bad reply_length', 'invalid_response');
    }

    // return curl response as is, do not extract protobuf message, not to slice strings with substr()
    return tuple((string)$curlBytes, null, null);
  }
}
