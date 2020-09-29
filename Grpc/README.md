# gRPC client with KPHP

gRPC is not embedded into KPHP: instead, it's implemented as curl+http/2 transport layer and manual Protobuf PHP implementation.

Note! gRPC is supported only in client mode, KPHP is not a gRPC server.


## Quick example: send PingMessage, receive PongMessage

Say, we have a simple echo server with [eg.echo.proto](./examples/eg.echo.proto) file description.  
We have PHP classes generated from this file — representing messages and services ([eg_echo folder](./examples/eg_echo/)).  
It's very handy to use them:

```php
$input = new \PB\eg_echo\Messages\PingMessage();
$input->message = 'hello';

$response = new \PB\eg_echo\Messages\PongMessage();

$channel = new \KPHP\Grpc\GrpcChannel('http://host:port');
$client = new \PB\eg_echo\Services\EchoService($channel);
$err = $client->echo($input)->call($response);
if ($err !== null) {
  echo $err, "\n";
  return;
}

echo $response->message, "\n";
```


## What is \PB and codegenerated classes?

*\PB* is a preferred (though not required) namespace for PHP classes representing Protobuf scheme.

For now, there is no autogenerator .proto->PHP, but writing classes manually is very easy (see [this](./examples/eg_profile/Messages/ImageSizeRequest_DownloadData.php) and [this](examples/eg_echo/Services/EchoService.php)).

Say, we have the following .proto scheme:
```
syntax = "proto3";
package eg.echo;

service EchoService {
    rpc echo (PingMessage) returns (PongMessage);
}

message PingMessage {
    string message = 1;
}

message PongMessage {
    string message = 1;
}
```

This is translated to the following file structure:
```
eg_echo/
    Messages/
        PingMessage.php
        PongMessage.php
    Services/
        EchoService.php
```

* **package eg.echo** turns into **folder eg_echo/** (preferred plain folder structure)
* **service EchoService** turns into **class EchoService** *extends GrpcServiceBase* with a single method: *$client->echo($ping_message)*
* **message PingMessage** turns info **class PingMessage** *implements ProtobufMessage* 
* **message PongMessage** turns info **class PongMessage** *implements ProtobufMessage*

Classes generated from messages can serialize to protobuf binary string and deserialize back. All fields are strictly typed, according to schema.  
Class generated from service has all methods specified in the service. They accept a typed message and return *GrpcUnaryCall*.

Enums are classes with numeric constants.  
Nested messages classes and nested enums have underscores in their names (e.g. *ImageRequest_DownloadData*). 


## Configuring response and timeouts

Here is a call with default settings:
```php
$service->someFunc($input)->call($response);
```

Before invoking *call()*, you can configure custom properties using *with-pattern*:
```php
$service->someFunc($input)
    ->withCustomCallTimeout(...)
    ->withCustomConnectionTimeout(...)
    ->withCustomHttp2Headers(...)
    ->withoutLoggingOnFail()
    ->call($response);
```

All these methods are self-documented, see sources.


## Parallel queries: call() = send() + get()

You can use multiplexing (sending multiple queries to one channel).

From the code, there are non-blocking functions: send a query, do some calculations, wait for response: 
```php
$q = $service->someFunc($input)->send();
// ... some php code, while request is being executed
$err = $q->get($response);
// and here we block until response ready
```

Another option — to send multiple queries and then wait for all responses:
```php
// use one $service (one channel == one connection)
$q1 = $service->someFunc1($input1)->send();
$q2 = $service->someFunc2($input2)->send();
// ...
$q1->get($response1);
$q2->get($response2);
```

*call()* is just *send() + get()*. While *get()* blocks and waits, other forks continue executing.


## Limitations

* some limitations in protobuf support, see [protobuf](../Protobuf)
* *\PB* classes are to be written manually, no auto-generation (though it's very simple)
* streaming is not supported, as it's curl-over-http-2


## How to use gRPC in your KPHP project

* copy this folder to *KPHP/* folder (so that namespaces coincide with paths)
* manually "generate" PHP classes based on .proto scheme (see examples)
* ready to use
* (optional) in *GrpcUnaryCall::get()* replace todo with logging failed queries

