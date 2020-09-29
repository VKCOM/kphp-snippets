<?php

/*
 * Generated from eg.profile.proto
 * (written manually, but very similar to autogen)
 */

namespace PB\eg_profile\Messages;

class UpdateProfileImagesRequest implements \KPHP\Protobuf\ProtobufMessage {
  /** @var int */
  public $userId = 0;
  /** @var int */
  public $gender = 0;
  /** @var UpdateProfileImagesRequest_ProfileImageData[] */
  public $image = [];


  function protoEncode(\KPHP\Protobuf\StreamEncoder $encoder): void {
    $encoder->writeInt64Field(1, $this->userId);
    $encoder->writeUInt32Field(2, $this->gender);
    $encoder->writeMessageRepeatedField(3, $this->image);
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
          $decoder->readUInt32Field($tag, $this->gender);
          break;
        case 3:
          $this->image[] = new UpdateProfileImagesRequest_ProfileImageData();
          $decoder->readMessageRepeatedField($tag, $this->image);
          break;

        default:
          $decoder->readAndSkipUnknownField($tag);
      }
      $tag = $decoder->readTag();
    }
  }
}

