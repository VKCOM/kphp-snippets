# Protobuf with KPHP

Protobuf can be used with gRPC or separately.  
See [gRPC with KPHP](../Grpc) for examples.


## .proto scheme and PHP classes

Having a .proto scheme with messages, you write PHP classes *implements ProtobufMessage* that can serialize to a binary string and deserialize back.  
For now, this is done manually (no autogeneration yet), but it's very easy. Check out [these classes](../Grpc/examples/eg_profile/Messages) for example.


## How to use Protobuf without gRPC

General advice is to "generate" PHP classes based on .proto file, so you can use this:
```php
$encoder = \KPHP\Protobuf\StreamEncoder::startRawProtobufBytes();
$object->protoEncode($encoder);
$protobuf_binary_data = $encoder->getDataAndFlush();
 
$decoder = \KPHP\Protobuf\StreamDecoder::fromRawProtobufBytes($protobuf_binary_data);
$object = new \PB\...;
$object->protoDecode($decoder);
if ($decoder->wasDecodingError()) { /* ... */ }
```

Without having PHP classes, you can manually call methods of *$encoder/$decoder*, they are self-documented. 


## Abilities

* encoding and decoding primitives and repeated fields (*writeInt32Field()*, *writeStringRepeatedField()*, *readBytesField()* and others)
* encoding and decoding submessages (*readMessageField()*, *writeMessageRepeatedField()* and others)
* enums are expressed as classes with integer constants


## Limitations

* **oneOf** — no support for now (but seems not hard to add)
* **maps** — no support for now (but seems not hard to add) 
* **groups, any** — no support for now (probably couldn't be added)
* *\PB* classes are to be written manually, no auto-generation (though it's very simple)


## How to use Protobuf in your KPHP project

* copy this folder to *KPHP/* folder (so that namespaces coincide with paths)
* manually "generate" PHP classes based on .proto scheme (see examples)
* ready to use

