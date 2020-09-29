<?php

namespace KPHP\Protobuf;

/**
 * A class with various static methods to encode/read/write numbers in binary protobuf format.
 * @link https://developers.google.com/protocol-buffers/docs/proto3#scalar
 * Works on 64-bit KPHP.
 * Maybe, would be integrated natively to KPHP in future, it can be optimized if written with low-level C++.
 */
class ProtoBytes {
  private const MAX_VARINT_BYTES = 10;

  public static function int32(int $value): int {
    return ($value << 32) >> 32;
  }

  static public function writeVarInt(string &$buffer, int $int_value) {
    while (($int_value >= 0x80 || $int_value < 0)) {
      $buffer .= chr($int_value | 0x80);
      $int_value = (($int_value >> 7) & 0x1FFFFFFFFFFFFFF);
    }
    $buffer .= chr($int_value);
  }

  /**
   * @kphp-inline
   */
  static public function writeLittleEndianInt(string &$buffer, int $value, int $bytes) {
    for ($byte = 0; $byte < $bytes; ++$byte) {
      $buffer .= chr(($value >> ($byte * 8)) & 0xFF);
    }
  }

  static public function writeBytes(string &$buffer, string $bytes) {
    self::writeVarInt($buffer, strlen($bytes));
    $buffer .= $bytes;
  }

  static public function readRaw(string $buffer, int &$offset, int $size): ?string {
    if ($size <= 0 || $offset + $size > strlen($buffer)) {
      return null;
    }

    $raw_bytes = (string)substr($buffer, $offset, $size);
    $offset += $size;
    return $raw_bytes;
  }

  static public function readVarInt(string $buffer, int &$offset): ?int {
    $count = 0;
    $int_value = 0;
    $buffer_len = strlen($buffer);

    do {
      if ($count === self::MAX_VARINT_BYTES || $offset >= $buffer_len) {
        return null;
      }
      $b = ord($buffer[$offset]);
      $bits = 7 * $count;
      $int_value |= (($b & 0x7F) << $bits);

      ++$offset;
      ++$count;
    } while ($b & 0x80);

    return $int_value;
  }

  static public function readLittleEndianInt(string $buffer, int &$offset, int $bytes): ?int {
    if ($offset + $bytes > strlen($buffer)) {
      return null;
    }
    $int_value = 0;
    for ($byte = 0; $byte < $bytes; ++$byte) {
      $int_value |= ord($buffer[$offset++]) << (8 * $byte);
    }
    return $int_value;
  }

  public static function zigZagEncode(int $int_value): int {
    return ($int_value << 1) ^ ($int_value >> 63);
  }

  public static function zigZagDecode(int $int_value): int {
    return (($int_value >> 1) & 0x7FFFFFFFFFFFFFFF) ^ (-($int_value & 1));
  }
}
