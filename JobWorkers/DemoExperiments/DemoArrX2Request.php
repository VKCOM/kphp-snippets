<?php

namespace JobWorkers\DemoExperiments;

class DemoArrX2Request extends \JobWorkers\JobWorkerSimple {
  /** @var int[] */
  public $arr_to_x2;

  public function __construct(array $arr_to_x2) {
    $this->arr_to_x2 = $arr_to_x2;
  }


  function handleRequest(): DemoArrX2Response {
    $response = new DemoArrX2Response();
    $response->arr_x2 = array_map(function($v) { return $v ** 2; }, $this->arr_to_x2);

    return $response;
  }
}
