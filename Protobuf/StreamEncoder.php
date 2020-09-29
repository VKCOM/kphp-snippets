<?php

namespace KPHP\Protobuf;

/**
 * High-level encoder to a binary protobuf string.
 * Usage:
 * 1) create $encoder using one of static start*() methods
 * 2) fill $instance - an object implements ProtobufMessage, which to encode
 * 3) call $instance->proto_encode($encoder)
 * 4) $encoder->getDataAndFlush() - resulting protobuf binary string
 */
final class StreamEncoder {
  /** @var string */
  private $buffer;
  /** @var bool */
  private $created_for_curl;

  private const SKIP_TAG_SAVING = 0;

  // constructor is private on purpose: see static start*() methods
  private function __construct(string $initialBuffer, bool $created_for_curl) {
    $this->buffer = $initialBuffer;
    $this->created_for_curl = $created_for_curl;  // not nice, but better than inheritance and virtual calls
  }

  /**
   * Create an empty encoder to just protobuf data.
   * getDataAndFlush() will return a serialized protobuf string, for any usage and interop, decode it as
   * @see StreamDecoder::fromRawProtobufBytes()
   */
  public static function startRawProtobufBytes(): self {
    return new self('', false);
  }

  /**
   * Create an encoder, data from which would be later sent with gRPC.
   * This is optimization not to do excess copying and string allocations later (while sending curl request).
   * curl http/2 protocol for gRPC unary calls contains 5 bytes of prefix + protobuf data.
   * That 5 bytes would be filled in getDataAndFlush()
   * When decoding, there is a similar optimization:
   * @see StreamDecoder::fromCurlResponse()
   */
  public static function startCurlRequest(): self {
    return new self("\0\0\0\0\0", true);
  }

  /**
   * Nested messages in protobuf are encoded as [length][sub_message].
   * While encoding nested messages, create a separate buffer, length of which would be measured after filling.
   * @see StreamDecoder::fromEncodedSubMessage()
   */
  private static function startEncodedSubMessage(): self {
    return new self('', false);
  }

  private static function checkInt32Value(int $int32_value): int {
    $casted_int32_value = ProtoBytes::int32($int32_value);
    if ($casted_int32_value !== $int32_value) {
      warning("Got int32 overflow on encoding '$int32_value', the value will be casted to '$casted_int32_value'");
    }
    return $casted_int32_value;
  }

  private static function checkUInt32Value(int $uint32_value): int {
    $casted_uint32_value = $uint32_value & 0xFFFFFFFF;
    if ($casted_uint32_value !== $uint32_value) {
      warning("Got uint32 overflow on encoding '$uint32_value', the value will be casted to '$casted_uint32_value'");
    }
    return $casted_uint32_value;
  }

  public function getDataAndFlush(): string {
    // having $s[$i] = ..., KPHP infers, that $s is an array; hence, we can'y modify $this->buffer
    // workaround - create mixed, containing string, and modify it (it works like expected at runtime)
    /** @var mixed $buffer_var */
    $buffer_var = $this->buffer;    // this is mixed, referencing to the same string
    $this->buffer = '';             // leave refcnt = 1

    // in case of curl, we have 5 bytes at the beginning
    // 0-th remains \0, the rest 4 - big endian length of protobuf-data
    if ($this->created_for_curl) {
      $packed_len = pack("N", strlen($buffer_var) - 5);
      for ($i = 0; $i < 4; ++$i) {
        $buffer_var[1 + $i] = $packed_len[$i];
      }
    }

    return (string)$buffer_var;
  }

  private function writePackedRepeatedField(int $field_id, array $value_array, callable $value_writer) {
    $count = count($value_array);
    if ($count === 0) {
      return;
    }
    if ($count === 1) {
      $value_writer($field_id, $value_array[0]);
      return;
    }

    ProtoBytes::writeVarInt($this->buffer, ProtoTypes::makePackedTag($field_id));
    $saved_buffer = $this->buffer;
    $this->buffer = '';
    foreach ($value_array as $value) {
      $value_writer(self::SKIP_TAG_SAVING, $value);
    }
    $encoded_pack = $this->buffer;
    $this->buffer = $saved_buffer;
    ProtoBytes::writeBytes($this->buffer, $encoded_pack);
  }

  private function writeVarIntField(int $field_id, int $type, int $value, bool $write_default) {
    if ($value !== 0 || $write_default) {
      if ($field_id !== self::SKIP_TAG_SAVING) {
        ProtoBytes::writeVarInt($this->buffer, ProtoTypes::makeTag($field_id, $type));
      }
      ProtoBytes::writeVarInt($this->buffer, $value);
    }
  }

  public function writeInt32Field(int $field_id, int $int32_value, bool $write_default = false) {
    $this->writeVarIntField($field_id, ProtoTypes::INT32, self::checkInt32Value($int32_value), $write_default);
  }

  /**
   * @param int[] $int32_array
   */
  public function writeInt32RepeatedField(int $field_id, array $int32_array) {
    $this->writePackedRepeatedField($field_id, $int32_array,
      function(int $field_id, int $int32_value) {
        $this->writeInt32Field($field_id, $int32_value, true);
      });
  }

  public function writeInt64Field(int $field_id, int $int64_value, bool $write_default = false) {
    $this->writeVarIntField($field_id, ProtoTypes::INT64, $int64_value, $write_default);
  }

  /**
   * @param int[] $int64_array
   */
  public function writeInt64RepeatedField(int $field_id, array $int64_array) {
    $this->writePackedRepeatedField($field_id, $int64_array,
      function(int $field_id, int $int64_value) {
        $this->writeInt64Field($field_id, $int64_value, true);
      });
  }

  public function writeUInt32Field(int $field_id, int $uint32_value, bool $write_default = false) {
    $this->writeVarIntField($field_id, ProtoTypes::UINT32, self::checkUInt32Value($uint32_value), $write_default);
  }

  /**
   * @param int[] $uint32_array
   */
  public function writeUInt32RepeatedField(int $field_id, array $uint32_array) {
    $this->writePackedRepeatedField($field_id, $uint32_array,
      function(int $field_id, int $uint32_value) {
        $this->writeUInt32Field($field_id, $uint32_value, true);
      });
  }

  public function writeUInt64Field(int $field_id, int $uint64_value, bool $write_default = false) {
    $this->writeVarIntField($field_id, ProtoTypes::UINT64, $uint64_value, $write_default);
  }

  /**
   * @param int[] $uint64_array
   */
  public function writeUInt64RepeatedField(int $field_id, array $uint64_array) {
    $this->writePackedRepeatedField($field_id, $uint64_array,
      function(int $field_id, int $uint64_value) {
        $this->writeUInt64Field($field_id, $uint64_value, true);
      });
  }

  public function writeSInt32Field(int $field_id, int $int32_value, bool $write_default = false) {
    $encoded_int = ProtoBytes::zigZagEncode(self::checkInt32Value($int32_value));
    $this->writeVarIntField($field_id, ProtoTypes::SINT32, $encoded_int, $write_default);
  }

  /**
   * @param int[] $int32_array
   */
  public function writeSInt32RepeatedField(int $field_id, array $int32_array) {
    $this->writePackedRepeatedField($field_id, $int32_array,
      function(int $field_id, int $int32_value) {
        $this->writeSInt32Field($field_id, $int32_value, true);
      });
  }

  public function writeSInt64Field(int $field_id, int $int64_value, bool $write_default = false) {
    $this->writeVarIntField($field_id, ProtoTypes::SINT64, ProtoBytes::zigZagEncode($int64_value), $write_default);
  }

  /**
   * @param int[] $int64_array
   */
  public function writeSInt64RepeatedField(int $field_id, array $int64_array) {
    $this->writePackedRepeatedField($field_id, $int64_array,
      function(int $field_id, int $int64_value) {
        $this->writeSInt64Field($field_id, $int64_value, true);
      });
  }

  private function writeFixedField(int $field_id, int $type, int $value, int $bytes, bool $write_default) {
    if ($value !== 0 || $write_default) {
      if ($field_id !== self::SKIP_TAG_SAVING) {
        ProtoBytes::writeVarInt($this->buffer, ProtoTypes::makeTag($field_id, $type));
      }
      ProtoBytes::writeLittleEndianInt($this->buffer, $value, $bytes);
    }
  }

  public function writeFixed32Field(int $field_id, int $uint32_value, bool $write_default = false) {
    $this->writeFixedField($field_id, ProtoTypes::FIXED32, self::checkUInt32Value($uint32_value), 4, $write_default);
  }

  /**
   * @param int[] $uint32_array
   */
  public function writeFixed32RepeatedField(int $field_id, array $uint32_array) {
    $this->writePackedRepeatedField($field_id, $uint32_array,
      function(int $field_id, int $uint32_value) {
        $this->writeFixed32Field($field_id, $uint32_value, true);
      });
  }

  public function writeFixed64Field(int $field_id, int $uint64_value, bool $write_default = false) {
    $this->writeFixedField($field_id, ProtoTypes::FIXED64, $uint64_value, 8, $write_default);
  }

  /**
   * @param int[] $uint64_array
   */
  public function writeFixed64RepeatedField(int $field_id, array $uint64_array) {
    $this->writePackedRepeatedField($field_id, $uint64_array,
      function(int $field_id, int $uint64_value) {
        $this->writeFixed64Field($field_id, $uint64_value, true);
      });
  }

  public function writeSFixed32Field(int $field_id, int $int32_value, bool $write_default = false) {
    $this->writeFixedField($field_id, ProtoTypes::SFIXED32, self::checkInt32Value($int32_value), 4, $write_default);
  }

  /**
   * @param int[] $int32_array
   */
  public function writeSFixed32RepeatedField(int $field_id, array $int32_array) {
    $this->writePackedRepeatedField($field_id, $int32_array,
      function(int $field_id, int $int32_value) {
        $this->writeSFixed32Field($field_id, $int32_value, true);
      });
  }

  public function writeSFixed64Field(int $field_id, int $int64_value, bool $write_default = false) {
    $this->writeFixedField($field_id, ProtoTypes::SFIXED64, $int64_value, 8, $write_default);
  }

  /**
   * @param int[] $int64_array
   */
  public function writeSFixed64RepeatedField(int $field_id, array $int64_array) {
    $this->writePackedRepeatedField($field_id, $int64_array,
      function(int $field_id, int $int64_value) {
        $this->writeSFixed64Field($field_id, $int64_value, true);
      });
  }

  public function writeBoolField(int $field_id, bool $bool_value, bool $write_default = false) {
    $this->writeVarIntField($field_id, ProtoTypes::BOOL, $bool_value ? 1 : 0, $write_default);
  }

  /**
   * @param bool[] $bool_array
   */
  public function writeBoolRepeatedField(int $field_id, array $bool_array) {
    $this->writePackedRepeatedField($field_id, $bool_array,
      function(int $field_id, bool $bool_value) {
        $this->writeBoolField($field_id, $bool_value, true);
      });
  }

  private function writeRealNumberField(int $field_id, int $type, float $value, string $pack_type, bool $write_default) {
    if ($value !== 0.0 || $write_default) {
      if ($field_id !== self::SKIP_TAG_SAVING) {
        ProtoBytes::writeVarInt($this->buffer, ProtoTypes::makeTag($field_id, $type));
      }
      $this->buffer .= pack($pack_type, $value);
    }
  }

  public function writeFloatField(int $field_id, float $float_value, bool $write_default = false) {
    $this->writeRealNumberField($field_id, ProtoTypes::FLOAT, $float_value, 'f', $write_default);
  }

  /**
   * @param float[] $float_array
   */
  public function writeFloatRepeatedField(int $field_id, array $float_array) {
    $this->writePackedRepeatedField($field_id, $float_array,
      function(int $field_id, float $float_value) {
        $this->writeFloatField($field_id, $float_value, true);
      });
  }

  public function writeDoubleField(int $field_id, float $double_value, bool $write_default = false) {
    $this->writeRealNumberField($field_id, ProtoTypes::DOUBLE, $double_value, 'd', $write_default);
  }

  /**
   * @param float[] $double_array
   */
  public function writeDoubleRepeatedField(int $field_id, array $double_array) {
    $this->writePackedRepeatedField($field_id, $double_array,
      function(int $field_id, float $double_value) {
        $this->writeDoubleField($field_id, $double_value, true);
      });
  }

  public function writeStringField(int $field_id, string $string_value, bool $write_default = false) {
    if ($string_value !== '' || $write_default) {
      ProtoBytes::writeVarInt($this->buffer, ProtoTypes::makeTag($field_id, ProtoTypes::STRING));
      ProtoBytes::writeBytes($this->buffer, $string_value);
    }
  }

  /**
   * @param string[] $string_array
   */
  public function writeStringRepeatedField(int $field_id, array $string_array) {
    foreach ($string_array as $string_value) {
      $this->writeStringField($field_id, $string_value, true);
    }
  }

  public function writeBytesField(int $field_id, string $bytes_value, bool $write_default = false) {
    $this->writeStringField($field_id, $bytes_value, $write_default);
  }

  /**
   * @param string[] $bytes_array
   */
  public function writeBytesRepeatedField(int $field_id, array $bytes_array) {
    $this->writeStringRepeatedField($field_id, $bytes_array);
  }

  public function writeMessageField(int $field_id, ?ProtobufMessage $inner_message, bool $write_default = false) {
    if ($inner_message !== null || $write_default) {
      ProtoBytes::writeVarInt($this->buffer, ProtoTypes::makeTag($field_id, ProtoTypes::MESSAGE));
      // format of nested messages: [length][sub_message]
      // create a new string, encode nested - to calculate length
      $inner_stream = StreamEncoder::startEncodedSubMessage();
      $inner_message->protoEncode($inner_stream);
      ProtoBytes::writeBytes($this->buffer, $inner_stream->getDataAndFlush());
    }
  }

  /**
   * @param ProtobufMessage[] $inner_messages_arr
   */
  public function writeMessageRepeatedField(int $field_id, array $inner_messages_arr) {
    foreach ($inner_messages_arr as $inner_message) {
      $this->writeMessageField($field_id, $inner_message, true);
    }
  }
}
