<?php

/*
 * Generated from eg.profile.proto
 * (written manually, but very similar to autogen)
 */

namespace PB\eg_profile\Messages;

class UpdateProfileImagesRequest_ProfileImageData implements \KPHP\Protobuf\ProtobufMessage {
  /** @var int */
  public $ownerId = 0;
  /** @var int */
  public $photoId = 0;
  /** @var string[] */
  public $url = [];
  /** @var PointData[] */
  public $point = [];
  /** @var bool */
  public $avatar = false;


  function protoEncode(\KPHP\Protobuf\StreamEncoder $encoder): void {
    $encoder->writeInt32Field(1, $this->ownerId);
    $encoder->writeUInt32Field(2, $this->photoId);
    $encoder->writeStringRepeatedField(3, $this->url);
    $encoder->writeMessageRepeatedField(4, $this->point);
    $encoder->writeBoolField(5, $this->avatar);
  }

  function protoDecode(\KPHP\Protobuf\StreamDecoder $decoder): void {
    $tag = $decoder->readTag();
    while ($tag !== null) {
      $tag = (int)$tag;

      switch (\KPHP\Protobuf\ProtoTypes::getTagFieldNumber($tag)) {
        case 1:
          $decoder->readInt32Field($tag, $this->ownerId);
          break;
        case 2:
          $decoder->readUInt32Field($tag, $this->photoId);
          break;
        case 3:
          $decoder->readStringRepeatedField($tag, $this->url);
          break;
        case 4:
          $this->point[] = new PointData();
          $decoder->readMessageRepeatedField($tag, $this->point);
          break;
        case 5:
          $decoder->readBoolField($tag, $this->avatar);
          break;

        default:
          $decoder->readAndSkipUnknownField($tag);
      }
      $tag = $decoder->readTag();
    }
  }
}

