<?php

/*
 * Generated from eg.profile.proto
 * (written manually, but very similar to autogen)
 */

namespace PB\eg_profile\Messages;

class PointData implements \KPHP\Protobuf\ProtobufMessage {
  /** @var int */
  public $userId = 0;
  /** @var float */
  public $x1 = 0.0;
  /** @var float */
  public $x2 = 0.0;
  /** @var float */
  public $y1 = 0.0;
  /** @var float */
  public $y2 = 0.0;


  function protoEncode(\KPHP\Protobuf\StreamEncoder $encoder): void {
    $encoder->writeInt64Field(1, $this->userId);
    $encoder->writeFloatField(2, $this->x1);
    $encoder->writeFloatField(3, $this->x2);
    $encoder->writeFloatField(4, $this->y1);
    $encoder->writeFloatField(5, $this->y2);
  }

  function protoDecode(\KPHP\Protobuf\StreamDecoder $decoder): void {
    $tag = $decoder->readTag();
    while ($tag !== null) {
      $tag = (int)$tag;

      switch (\KPHP\Protobuf\ProtoTypes::getTagFieldNumber($tag)) {
        case 1:
          $decoder->readInt64Field($tag, $this->userId);
          break;
        case 2:
          $decoder->readFloatField($tag, $this->x1);
          break;
        case 3:
          $decoder->readFloatField($tag, $this->x2);
          break;
        case 4:
          $decoder->readFloatField($tag, $this->y1);
          break;
        case 5:
          $decoder->readFloatField($tag, $this->y2);
          break;

        default:
          $decoder->readAndSkipUnknownField($tag);
      }
      $tag = $decoder->readTag();
    }
  }
}

