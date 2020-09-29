<?php

namespace KPHP\Protobuf;

/**
 * High-level decoder from a binary protobuf string.
 * Usage:
 * 1) create $decoder using one of static from*() methods
 * 2) fill $instance - an object implements ProtobufMessage, which to decode to
 * 3) call $instance->proto_decode($decoder)
 * 4) check for error: if($decoder->wasDecodingError()) ...; if no error, $instance is filled
 */
final class StreamDecoder {
  /**
   * @var string
   * @kphp-const
   */
  private $buffer;
  /** @var int */
  private $offset;
  /**
   * @var int
   * @kphp-const
   */
  private $end_limit;
  /** @var bool */
  private $was_any_field_error = false;

  private const SKIP_TAG_CHECKING = 0;

  // constructor is private on purpose: see static from*() methods
  private function __construct(string $buffer, int $offset, int $end_limit) {
    $this->buffer = $buffer;
    $this->offset = $offset;
    $this->end_limit = $end_limit;
  }

  /**
   * When an input is just protobuf binary string, buffer is from 0 to the end
   * This encoded string could be received from other languages or from
   * @see StreamEncoder::startRawProtobufBytes()
   */
  public static function fromRawProtobufBytes(string $protobufBinaryData): self {
    return new self($protobufBinaryData, 0, strlen($protobufBinaryData));
  }

  /**
   * When we receive curl answer via gRPC, it contains curl prefix + protobuf data.
   * This is optimization not to do substr, but to use the same curl response.
   * Curl prefix is 5 bytes length. Don't pay attention to them and assume they had been validated.
   * While encoding, there is a similar optimization:
   * @see StreamEncoder::startCurlRequest()
   */
  public static function fromCurlResponse(string $curlBinaryResponse): self {
    return new self($curlBinaryResponse, 5, strlen($curlBinaryResponse));
  }

  /**
   * Nested messages in protobuf are encoded as [length][sub_message].
   * That's why while reading - use the same buffer and offset, not to allocate strings.
   * The main is not to overrun this length while reading, so we have end_limit.
   * @see StreamEncoder::startEncodedSubMessage()
   */
  private static function fromEncodedSubMessage(self $curDecoder, int $lengthOfSubMessage): self {
    return new self($curDecoder->buffer, $curDecoder->offset, $curDecoder->offset + $lengthOfSubMessage);
  }

  /**
   * @kphp-inline
   */
  private function checkTag(int $tag, int $expected): bool {
    return $tag == self::SKIP_TAG_CHECKING || ProtoTypes::getTagWireType($tag) == ProtoTypes::getWireType($expected);
  }

  private function readBytes(int $tag, int $expected_type): ?string {
    if (!$this->checkTag($tag, $expected_type)) {
      return null;
    }
    $length = ProtoBytes::readVarInt($this->buffer, $this->offset);
    if ($length === null || $length <= 0) {
      return null;
    }
    $this->offset += $length;
    return (string)substr($this->buffer, $this->offset - $length, $length);
  }

  private function readRepeatedField(int $tag, array &$array_out, int $expected_single_type,
                                     callable $def_val_getter,
                                     callable $field_reader): bool {
    // ad-hoc template strictly typed $def_value argument, assume trivially copyable
    $def_value = $def_val_getter();

    if (!ProtoTypes::isPackedTag($tag)) {
      if (!$this->checkTag($tag, $expected_single_type)) {
        $this->was_any_field_error = true;
        return false;
      }
      $value = $def_value;
      if (!$field_reader($tag, $value)) {
        $this->was_any_field_error = true;
        return false;
      }
      $array_out[] = $value;
      return true;
    }

    $pack_size_left = ProtoBytes::readVarInt($this->buffer, $this->offset);
    if ($pack_size_left === null || $pack_size_left < 0 || $pack_size_left + $this->offset > $this->end_limit) {
      return false;
    }

    while ($pack_size_left > 0) {
      $current = $this->offset;
      $next_value = $def_value;
      if (!$field_reader(self::SKIP_TAG_CHECKING, $next_value)) {
        return false;
      }
      $array_out[] = $next_value;
      $pack_size_left -= $this->offset - $current;
    }
    if ($pack_size_left < 0) {
      $this->was_any_field_error = true;
      return false;
    }
    return true;
  }

  /**
   * @kphp-inline
   */
  public function readTag(): ?int {
    if ($this->offset >= $this->end_limit) {
      return null;
    }
    return ProtoBytes::readVarInt($this->buffer, $this->offset);
  }

  public function wasDecodingError(): bool {
    return $this->was_any_field_error;
  }

  private function readVarIntField(int $tag, int $expected_type, int &$int_out): bool {
    $int_or_null = $this->checkTag($tag, $expected_type) ? ProtoBytes::readVarInt($this->buffer, $this->offset) : null;
    if ($int_or_null !== null) {
      $int_out = $int_or_null;
      return true;
    }
    $this->was_any_field_error = true;
    return false;
  }

  public function readInt32Field(int $tag, int &$int32_out): bool {
    $res = $this->readVarIntField($tag, ProtoTypes::INT32, $int32_out);
    $int32_out = ProtoBytes::int32($int32_out);
    return $res;
  }

  /**
   * @param int[] $int32_array_out
   */
  public function readInt32RepeatedField(int $tag, array &$int32_array_out): bool {
    return $this->readRepeatedField($tag, $int32_array_out, ProtoTypes::INT32,
      function(): int { return 0; },
      function(int $tag, int &$v) { return $this->readInt32Field($tag, $v); }
    );
  }

  public function readInt64Field(int $tag, int &$int64_out): bool {
    return $this->readVarIntField($tag, ProtoTypes::INT64, $int64_out);
  }

  /**
   * @param int[] $int64_array_out
   */
  public function readInt64RepeatedField(int $tag, array &$int64_array_out): bool {
    return $this->readRepeatedField($tag, $int64_array_out, ProtoTypes::INT64,
      function(): int { return 0; },
      function(int $tag, int &$v) { return $this->readInt64Field($tag, $v); }
    );
  }

  public function readUInt32Field(int $tag, int &$uint32_out): bool {
    $res = $this->readVarIntField($tag, ProtoTypes::UINT32, $uint32_out);
    $uint32_out &= 0xFFFFFFFF;
    return $res;
  }

  /**
   * @param int[] $uint32_array_out
   */
  public function readUInt32RepeatedField(int $tag, array &$uint32_array_out): bool {
    return $this->readRepeatedField($tag, $uint32_array_out, ProtoTypes::UINT32,
      function(): int { return 0; },
      function(int $tag, int &$v) { return $this->readUInt32Field($tag, $v); }
    );
  }

  public function readUInt64Field(int $tag, int &$uint64_out): bool {
    return $this->readVarIntField($tag, ProtoTypes::UINT64, $uint64_out);
  }

  /**
   * @param int[] $uint64_array_out
   */
  public function readUInt64RepeatedField(int $tag, array &$uint64_array_out): bool {
    return $this->readRepeatedField($tag, $uint64_array_out, ProtoTypes::UINT64,
      function(): int { return 0; },
      function(int $tag, int &$v) { return $this->readUInt64Field($tag, $v); }
    );
  }

  public function readSInt32Field(int $tag, int &$int32_out): bool {
    $res = $this->readVarIntField($tag, ProtoTypes::SINT32, $int32_out);
    $int32_out = ProtoBytes::int32(ProtoBytes::zigZagDecode($int32_out));
    return $res;
  }

  /**
   * @param int[] $int32_array_out
   */
  public function readSInt32RepeatedField(int $tag, array &$int32_array_out): bool {
    return $this->readRepeatedField($tag, $int32_array_out, ProtoTypes::SINT32,
      function(): int { return 0; },
      function(int $tag, int &$v) { return $this->readSInt32Field($tag, $v); }
    );
  }

  public function readSInt64Field(int $tag, int &$int64_out): bool {
    $res = $this->readVarIntField($tag, ProtoTypes::SINT64, $int64_out);
    $int64_out = ProtoBytes::zigZagDecode($int64_out);
    return $res;
  }

  /**
   * @param int[] $int64_array_out
   */
  public function readSInt64RepeatedField(int $tag, array &$int64_array_out): bool {
    return $this->readRepeatedField($tag, $int64_array_out, ProtoTypes::SINT64,
      function(): int { return 0; },
      function(int $tag, int &$v) { return $this->readSInt64Field($tag, $v); }
    );
  }

  private function readFixedField(int $tag, int $expected_type, int &$int_out, int $bytes): bool {
    $int_or_null = $this->checkTag($tag, $expected_type) ? ProtoBytes::readLittleEndianInt($this->buffer, $this->offset, $bytes) : null;
    if ($int_or_null !== null) {
      $int_out = $int_or_null;
      return true;
    }
    $this->was_any_field_error = true;
    return false;
  }

  public function readFixed32Field(int $tag, int &$uint32_out): bool {
    return $this->readFixedField($tag, ProtoTypes::FIXED32, $uint32_out, 4);
  }

  /**
   * @param int[] $uint32_array_out
   */
  public function readFixed32RepeatedField(int $tag, array &$uint32_array_out): bool {
    return $this->readRepeatedField($tag, $uint32_array_out, ProtoTypes::FIXED32,
      function(): int { return 0; },
      function(int $tag, int &$v) { return $this->readFixed32Field($tag, $v); }
    );
  }

  public function readFixed64Field(int $tag, int &$uint64_out): bool {
    return $this->readFixedField($tag, ProtoTypes::FIXED64, $uint64_out, 8);
  }

  /**
   * @param int[] $uint64_array_out
   */
  public function readFixed64RepeatedField(int $tag, array &$uint64_array_out): bool {
    return $this->readRepeatedField($tag, $uint64_array_out, ProtoTypes::FIXED64,
      function(): int { return 0; },
      function(int $tag, int &$v) { return $this->readFixed64Field($tag, $v); }
    );
  }

  public function readSFixed32Field(int $tag, int &$int32_out): bool {
    $res =  $this->readFixedField($tag, ProtoTypes::SFIXED32, $int32_out, 4);
    $int32_out = ProtoBytes::int32($int32_out);
    return $res;
  }

  /**
   * @param int[] $int32_array_out
   */
  public function readSFixed32RepeatedField(int $tag, array &$int32_array_out): bool {
    return $this->readRepeatedField($tag, $int32_array_out, ProtoTypes::SFIXED32,
      function(): int { return 0; },
      function(int $tag, int &$v) { return $this->readSFixed32Field($tag, $v); }
    );
  }

  public function readSFixed64Field(int $tag, int &$int64_out): bool {
    return $this->readFixedField($tag, ProtoTypes::SFIXED64, $int64_out, 8);
  }

  /**
   * @param int[] $int64_array_out
   */
  public function readSFixed64RepeatedField(int $tag, array &$int64_array_out): bool {
    return $this->readRepeatedField($tag, $int64_array_out, ProtoTypes::SFIXED64,
      function(): int { return 0; },
      function(int $tag, int &$v) { return $this->readSFixed64Field($tag, $v); }
    );
  }

  private function readRealNumberField(int $tag, int $expected_type, float &$float_out, int $bytes, string $unpack_type): bool {
    $packed_or_null = $this->checkTag($tag, $expected_type) ? ProtoBytes::readRaw($this->buffer, $this->offset, $bytes) : null;
    if ($packed_or_null !== null) {
      $float_out = (float)(unpack($unpack_type, $packed_or_null)[1]);
      return true;
    }
    $this->was_any_field_error = true;
    return false;
  }

  public function readFloatField(int $tag, float &$float_out): bool {
    return $this->readRealNumberField($tag, ProtoTypes::FLOAT, $float_out, 4, 'f');
  }

  /**
   * @param float[] $float_array_out
   */
  public function readFloatRepeatedField(int $tag, array &$float_array_out): bool {
    return $this->readRepeatedField($tag, $float_array_out, ProtoTypes::FLOAT,
      function(): float { return 0.0; },
      function(int $tag, float &$v) { return $this->readFloatField($tag, $v); }
    );
  }

  public function readDoubleField(int $tag, float &$float_out): bool {
    return $this->readRealNumberField($tag, ProtoTypes::DOUBLE, $float_out, 8, 'd');
  }

  /**
   * @param float[] $double_array_out
   */
  public function readDoubleRepeatedField(int $tag, array &$double_array_out): bool {
    return $this->readRepeatedField($tag, $double_array_out, ProtoTypes::DOUBLE,
      function(): float { return 0.0; },
      function(int $tag, float &$v) { return $this->readDoubleField($tag, $v); }
    );
  }

  public function readBoolField(int $tag, bool &$bool_out): bool {
    $int_out = 0;
    $res = $this->readVarIntField($tag, ProtoTypes::BOOL, $int_out);
    $bool_out = $int_out !== 0;
    return $res;
  }

  /**
   * @param bool[] $bool_array_out
   */
  public function readBoolRepeatedField(int $tag, array &$bool_array_out): bool {
    return $this->readRepeatedField($tag, $bool_array_out, ProtoTypes::BOOL,
      function(): bool { return false; },
      function(int $tag, bool &$bool_out) { return $this->readBoolField($tag, $bool_out); }
    );
  }

  public function readStringField(int $tag, string &$string_out): bool {
    $string_or_null = $this->readBytes($tag, ProtoTypes::STRING);
    if ($string_or_null !== null) {
      $string_out = $string_or_null;
      return true;
    }
    $this->was_any_field_error = true;
    return false;
  }

  /**
   * @param string[] $string_array_out
   */
  public function readStringRepeatedField(int $tag, array &$string_array_out): bool {
    // string arrays can't be packed
    $string_field = '';
    if ($this->readStringField($tag, $string_field)) {
      $string_array_out[] = $string_field;
      return true;
    }
    $this->was_any_field_error = true;
    return false;
  }

  public function readBytesField(int $tag, string &$bytes_out): bool {
    $bytes_or_null = $this->readBytes($tag, ProtoTypes::BYTES);
    if ($bytes_or_null !== null) {
      $bytes_out = $bytes_or_null;
      return true;
    }
    $this->was_any_field_error = true;
    return false;
  }

  /**
   * @param string[] $bytes_array_out
   */
  public function readBytesRepeatedField(int $tag, array &$bytes_array_out): bool {
    // byte arrays can't be packed
    $bytes_field = '';
    if ($this->readBytesField($tag, $bytes_field)) {
      $bytes_array_out[] = $bytes_field;
      return true;
    }
    $this->was_any_field_error = true;
    return false;
  }

  public function readMessageField(int $tag, ProtobufMessage $message_to_decode_to): bool {
    // assume, that $message_to_decode_to is already created by external code
    $length_or_null = $this->checkTag($tag, ProtoTypes::MESSAGE) ? ProtoBytes::readVarInt($this->buffer, $this->offset) : null;
    if ($length_or_null !== null) {
      // protobuf is encoded in a way, that next length bytes is a nested array, so continue from the same offset
      $slice_decoder = StreamDecoder::fromEncodedSubMessage($this, $length_or_null);
      $message_to_decode_to->protoDecode($slice_decoder);
      $this->offset = $slice_decoder->end_limit;
      if (!$slice_decoder->was_any_field_error) {
        return true;
      }
    }
    $this->was_any_field_error = true;
    return false;
  }

  /**
   * @param ProtobufMessage[] $array_to_update_last_elem
   */
  public function readMessageRepeatedField(int $tag, array $array_to_update_last_elem): bool {
    // assume, that external code has just added an element to $array_to_update_last_elem - read into it
    // nested messages arrays can't be packed
    return $this->readMessageField($tag, $array_to_update_last_elem[count($array_to_update_last_elem) - 1]);
  }

  public function readAndSkipUnknownField(int $tag) {
    switch (ProtoTypes::getWireType($tag)) {
      case ProtoTypes::WIRETYPE_VARINT:
        ProtoBytes::readVarInt($this->buffer, $this->offset);
        break;
      case ProtoTypes::WIRETYPE_FIXED64:
        $this->offset += 8;
        break;
      case ProtoTypes::WIRETYPE_LENGTH_DELIMITED:
        $this->offset += (int)ProtoBytes::readVarInt($this->buffer, $this->offset);
        break;
      case ProtoTypes::WIRETYPE_FIXED32:
        $this->offset += 4;
        break;
    }
  }
}
