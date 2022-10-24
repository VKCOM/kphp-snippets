<?php

namespace JobWorkers;

/**
 * KPHP job workers — true parallelism: separate processes to parallelize large (unforkable) code pieces and CPU work.
 *
 * "NoReply" job requests work as follows:
 * * takes a request
 * * performs execution (handleRequest), never returning a response
 * * becomes free when the script finishes
 * http worker continues execution immediateately after sending a job: wait() can't be called, a response won't exist.
 * http worker can even finish a script and reset, and a launched job worker would still continue running in the background.
 *
 * It's similar to `fastcgi_finish_request()`, to perform some actions in the background.
 * Purpose: send a response to a user as quick as possible, writing stats and other bg-processes afterward.
 * handleRequest() returns void, intentionally meaning "never".
 *
 * No-reply job workers are launched by
 * @see JobLauncher::startNoReply() (it doesn't return future, wait() can't be called)
 * (multi-function doesn't exist)
 */
abstract class JobWorkerNoReply extends BaseJobWorker {

  abstract function handleRequest(): void;

  /**
   * Call instead of @see JobLauncher::startNoReply(), when you need local same-process execution (JW not available?)
   * @return void As no-reply jobs never return a response
   */
  final function localVoidFallback() {
    $this->handleRequest();
  }

}
