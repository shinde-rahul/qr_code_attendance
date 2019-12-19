<?php

namespace Drupal\qr_code\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for qr_code routes.
 */
class QrCodeController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
