<?php

/*
 * Generated from eg.echo.proto
 * (written manually, but very similar to autogen)
 */

namespace PB\eg_echo\Messages;

class PongMessage implements \KPHP\Protobuf\ProtobufMessage {
  /** @var string */
  public $message = '';


  function protoEncode(\KPHP\Protobuf\StreamEncoder $encoder): void {
    $encoder->writeStringField(1, $this->message);
  }

  function protoDecode(\KPHP\Protobuf\StreamDecoder $decoder): void {
    $tag = $decoder->readTag();
    while ($tag !== null) {
      $tag = (int)$tag;

      switch (\KPHP\Protobuf\ProtoTypes::getTagFieldNumber($tag)) {
        case 1:
          $decoder->readStringField($tag, $this->message);
          break;

        default:
          $decoder->readAndSkipUnknownField($tag);
      }
      $tag = $decoder->readTag();
    }
  }
}

