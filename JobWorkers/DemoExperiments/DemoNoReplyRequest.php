<?php

namespace JobWorkers\DemoExperiments;

class DemoNoReplyRequest extends \JobWorkers\JobWorkerNoReply {

  /** @var mixed */
  public $arg_to_print;

  /**
   * @param mixed $arg_to_print
   */
  public function __construct($arg_to_print) {
    $this->arg_to_print = $arg_to_print;
  }


  function handleRequest(): void {
    sleep(4);
  }

}
