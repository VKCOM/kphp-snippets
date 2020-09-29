<?php

/*
 * Generated from eg.profile.proto
 * (written manually, but very similar to autogen)
 */

namespace PB\eg_profile\Messages;

class ImageSizeResult implements \KPHP\Protobuf\ProtobufMessage {
  /** @var PointData */
  public $point = null;


  function protoEncode(\KPHP\Protobuf\StreamEncoder $encoder): void {
    $encoder->writeMessageField(1, $this->point);
  }

  function protoDecode(\KPHP\Protobuf\StreamDecoder $decoder): void {
    $tag = $decoder->readTag();
    while ($tag !== null) {
      $tag = (int)$tag;

      switch (\KPHP\Protobuf\ProtoTypes::getTagFieldNumber($tag)) {
        case 1:
          $this->point = new PointData();
          $decoder->readMessageField($tag, $this->point);
          break;

        default:
          $decoder->readAndSkipUnknownField($tag);
      }
      $tag = $decoder->readTag();
    }
  }
}

