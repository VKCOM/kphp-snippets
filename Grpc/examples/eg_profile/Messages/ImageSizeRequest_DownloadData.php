<?php

/*
 * Generated from eg.profile.proto
 * (written manually, but very similar to autogen)
 */

namespace PB\eg_profile\Messages;

class ImageSizeRequest_DownloadData implements \KPHP\Protobuf\ProtobufMessage {
  /** @var int */
  public $ownerId = 0;
  /** @var int */
  public $photoId = 0;
  /** @var string */
  public $url = '';


  function protoEncode(\KPHP\Protobuf\StreamEncoder $encoder): void {
    $encoder->writeInt32Field(1, $this->ownerId);
    $encoder->writeUInt32Field(2, $this->photoId);
    $encoder->writeStringField(3, $this->url);
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
          $decoder->readStringField($tag, $this->url);
          break;

        default:
          $decoder->readAndSkipUnknownField($tag);
      }
      $tag = $decoder->readTag();
    }
  }
}

