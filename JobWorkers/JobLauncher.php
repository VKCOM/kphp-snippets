<?php

namespace JobWorkers;

/**
 * Wrappers around low-level `kphp_job_worker_*` functions,
 * considering some KPHP real-world usage specifics and sending globals to job workers.
 *
 * Use them in your code: `JobLauncher::start()` instead of `kphp_job_worker_start()`, etc.
 */
final class JobLauncher {

  /**
   * Launch a job worker that would be executed in a separate process on the same machine.
   * Internally, the $job instance is deep copied into shared memory which is used by another process for reading.
   * Attention! Use only in KPHP, check @see isEnabled in advance.
   * @return future<\KphpJobWorkerResponse> | false To be passed to wait()
   */
  static public function start(BaseJobWorker $job, float $timeout) {
    $job->beforeStart();
    return kphp_job_worker_start($job, $timeout);
  }

  /**
   * Start multiple jobs at once, that can share the same memory piece, avoiding multiple copying.
   * See the KPHP documentation for details.
   * Attention! Use only in KPHP, check @see isEnabled in advance.
   * @param BaseJobWorker[] $jobs
   * @return (future<\KphpJobWorkerResponse> | false)[] Array of future's, to be added to a wait queue, etc.
   */
  static public function startMulti(array $jobs, float $timeout) {
    foreach ($jobs as $job) {
      $job->beforeStart();
    }
    return kphp_job_worker_start_multi($jobs, $timeout);
  }

  /**
   * Launch a job which isn't supposed to return any result at all, it can continue working even after http script end.
   * It's analogous to fastcgi_finish_request — writing stats in the background and similar.
   * Attention! Use only in KPHP, check @see isEnabled in advance.
   * @return bool true|false instead of future|false (returns whether a job was added to the queue)
   */
  static public function startNoReply(JobWorkerNoReply $job, float $timeout) {
    $job->beforeStart();
    return kphp_job_worker_start_no_reply($job, $timeout);
  }


  /**
   * Returns whether we can launch a job worker from a current process.
   */
  static public function isEnabled(): bool {
    #ifndef KPHP
    return false;
    #endif
    return is_kphp_job_workers_enabled();
  }

  /**
   * Returns the amount of job worker processes launched.
   * Could be used to group data per chunks, but remember, that job workers are shared by all http workers.
   */
  static public function getJobWorkersNumber(): int {
    #ifndef KPHP
    return false;
    #endif
    return get_job_workers_number();
  }

  /**
   * Returns whether we are inside a job worker now.
   * Try not to use this function and keep your code unrelated to the fact whether it's inside a job or not.
   */
  static public function isExecutionInsideJobWorker(): bool {
    return isset($_SERVER['JOB_ID']);
  }
}
