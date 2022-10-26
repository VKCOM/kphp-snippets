<?php

#ifndef KPHP
spl_autoload_register(function($class) {
  $rel_filename = trim(str_replace('\\', '/', $class), '/') . '.php';
  $filename = __DIR__ . '/..' . '/' . $rel_filename;
  if (file_exists($filename)) {
    require_once $filename;
  }
}, true, true);

// todo require kphp_polyfills or install them using Composer
// see https://github.com/VKCOM/kphp-polyfills
// (or don't do this, since job workers just don't work in plain PHP,
//  and in practice, you should provide local fallback; this demo is focused to be KPHP-only :)
require_once '/some/where/kphp-polyfills/kphp_polyfills.php';
#endif


class MyRequest extends \JobWorkers\JobWorkerSimple {
  /** @var int[] */
  public $arr_to_x2;

  public function __construct(array $arr_to_x2) {
    $this->arr_to_x2 = $arr_to_x2;
  }

  function handleRequest(): ?\KphpJobWorkerResponse {
    $response = new MyResponse();
    $response->arr_x2 = array_map(fn($v) => $v ** 2, $this->arr_to_x2);
    return $response;
  }
}

class MyResponse implements \KphpJobWorkerResponse {
  /** @var int[] */
  public $arr_x2;
}


if (PHP_SAPI !== 'cli' && isset($_SERVER["JOB_ID"])) {
  handleKphpJobWorkerRequest();
} else {
  handleHttpRequest();
}


function handleHttpRequest() {
  if (!\JobWorkers\JobLauncher::isEnabled()) {
    echo "JOB WORKERS DISABLED at server start, use -f 2 --job-workers-ratio 0.5", "\n";
    return;
  }

  $arr = [1, 2, 3, 4, 5];
  $timeout = 0.1;
  $job_request = new MyRequest($arr);
  $job_id = \JobWorkers\JobLauncher::start($job_request, $timeout);

  // ... in practice, there are of course some useful executions between start and wait

  $response = wait($job_id);
  if ($response instanceof MyResponse) {
    echo "This array was calculated inside a job worker:", "\n<br>";
    var_dump($response->arr_x2);
  }
}

function handleKphpJobWorkerRequest() {
  $kphp_job_request = kphp_job_worker_fetch_request();
  if (!$kphp_job_request) {
    warning("Couldn't fetch a job worker request");
    return;
  }

  if ($kphp_job_request instanceof \JobWorkers\JobWorkerSimple) {
    // simple jobs: they start, finish, and return the result
    $kphp_job_request->beforeHandle();
    $response = $kphp_job_request->handleRequest();
    if ($response === null) {
      warning("Job request handler returned null for " . get_class($kphp_job_request));
      return;
    }
    kphp_job_worker_store_response($response);

  } else if ($kphp_job_request instanceof \JobWorkers\JobWorkerManualRespond) {
    // more complicated jobs: they start, send a result in the middle (here get get it) — and continue working
    $kphp_job_request->beforeHandle();
    $kphp_job_request->handleRequest();
    if (!$kphp_job_request->wasResponded()) {
      warning("Job request handler didn't call respondAndContinueExecution() manually " . get_class($kphp_job_request));
    }

  } else if ($kphp_job_request instanceof \JobWorkers\JobWorkerNoReply) {
    // background jobs: they start and never send any result, just continue in the background and finish somewhen
    $kphp_job_request->beforeHandle();
    $kphp_job_request->handleRequest();

  } else {
    warning("Got unexpected job request class: " . get_class($kphp_job_request));
  }
}
