<?php

/*
 * Generated from eg.profile.proto
 * (written manually, but very similar to autogen)
 */

namespace PB\eg_profile\Messages;

class ImageSizeRequest implements \KPHP\Protobuf\ProtobufMessage {
  /** @var ImageSizeRequest_DownloadData */
  public $image = null;


  function protoEncode(\KPHP\Protobuf\StreamEncoder $encoder): void {
    $encoder->writeMessageField(1, $this->image);
  }

  function protoDecode(\KPHP\Protobuf\StreamDecoder $decoder): void {
    $tag = $decoder->readTag();
    while ($tag !== null) {
      $tag = (int)$tag;

      switch (\KPHP\Protobuf\ProtoTypes::getTagFieldNumber($tag)) {
        case 1:
          $this->image = new ImageSizeRequest_DownloadData();
          $decoder->readMessageField($tag, $this->image);
          break;

        default:
          $decoder->readAndSkipUnknownField($tag);
      }
      $tag = $decoder->readTag();
    }
  }
}

