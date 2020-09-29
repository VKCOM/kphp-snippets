<?php

namespace KPHP\Protobuf;

/**
 * Internal utilities for protobuf types
 * @link https://developers.google.com/protocol-buffers/docs/encoding
 */
class ProtoTypes {
  const DOUBLE   = 1;
  const FLOAT    = 2;
  const INT64    = 3;
  const UINT64   = 4;
  const INT32    = 5;
  const FIXED64  = 6;
  const FIXED32  = 7;
  const BOOL     = 8;
  const STRING   = 9;
  const GROUP    = 10;
  const MESSAGE  = 11;
  const BYTES    = 12;
  const UINT32   = 13;
  const ENUM     = 14;
  const SFIXED32 = 15;
  const SFIXED64 = 16;
  const SINT32   = 17;
  const SINT64   = 18;

  const WIRETYPE_VARINT           = 0;
  const WIRETYPE_FIXED64          = 1;
  const WIRETYPE_LENGTH_DELIMITED = 2;
  const WIRETYPE_FIXED32          = 5;

  const TAG_TYPE_BITS = 3;

  // correspoding types to wire types (see protobuf specification)
  // important! it should remain array-vector in KPHP, to have linear memory access
  private const MAP_TYPE_TO_WIRETYPE = [
    0,
    self::WIRETYPE_FIXED64,
    self::WIRETYPE_FIXED32,
    self::WIRETYPE_VARINT,
    self::WIRETYPE_VARINT,
    self::WIRETYPE_VARINT,
    self::WIRETYPE_FIXED64,
    self::WIRETYPE_FIXED32,
    self::WIRETYPE_VARINT,
    self::WIRETYPE_LENGTH_DELIMITED,
    0,
    self::WIRETYPE_LENGTH_DELIMITED,
    self::WIRETYPE_LENGTH_DELIMITED,
    self::WIRETYPE_VARINT,
    self::WIRETYPE_VARINT,
    self::WIRETYPE_FIXED32,
    self::WIRETYPE_FIXED64,
    self::WIRETYPE_VARINT,
    self::WIRETYPE_VARINT,
  ];

  /**
   * @kphp-inline
   * @noinspection PhpUnreachableStatementInspection
   */
  public static function getWireType(int $type): int {
    #ifndef KittenPHP
    return self::MAP_TYPE_TO_WIRETYPE[$type] ?? 0;
    #endif
    return self::MAP_TYPE_TO_WIRETYPE[$type];   // KPHP will return 0 for unexisting elements, as array<int>
  }

  /**
   * @kphp-inline
   */
  public static function makeTag(int $field_number, int $type): int {
    return ($field_number << self::TAG_TYPE_BITS) | self::getWireType($type);
  }

  /**
   * @kphp-inline
   */
  public static function getTagFieldNumber(int $tag): int {
    return ($tag >> self::TAG_TYPE_BITS) & (1 << ((PHP_INT_SIZE * 8) - self::TAG_TYPE_BITS)) - 1;
  }

  /**
   * @kphp-inline
   */
  public static function getTagWireType(int $tag): int {
    return $tag & 0x7;
  }

  /**
   * @kphp-inline
   */
  public static function isPackedTag(int $tag): bool {
    return self::getTagWireType($tag) === self::WIRETYPE_LENGTH_DELIMITED;
  }

  /**
   * @kphp-inline
   */
  public static function makePackedTag(int $field_number): int {
    return ($field_number << self::TAG_TYPE_BITS) | self::WIRETYPE_LENGTH_DELIMITED;
  }
}
