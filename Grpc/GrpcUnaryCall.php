<?php

namespace KPHP\Grpc;

class GrpcUnaryCall {
  /** @var GrpcChannel */
  private $channel;
  /** @var string */
  private $methodName;
  /** @var string */
  private $curlBinaryData;
  /** @var string[] */
  private $customHttp2Headers = [];
  /** @var int */
  private $customCallTimeoutMs = 0;
  /** @var int */
  private $customConnectionTimeoutMs = 0;
  /** @var bool */
  private $logIfFail = true;
  /**
   * null - request not sent
   * int - request is in progress
   * false - request is finished
   * @var null|int|false
   */
  private $processingCurlHandlerId = null;

  /**
   * While serializing protobuf messages for gRPC, there is an optimization, not to concatenate strings afterwards:
   * @see \KPHP\Protobuf\StreamDecoder::fromCurlResponse()
   * @see \KPHP\Protobuf\StreamEncoder::startCurlRequest()
   * Hence, we have curlBinaryData, not protobuf data.
   */
  public function __construct(GrpcChannel $channel, string $methodName, string $curlBinaryData) {
    $this->channel = $channel;
    $this->methodName = $methodName;
    $this->curlBinaryData = $curlBinaryData;
  }

  /**
   * @kphp-inline
   * Add 1 custom http header
   */
  public function withCustomHttp2Header(string $headerName, string $headerValue): self {
    $this->customHttp2Headers[$headerName] = $headerValue;
    return $this;
  }

  /**
   * @kphp-inline
   * Add multiple custom http headers (key-value)
   * @param string[] $headersAssoc
   */
  public function withCustomHttp2Headers(array $headersAssoc): self {
    array_merge_into($this->customHttp2Headers, $headersAssoc);
    return $this;
  }

  /**
   * @kphp-inline
   */
  public function withCustomCallTimeout(int $customCallTimeoutMs): self {
    $this->customCallTimeoutMs = $customCallTimeoutMs;
    return $this;
  }

  /**
   * @kphp-inline
   */
  public function withCustomConnectionTimeout(int $customConnectionTimeoutMs): self {
    $this->customConnectionTimeoutMs = $customConnectionTimeoutMs;
    return $this;
  }

  /**
   * @kphp-inline
   */
  public function withoutLoggingOnFail(): self {
    $this->logIfFail = false;
    return $this;
  }

  /**
   * Send a query, using curl+http/2 as gRPC transport layer and having pre-serialized protobuf data.
   * This call is asynchronous: after send() script remains executing and would block on get() call.
   */
  public function send(bool $trySendImmediately = true): self {
    if ($this->processingCurlHandlerId === null) {
      $this->processingCurlHandlerId = $this->channel->sendAsync(
        $this->methodName, $this->curlBinaryData, $this->customHttp2Headers,
        $this->customCallTimeoutMs, $this->customConnectionTimeoutMs, $trySendImmediately);
    } else {
      warning("Can't send gRPC request to ".$this->channel->getRemoteHost().$this->methodName.": already sent");
    }
    $this->curlBinaryData = '';
    $this->customHttp2Headers = [];
    return $this;
  }

  /**
   * Get a result of a query sent earlier with get(). A blocking call (if an answer not received yet, wait).
   * @return string|null Error or null (if not error - $outResult would be filled)
   */
  public function get(\KPHP\Protobuf\ProtobufMessage $outResult) {
    if ($this->processingCurlHandlerId === null || $this->processingCurlHandlerId === false) {
      $curlBytes = '';
      $errStr = $this->processingCurlHandlerId === null ? "using get() without send()" : "using get() after get()";
      $errTag = 'bad_call_usage';
      warning("Can't get gRPC result from ".$this->channel->getRemoteHost().$this->methodName.": ".$errStr);
    } else {
      list($curlBytes, $errStr, $errTag) = $this->channel->getResult((int)$this->processingCurlHandlerId);
      $this->processingCurlHandlerId = false;
    }

    if ($errStr === null) {
      $decoder = \KPHP\Protobuf\StreamDecoder::fromCurlResponse($curlBytes);
      $outResult->protoDecode($decoder);
      if ($decoder->wasDecodingError()) {
        $errStr = "protobuf decoding error";
      }
    }

    if ($errStr !== null && $this->logIfFail) {
      // todo insert your logging logic here, like
      // logfailedGRPCQuery($this->channel->getRemoteHost(), $this->methodName, (string)$errStr, (string)$errTag);
    }

    return $errStr;
  }

  /**
   * Send a query and wait for the response (send+get).
   * @return string|null Error or null (if not error - $outResult would be filled)
   */
  public function call(\KPHP\Protobuf\ProtobufMessage $outResult) {
    $this->send(false);
    return $this->get($outResult);
  }
}
