<?php

/*
 * Generated from eg.profile.proto
 * (written manually, but very similar to autogen)
 */

namespace PB\eg_profile\Services;

use KPHP\Grpc\GrpcUnaryCall;

class FaceRecognitionService extends \KPHP\Grpc\GrpcServiceBase {
  public function __construct(\KPHP\Grpc\GrpcChannel $connection) {
    parent::__construct($connection);
  }

  public function getImageSize(\PB\eg_profile\Messages\ImageSizeRequest $arg): GrpcUnaryCall {
    return $this->makeUnaryCall($arg, '/eg.profile.DemoImageSizeService/getImageSize');
  }

  public function updateProfileImages(\PB\eg_profile\Messages\UpdateProfileImagesRequest $arg): GrpcUnaryCall {
    return $this->makeUnaryCall($arg, '/eg.profile.DemoImageSizeService/updateProfileImages');
  }
}

