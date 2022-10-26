<?php

namespace JobWorkers\DemoExperiments;

class DemoManualRequest extends \JobWorkers\JobWorkerManualRespond {
  /** @var int */
  public $num_to_x2;

  public function __construct(int $num_to_x2) {
    $this->num_to_x2 = $num_to_x2;
  }


  function handleRequest(): void {
    $response = new DemoArrX2Response();    // respond with this class also, not to create a duplicate, not the point
    $response->arr_x2 = [$this->num_to_x2 ** 2];

    $this->respondAndContinueExecution($response);

    sleep(1);
  }
}
