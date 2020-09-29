<?php

// see eg.echo.proto — messages and services are described there
// see eg_echo/ folder — "codegenerated" PHP classes based on scheme
// see eg.profile.proto and eg_profile/ for more compilated examples

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
