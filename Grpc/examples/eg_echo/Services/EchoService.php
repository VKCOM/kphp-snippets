<?php

/*
 * Generated from eg.echo.proto
 * (written manually, but very similar to autogen)
 */

namespace PB\eg_echo\Services;

use KPHP\Grpc\GrpcUnaryCall;

class EchoService extends \KPHP\Grpc\GrpcServiceBase {
  public function __construct(\KPHP\Grpc\GrpcChannel $connection) {
    parent::__construct($connection);
  }

  public function echo(\PB\eg_echo\Messages\PingMessage $arg): GrpcUnaryCall {
    return $this->makeUnaryCall($arg, '/eg.echo.EchoService/echo');
  }
}

