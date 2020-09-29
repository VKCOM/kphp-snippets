<?php

namespace KPHP\Grpc;

/**
 * All gRPC services ("service" in .proto scheme) must extend this class.
 * It's pretty easy to write them manually based on scheme.
 */
class GrpcServiceBase {
  /** @var GrpcChannel */
  protected $connection;

  public function __construct(GrpcChannel $connection) {
    $this->connection = $connection;
  }

  protected function makeUnaryCall(\KPHP\Protobuf\ProtobufMessage $arg, string $methodName): GrpcUnaryCall {
    $encoder = \KPHP\Protobuf\StreamEncoder::startCurlRequest();
    $arg->protoEncode($encoder);
    return new GrpcUnaryCall($this->connection, $methodName, $encoder->getDataAndFlush());
  }
}
