<?php

namespace JobWorkers;

/**
 * KPHP job workers — true parallelism: separate processes to parallelize large (unforkable) code pieces and CPU work.
 *
 * "Simple" job requests work as follows:
 * * takes a request
 * * performs execution (handleRequest)
 * * responds, and finishes immediately
 * On wait(), http worker continues after job worker finishes.
 *
 * Simple job requests are launched in one of two ways:
 * @see JobLauncher::start()
 * @see JobLauncher::startMulti() (allows sharing a single piece of memory per many child jobs)
 */
abstract class JobWorkerSimple extends BaseJobWorker {

  abstract function handleRequest(): ?\KphpJobWorkerResponse;

  /**
   * Call instead of @see JobLauncher::start(), when you need local same-process execution (JW not available?)
   * @return future<\KphpJobWorkerResponse> future for a result computed in the same process
   */
  final public function localWaitableFallback() {
    $local_response = $this->handleRequest();
    $fake_forkable = function() use($local_response) {
      return $local_response;
    };
    return fork($fake_forkable());
  }
}
