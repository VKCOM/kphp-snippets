<?php

namespace JobWorkers;

/**
 * All user-defined workers indirectly extend this class. Not directly! Use one of:
 * @see JobWorkerSimple
 * @see JobWorkerManualRespond
 * @see JobWorkerNoReply
 */
abstract class BaseJobWorker implements \KphpJobWorkerRequest {

  /** @var mixed[] */
  protected $_untyped_context = [];

  /**
   * @param mixed[] $untyped_context
   */
  protected function saveGlobalsContext(array &$untyped_context) {
    // to be overridden
  }

  /**
   * @param mixed[] $untyped_context
   */
  protected function restoreGlobalsContext(array $untyped_context) {
    // to be overridden
  }

  public function beforeStart() {
    $this->saveGlobalsContext($this->_untyped_context);
  }

  public function beforeHandle() {
    $this->restoreGlobalsContext($this->_untyped_context);
  }

}
