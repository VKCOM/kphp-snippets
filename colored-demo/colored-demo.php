<?php

// see index.php

function createLogger(): Logger {
  return new Logger();
}

class DemoRequest {
  public int $id = 0;
}

class DBLayer {
  private static ?DBLayer $instance = null;

  static public function instance(): DBLayer {
    if (self::$instance === null) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  /**
   * @kphp-color slow
   */
  function addToLogTable(array $kvMap) {
    // ... assume we're inserting to db here
    var_dump($kvMap);
  }
}

class Logger {
  function debug(string $msg) {
    DBLayer::instance()->addToLogTable([
      'time'    => time(),
      'message' => $msg,
    ]);
  }
}

class ApiRequestHandler {
  /**
   * @kphp-color fast
   */
  function handleRequest(DemoRequest $req) {
    $logger = createLogger();
    $logger->debug('Processing ' . $req->id);
  }
}

function coloredDemoThatDoesNotCompile() {
  $handler = new ApiRequestHandler();
  $handler->handleRequest(new DemoRequest());
}
