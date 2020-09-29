<?php

namespace KPHP\Protobuf;

/**
 * All objects, that can be encoded to binary protobuf string, must implement this interface.
 * In protobuf terms, they are "objects".
 * For now, [de]serialization is written manually, it's pretty easy, see examples.
 */
interface ProtobufMessage {

  function protoEncode(StreamEncoder $encoder): void;

  function protoDecode(StreamDecoder $decoder): void;

}
