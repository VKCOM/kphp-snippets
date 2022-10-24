<?php

namespace JobWorkers;

/**
 * KPHP job workers — true parallelism: separate processes to parallelize large (unforkable) code pieces and CPU work.
 *
 * "Manual" job requests are more complicated than @see JobWorkerSimple. They fork as follows:
 * * takes a request
 * * prepares data to respond
 * * sends a response, unfreezing HTTP worker
 * * keeps working in the background, along with the HTTP worker
 * * finishes
 * On wait(), HTTP worker continues in the middle of job worker execution. After wait(), they work simultaneously.
 *
 * Inheritors should implement `handleRequest()` as follows:
 * handleRequest() {
 *   ...   // prepare response
 *   $this->respondAndContinueExecution($response);
 *   ...   // other actions inside a job
 * }
 *
 * Manual job requests are launched like simple ones:
 * @see JobLauncher::start()
 * @see JobLauncher::startMulti() (allows sharing a single piece of memory per many child jobs)
 */
abstract class JobWorkerManualRespond extends BaseJobWorker {

  /**
   * This field is used
   * 1) inside job worker — to prevent `respond()` called twice
   * 2) inside http worker — when falled back to local execution
   * In other words, it's NOT send from http to job, it's null on job start.
   * @var ?\KphpJobWorkerResponse
   */
  private $_response = null;

  final public function respondAndContinueExecution(\KphpJobWorkerResponse $response) {
    if ($this->_response) {
      warning("Called respond() more that once");
      return;
    }

    $this->_response = $response;
    if (JobLauncher::isExecutionInsideJobWorker()) {
      kphp_job_worker_store_response($response);
      // job worker has sent a response and continues execution
    }
  }

  final public function wasResponded(): bool {
    return $this->_response !== null;
  }

  abstract function handleRequest(): void;

  /**
   * Call instead of @see JobLauncher::start(), when you need local same-process execution (JW not available?)
   * @return future<\KphpJobWorkerResponse> future for a result computed in the same process
   */
  final function localWaitableFallback() {
    $this->handleRequest();
    $fake_forkable = function() {
      return $this->_response;
    };
    return fork($fake_forkable());
  }
}
